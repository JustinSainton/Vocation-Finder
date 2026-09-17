<?php

namespace App\Support;

use App\Enums\CohortSignal;
use App\Enums\GapType;
use App\Models\Action;
use App\Models\Gap;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The one thing an adult in this student's life could actually say this month.
 *
 * The vision's mechanism for involving a family is giving them the question to
 * ask, not a window into the coaching: a parent handed a transcript reads, a
 * parent handed a question talks to their kid. The same is true of a
 * counsellor with thirty names in front of them.
 *
 * Three rules make this a catalogue rather than a prompt to a model:
 *
 * 1. **Nothing here is generated.** These are written once, reviewed once, and
 *    linted by the same red-team rules that govern anything else a person
 *    reads — a test fails if any line in this file would be refused as a
 *    narrative. A model writing a sentence about a specific teenager to their
 *    parent, monthly, unreviewed, is the single worst place in this product to
 *    put a language model.
 * 2. **A prompt is a move, not a description.** Every line asks the adult to
 *    do or say something. "Maya seems disengaged" is a verdict delivered to
 *    somebody with no way to act on it.
 * 3. **A prompt may only interpolate what a parent already sees.** `:name` and
 *    `:action` are the entire placeholder vocabulary and a test asserts no
 *    other exists — both are fields already on the parent payload. A
 *    catalogue able to reach a reflection, a gap's evidence or a brain entry
 *    would be the privacy boundary broken in the one payload that leaves the
 *    building by email, so the allowlist is the boundary, not good intentions.
 *
 * Range matters as much as content. A parent who receives the identical
 * sentence every month stops reading the email, and a counsellor whose roster
 * repeats one line thirty times is reading wallpaper. Selection is therefore
 * varied by month and by student — deterministically, so the email and the
 * page agree, and so nothing changes under somebody mid-conversation.
 */
class ConversationPrompts
{
    /**
     * What to say when the student is working on something.
     *
     * Deliberately about the *arranging* rather than the doing. A parent who
     * asks "did you do it yet" is a deadline; a parent who offers to drive
     * them is the reason it happens.
     *
     * @var list<string>
     */
    public const PARENT_ACTION = [
        'Ask how ":action" is going — and offer to help with the part that is hard to arrange.',
        'Ask :name what the hardest part of ":action" is. Not whether it is done.',
        'Ask what they would need from you to get ":action" done, and then do that thing.',
        'Ask :name what they expect to find out by doing ":action".',
    ];

    /**
     * What to say when nothing is in progress.
     *
     * A quiet month is not a problem to be solved out loud. These are openers,
     * not interventions.
     *
     * @var list<string>
     */
    public const PARENT_QUIET = [
        'Ask them what they are curious about right now, and listen for what they keep coming back to.',
        'Tell :name about a part of your own work you did not expect to like.',
        'Ask what they would spend a whole Saturday on if nobody was watching.',
        'Ask :name who they know that has an interesting job, and what makes it interesting.',
    ];

    /**
     * What to say when something is in the way, by what kind of thing it is.
     *
     * The gap's own closing move is the first entry in each bank, so the
     * catalogue extends {@see GapType::closingMove()} rather than competing
     * with it.
     *
     * @var array<string, list<string>>
     */
    public const PARENT_GAP = [
        'information' => [
            'Ask :name who they could talk to that actually does this work, and help them get in the room.',
            'Offer to sit with them while they write the email asking someone about it.',
        ],
        'access' => [
            'Ask what would have to be true for them to try it once, and see which part you can arrange.',
            'Offer to make one phone call on their behalf — not instead of them, alongside them.',
        ],
        'finances' => [
            'Find out together what it actually costs. Guessing is what makes it feel impossible.',
            'Tell :name plainly what is and is not possible at home, so they are planning against real numbers.',
        ],
        'habits' => [
            'Ask what the smallest version of it would be, and when in the week it would fit.',
            'Ask :name what gets in the way on the days it does not happen.',
        ],
        'relationships' => [
            'Ask who they already know that does something like this, and offer an introduction if you have one.',
            'Ask :name what they would want to know from someone doing the work.',
        ],
        'future_outlook' => [
            'Ask what they think would have to go right. Then ask what they think would go wrong.',
            'Tell :name about a time something worked out for you that you did not expect to.',
        ],
    ];

    /**
     * What a counsellor or pastor could do about a whole group.
     *
     * These never name a student: they head a list of names, and a heading
     * that names one of them is describing that person to everybody else in
     * the room.
     *
     * @var array<string, list<string>>
     */
    public const MENTOR = [
        'needs_consent' => [
            'Call a parent. No consent is on file yet, and a phone call closes this faster than another email does.',
            'Check whether the address on file is one a parent actually reads.',
        ],
        'not_started' => [
            'Ask what stopped them. It is almost always time, not willingness.',
            'Offer twenty quiet minutes and a room. Most of these finish in one sitting.',
        ],
        'blocked' => [
            'Ask what it would take. They have named something a school can actually do something about.',
            'Ask what the smallest version of a way in would be, and see whether you can arrange that one.',
        ],
        'stalled' => [
            'Ask what is in the way of the step, not whether they have done it yet.',
            'Ask whether the step still makes sense to them. A step somebody has outgrown looks exactly like a step they are avoiding.',
        ],
        'moving' => [
            'Nothing needed from you this week.',
            'Nothing needed. If you have a minute, tell one of them you noticed.',
        ],
    ];

