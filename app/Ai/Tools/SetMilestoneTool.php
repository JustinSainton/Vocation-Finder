<?php

namespace App\Ai\Tools;

use App\Enums\MilestoneKind;
use App\Models\Milestone;
use App\Models\User;
use App\Support\StudentPlan;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Put a dated outcome into the student's plan.
 *
 * `due_on` is required and must be a real, future date, because the schema
 * behind this makes it so: a milestone with no date is a wish, and a plan made
 * of sections has nowhere to put a wish. Refusals come back as guidance so the
 * model re-asks the student for the date rather than inventing one.
 *
 * `kind` is `passage` in the enum but not offered here. Finishing a year and
 * graduating are computed from the calendar in {@see StudentPlan}
 * — a model that could write them would be able to hand a student a task for
 * turning seventeen.
 */
class SetMilestoneTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'Record one dated thing in this student\'s plan — a test sitting, a deadline, a term to raise a grade in, a job to have started by. Only with a date they gave you.';
    }

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return array_values(array_map(
            fn (MilestoneKind $kind) => $kind->value,
            array_filter(MilestoneKind::cases(), fn (MilestoneKind $kind) => $kind->isAchieved()),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema
                ->string()
                ->description('The outcome, written to them in one sentence. One milestone, not several.')
                ->required(),
            'kind' => $schema
                ->string()
                ->enum(static::kinds())
                ->description('What sort of thing it is')
                ->required(),
            'due_on' => $schema
                ->string()
                ->description('The date it is due or happens, as YYYY-MM-DD. Ask them for it. Do not guess.')
                ->required(),
            'why' => $schema
                ->string()
                ->description('Why it is in their plan, in their terms')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $kind = MilestoneKind::tryFrom((string) $request['kind']);

        if (! $kind || ! $kind->isAchieved()) {
            return json_encode([
                'recorded' => false,
                'guidance' => 'Kind must be one of: '.implode(', ', static::kinds()).'.',
            ]);
        }

        $given = trim((string) $request['due_on']);
        $dueOn = null;

        try {
            $dueOn = CarbonImmutable::createFromFormat('!Y-m-d', $given);
        } catch (\Throwable) {
            $dueOn = null;
        }

        /*
         * The round-trip is the actual check. Carbon happily overflows an
         * impossible date — '2026-13-45' becomes 14 February 2027 without
         * complaint — so parsing succeeding proves nothing. Comparing the
         * formatted result back to what was given is the only way to tell a
         * date from a typo, and a typo in a sixteen-year-old's plan is worse
         * than no date, because they will believe it.
         */
        if (! $dueOn || $dueOn->format('Y-m-d') !== $given) {
            return json_encode([
                'recorded' => false,
                'guidance' => 'due_on must be a real date as YYYY-MM-DD. If the student does not know it yet, ask them — do not put a made-up date in their plan.',
            ]);
        }

        if ($dueOn->lessThan(CarbonImmutable::now()->startOfDay())) {
            return json_encode([
                'recorded' => false,
                'guidance' => 'That date has already passed. A plan is about what is ahead; if this already happened, save it to their brain instead.',
            ]);
        }

        $milestone = Milestone::create([
            'user_id' => $this->user->id,
            'kind' => $kind,
            'title' => trim((string) $request['title']),
            'why' => trim((string) $request['why']) ?: null,
            'due_on' => $dueOn->toDateString(),
        ]);

        return json_encode([
            'recorded' => true,
            'milestone_id' => $milestone->id,
            'title' => $milestone->title,
            'due_on' => $milestone->due_on->toDateString(),
            'guidance' => 'It is in their plan now. Do not turn it into this conversation\'s action unless it is genuinely the next step — the plan holds many dates, the action queue holds one.',
        ]);
    }
}
