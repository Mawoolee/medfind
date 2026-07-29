<?php
// app/Http/Controllers/MessageController.php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Pharmacy;
use App\Models\User;
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

        if ($user->role === 'pharmacy' && $user->pharmacy_id !== $pharmacy->id) {
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
        if ($user->role !== 'pharmacy') {
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
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'message' => 'required|string|max:1000',
            'prescription_image' => 'nullable|image|max:5120', // 5MB max
        ]);

        $user = Auth::user();
        if ($user->role !== 'consumer') {
            return redirect()->back()->with('error', 'Only consumers can send messages.');
        }

        $message = new Message();
        $message->consumer_id = $user->id;
        $message->pharmacy_id = $request->pharmacy_id;
        $message->message = $request->message;
        $message->is_read = false;

        if ($request->hasFile('prescription_image')) {
            $path = $request->file('prescription_image')->store('prescriptions', 'public');
            $message->prescription_image = $path;
        }

        $message->save();

        return redirect()->back()->with('success', 'Message sent successfully! The pharmacy will respond shortly.');
    }

    /**
     * Mark message as read
     */
    public function markRead(Message $message)
    {
        $user = Auth::user();

        // Verify ownership
        if ($user->role === 'pharmacy') {
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
     * Reply to a message from pharmacy
     */
    public function reply(Request $request, Message $message)
    {
        $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $user = Auth::user();

        if ($user->role !== 'pharmacy') {
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

        return redirect()->back()->with('success', 'Reply sent successfully!');
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

        $messages = Message::where('consumer_id', $user->id)
            ->with('pharmacy')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('consumer.messages', compact('messages'));
    }
}