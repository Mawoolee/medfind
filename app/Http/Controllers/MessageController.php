<?php
// app/Http/Controllers/MessageController.php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Pharmacy;
use App\Models\User;
use App\Events\MessageSent;
use App\Services\PrescriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Get all messages for a pharmacy
     */
    public function index(Pharmacy $pharmacy)
    {
        // Check if user owns this pharmacy or is admin
        $user = Auth::user();
        if ($user->role === 'consumer') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($user->isPharmacy() && $user->pharmacy_id !== $pharmacy->id) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $messages = Message::where('pharmacy_id', $pharmacy->id)
            ->with('consumer')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pharmacy.messages', compact('pharmacy', 'messages'));
    }

    /**
     * Get unread message count for pharmacy
     */
    public function unreadCount()
    {
        $user = Auth::user();
        if (! $user->isPharmacy()) {
            return response()->json(['count' => 0]);
        }

        $pharmacy = Pharmacy::where('user_id', $user->id)->first();
        if (!$pharmacy) {
            return response()->json(['count' => 0]);
        }

        $count = Message::where('pharmacy_id', $pharmacy->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Send a message from consumer to pharmacy
     */
    public function store(Request $request)
    {
        $request->validate([
            'pharmacy_id'        => 'required|exists:pharmacies,id',
            'message'            => 'required|string|max:1000',
            'prescription_image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:5120',
            'attachments'        => 'nullable|array|max:10',
            'attachments.*'      => 'file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
        ]);

        $user = Auth::user();
        if ($user->role !== 'consumer') {
            return redirect()->back()->with('error', 'Only consumers can send messages.');
        }

        $message = new Message();
        $message->consumer_id = $user->id;
        $message->pharmacy_id = $request->pharmacy_id;
        $message->message     = $request->message;
        $message->is_read     = false;

        if ($request->hasFile('prescription_image')) {
            // Encrypt and store to private disk — never in public storage
            $svc = app(PrescriptionService::class);
            $message->prescription_image = $svc->store($request->file('prescription_image'));
        }

        $message->save();

        // Handle multiple attachments
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            $svc = app(PrescriptionService::class);
            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $svc->store($file);
            }
        }
        if (!empty($attachmentPaths)) {
            $message->attachments = $attachmentPaths;
            $message->save();
        }

        // Broadcast real-time new-message notification to the pharmacy channel
        MessageSent::dispatch(
            $message->id,
            $user->id,
            $request->pharmacy_id,
            $request->message,
            $user->name,
            'consumer_to_pharmacy'
        );

        return redirect()->back()->with('success', 'Message sent successfully! The pharmacy will respond shortly.');
    }

    /**
     * Mark message as read
     */
    public function markRead(Message $message)
    {
        $user = Auth::user();

        // Verify ownership
        if ($user->isPharmacy()) {
            $pharmacy = Pharmacy::where('user_id', $user->id)->first();
            if (!$pharmacy || $message->pharmacy_id !== $pharmacy->id) {
                return redirect()->back()->with('error', 'Unauthorized.');
            }
        } else if ($user->role !== 'admin' && $user->id !== $message->consumer_id) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $message->is_read = true;
        $message->save();

        return redirect()->back()->with('success', 'Message marked as read.');
    }

    /**
     * Mark message as read via AJAX (returns JSON)
     */
    public function markReadAjax($id)
    {
        $user = Auth::user();

        if (! $user->isPharmacy()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $pharmacy = Pharmacy::where('user_id', $user->id)->first();
        if (! $pharmacy) {
            return response()->json(['success' => false, 'error' => 'Pharmacy not found'], 404);
        }

        $message = Message::where('id', $id)->where('pharmacy_id', $pharmacy->id)->first();
        if (! $message) {
            return response()->json(['success' => false, 'error' => 'Message not found'], 404);
        }

        $message->is_read = true;
        $message->save();

        $count = Message::where('pharmacy_id', $pharmacy->id)->where('is_read', false)->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * Mark message as unread via AJAX (returns JSON)
     */
    public function markUnreadAjax($id)
    {
        $user = Auth::user();

        if (! $user->isPharmacy()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $pharmacy = Pharmacy::where('user_id', $user->id)->first();
        if (! $pharmacy) {
            return response()->json(['success' => false, 'error' => 'Pharmacy not found'], 404);
        }

        $message = Message::where('id', $id)->where('pharmacy_id', $pharmacy->id)->first();
        if (! $message) {
            return response()->json(['success' => false, 'error' => 'Message not found'], 404);
        }

        $message->is_read = false;
        $message->save();

        $count = Message::where('pharmacy_id', $pharmacy->id)->where('is_read', false)->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * Reply to a message from pharmacy
     */
    public function reply(Request $request, Message $message)
    {
        $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $user = Auth::user();

        if (! $user->isPharmacy()) {
            return redirect()->back()->with('error', 'Only pharmacies can reply.');
        }

        $pharmacy = Pharmacy::where('user_id', $user->id)->first();
        if (!$pharmacy || $message->pharmacy_id !== $pharmacy->id) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $message->reply = $request->reply;
        $message->replied_at = now();
        $message->is_read = true;
        $message->save();

        // Broadcast reply to consumer
        MessageSent::dispatch(
            $message->id,
            $message->consumer_id,
            $pharmacy->id,
            $request->reply,
            auth()->user()->name,
            'pharmacy_to_consumer',
            $request->reply
        );

        return redirect()->back()->with('success', 'Reply sent successfully!');
    }

    /**
     * Verify prescription (AJAX) - records verifier, status and notes
     */
    public function verifyAjax(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        if (! $user->isPharmacy()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $pharmacy = Pharmacy::where('user_id', $user->id)->first();
        if (! $pharmacy) {
            return response()->json(['success' => false, 'error' => 'Pharmacy not found'], 404);
        }

        $message = Message::where('id', $id)->where('pharmacy_id', $pharmacy->id)->first();
        if (! $message) {
            return response()->json(['success' => false, 'error' => 'Message not found'], 404);
        }

        $message->verified_by = $user->id;
        $message->verification_status = $request->input('status');
        $message->verification_notes = $request->input('notes');
        $message->verified_at = now();
        $message->save();

        return response()->json(['success' => true, 'status' => $message->verification_status, 'verifier' => $user->name, 'verified_at' => $message->verified_at->toDateTimeString()]);
    }

    /**
     * Securely serve a decrypted prescription image to authorised pharmacy staff.
     * The file is NEVER written to a public URL.
     */
    public function servePrescription(Request $request, $messageId)
    {
        $user = Auth::user();
        if (!$user->isPharmacy() && $user->role !== 'admin') {
            abort(403, 'Unauthorised.');
        }

        $message = Message::findOrFail($messageId);

        // Pharmacy staff can only view prescriptions sent to their own pharmacy
        if ($user->isPharmacy()) {
            $pharmacy = Pharmacy::where('user_id', $user->id)->first();
            if (!$pharmacy || $message->pharmacy_id !== $pharmacy->id) {
                abort(403, 'Unauthorised.');
            }
        }

        if (empty($message->prescription_image)) {
            abort(404, 'No prescription attached to this message.');
        }

        $svc      = app(\App\Services\PrescriptionService::class);
        $rawBytes = $svc->retrieve($message->prescription_image);
        $mime     = $svc->mimeType($rawBytes);

        return response($rawBytes, 200)->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="prescription"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Get conversation for consumer
     */
    public function consumerConversations()
    {
        $user = Auth::user();
        if ($user->role !== 'consumer') {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        // Get all messages grouped by pharmacy, latest message per pharmacy first
        $messages = Message::where('consumer_id', $user->id)
            ->with('pharmacy')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('pharmacy_id');

        return view('consumer.messages', compact('messages'));
    }

    /**
     * Delete all messages in a conversation between consumer and pharmacy
     */
    public function deleteConversation($pharmacyId)
    {
        $user = Auth::user();
        if ($user->role !== 'consumer') {
            return redirect()->back()->with('error', 'Unauthorized.');
        }
        Message::where('consumer_id', $user->id)
               ->where('pharmacy_id', $pharmacyId)
               ->delete();
        return redirect()->route('consumer.messages')->with('success', 'Conversation deleted.');
    }

    /**
     * Delete all messages in a conversation between pharmacy and a consumer
     */
    public function pharmacyDeleteConversation($consumerId)
    {
        $user = Auth::user();
        $pharmacy = \App\Models\Pharmacy::where('user_id', $user->id)->first();
        if (!$pharmacy) return redirect()->back()->with('error', 'Unauthorized.');

        Message::where('pharmacy_id', $pharmacy->id)
               ->where('consumer_id', $consumerId)
               ->delete();

        return redirect()->route('pharmacy.messages')->with('success', 'Conversation deleted.');
    }

    /**
     * Return conversations as JSON for the chat heads widget
     */
    public function consumerMessagesJson()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'consumer') {
            return response()->json([]);
        }
        $messages = Message::where('consumer_id', $user->id)
            ->with('pharmacy')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->groupBy('pharmacy_id')
            ->map(function($thread) {
                $pharmacy = $thread->first()->pharmacy;
                $unread = $thread->whereNotNull('reply')->filter(function($m) {
                    return !$m->consumer_read_at;
                })->count();
                return [
                    'pharmacy_id'   => $thread->first()->pharmacy_id,
                    'pharmacy_name' => $pharmacy->pharmacy_name ?? 'Pharmacy',
                    'unread'        => $unread,
                    'messages'      => $thread->sortBy('created_at')->map(function($m) {
                        return [
                            'id'         => $m->id,
                            'message'    => $m->message,
                            'reply'      => $m->reply,
                            'replied_at' => $m->replied_at?->toDateTimeString(),
                            'created_at' => $m->created_at->toDateTimeString(),
                            'has_prescription' => !empty($m->prescription_image),
                            'attachment_count' => !empty($m->attachments) ? count($m->attachments) : 0,
                        ];
                    })->values(),
                ];
            })->values();
        return response()->json($messages);
    }


    /**
     * Show chat page for a specific pharmacy conversation
     */
    public function consumerChat($pharmacyId)
    {
        $user = Auth::user();
        if ($user->role !== "consumer") {
            return redirect()->back()->with("error", "Unauthorized.");
        }

        $pharmacy = \App\Models\Pharmacy::findOrFail($pharmacyId);
        $messages = Message::where("consumer_id", $user->id)
            ->where("pharmacy_id", $pharmacyId)
            ->orderBy("created_at", "asc")
            ->get();

        return view("consumer.chat", compact("pharmacy", "messages"));
    }


    /**
     * Serve prescription image for the consumer who sent it
     */
    public function consumerPrescription(Request $request, $messageId)
    {
        $user = Auth::user();
        $message = Message::findOrFail($messageId);

        // Only the consumer who sent it can view it
        if ($message->consumer_id !== $user->id) {
            abort(403, "Unauthorized.");
        }

        if (empty($message->prescription_image)) {
            abort(404, "No prescription attached.");
        }

        $svc = app(\App\Services\PrescriptionService::class);
        $rawBytes = $svc->retrieve($message->prescription_image);
        $mime = $svc->mimeType($rawBytes);

        return response($rawBytes)
            ->header("Content-Type", $mime)
            ->header("Content-Length", strlen($rawBytes))
            ->header("Content-Disposition", "inline")
            ->header("Cache-Control", "no-store");
    }


    public function pharmacyMessagesJson()
    {
        $user = Auth::user();
        if (!$user || !$user->isPharmacy()) return response()->json([]);
        $pharmacy = \App\Models\Pharmacy::where("user_id", $user->id)->first();
        if (!$pharmacy) return response()->json([]);

        $messages = Message::where("pharmacy_id", $pharmacy->id)
            ->with("consumer")
            ->orderBy("created_at", "asc")
            ->get()
            ->groupBy("consumer_id")
            ->map(function($thread) {
                $consumer = $thread->first()->consumer;
                return [
                    "consumer_id" => $thread->first()->consumer_id,
                    "consumer_name" => $consumer->name ?? "Customer",
                    "messages" => $thread->map(function($m) {
                        return [
                            "id" => $m->id,
                            "message" => $m->message,
                            "reply" => $m->reply,
                            "replied_at" => $m->replied_at?->toDateTimeString(),
                            "created_at" => $m->created_at->toDateTimeString(),
                            "has_prescription" => !empty($m->prescription_image),
                            "attachment_count" => !empty($m->attachments) ? count($m->attachments) : 0,
                        ];
                    })->values(),
                ];
            })->values();

        return response()->json($messages);
    }

    /**
     * Serve an attachment file to the consumer who sent the message.
     */
    public function consumerAttachment($messageId, $index)
    {
        $user = Auth::user();
        $message = Message::findOrFail($messageId);
        if ($message->consumer_id !== $user->id) abort(403);
        $attachments = $message->attachments ?? [];
        if (!isset($attachments[$index])) abort(404);
        $svc = app(\App\Services\PrescriptionService::class);
        $rawBytes = $svc->retrieve($attachments[$index]);
        $mime = $svc->mimeType($rawBytes);
        return response($rawBytes)->header('Content-Type', $mime)->header('Content-Length', strlen($rawBytes))->header('Content-Disposition', 'inline')->header('Cache-Control', 'no-store');
    }

    /**
     * Serve an attachment file to the pharmacy staff.
     */
    public function pharmacyAttachment($messageId, $index)
    {
        $user = Auth::user();
        $message = Message::findOrFail($messageId);
        $pharmacy = \App\Models\Pharmacy::where('user_id', $user->id)->first();
        if (!$pharmacy || $message->pharmacy_id !== $pharmacy->id) abort(403);
        $attachments = $message->attachments ?? [];
        if (!isset($attachments[$index])) abort(404);
        $svc = app(\App\Services\PrescriptionService::class);
        $rawBytes = $svc->retrieve($attachments[$index]);
        $mime = $svc->mimeType($rawBytes);
        return response($rawBytes)->header('Content-Type', $mime)->header('Content-Length', strlen($rawBytes))->header('Content-Disposition', 'inline')->header('Cache-Control', 'no-store');
    }

}