<?php

namespace App\Support;

use App\Enums\ArtifactKind;
use App\Models\Artifact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class Locker
{
    /**
     * What a sixteen-year-old may put in their own locker.
     *
     * Deliberately narrow, and deliberately a list of types rather than a list
     * of forbidden ones: a denylist is a guess about what is dangerous, and
     * the guess is wrong the first time somebody invents a new format. No
     * SVG and no HTML — both are documents that execute, and an artifact that
     * can run script is a stored cross-site scripting payload with the
     * student's name on it.
     *
     * @return list<string>
     */
    public const MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'text/markdown',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    public const MAX_KILOBYTES = 10240;

    public const DISK = 'local';

    public function put(
        User $user,
        ArtifactKind $kind,
        string $title,
        UploadedFile $file,
        ?string $note = null,
    ): Artifact {
        /*
         * Stored under a generated name, never the one the browser sent.
         * An uploaded filename is attacker-controlled text; letting it reach
         * the filesystem is how a path separator becomes a directory
         * traversal. The original is kept in a column, where it is only ever
         * printed.
         */
        $path = $file->store('artifacts/'.$user->id, self::DISK);

        return Artifact::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'title' => $title,
            'note' => $note,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    public function link(
        User $user,
        ArtifactKind $kind,
        string $title,
        string $url,
        ?string $note = null,
    ): Artifact {
        return Artifact::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'title' => $title,
            'note' => $note,
            'link_url' => $url,
        ]);
    }

    /**
     * @return Collection<int, Artifact>
     */
    public function shelf(User $user): Collection
    {
        return $user->artifacts()->orderByDesc('created_at')->get();
    }

    /**
     * What the student sees. Newest first, because the locker's job is to let
     * them find the thing they just made when somebody asks for it.
     *
     * @return list<array<string, mixed>>
     */
    public function forStudent(User $user): array
    {
        return $this->shelf($user)
            ->map(fn (Artifact $artifact) => [
                'id' => $artifact->id,
                'kind' => $artifact->kind->label(),
                'title' => $artifact->title,
                'note' => $artifact->note,
                'added_on' => $artifact->created_at->toDateString(),
                'is_file' => $artifact->isFile(),
                'original_name' => $artifact->original_name,
                'link_url' => $artifact->link_url,
            ])
            ->values()
            ->all();
    }
}
