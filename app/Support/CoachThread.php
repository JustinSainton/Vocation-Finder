<?php

namespace App\Support;

use App\Ai\Agents\PathwayCoachAgent;
use App\Data\Coach\CoachThreadMessageData;
use App\Data\Coach\CoachThreadStepData;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\ConversationStore;

/**
 * The student's one ongoing conversation, as they should see it.
 *
 * Read straight from the SDK's conversation store rather than from a copy, so
 * the thread on screen and the context the model is given are the same rows
 * and cannot drift. Two things are removed on the way out: prompts the system
 * wrote (the opening instruction and the tools-only recovery), which are not
 * the student's words, and assistant rows with no prose, which are tool
 * bookkeeping rather than something the coach said.
 *
 * Steps are interleaved by the time they were assigned, so the student can see
 * where in the conversation their current step came from. They are markers,
 * not cards: DESIGN.md allows one action card per screen, and that is the
 * current step pinned above the composer.
 */
class CoachThread
{
    public const LIMIT = 100;

    /**
     * @return list<CoachThreadMessageData|CoachThreadStepData>
     */
    public function items(User $user): array
    {
        $messages = $this->messages($user);

        if ($messages->isEmpty()) {
            return [];
        }

        $since = CarbonImmutable::parse($messages->first()->at);

        $steps = $user->actions()
            ->where('assigned_at', '>=', $since)
            ->orderBy('assigned_at')
            ->get(['id', 'title', 'rationale', 'status', 'assigned_at'])
            ->map(fn ($action) => new CoachThreadStepData(
                id: (string) $action->id,
                title: $action->title,
                rationale: $action->rationale,
                status: $action->status->value,
                at: $action->assigned_at->toIso8601String(),
            ));

        return $messages
            ->concat($steps)
            ->sortBy(fn (CoachThreadMessageData|CoachThreadStepData $item) => $item->at.($item instanceof CoachThreadStepData ? '~' : ''))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CoachThreadMessageData>
     */
    public function messages(User $user): Collection
    {
        $conversationId = $this->conversationId($user);

        if (! $conversationId) {
            return collect();
        }

        return DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'role', 'content', 'created_at'])
            ->reverse()
            ->reject(fn ($row) => static::isInternal($row->role, $row->content) || trim((string) $row->content) === '')
            ->map(fn ($row) => new CoachThreadMessageData(
                id: (string) $row->id,
                role: $row->role,
                content: (string) $row->content,
                at: CarbonImmutable::parse($row->created_at)->toIso8601String(),
            ))
            ->values();
    }

    /**
     * When the coach last opened a conversation, when the student last spoke
     * for themselves, and when anything last happened.
     *
     * @return array{last_opener: ?CarbonImmutable, last_student: ?CarbonImmutable, last_any: ?CarbonImmutable}
     */
    public function rhythm(User $user): array
    {
        $conversationId = $this->conversationId($user);

        if (! $conversationId) {
            return ['last_opener' => null, 'last_student' => null, 'last_any' => null];
        }

        $rows = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['content', 'created_at']);

        $at = fn ($row) => $row ? CarbonImmutable::parse($row->created_at) : null;
        $lastAny = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->max('created_at');

        return [
            'last_opener' => $at($rows->first(fn ($row) => str_starts_with((string) $row->content, PathwayCoachAgent::INTERNAL_PREFIX.' opening:'))),
            'last_student' => $at($rows->first(fn ($row) => ! static::isInternal('user', $row->content))),
            'last_any' => $lastAny ? CarbonImmutable::parse($lastAny) : null,
        ];
    }

    public function conversationId(User $user): ?string
    {
        return resolve(ConversationStore::class)->latestConversationId($user->id);
    }

    public static function isInternal(string $role, ?string $content): bool
    {
        return $role === 'user' && str_starts_with((string) $content, PathwayCoachAgent::INTERNAL_PREFIX);
    }
}
