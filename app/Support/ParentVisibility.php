<?php

namespace App\Support;

use App\Models\Action;
use App\Models\Gap;
use App\Models\Milestone;
use App\Models\User;

/**
 * Everything a parent is permitted to see, and nothing else.
 *
 * This exists as a single function so the boundary has one place to live. The
 * rule is that parents get progress and milestones — never the coaching
 * conversation, and never the student's own words about themselves.
 *
 * The distinction is finer than "hide the chat":
 *
 * - An action's **title** is a milestone and is shown. A parent knowing their
 *   child is going to ask an aunt about nursing is the point, and the vision
 *   asks for a concrete thing the parent can actually do.
 * - An action's **reflection** is the student writing privately about how it
 *   went, and is not shown.
 * - A gap's **type and summary** are shown; its **evidence** is a quote of the
 *   student and is not.
 */
class ParentVisibility
{
    /**
     * Keys that must never appear in a parent-facing payload.
     */
    public const FORBIDDEN_KEYS = [
        'reflection', 'evidence', 'verbatim', 'they_said', 'said',
        'messages', 'conversation', 'turns', 'transcript',
        'response_text', 'audio_transcript', 'opening_synthesis',
        'brain', 'brain_entries', 'entries', 'in_their_words', 'content',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function summaryFor(User $student): array
    {
        if (! AccessPolicy::permitsParentReporting($student)) {
            return [
                'reportable' => false,
                'reason' => 'This student is 18 or older. Their account is their own.',
            ];
        }

        $actions = $student->actions()->latest()->get();
        $current = $actions->firstWhere(fn (Action $action) => $action->isActive());
        $gaps = $student->gaps()->latest()->get();

        return [
            'reportable' => true,
            'student_name' => $student->name,
            'assessment_completed' => $student->assessments()->where('status', 'completed')->exists(),
            'actions_completed' => $actions->where('status.value', 'completed')->count(),
            'gaps_closed' => $gaps->reject(fn (Gap $gap) => $gap->isActive())->count(),
            'current_action' => $current?->title,
            /**
             * The vision's phrase is "progress and milestones", and 3.2 made
             * milestones a real thing. Titles, kinds and dates cross the line;
             * `why` does not, because it is the coach writing in the
             * student's terms about what this would change for them, and that
             * is the coaching conversation in one sentence.
             */
            'milestones' => static::milestones($student),
            /**
             * The vision's mechanism for involving a family is giving them the
             * question to ask, not a window into the coaching. A parent handed
             * a transcript reads; a parent handed a question talks to their
             * kid. The wording lives in {@see ConversationPrompts}, which is a
             * reviewed catalogue rather than anything a model writes.
             */
            'suggested_conversation' => (new ConversationPrompts)->forParent($student, $current, $gaps),
        ];
    }

    /**
     * @return list<array{title: string, kind: string, due_on: string, status: string}>
     */
    protected static function milestones(User $student): array
    {
        return $student->milestones()
            ->orderBy('due_on')
            ->get()
            ->map(fn (Milestone $milestone) => [
                'title' => $milestone->title,
                'kind' => $milestone->kind->label(),
                'due_on' => $milestone->due_on->toDateString(),
                'status' => $milestone->status->label(),
            ])
            ->all();
    }
}
