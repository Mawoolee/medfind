<?php
namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PharmacyRequirementsController extends Controller
{
    // The 5 document slots ? key matches the form input name
    const DOCS = [
        'bir'          => ['label' => 'BIR Certificate of Registration', 'required' => true],
        'business'     => ['label' => "Mayor's / Business Permit",        'required' => true],
        'philhealth'   => ['label' => 'PhilHealth Accreditation',          'required' => false],
        'fda'          => ['label' => 'FDA Certificate',                   'required' => true],
        'pharmacist'   => ['label' => 'Pharmacist License',                'required' => true],
    ];

    public function show()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->firstOrFail();
        $docs     = self::DOCS;
        $uploaded = $pharmacy->requirements ?? [];   // ['bir' => 'path', 'business' => 'path', ...]
        return view('pharmacy.requirements', compact('pharmacy', 'docs', 'uploaded'));
    }

    public function store(Request $request)
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->firstOrFail();
        $uploaded = $pharmacy->requirements ?? [];

        $uploadErrors = [];
        foreach (array_keys(self::DOCS) as $key) {
            $field = "doc_{$key}";
            if ($request->has($field) && ! $request->hasFile($field)) {
                $label = self::DOCS[$key]['label'];
                $uploadErrors[$field] = "The {$label} could not be uploaded. The file may exceed the server upload limit or the upload was interrupted.";
            }
        }

        if ($uploadErrors !== []) {
            if ($request->isMethod('post') && $request->allFiles() === []) {
                $uploadErrors['documents'] = 'The documents could not be uploaded together. The combined file size may exceed the server upload limit. Please compress the files or upload fewer documents at a time.';
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Document upload failed.', 'errors' => $uploadErrors], 422);
            }
            throw ValidationException::withMessages($uploadErrors);
        }

        $rules = [];
        foreach (array_keys(self::DOCS) as $key) {
            $rules["doc_{$key}"] = 'nullable|file|mimes:jpeg,jpg,png,pdf|max:10240';
        }

        $request->validate($rules, [
            '*.file' => 'The selected file could not be uploaded. Please try again with a PDF, JPG, JPEG, or PNG file.',
            '*.mimes' => 'The selected file must be a PDF, JPG, JPEG, or PNG.',
            '*.max' => 'The selected file must not be larger than 10 MB.',
        ]);

        $saved = false;
        foreach (array_keys(self::DOCS) as $key) {
            if ($request->hasFile("doc_{$key}")) {
                // Delete old file for this slot if it exists
                if (!empty($uploaded[$key])) {
                    \Illuminate\Support\Facades\Storage::disk(config('filesystems.requirements_disk'))->delete($uploaded[$key]);
                }
                $path = $request->file("doc_{$key}")->store(
                    'pharmacy-requirements/' . $pharmacy->id, config('filesystems.requirements_disk')
                );
                $uploaded[$key] = $path;
                $saved = true;
            }
        }

        if (!$saved) {
            return redirect()->back()->with('error', 'Please select at least one file to upload.');
        }

        $pharmacy->requirements = $uploaded;
        $pharmacy->save();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Documents uploaded successfully.']);
        }

        return redirect()->route('pharmacy.requirements')->with('success', 'Documents uploaded successfully!');
    }

    /**
     * Upload exactly one document. Keeping this request limited to one file
     * avoids PHP/Railway multipart request limits dropping the last file in a
     * large multi-document submission.
     */
    public function uploadDocument(Request $request, string $document)
    {
        if (! array_key_exists($document, self::DOCS)) {
            abort(404);
        }

        $pharmacy = Pharmacy::where('user_id', auth()->id())->firstOrFail();
        $field = "doc_{$document}";
        $label = self::DOCS[$document]['label'];

        $validated = $request->validate([
            $field => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:10240'],
        ], [
            "{$field}.required" => "Please select the {$label}.",
            "{$field}.file" => "The {$label} could not be uploaded.",
            "{$field}.mimes" => "The {$label} must be a PDF, JPG, JPEG, or PNG.",
            "{$field}.max" => "The {$label} must not be larger than 10 MB.",
        ]);

        $uploaded = $pharmacy->requirements ?? [];
        if (! empty($uploaded[$document])) {
            \Illuminate\Support\Facades\Storage::disk(config('filesystems.requirements_disk'))->delete($uploaded[$document]);
        }

        $uploaded[$document] = $request->file($field)->store(
            'pharmacy-requirements/'.$pharmacy->id,
            config('filesystems.requirements_disk')
        );
        $pharmacy->requirements = $uploaded;
        $pharmacy->save();

        return response()->json([
            'message' => "{$label} uploaded successfully.",
            'document' => $document,
        ]);
    }
}
