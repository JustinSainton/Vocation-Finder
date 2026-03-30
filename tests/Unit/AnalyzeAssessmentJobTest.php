<?php

namespace Tests\Unit;

use App\Jobs\AnalyzeAssessmentJob;
use App\Models\Assessment;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class AnalyzeAssessmentJobTest extends TestCase
{
    #[Test]
    public function it_extracts_multiple_markdown_list_items_cleanly(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $method = new ReflectionMethod($job, 'extractListItems');
        $method->setAccessible(true);

        $items = $method->invoke($job, <<<'TEXT'
- **Architecture** — Designing schools and community spaces that dignify the people who use them.
- **Urban planning** — Shaping neighborhoods and civic systems with long-term stewardship in view.
- **Design-build leadership** — Combining design, execution, and team leadership in one practice.
TEXT);

        $this->assertSame([
            'Architecture — Designing schools and community spaces that dignify the people who use them.',
            'Urban planning — Shaping neighborhoods and civic systems with long-term stewardship in view.',
            'Design-build leadership — Combining design, execution, and team leadership in one practice.',
        ], $items);
    }

    #[Test]
    public function it_rejects_narratives_with_too_few_pathways_or_next_steps(): void
    {
        $job = new AnalyzeAssessmentJob(new Assessment);

        $method = new ReflectionMethod($job, 'validateParsedSections');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);

        $method->invoke($job, [
            'primary_pathways' => ['Only one pathway'],
            'next_steps' => ['Only one next step'],
        ]);
    }
}
