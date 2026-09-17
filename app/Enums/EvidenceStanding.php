<?php

namespace App\Enums;

/**
 * Where a pathway's case actually rests, from blueprint 10.3.
 *
 * This is a closed vocabulary rather than a ratio because the distinction the
 * blueprint draws is categorical, not continuous: a student who has done the
 * work and a student who has only imagined it are in different situations,
 * and the move that serves each is different. A number would invite ranking
 * students against each other; these four invite a next step.
 *
 * None of these is a verdict. "Aspiration only" is not a deficiency — it is
 * the raw material of the next experiment, and it is the most common and most
 * appropriate standing for a sixteen-year-old.
 */
enum EvidenceStanding: string
{
    /** They have practised, endured, produced, or been trusted to carry it. */
    case Demonstrated = 'demonstrated';

    /** Some of both, with the doing beginning to catch up to the wanting. */
    case Emerging = 'emerging';

    /** They want it, admire it, or imagine it. They have not yet done it. */
    case AspirationOnly = 'aspiration_only';

    /** Nothing they said evidences this pathway either way. */
    case Unevidenced = 'unevidenced';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Demonstrated => 'Demonstrated',
            self::Emerging => 'Emerging',
            self::AspirationOnly => 'Aspiration only',
            self::Unevidenced => 'Not yet evidenced',
        };
    }

    /**
     * What this standing means for the student, in their own register.
     *
     * Written to be quotable to a sixteen-year-old without translation, and
     * deliberately free of the word "gap" — the distance is a place to work,
     * not a hole in them.
     */
    public function meaning(): string
    {
        return match ($this) {
            self::Demonstrated => 'You have already done things that point this way, not just wanted to.',
            self::Emerging => 'You have started doing this, not only wanting it. There is not much of a track record yet.',
            self::AspirationOnly => 'This is something you want. Nothing you told us shows you have tried it yet.',
            self::Unevidenced => 'Nothing you told us speaks to this either way.',
        };
    }

    /**
     * The move that closes the distance.
     *
     * The blueprint is explicit that the distance between the two tracks
     * "generates the development plan and real-world experiments" — not
     * automatic validation, and not dismissal. So each standing names an
     * action rather than a judgement.
     */
    public function nextMove(): string
    {
        return match ($this) {
            self::Demonstrated => 'Go deeper or go wider: more responsibility in it, or a harder version of it.',
            self::Emerging => 'Do it once more, somewhere slightly harder, and see whether it still holds.',
            self::AspirationOnly => 'Find the smallest real version of it you can try in the next two weeks.',
            self::Unevidenced => 'There is nothing to build on here yet. Look elsewhere first.',
        };
    }

    /**
     * Whether a pathway at this standing may be named as a direction.
     *
     * Aspiration alone is a reason to run an experiment, never a reason to
     * tell a student what they are. Naming a direction on wanting alone is
     * identity foreclosure with extra steps.
     */
    public function supportsNamingADirection(): bool
    {
        return in_array($this, [self::Demonstrated, self::Emerging], true);
    }
}
