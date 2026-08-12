<?php
namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\Http\Request;

class PharmacyRequirementsController extends Controller
{
    public function show()
    {
        $pharmacy = Pharmacy::where('user_id', auth()->id())->firstOrFail();
        return view('pharmacy.requirements', compact('pharmacy'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'requirements.*' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240',
            'requirements'   => 'required|array|min:1',
        ]);

        $pharmacy = Pharmacy::where('user_id', auth()->id())->firstOrFail();

        $paths = [];
        foreach ($request->file('requirements') as $file) {
            $paths[] = $file->store('pharmacy-requirements/' . $pharmacy->id, 'private');
        }

        $existing = $pharmacy->requirements ?? [];
        $pharmacy->requirements = array_merge($existing, $paths);
        $pharmacy->save();

        return redirect()->route('pharmacy.profile.edit')->with('success', 'Requirements uploaded successfully! The admin will review your documents.');
    }
}