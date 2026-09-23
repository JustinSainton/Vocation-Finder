<?php

namespace App\Support;

use App\Ai\Agents\PathwayCoachAgent;
use App\Models\User;
use App\Models\VocationalProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;

/**
 * Whether the coach should speak before the student does, and what it says
 * if the model cannot.
 *
 * The vision's return loop is "open the app, the coach opens the
 * conversation". Two moments qualify: the first time, straight after the
 * assessment, and coming back after a real gap. An opener is never stacked
 * on an opener the student has not answered — two unprompted messages in a
 * row is a nag, not a coach.
 */
class CoachOpening
{
    public const FIRST = 'first';

    public const RETURNING = 'returning';

    public const RETURN_AFTER_HOURS = 12;

    public function __construct(
        private CoachThread $thread = new CoachThread,
    ) {}

    public function due(User $user): ?string
    {
        if ($this->thread->messages($user)->isEmpty()) {
            return self::FIRST;
        }

        $rhythm = $this->thread->rhythm($user);

        if ($rhythm['last_any'] === null || $rhythm['last_any']->gt(now()->subHours(self::RETURN_AFTER_HOURS))) {
            return null;
        }

        if ($rhythm['last_student'] === null) {
            return null;
        }

        if ($rhythm['last_internal'] !== null && $rhythm['last_internal']->gt($rhythm['last_student'])) {
            return null;
        }

        return self::RETURNING;
    }

    /**
     * The first message when the model is unreachable.
     *
     * Built only from what the profile already says, at the confidence it
     * allows, and it never quotes the student — there is no verified span to
     * quote without the signals tool. Recorded into the same conversation as
     * a real turn, so the model reads it as its own words next time.
     */
    public function fallback(User $user, string $kind = self::FIRST): string
    {
        if ($kind === self::RETURNING) {
            $current = (new ActionQueue)->current($user);

            return $current
                ? "Welcome back. Last time you took on one step: \"{$current->title}\". How did it go? Tell me plainly, including if it did not happen."
                : 'Welcome back. Before we pick anything new, what has been on your mind about your direction since we last talked?';
        }

        $profile = $this->profile($user);

        if (! $profile) {
            return 'I am your coach. Before I can be useful I need to know you a little. What year are you in, and what are you actually weighing up right now?';
        }

        $reading = ($profile->confidence_level?->permitsConclusion() ?? false) && filled($profile->primary_domain)
            ? "I have read everything you wrote. A recurring pattern in your answers points toward {$profile->primary_domain} — a reading of the evidence, not a verdict about who you are, and one we can test."
            : 'I have read everything you wrote. There is not enough evidence yet to make a strong interpretation, but there is enough to know what we need to test next.';

        $missing = collect($profile->missing_evidence ?? [])->filter()->first();

        $gap = $missing
            ? 'One thing the questions could not see: '.Str::lcfirst(rtrim((string) $missing, '.')).'.'
            : 'The questions could not see your life around them — your year, what you can afford, and who you already know doing this kind of work.';

        return "{$reading}\n\n{$gap}\n\nSo let me start there. What year are you in, and what are you actually weighing up right now?";
    }

    /**
     * Writes a fallback opener as a real turn: an internal prompt, then the
     * coach's words, so the stored conversation still alternates roles.
     */
    public function recordFallback(User $user, string $kind, string $text): void
    {
        $store = resolve(ConversationStore::class);
        $conversationId = $store->latestConversationId($user->id)
            ?? $store->storeConversation($user->id, 'Your coach');

        foreach ([
            ['user', PathwayCoachAgent::INTERNAL_PREFIX.' opening:'.$kind],
            ['assistant', $text],
        ] as [$role, $content]) {
            DB::table('agent_conversation_messages')->insert([
                'id' => (string) Str::uuid7(),
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'agent' => PathwayCoachAgent::class,
                'role' => $role,
                'content' => $content,
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('agent_conversations')->where('id', $conversationId)->update(['updated_at' => now()]);
    }

    protected function profile(User $user): ?VocationalProfile
    {
        return $user->assessments()
            ->where('status', 'completed')
            ->whereHas('vocationalProfile')
            ->latest()
            ->first()
            ?->vocationalProfile;
    }
}
