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
 *
 * "Coming back" is at most once a day, the same rhythm as the habit
 * check-in the vision puts right after it ("the coach opens the
 * conversation → action queue → habits check in"). Two numbers, because one
 * cannot express it: a real absence ({@see RETURN_AFTER_HOURS}, roughly a
 * night away) and a daily ceiling ({@see OPENER_CEILING_HOURS}), so a
 * student who checks in morning and evening is greeted once, not twice.
 * Measured in elapsed hours rather than calendar days because the app does
 * not know the student's timezone, and a UTC midnight falls mid-evening for
 * most of them.
 */
class CoachOpening
{
    public const FIRST = 'first';

    public const RETURNING = 'returning';

    public const RETURN_AFTER_HOURS = 8;

    public const OPENER_CEILING_HOURS = 20;

    public function __construct(
        private CoachThread $thread = new CoachThread,
    ) {}

    public function due(User $user): ?string
    {
        $readiness = new PathwayProfileReadiness;

        if ($this->shouldRegeneratePrematureOpener($user, $readiness)) {
            $this->discardPrematureOpener($user);
        }

        if ($this->thread->messages($user)->isEmpty()) {
            return $readiness->hasPortrait($user) ? self::FIRST : null;
        }

        $rhythm = $this->thread->rhythm($user);

        if ($rhythm['last_any'] === null || $rhythm['last_any']->gt(now()->subHours(self::RETURN_AFTER_HOURS))) {
            return null;
        }

        if ($rhythm['last_student'] === null) {
            return null;
        }

        if ($rhythm['last_opener'] !== null) {
            if ($rhythm['last_opener']->gt($rhythm['last_student'])) {
                return null;
            }

            if ($rhythm['last_opener']->gt(now()->subHours(self::OPENER_CEILING_HOURS))) {
                return null;
            }
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
            ['user', PathwayCoachAgent::openingMarker($kind)],
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
        return (new PathwayProfileReadiness)->latestPortraitAssessment($user)?->vocationalProfile;
    }

    /**
     * A portrait-based opener recorded before a profile existed, or one that
     * used the no-profile fallback. The student has not answered yet, so it
     * can be replaced when the portrait lands.
     */
    protected function shouldRegeneratePrematureOpener(User $user, PathwayProfileReadiness $readiness): bool
    {
        if (! $readiness->hasPortrait($user)) {
            return false;
        }

        $rhythm = $this->thread->rhythm($user);

        if ($rhythm['last_student'] !== null) {
            return false;
        }

        $messages = $this->thread->messages($user);

        if ($messages->isEmpty()) {
            return false;
        }

        if ($messages->count() !== 1 || $messages->first()->role !== 'assistant') {
            return false;
        }

        $content = $messages->first()->content;

        return str_starts_with($content, 'I am your coach. Before I can be useful');
    }

    protected function discardPrematureOpener(User $user): void
    {
        $conversationId = $this->thread->conversationId($user);

        if (! $conversationId) {
            return;
        }

        DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->delete();

        DB::table('agent_conversations')->where('id', $conversationId)->delete();
    }
}
