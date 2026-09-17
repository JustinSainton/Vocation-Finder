<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Support\Locker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * See what the student has actually made.
 *
 * Read only, and there is deliberately no writing counterpart. The coach can
 * notice that the essay exists and ask them about it; it cannot put anything
 * in the locker, because a locker the coach can fill is the coach's work with
 * the student's name on it — and "the tool never does the student's work for
 * them" is the line this product is built on.
 */
class GetLockerTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'See what this student has made and kept — résumés, essays, portfolio pieces. Titles and dates only; you cannot read or write the files.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $shelf = (new Locker)->forStudent($this->user);

        return json_encode([
            'artifacts' => array_map(
                fn (array $artifact) => [
                    'kind' => $artifact['kind'],
                    'title' => $artifact['title'],
                    'added_on' => $artifact['added_on'],
                ],
                $shelf,
            ),
            'guidance' => $shelf === []
                ? 'Nothing in their locker yet. If they are working on something, that is worth asking about — do not offer to write it.'
                : 'Ask them about it in their words. Do not offer to rewrite or improve it for them.',
        ]);
    }
}
