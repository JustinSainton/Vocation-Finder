<?php

namespace App\Http\Controllers\Web;

use App\Enums\ArtifactKind;
use App\Enums\StudentPlace;
use App\Http\Controllers\Controller;
use App\Models\Artifact;
use App\Support\Locker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The locker — where what the student made is kept.
 *
 * Every route here is scoped to the signed-in student and none of them takes
 * another person's id, the same shape as the coach, the brain and the habit
 * check-in. The counsellor flag in `Organization` governs the *portrait* and
 * nothing else; a locker a teacher can browse is a teacher's filing cabinet.
 */
class LockerController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Locker/Index', [
            'blurb' => StudentPlace::Locker->blurb(),
            'kinds' => array_map(
                fn (ArtifactKind $kind) => ['value' => $kind->value, 'label' => $kind->label()],
                ArtifactKind::cases(),
            ),
            'artifacts' => (new Locker)->forStudent($request->user()),
            'max_kilobytes' => Locker::MAX_KILOBYTES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::enum(ArtifactKind::class)],
            'title' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:2000'],
            /*
             | An allowlist of types, not a denylist of extensions. A denylist
             | is a guess about what is dangerous and the guess is wrong the
             | first time somebody invents a format; `mimetypes` checks what
             | the file actually is rather than what it is called.
             */
            'file' => [
                'required_without:link_url',
                'nullable',
                'file',
                'max:'.Locker::MAX_KILOBYTES,
                'mimetypes:'.implode(',', Locker::MIME_TYPES),
            ],
            'link_url' => ['required_without:file', 'nullable', 'url:http,https', 'max:2000'],
        ]);

        $locker = new Locker;
        $kind = ArtifactKind::from($validated['kind']);

        $request->hasFile('file')
            ? $locker->put(
                $request->user(),
                $kind,
                $validated['title'],
                $request->file('file'),
                $validated['note'] ?? null,
            )
            : $locker->link(
                $request->user(),
                $kind,
                $validated['title'],
                $validated['link_url'],
                $validated['note'] ?? null,
            );

        return back();
    }

    /**
     * Hand the file back to the person who put it there.
     *
     * Files are written to a private disk and never to `public/`, and this is
     * the only way one comes back out. The results page shipped with exactly
     * the opposite arrangement once — a minor's vocational portrait readable
     * by anyone holding the URL — and an uploaded essay is the same category
     * of thing.
     *
     * `attachment` and `nosniff` together mean the file is downloaded rather
     * than rendered, so a document that contains markup cannot execute as a
     * page inside this application's origin.
     */
    public function download(Request $request, Artifact $artifact): StreamedResponse
    {
        abort_unless($artifact->user_id === $request->user()?->id, 403);
        abort_unless($artifact->isFile(), 404);

        return response()->streamDownload(
            fn () => print Storage::disk($artifact->disk)->get($artifact->path),
            $artifact->original_name ?: 'artifact',
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function destroy(Request $request, Artifact $artifact): RedirectResponse
    {
        abort_unless($artifact->user_id === $request->user()?->id, 403);

        /*
         * The student may remove their own work, and only they may. This is
         * not the brain: the brain is what they said, and the promise there
         * is that it is never destroyed. This is a draft they thought better
         * of, and refusing to let a teenager delete one would be the creepy
         * reading of that promise rather than the faithful one.
         */
        $artifact->delete();

        return back();
    }
}
