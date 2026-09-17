<?php

namespace App\Enums;

/**
 * The parts readiness decomposes into.
 *
 * The vision's requirement is that a student can see *what moves it*. A single
 * opaque level is a score with better manners; it has to break into pieces,
 * and each piece has to name one thing that would advance it.
 *
 * Note that naming an obstacle is a factor that goes **up**, not down. A
 * student who has articulated that they cannot afford the program, or that
 * nobody in their family has done this work, has learned something true and
 * moved closer to acting. Scoring them down for saying it would teach them not
 * to say it, and the gaps are how the coach knows what to do next.
 */
enum ReadinessFactor: string
{
    case SelfKnowledge = 'self_knowledge';
    case Direction = 'direction';
    case ObstaclesNamed = 'obstacles_named';
    case ObstaclesCleared = 'obstacles_cleared';
    case Testing = 'testing';

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
            self::SelfKnowledge => 'How much you have said about yourself',
            self::Direction => 'How clear the direction is',
            self::ObstaclesNamed => 'What you have named as being in the way',
            self::ObstaclesCleared => 'What you have gotten out of the way',
            self::Testing => 'What you have actually tried',
        };
    }

    /**
     * The one thing that would move this factor. One, not a list — the list is
     * the friction this product exists to remove.
     */
    public function move(): string
    {
        return match ($this) {
            self::SelfKnowledge => 'Tell your coach about something you did recently that you would have done even if nobody asked.',
            self::Direction => 'Answer the question your coach keeps circling back to. The direction gets clearer from evidence, not from deciding harder.',
            self::ObstaclesNamed => 'Say out loud the thing you think might stop you. Naming it is how it becomes workable.',
            self::ObstaclesCleared => 'Finish the step you are on. Each one closes something.',
            self::Testing => 'Do the one thing your coach gave you, then tell it how it went.',
        };
    }
}
