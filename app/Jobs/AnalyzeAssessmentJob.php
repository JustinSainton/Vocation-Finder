<?php

namespace App\Jobs;

use App\Ai\Agents\CategoryDisambiguation;
use App\Ai\Agents\NarrativeSynthesis;
use App\Ai\Agents\VocationalAnalysis;
use App\Enums\ConfidenceLevel;
use App\Enums\EvaluationOutcome;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\EvaluationLog;
use App\Models\SignalExtraction;
use App\Support\BrainCapture;
use App\Support\CompetingPathways;
use App\Support\ConfidenceCalculator;
use App\Support\ConversationLocale;
use App\Support\DualTrack;
use App\Support\EngineVersion;
use App\Support\RedTeamLint;
use App\Support\RedTeamViolation;
use App\Support\ResponseQuality;
use App\Support\SignalExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\ProviderOverloadedException;

class AnalyzeAssessmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?float $startedAt = null;

    public int $timeout = 120;

    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(
        public Assessment $assessment,
    ) {
        $this->onQueue('ai-analysis');
    }

    /**
     * How many narrative drafts it took, and what the red-team pass said about
     * them. Held on the job so {@see static::recordEvaluation()} can report
     * them without threading two more return values through five layers.
     */
    protected int $narrativeAttempts = 0;

    /**
     * @var array<int, array{rule: string, severity: string, match: string}>
     */
    protected array $redTeamFindings = [];

    public function handle(): void
    {
        $this->startedAt = microtime(true);
        $startedAt = $this->startedAt;

        Log::info('assessment_analysis_started', [
            'assessment_id' => $this->assessment->id,
            'attempt' => $this->attempts(),
            'queue' => $this->queue,
        ]);

        $model = $this->resolveModel();
        $locale = ConversationLocale::normalize($this->assessment->locale);

        // Layer 4: Signal detection. Runs before mapping because mapping
        // consumes signals; every stored signal has been proven to trace back
        // to a span the respondent actually wrote.
        $this->detectSignals($locale);

        // Phase A: Structured pattern analysis (Layer 5, taxonomy mapping)
        $agent = new VocationalAnalysis($this->assessment->fresh(), $locale);
        $analysisResponse = $this->retryOnOverload(fn () => $agent->prompt(
            $agent->buildPrompt(),
            model: $model,
        ));

        $analysisData = $analysisResponse->structured;

        // Validate Phase A output
        $this->validateAnalysis($analysisData, $analysisResponse->text ?? '');

        // Phase A2: Disambiguation — only when pathways genuinely compete
        $analysisData = $this->disambiguateCompetingPathways($analysisData, $model);

        // Phase A2.5: Score each answer's evidentiary richness. Runs after
        // Layer 4 because it counts verified signals, not words.
        $quality = $this->scoreResponseQuality();

        // Phase A3: Confidence. Derived from the scores and the evidence
        // behind them, never asked of the model.
        $confidence = ConfidenceCalculator::explain(
            $analysisData['category_scores'],
            $this->respondentWords(),
            $quality,
        );
        $analysisData['category_scores'] = ConfidenceCalculator::annotate(
            $analysisData['category_scores'],
            $this->respondentWords(),
            $quality,
        );

        // Phase A4: The dual track. The analysis pass cited which signals bear
        // on which pathway; the weights and the standing are computed here
        // from the signals themselves, so the model cannot talk its way into
        // "demonstrated" on a pathway the student has only ever wanted.
        $signals = $this->assessment->signalExtractions()->get();

        /*
         | Said out loud before it is dropped. A model that cites anything
         | other than a signal reference produces a portrait in which every
         | layer worked and nothing is evidenced, and the only visible symptom
         | is a standing of `unevidenced` on a rich assessment.
         */
        $uncited = DualTrack::uncitedReferences($analysisData['category_scores'], $signals);

        if ($uncited !== []) {
            Log::warning('evidence_citations_discarded', [
                'assessment_id' => $this->assessment->id,
                'signals_available' => $signals->count(),
                'discarded' => count($uncited),
                'sample' => array_slice($uncited, 0, 5),
            ]);
        }

        $analysisData['category_scores'] = DualTrack::annotate(
            $analysisData['category_scores'],
            $signals,
        );
        $evidenceGap = DualTrack::gap($analysisData['category_scores']);

        // Phase B: Narrative synthesis
        [$narrative, $sections] = $this->synthesizeNarrative($analysisData, $model, $locale, $confidence);

        // Save vocational profile
        $this->assessment->vocationalProfile()->updateOrCreate(
            ['assessment_id' => $this->assessment->id],
            [
                'opening_synthesis' => $sections['opening_synthesis'],
                'vocational_orientation' => $sections['vocational_orientation'],
                'primary_pathways' => $sections['primary_pathways'],
                'specific_considerations' => $sections['specific_considerations'],
                'next_steps' => $sections['next_steps'],
                'ministry_integration' => $sections['ministry_integration'],
                'primary_domain' => $analysisData['primary_domain'],
                'mode_of_work' => $analysisData['mode_of_work'],
                'secondary_orientation' => $analysisData['secondary_orientation'],
                'category_scores' => $analysisData['category_scores'],
                'confidence_level' => $confidence['level'],
                'confidence_rationale' => $confidence['rationale'],
                'missing_evidence' => $confidence['missing_evidence'],
                'evidence_gap' => $evidenceGap,
                'ai_analysis_raw' => $analysisData,
                ...EngineVersion::all(),
            ],
        );

        $this->assessment->update(['status' => 'completed']);

        $this->seedBrain();

        $this->recordEvaluation(EvaluationOutcome::Completed, confidence: $confidence['level']);

        $this->dispatchCurriculum();

        Log::info('assessment_analysis_completed', [
            'assessment_id' => $this->assessment->id,
            'confidence_level' => $confidence['level']->value,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    /**
     * Roadmap 0.5 — write down what this run actually did.
     *
     * Every defect the first live run exposed had been running "successfully"
     * for weeks. A lint refusing every healthcare narrative shows up here as
     * `narrative_attempts = 2` with a `clinical` finding, in a query that
     * takes a second. Without the row it took a live run and a transcript.
     *
     * Recorded on the failure path too, and that path matters more: a run that
     * produced nothing leaves no profile, so this row is the only evidence it
     * ever happened.
     *
     * Logging must never be able to break the thing it observes, so the whole
     * method is swallowed. A dropped row is a gap in the record; an exception
     * here would be a student losing a result they had already earned.
     */
    protected function recordEvaluation(
        EvaluationOutcome $outcome,
        ?ConfidenceLevel $confidence = null,
        ?string $failureReason = null,
    ): void {
        try {
            EvaluationLog::create([
                'assessment_id' => $this->assessment->id,
                'user_id' => $this->assessment->user_id,
                ...EngineVersion::all(),
                'outcome' => $outcome,
                'failure_reason' => $failureReason ? Str::limit($failureReason, 250) : null,
                'confidence_level' => $confidence,
                'signals_kept' => SignalExtraction::where('assessment_id', $this->assessment->id)->count(),
                'response_quality' => ($quality = $this->assessment->answers()->avg('response_quality_score')) !== null
                    ? (int) round((float) $quality)
                    : null,
                'narrative_attempts' => $this->narrativeAttempts ?: null,
                'red_team_findings' => $this->redTeamFindings,
                'duration_ms' => $this->startedAt
                    ? (int) round((microtime(true) - $this->startedAt) * 1000)
                    : null,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('evaluation_log_failed', [
                'assessment_id' => $this->assessment->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Hand off to curriculum generation, if there is a curriculum to generate.
     *
     * Two things are deliberate here, and both were found by running the loop
     * against a live model rather than by reading the code.
     *
     * First, the guard. {@see CurriculumEngine} throws when no course is
     * published, and courses are a legacy surface that V1 does not ship. Every
     * completed assessment was therefore queuing a job that could only fail,
     * marking a pathway "failed" and logging an error for a feature the
     * student was never offered. Asking the question at the call site is the
     * honest version of the engine's own precondition.
     *
     * Second, the catch. By this point the profile is persisted and the
     * assessment is marked complete — the student's result exists and is
     * theirs. A downstream hand-off must not be able to unwind that. Under a
     * synchronous dispatcher it otherwise propagates straight out of handle(),
     * and the whole nine-layer pipeline is re-run on retry at full cost for a
     * result that was already correct.
     */
    protected function dispatchCurriculum(): void
    {
        $user = $this->assessment->user;

        if (! $user || Course::published()->doesntExist()) {
            return;
        }

        try {
            GenerateCurriculumJob::dispatch($user, $this->assessment);
        } catch (\Throwable $exception) {
            Log::warning('curriculum_dispatch_failed', [
                'assessment_id' => $this->assessment->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Resolve closely-scored pathways with a second interpretive pass.
     *
     * Blueprint layers 5 and 6 are distinct: mapping to the taxonomy and
     * resolving between competing mappings are different operations. Most
     * assessments produce a clear leader and skip this entirely.
     *
     * Failure here is non-fatal by design. An unresolved ambiguity leaves the
     * first-pass scores intact, which is a worse result but still a valid one;
     * losing the whole analysis would be worse still.
     *
     * @param  array<string, mixed>  $analysisData
     * @return array<string, mixed>
     */
    protected function disambiguateCompetingPathways(array $analysisData, string $model): array
    {
        $competing = CompetingPathways::detect($analysisData['category_scores'] ?? []);

        if ($competing === []) {
            return $analysisData;
        }

        Log::info('assessment_pathways_competing', [
            'assessment_id' => $this->assessment->id,
            'candidates' => $competing,
        ]);

        try {
            $agent = new CategoryDisambiguation(
                competingSlugs: $competing,
                answers: $this->answersForPrompt(),
                areAdjacent: CompetingPathways::areAdjacent($competing),
            );

            $resolution = $this->retryOnOverload(fn () => $agent->prompt(
                $agent->buildPrompt(),
                model: $model,
            ))->structured;
        } catch (\Throwable $exception) {
            Log::warning('assessment_disambiguation_failed', [
                'assessment_id' => $this->assessment->id,
                'error' => $exception->getMessage(),
            ]);

            return $analysisData;
        }

        $analysisData['disambiguation'] = $resolution;

        if (($resolution['resolved'] ?? false) !== true) {
            return $analysisData;
        }

        return $this->applyResolvedRanking($analysisData, $resolution['ranking'] ?? []);
    }

    /**
     * Overlay the resolved scores onto the first-pass ranking.
     *
     * Only the competing categories are touched. Everything the second pass was
     * not asked about keeps its original score.
     *
     * @param  array<string, mixed>  $analysisData
     * @param  array<int, array{category?: string, score?: int}>  $ranking
     * @return array<string, mixed>
     */
    protected function applyResolvedRanking(array $analysisData, array $ranking): array
    {
        if ($ranking === []) {
            return $analysisData;
        }

        $resolved = collect($ranking)
            ->filter(fn (array $row) => filled($row['category'] ?? null))
            ->keyBy(fn (array $row) => Str::lower(str_replace('&', 'and', $row['category'])));

        $analysisData['category_scores'] = collect($analysisData['category_scores'] ?? [])
            ->map(function (array $row) use ($resolved) {
                $match = $resolved->get(Str::lower(str_replace('&', 'and', $row['category'] ?? '')));

                if ($match === null) {
                    return $row;
                }

                return array_merge($row, [
                    'score' => $match['score'] ?? $row['score'],
                    'rationale' => $match['rationale'] ?? $row['rationale'],
                ]);
            })
            ->all();

        return $analysisData;
    }

    /**
     * The student's answers, shaped for the disambiguation prompt.
     *
     * @return array<int, array{question: string, response: string}>
     */
    /**
     * The respondent's own words, one entry per answered question.
     *
     * Audio-mode assessments carry their content in the transcript, matching
     * how VocationalAnalysis reads the same answers.
     *
     * @return list<string>
     */
    /**
     * Run Layer 4 and swallow its failure.
     *
     * Signals enrich the mapping pass but are not load-bearing for it: the
     * analysis was correct before this layer existed and stays correct if the
     * extraction call fails. Letting a Layer 4 timeout fail the whole job
     * would trade a better result for no result at all.
     */
    protected function detectSignals(string $locale): void
    {
        try {
            $signals = $this->retryOnOverload(
                fn () => (new SignalExtractor)->extract($this->assessment, $locale),
            );

            Log::info('signal_detection_completed', [
                'assessment_id' => $this->assessment->id,
                'signals' => $signals->count(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('signal_detection_failed', [
                'assessment_id' => $this->assessment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Put the student's assessment answers into their brain.
     *
     * Runs at the end so the brain is never empty the first time they open it.
     * These are the earliest words we have from them, and in nine months they
     * are the ones worth showing back.
     *
     * Non-fatal, like every other post-analysis layer: a student not entitled
     * to a brain still gets their portrait, and a capture failure must not
     * cost them a completed analysis.
     */
    protected function seedBrain(): void
    {
        try {
            $entries = (new BrainCapture)->captureAssessment($this->assessment);

            Log::info('brain_seeded', [
                'assessment_id' => $this->assessment->id,
                'entries' => count($entries),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('brain_seeding_failed', [
                'assessment_id' => $this->assessment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Score every answer and return the scores for the confidence pass.
     *
     * Returns null rather than an empty list on failure: null means "no
     * quality signal, fall back to word counts", while an empty list would
     * mean "measured, and there is nothing there" and would wrongly force
     * insufficient evidence.
     *
     * @return list<int>|null
     */
    protected function scoreResponseQuality(): ?array
    {
        try {
            $scores = (new ResponseQuality)->scoreAssessment($this->assessment);

            return $scores->isEmpty() ? null : $scores->values()->all();
        } catch (\Throwable $exception) {
            Log::warning('response_quality_scoring_failed', [
                'assessment_id' => $this->assessment->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function respondentWords(): array
    {
        return $this->assessment->answers()
            ->orderBy('id')
            ->get()
            ->map(fn ($answer) => (string) ($answer->response_text ?: $answer->audio_transcript))
            ->filter(fn (string $text) => trim($text) !== '')
            ->values()
            ->all();
    }

    protected function answersForPrompt(): array
    {
        return $this->assessment->answers()
            ->with('question')
            ->get()
            ->map(fn ($answer) => [
                'question' => $answer->question->question_text ?? '',
                // Audio-mode assessments carry their content in the transcript,
                // matching how VocationalAnalysis reads the same answers.
                'response' => $answer->response_text ?: $answer->audio_transcript,
            ])
            ->filter(fn (array $row) => filled($row['response']))
            ->values()
            ->all();
    }

    /**
     * The job passes one model to every phase, so it has to honour the engine
     * override itself: a `model:` argument wins over the agent's own
     * `model()`, and an override the call site quietly outranked would send a
     * Claude checkpoint to whatever provider was actually configured. That
     * failure surfaces as a 404 from the local server, which reads like the
     * server being down.
     */
    protected function resolveModel(): string
    {
        return config('vocation.engine.model') ?: config('vocation.ai.model');
    }

    /**
     * Retry a closure up to 3 times when the AI provider is overloaded (HTTP 529),
     * using exponential backoff. Works regardless of queue driver.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function retryOnOverload(callable $callback): mixed
    {
        $delays = [10, 20];

        for ($attempt = 0; ; $attempt++) {
            try {
                return $callback();
            } catch (ProviderOverloadedException $e) {
                if ($attempt >= count($delays)) {
                    throw $e;
                }

                Log::warning('ai_provider_overloaded_retrying', [
                    'assessment_id' => $this->assessment->id,
                    'attempt' => $attempt + 1,
                    'delay_seconds' => $delays[$attempt],
                ]);

                sleep($delays[$attempt]);
            }
        }
    }

    protected function validateAnalysis(array $data, string $raw = ''): void
    {
        // Layer 1: Structure check (JSON schema enforced by SDK, but verify critical fields)
        $required = ['dimensions', 'category_scores', 'primary_domain', 'mode_of_work', 'secondary_orientation'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                /*
                 | A schema-constrained model can satisfy the grammar and
                 | still say nothing — `"dimensions": {}` is a valid object.
                 | Naming only the field left the reader unable to tell an
                 | absent key from a present but empty one, which are
                 | different defects with different fixes.
                 */
                /*
                 | The raw text is logged because without it a bad structured
                 | response is undiagnosable: the parsed array is empty, the
                 | exception names a field, and whatever the model actually
                 | said is gone. Truncated, because it can be very long.
                 */
                Log::error('analysis_structure_rejected', [
                    'assessment_id' => $this->assessment->id,
                    'field' => $field,
                    'keys' => array_keys($data),
                    'raw_length' => mb_strlen($raw),
                    'raw_parses' => json_decode($raw) !== null,
                    'raw_head' => mb_substr($raw, 0, 400),
                    'raw_tail' => mb_substr($raw, -400),
                ]);

                throw new \RuntimeException(sprintf(
                    'Analysis missing required field: %s (%s; keys returned: %s)',
                    $field,
                    array_key_exists($field, $data) ? 'present but empty' : 'absent',
                    implode(', ', array_keys($data)) ?: 'none',
                ));
            }
        }

        // Layer 2: Completeness — verify all 17 categories scored
        if (count($data['category_scores']) < 17) {
            Log::warning('Analysis returned fewer than 17 category scores', [
                'assessment_id' => $this->assessment->id,
                'count' => count($data['category_scores']),
            ]);
        }

        // Layer 3: Content safety — check for clinically diagnostic language
        $flaggedTerms = ['diagnos', 'disorder', 'syndrome', 'patholog', 'mental health condition'];
        $json = strtolower(json_encode($data));
        foreach ($flaggedTerms as $term) {
            if (str_contains($json, $term)) {
                Log::warning('Analysis contains flagged clinical language', [
                    'assessment_id' => $this->assessment->id,
                    'term' => $term,
                ]);
            }
        }
    }

    protected function validateNarrative(string $narrative): void
    {
        // Layer 3: Completeness — verify all sections present
        $requiredSections = [
            'opening_synthesis' => ['Opening Synthesis', 'opening synthesis'],
            'vocational_orientation' => ['Vocational Orientation', 'vocational orientation'],
            'primary_pathways' => ['Primary Pathways', 'primary pathways'],
            'specific_considerations' => ['Specific Considerations', 'specific considerations'],
            'next_steps' => ['Next Steps', 'next steps'],
            'ministry_integration' => ['Ministry Integration', 'ministry integration'],
        ];

        $lowerNarrative = strtolower($narrative);
        foreach ($requiredSections as $key => $variants) {
            $found = false;
            foreach ($variants as $variant) {
                if (str_contains($lowerNarrative, strtolower($variant))) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw new \RuntimeException("Narrative missing required section: {$key}");
            }
        }

        // Layer 8: Red-team. Blocking language is refused outright rather than
        // logged; a warning is recorded and the narrative still ships.
        $findings = RedTeamLint::inspect($narrative);

        $this->redTeamFindings = $findings;

        if (filled($warnings = RedTeamLint::warnings($findings))) {
            Log::warning('narrative_red_team_warnings', [
                'assessment_id' => $this->assessment->id,
                'findings' => $warnings,
            ]);
        }

        if (filled($blocking = RedTeamLint::blocking($findings))) {
            Log::error('narrative_red_team_blocked', [
                'assessment_id' => $this->assessment->id,
                'findings' => $blocking,
            ]);

            throw new RedTeamViolation($blocking);
        }

        // Layer 4: Theological alignment — ministry integration must be substantive
        $ministryPos = strripos($narrative, 'Ministry Integration');
        if ($ministryPos !== false) {
            $ministrySection = substr($narrative, $ministryPos);
            if (strlen($ministrySection) < 200) {
                Log::warning('Ministry integration section is thin', [
                    'assessment_id' => $this->assessment->id,
                    'length' => strlen($ministrySection),
                ]);
            }
        }
    }

    protected function validateParsedSections(array $sections): void
    {
        $pathwayCount = count($sections['primary_pathways'] ?? []);
        if ($pathwayCount < 3) {
            throw new \RuntimeException("Narrative should contain at least 3 primary pathways, got {$pathwayCount}");
        }

        $nextStepCount = count($sections['next_steps'] ?? []);
        if ($nextStepCount < 3) {
            throw new \RuntimeException("Narrative should contain at least 3 next steps, got {$nextStepCount}");
        }
    }

    protected function synthesizeNarrative(array $analysisData, string $model, string $locale, ?array $confidence = null): array
    {
        /*
         * Refused before generation rather than after, because there is no
         * repair for it: the lint cannot read the language, so a rewritten
         * draft would come back just as unreadable and the retry loop would
         * simply burn three model calls before failing anyway.
         */
        if (! RedTeamLint::covers($locale)) {
            throw new \RuntimeException(RedTeamLint::unverifiableLanguageReason($locale));
        }

        $feedback = '';
        $lastException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->narrativeAttempts = $attempt;

            try {
                $narrativeAgent = new NarrativeSynthesis(
                    $analysisData,
                    $feedback,
                    $locale,
                    $confidence['level'] ?? null,
                    $confidence['missing_evidence'] ?? [],
                );
                $narrativeResponse = $this->retryOnOverload(fn () => $narrativeAgent->prompt(
                    $narrativeAgent->buildPrompt(),
                    model: $model,
                ));

                $narrative = $narrativeResponse->text;

                $this->validateNarrative($narrative);

                $sections = $this->parseNarrativeSections($narrative);
                $this->validateParsedSections($sections);

                return [$narrative, $sections];
            } catch (RedTeamViolation $violation) {
                $lastException = $violation;
                $feedback = RedTeamLint::repairInstruction($violation->findings);
            } catch (\Throwable $exception) {
                $lastException = $exception;
                $feedback = <<<TEXT
The previous draft did not conform to the required output format.

Correction needed:
- {$exception->getMessage()}
- Use the exact markdown headers already specified
- Provide 3-5 distinct `Primary Pathways` bullets
- Provide 3-5 distinct `Next Steps` numbered items
- Do not collapse multiple ideas into a single bullet
TEXT;
            }
        }

        throw $lastException ?? new \RuntimeException('Narrative synthesis failed unexpectedly.');
    }

    protected function parseNarrativeSections(string $narrative): array
    {
        $sections = [
            'opening_synthesis' => '',
            'vocational_orientation' => '',
            'primary_pathways' => [],
            'specific_considerations' => '',
            'next_steps' => [],
            'ministry_integration' => '',
        ];

        // Split by markdown headers (## or **)
        $pattern = '/(?:^|\n)(?:#{1,3}|\*\*)\s*(.+?)(?:\*\*)?(?:\n)/i';
        $parts = preg_split($pattern, $narrative, -1, PREG_SPLIT_DELIM_CAPTURE);

        $currentSection = null;
        $sectionMap = [
            'opening synthesis' => 'opening_synthesis',
            'vocational orientation' => 'vocational_orientation',
            'primary pathways' => 'primary_pathways',
            'specific considerations' => 'specific_considerations',
            'next steps' => 'next_steps',
            'ministry integration' => 'ministry_integration',
        ];

        for ($i = 0; $i < count($parts); $i++) {
            $normalized = strtolower(trim(str_replace(['#', '*'], '', $parts[$i])));

            foreach ($sectionMap as $needle => $key) {
                if (str_contains($normalized, $needle)) {
                    $currentSection = $key;

                    continue 2;
                }
            }

            if ($currentSection && isset($parts[$i]) && trim($parts[$i]) !== '') {
                $content = trim($parts[$i]);

                if (in_array($currentSection, ['primary_pathways', 'next_steps'])) {
                    $sections[$currentSection] = $this->extractListItems($content);
                } else {
                    $sections[$currentSection] .= $content;
                }
            }
        }

        return $sections;
    }

    protected function extractListItems(string $content): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($content)) ?: [];
        $items = [];
        $current = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(?:[-•*]|\d+[.)])\s+(.*)$/u', $trimmed, $matches)) {
                if ($current !== null) {
                    $items[] = $this->normalizeListItem($current);
                }

                $current = $matches[1];

                continue;
            }

            if ($current === null) {
                $current = $trimmed;

                continue;
            }

            $current .= ' '.$trimmed;
        }

        if ($current !== null) {
            $items[] = $this->normalizeListItem($current);
        }

        return array_values(array_filter($items));
    }

    protected function normalizeListItem(string $item): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $item) ?? $item);
        $normalized = str_replace('**', '', $normalized);
        $normalized = preg_replace('/^\s*[-•*]\s*/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Assessment analysis failed', [
            'assessment_id' => $this->assessment->id,
            'error' => $exception->getMessage(),
        ]);

        $this->recordEvaluation(EvaluationOutcome::Failed, failureReason: $exception->getMessage());

        $this->assessment->update(['status' => 'failed']);
    }
}
