<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ParseResumeUploadJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CareerProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $profile = $request->user()->careerProfile;

        return Inertia::render('CareerProfile/Index', [
            'profile' => $profile,
        ]);
    }

    public function import(): Response
    {
        return Inertia::render('CareerProfile/Import');
    }

    public function storeImport(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->file('file')->store(
            "career-profiles/{$request->user()->id}",
            's3'
        );

        $profile = $request->user()->careerProfile;

        if ($profile) {
            $profile->update([
                'raw_import_data' => ['file_path' => $path],
                'import_source' => 'pdf_upload',
                'imported_at' => now(),
            ]);
        } else {
            $profile = $request->user()->careerProfile()->create([
                'raw_import_data' => ['file_path' => $path],
                'import_source' => 'pdf_upload',
                'imported_at' => now(),
            ]);
        }

        ParseResumeUploadJob::dispatch($profile);

        return redirect('/career-profile')->with('success', 'Resume uploaded. Parsing in progress.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_history' => ['nullable', 'array'],
            'education' => ['nullable', 'array'],
            'skills' => ['nullable', 'array'],
            'certifications' => ['nullable', 'array'],
            'volunteer' => ['nullable', 'array'],
        ]);

        $profile = $request->user()->careerProfile;

        if ($profile) {
            $profile->update($validated);
        } else {
            $request->user()->careerProfile()->create([
                ...$validated,
                'import_source' => 'manual',
            ]);
        }

        return redirect('/career-profile')->with('success', 'Career profile updated.');
    }

    public function voiceProfile(Request $request): Response
    {
        return Inertia::render('CareerProfile/VoiceProfile', [
            'voiceProfile' => $request->user()->voiceProfile,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->careerProfile?->delete();

        return redirect('/career-profile')->with('success', 'Career profile removed.');
    }
}
