<?php

namespace App\Ai\Agents;

use App\Support\SyllabusImporter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Pull the dated work out of a syllabus.
 *
 * This is the one place in the in-college layer where a model touches a
 * student's calendar, and a wrong deadline here is worse than no deadline:
 * a student who trusts the tool and misses a paper has been harmed by the
 * thing that was supposed to help. So the model is given the smallest
 * possible job — **point at the words** — and everything consequential is
 * computed afterwards by {@see SyllabusImporter}.
 *
 * Two deliberate omissions from this schema:
 *
 * 1. **No ISO date.** The model returns `due_text`: the date exactly as it is
 *    printed in the syllabus. The calendar date is then parsed from that span
 *    in PHP. Asking for `2026-10-14` invites a plausible-looking number that
 *    nothing can check; asking for "Oct 14" yields a string that either
 *    appears in the document or does not.
 * 2. **No priority, weight or difficulty.** Ranking a student's coursework is
 *    not ours to do, and a model's guess at it would read as authority.
 */
#[Provider('anthropic')]
#[Model('claude-sonnet-4-6')]
#[Timeout(120)]
class SyllabusParser implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected string $sourceText,
        protected ?string $courseCode = null,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You read a course syllabus and list the dated work in it.

        You are not summarising the course, not advising the student, and not
        judging how hard anything is. You are transcribing.

        ## What counts

        Anything with a date attached that the student has to hand in, sit, or
        show up for: assignments, papers, problem sets, labs, quizzes,
        midterms, finals, presentations, required events.

        Not: office hours, weekly readings with no due date, the instructor's
        policies, or the class meeting pattern.

        ## The copying rule

        Both fields you return must be copied **character for character** from
        the syllabus:

        - `title` — the name of the work as the syllabus writes it.
        - `due_text` — the date as the syllabus prints it. "Friday, Oct 14",
          "10/14", "Week 6 — Nov 3". Whatever is actually there.

        Do not tidy, expand, translate, or normalise either one. Do not convert
        a date to another format. Do not work out a date that the syllabus only
        implies. If the work has no printed date, leave it out entirely — an
        assignment you invent a date for is the single worst thing you could
        return here.

        If the document is not a syllabus, or contains no dated work, return an
        empty list. An empty list is a correct answer.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'assignments' => $schema
                ->array()
                ->items($schema->object([
                    'title' => $schema
                        ->string()
                        ->description('The name of the work, copied exactly from the syllabus')
                        ->required(),
                    'due_text' => $schema
                        ->string()
                        ->description('The date exactly as the syllabus prints it, not reformatted')
                        ->required(),
                ]))
                ->description('Every piece of dated work, in the order the syllabus lists it')
                ->required(),
        ];
    }

    public function buildPrompt(): string
    {
        $course = $this->courseCode ? "## Course\n\n{$this->courseCode}\n\n" : '';
        $text = mb_substr($this->sourceText, 0, 60000);

        return <<<PROMPT
        {$course}## Syllabus

        {$text}

        ---

        List the dated work. Copy the words; do not compute anything.
        PROMPT;
    }
}