    /**
     * What to take to an adult about a specific college.
     *
     * The vision is explicit that college choice "isn't meant to be self-serve,
     * it's meant to pull the family into the decision", so every college page
     * hands the student a question rather than a verdict. These are addressed
     * to the *student* — unlike every other bank here, which is addressed to
     * the adult — because the student is the one who has to start the
     * conversation, and a tool that offers to start it for them has taken the
     * first hard thing about leaving home and done it on their behalf.
     *
     * The questions are deliberately the uncomfortable ones. A family that
     * only ever discusses whether a school is good never discusses whether it
     * is payable, and the second conversation is the one that decides what
     * actually happens.
     *
     * @var list<string>
     */
    public const COLLEGE = [
        'Ask a parent what they honestly think your family could pay for :college a year, before anybody looks at aid.',
        'Ask someone who knows you whether they can picture you at :college — and ask them why, not just whether.',
        'Ask a parent what they would want to know about :college that you have not thought to look up.',
        'Ask an adult who went to college what they wish they had known before they chose, and then ask what that would mean for :college.',
        'Tell a parent you are considering :college and then let them talk first. You will learn more from what they ask than from what they advise.',
    ];

    /**
     * What to take to an adult about a job or an apprenticeship.
     *
     * Addressed to the student, like {@see self::COLLEGE}. The questions are
     * about the terms rather than about whether to apply, because the terms
     * are what a sixteen-year-old has no way of knowing are unusual and an
     * adult who has held a job knows immediately.
     *
     * @var list<string>
     */
    public const WORK = [
        'Ask an adult who has hired people what they would want to know about :company before you said yes.',
        'Tell a parent you are applying to :company, and ask them what the hours would mean for everything else you are doing.',
        'Ask somebody who has worked a job like this what the first week was actually like.',
        'Ask an adult you trust to read the posting from :company with you. Two people spot things one does not.',
    ];

    /**
     * The prompt for this student's parent this month.
     *
     * @param  Collection<int, Gap>  $gaps
     */
    public function forParent(User $student, ?Action $current, Collection $gaps, ?Carbon $asOf = null): string
    {
        $seed = $student->id.'|'.$this->period($asOf);

        if ($current) {
            return $this->fill($this->pick(self::PARENT_ACTION, $seed), $student, $current->title);
        }

        $openGap = $gaps->first(fn (Gap $gap) => $gap->isActive());

        if ($openGap instanceof Gap) {
            $bank = array_merge(
                [$openGap->type->closingMove()],
                self::PARENT_GAP[$openGap->type->value] ?? [],
            );

            return $this->fill($this->pick($bank, $seed), $student);
        }

        return $this->fill($this->pick(self::PARENT_QUIET, $seed), $student);
    }

    /**
     * The move for a cohort group this month.
     *
     * Seeded on the signal rather than on any student, because this heads a
     * list of people and must read the same way to everybody in the room.
     */
    public function forMentor(CohortSignal $signal, ?Carbon $asOf = null): string
    {
        $bank = array_merge([$signal->move()], self::MENTOR[$signal->value] ?? []);

        return $this->pick($bank, $signal->value.'|'.$this->period($asOf));
    }

    /**
     * The question to take to an adult about one college.
     *
     * Seeded on the college rather than on the month: a student comparing
     * eight schools in one sitting should get eight different questions, and
     * two students looking at the same school are not in the same room as each
     * other the way a cohort is.
     */
    public function forCollege(User $student, string $collegeName): string
    {
        $prompt = $this->pick(self::COLLEGE, $student->id.'|'.$collegeName);

        return str_replace(':college', $collegeName, $this->fill($prompt, $student));
    }

    /**
     * The question to take to an adult about one employer.
     *
     * Seeded on the employer for the same reason {@see self::forCollege()} is
     * seeded on the college: a student working through six postings in one
     * sitting should not be handed the same sentence six times.
     */
    public function forWork(User $student, string $companyName): string
    {
        $prompt = $this->pick(self::WORK, $student->id.'|'.$companyName);

        return str_replace(':company', $companyName, $this->fill($prompt, $student));
    }

    /**
     * Every line in the catalogue, for the tests that hold it to its rules.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(
            self::PARENT_ACTION,
            self::PARENT_QUIET,
            self::COLLEGE,
            self::WORK,
            ...array_values(self::PARENT_GAP),
            ...array_values(self::MENTOR),
        );
    }

    /**
     * Stable within a month, different between months.
     *
     * The parent email is monthly, so the month is the natural unit: a family
     * that opens the email twice sees the same sentence, and a family that
     * opens it in March gets a different one from February.
     */
    protected function period(?Carbon $asOf): string
    {
        return ($asOf ?? Carbon::now())->format('Y-m');
    }

    /**
     * @param  list<string>  $bank
     */
    protected function pick(array $bank, string $seed): string
    {
        if ($bank === []) {
            return '';
        }

        return $bank[crc32($seed) % count($bank)];
    }

    /**
     * The action's title arrives with its trailing full stop already in it,
     * and a prompt quotes it mid-sentence. Trimming it here keeps the
     * punctuation out of the middle of somebody's question.
     */
    protected function fill(string $prompt, User $student, ?string $action = null): string
    {
        return str_replace(
            [':name', ':action'],
            [strtok($student->name, ' ') ?: $student->name, rtrim((string) $action, '. ')],
            $prompt,
        );
    }
}
