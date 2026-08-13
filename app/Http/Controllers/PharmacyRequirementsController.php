<?php
namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\Http\Request;

class PharmacyRequirementsController extends Controller
{
    // The 5 document slots ? key matches the form input name
    const DOCS = [
        'bir'          => ['label' => 'BIR Certificate of Registration', 'required' => true],
        'business'     => ['label' => "Mayor's / Business Permit",        'required' => true],
        'philhealth'   => ['label' => 'PhilHealth Accreditation',          'required' => false],
        'fda'          => ['label' => 'FDA Certificate',                   'required' => false],
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

        $rules = [];
        foreach (array_keys(self::DOCS) as $key) {
            $rules["doc_{$key}"] = 'nullable|file|mimes:jpeg,jpg,png,pdf|max:10240';
        }

        $request->validate($rules);

        $saved = false;
        foreach (array_keys(self::DOCS) as $key) {
            if ($request->hasFile("doc_{$key}")) {
                // Delete old file for this slot if it exists
                if (!empty($uploaded[$key])) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($uploaded[$key]);
                }
                $path = $request->file("doc_{$key}")->store(
                    'pharmacy-requirements/' . $pharmacy->id, 'local'
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

        return redirect()->route('pharmacy.requirements')
            ->with('success', 'Documents uploaded successfully!');
    }
}
