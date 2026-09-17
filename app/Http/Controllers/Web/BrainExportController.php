<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\BrainRetrieval;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Everything the student has said, handed back to them.
 *
 * Note the checks this controller does not perform. There is no subscription
 * check, no consent check and no tier check, and adding one would be a bug
 * rather than a hardening: a lapse freezes the brain, it does not seize it.
 * We promise this to a parent in writing at the moment they consent, so the
 * mechanism has to outlive every other relationship in the system.
 *
 * Default format is a readable document rather than JSON because of what this
 * is actually for — a senior writing a college essay, needing the thing they
 * said about why the work matters to them, in the words they said it in.
 */
class BrainExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        $entries = (new BrainRetrieval)->export($user);

        $wantsJson = $request->query('format') === 'json';

        $filename = str($user->name)->slug()->value().'-vocational-brain.'.($wantsJson ? 'json' : 'md');

        $body = $wantsJson
            ? json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : static::document($user, $entries);

        return response()->streamDownload(
            fn () => print $body,
            $filename,
            ['Content-Type' => $wantsJson ? 'application/json' : 'text/markdown; charset=UTF-8'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    protected static function document(User $user, array $entries): string
    {
        $lines = [
            "# {$user->name} — in your own words",
            '',
            'Everything below is something you said, exactly as you said it. Nothing here was written for you.',
            '',
        ];

        if ($entries === []) {
            $lines[] = 'There is nothing here yet. It fills up as you talk, and it is yours when it does.';

            return implode("\n", $lines)."\n";
        }

        foreach ($entries as $entry) {
            $lines[] = '## '.$entry['said_on'];

            if ($entry['context']) {
                $lines[] = '';
                $lines[] = '*'.$entry['context'].'*';
            }

            $lines[] = '';
            $lines[] = '> '.str_replace("\n", "\n> ", $entry['in_their_words']);
            $lines[] = '';
            $lines[] = '— '.$entry['source'];
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
