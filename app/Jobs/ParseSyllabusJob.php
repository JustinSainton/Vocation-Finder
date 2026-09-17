<?php

namespace App\Jobs;

use App\Models\Syllabus;
use App\Support\SyllabusImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Parse an uploaded syllabus off the request cycle.
 *
 * Queued rather than inline because a student pasting a syllabus should get
 * their page back immediately, and because a provider timeout must not lose
 * the document — the text is already stored before this runs, so a failed
 * parse is retryable against the same source.
 */
class ParseSyllabusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public Syllabus $syllabus)
    {
        $this->onQueue('ai-analysis');
    }

    public function handle(SyllabusImporter $importer): void
    {
        $importer->import($this->syllabus);
    }
}
