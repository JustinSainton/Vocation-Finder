<?php

namespace App\Enums;

use App\Http\Controllers\Web\BrainExportController;
use Illuminate\Support\Facades\Route;

/**
 * The five places the vision document names, as one declaration.
 *
 * The list exists here rather than in a `<nav>` for the reason every such
 * list belongs in one file: a place typed into markup is a place that can be
 * promised in a document, rendered in a menu, and never actually built. Here
 * a place is *open* only if the router can name it, so an unbuilt place is
 * absent from the interface without anybody deciding to hide it — and 3.2 and
 * 3.3 make theirs appear by registering a route, not by editing a component.
 */
enum StudentPlace: string
{
    case Coach = 'coach';
    case Brain = 'brain';
    case Habits = 'habits';
    case Locker = 'locker';
    case Plan = 'plan';

    /**
     * The order the student meets them in: the conversation first, then what
     * they have said, then what they keep doing, then what they have made,
     * then where it is going. Widest-lens last on purpose — a sixteen-year-old
     * opening on a four-year plan is being asked the question the product
     * exists to stop asking cold.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Coach, self::Brain, self::Habits, self::Locker, self::Plan];
    }

    /**
     * The places that actually exist, asked of the router rather than of a
     * flag somebody maintains by hand.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::ordered(), fn (self $place) => $place->isOpen()));
    }

    public function isOpen(): bool
    {
        return Route::has($this->routeName());
    }

    public function routeName(): string
    {
        return $this->value;
    }

    public function path(): string
    {
        return '/'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Coach => 'Coach',
            self::Brain => 'Your words',
            self::Habits => 'What you keep doing',
            self::Locker => 'Locker',
            self::Plan => 'Plan',
        };
    }

    /**
     * One sentence, in the second person, saying what is kept here. Used as
     * the nav item's title and as the empty state, so the two cannot drift.
     */
    public function blurb(): string
    {
        return match ($this) {
            self::Coach => 'Where you talk it through and leave with one thing to do.',
            self::Brain => 'Everything you have said, in the words you said it in.',
            self::Habits => 'The small things you are repeating on purpose.',
            self::Locker => 'What you have made — resumes, essays, work worth showing.',
            self::Plan => 'Where this is going, and what it needs from you next.',
        };
    }

    /**
     * DESIGN.md reserves the dark surface for the two places where the
     * student is talking rather than reading.
     */
    public function surface(): string
    {
        return match ($this) {
            self::Coach, self::Brain => 'dark',
            default => 'light',
        };
    }

    /**
     * The feature flag a place sits behind, or null for a place that must
     * never be gated.
     *
     * The brain is null deliberately, and matches
     * {@see BrainExportController}: a lapse freezes
     * the brain, it does not seize it. Letting a student download their own
     * words while refusing to show them on a page would be the same seizure
     * with an extra step.
     */
    public function gate(): ?string
    {
        return match ($this) {
            self::Brain => null,
            default => 'pathway_coach',
        };
    }
}
