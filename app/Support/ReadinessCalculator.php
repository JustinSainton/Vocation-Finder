<?php

namespace App\Support;

use App\Enums\ConfidenceLevel;
use App\Enums\FactorStanding;
use App\Enums\GapStatus;
use App\Enums\ReadinessFactor;
use App\Enums\ReadinessLevel;
use App\Models\ReadinessSnapshot;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Readiness to Change, computed from what the student has actually done.
 *
 * **No model call.** Every input here is a count or an enum already sitting in
 * the database, and per the governing guardrail principle we never ask a model
 * for a value we can compute. A model-generated readiness level would also be
 * unstable week to week for reasons the student could not see, which destroys
 * the one property that makes this metric worth having: that working at it
 * moves it, visibly and for reasons you can name.
 *
 * Nothing numeric reaches the student. The thresholds below are internal; the
 * payload is levels and next moves, per DESIGN.md's ban on percentages, dials
 * and scores.
 */
class ReadinessCalculator
{
    /**
     * Counts at which each factor moves up a standing.
     *
     * Deliberately shallow at the bottom: the first brain entry, the first
     * named obstacle and the first finished action each move something. A
     * metric whose first increment costs a month teaches a student that
     * nothing they do matters.
     *
     * @var array<string, array{int, int, int}>
     */
    protected const THRESHOLDS = [
        ReadinessFactor::SelfKnowledge->value => [1, 6, 15],
        ReadinessFactor::ObstaclesNamed->value => [1, 3, 5],
        ReadinessFactor::ObstaclesCleared->value => [1, 2, 4],
        ReadinessFactor::Testing->value => [1, 3, 6],
    ];

    /**
     * Compute readiness without writing anything.
     *
     * @return array{level: ReadinessLevel, factors: array<string, string>}
     */
    public function evaluate(User $user): array
    {
        $standings = $this->standings($user);

        return [
            'level' => static::levelFrom($standings),
            'factors' => $standings->map(fn (FactorStanding $standing) => $standing->value)->all(),
        ];
    }

    /**
     * Compute and record, so the history exists.
     *
     * Skips writing when nothing has changed. A timeline where every page load
     * adds a row is not a history, it is a log, and the student cannot read
     * change out of it.
     */
    public function record(User $user, ?string $reason = null): ReadinessSnapshot
    {
        $evaluated = $this->evaluate($user);
        $latest = $this->latest($user);

        if ($latest
            && $latest->level === $evaluated['level']
            && $latest->factors === $evaluated['factors']) {
            return $latest;
        }

        return ReadinessSnapshot::create([
            'user_id' => $user->id,
            'level' => $evaluated['level'],
            'factors' => $evaluated['factors'],
            'reason' => $reason,
            'captured_at' => now(),
        ]);
    }

    public function latest(User $user): ?ReadinessSnapshot
    {
        return $user->readinessSnapshots()->latest('captured_at')->first();
    }

    /**
     * Everything the dashboard needs: the level, what moves it, and how it has
     * changed. Those three, because those are the three the vision names.
     *
     * @return array<string, mixed>
     */
    public function explain(User $user): array
    {
        $evaluated = $this->evaluate($user);
        $standings = $this->standings($user);

        return [
            'level' => $evaluated['level']->value,
            'level_label' => $evaluated['level']->label(),
            'level_description' => $evaluated['level']->description(),
            'what_moves_it' => static::whatMovesIt($standings),
            'factors' => $standings->map(fn (FactorStanding $standing, string $factor) => [
                'label' => ReadinessFactor::from($factor)->label(),
                'standing' => $standing->label(),
                'move' => ReadinessFactor::from($factor)->move(),
            ])->all(),
            'history' => $this->history($user),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public function history(User $user, int $limit = 12): array
    {
        return $user->readinessSnapshots()
            ->latest('captured_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (ReadinessSnapshot $snapshot) => array_filter([
                'on' => $snapshot->captured_at->toDateString(),
                'level' => $snapshot->level->label(),
                'because' => $snapshot->reason,
            ], fn ($value) => $value !== null))
            ->values()
            ->all();
    }

    /**
     * The single lowest factor's move.
     *
     * One, not the full list. A student handed five things to work on is back
     * in the decision friction this product exists to remove — and the lowest
     * factor is genuinely where the next increment is cheapest.
     */
    protected static function whatMovesIt(Collection $standings): string
    {
        $lowest = $standings->sortBy(fn (FactorStanding $standing) => $standing->rank())->keys()->first();

        return ReadinessFactor::from($lowest)->move();
    }

    /**
     * @return Collection<string, FactorStanding>
     */
    protected function standings(User $user): Collection
    {
        $gaps = $user->gaps()->get();

        return collect([
            ReadinessFactor::SelfKnowledge->value => FactorStanding::fromCount(
                $user->brainEntries()->count(),
                self::THRESHOLDS[ReadinessFactor::SelfKnowledge->value],
            ),
            ReadinessFactor::Direction->value => static::fromConfidence($user),
            /**
             * Naming an obstacle moves this **up**. A student who says they
             * cannot afford the program has learned something true; scoring
             * them down for saying it would teach them not to say it, and the
             * gaps are how the coach knows what to do next.
             */
            ReadinessFactor::ObstaclesNamed->value => FactorStanding::fromCount(
                $gaps->count(),
                self::THRESHOLDS[ReadinessFactor::ObstaclesNamed->value],
            ),
            ReadinessFactor::ObstaclesCleared->value => FactorStanding::fromCount(
                $gaps->where('status', GapStatus::Closed)->count(),
                self::THRESHOLDS[ReadinessFactor::ObstaclesCleared->value],
            ),
            ReadinessFactor::Testing->value => FactorStanding::fromCount(
                $user->actions()->where('status', 'completed')->count(),
                self::THRESHOLDS[ReadinessFactor::Testing->value],
            ),
        ]);
    }

    /**
     * Direction is the one factor the student does not move directly — it
     * comes from the evidence the engine has, which is why the move for it is
     * phrased as answering rather than deciding.
     */
    protected static function fromConfidence(User $user): FactorStanding
    {
        $confidence = $user->assessments()
            ->latest()
            ->first()
            ?->vocationalProfile
            ?->confidence_level;

        return match ($confidence) {
            ConfidenceLevel::Strong => FactorStanding::Solid,
            ConfidenceLevel::Moderate => FactorStanding::Building,
            ConfidenceLevel::Emerging => FactorStanding::Beginning,
            default => FactorStanding::NotYet,
        };
    }

    /**
     * @param  Collection<string, FactorStanding>  $standings
     */
    protected static function levelFrom(Collection $standings): ReadinessLevel
    {
        $average = $standings->avg(fn (FactorStanding $standing) => $standing->rank());

        return match (true) {
            $average >= 2.4 => ReadinessLevel::Moving,
            $average >= 1.5 => ReadinessLevel::Testing,
            $average >= 0.6 => ReadinessLevel::Exploring,
            default => ReadinessLevel::Considering,
        };
    }
}
