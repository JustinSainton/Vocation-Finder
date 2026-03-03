<?php

namespace App\Console\Commands;

use App\Ai\Agents\ConversationAgent;
use App\Models\Answer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Throwable;

class EvaluateConversationModelsCommand extends Command
{
    protected $signature = 'ai:evaluate-conversation-models
        {--sample-size=20 : Number of stored answers to evaluate}
        {--seed=42 : Seed for deterministic sample selection}
        {--control-provider= : Override control arm provider}
        {--control-model= : Override control arm model}
        {--treatment-provider= : Override treatment arm provider}
        {--treatment-model= : Override treatment arm model}
        {--output= : Optional JSON output file path}
        {--dry-run : Build sample set and print arm config without calling model APIs}';

    protected $description = 'Evaluate conversation model control vs treatment on stored answer samples';

    public function handle(): int
    {
        $sampleSize = max(1, (int) $this->option('sample-size'));
        $seed = (string) $this->option('seed');

        $control = $this->armConfig('control');
        $treatment = $this->armConfig('treatment');

        try {
            $samples = $this->loadSamples($sampleSize, $seed);
        } catch (Throwable $e) {
            $this->error('Failed to load answer samples from the database: '.$e->getMessage());

            return self::FAILURE;
        }
        if ($samples->isEmpty()) {
            $this->warn('No answer samples found. Add assessment answers first, then rerun.');

            return self::FAILURE;
        }

        $this->info(sprintf('Loaded %d samples (seed=%s)', $samples->count(), $seed));
        $this->line(sprintf(
            'Control: %s / %s',
            $control['provider'] ?? '(default)',
            $control['model'] ?? '(default)'
        ));
        $this->line(sprintf(
            'Treatment: %s / %s',
            $treatment['provider'] ?? '(default)',
            $treatment['model'] ?? '(default)'
        ));

        if ((bool) $this->option('dry-run')) {
            $this->comment('Dry run enabled; no provider API calls were made.');

            return self::SUCCESS;
        }

        $controlResults = $this->evaluateArm('control', $control, $samples);
        $treatmentResults = $this->evaluateArm('treatment', $treatment, $samples);

        $rows = [
            $this->summaryRow($controlResults),
            $this->summaryRow($treatmentResults),
        ];

        $this->newLine();
        $this->table(
            ['Arm', 'Provider', 'Model', 'Samples', 'Success', 'Schema Pass', 'Avg Quality', 'Avg Latency (ms)'],
            $rows
        );

        $outputPath = $this->option('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            $this->writeJsonReport($outputPath, $samples, $controlResults, $treatmentResults);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{provider: ?string, model: ?string}
     */
    protected function armConfig(string $arm): array
    {
        $config = config("vocation.ai.conversation_experiment.{$arm}", []);
        $defaultProvider = is_array($config) ? ($config['provider'] ?? null) : null;
        $defaultModel = is_array($config) ? ($config['model'] ?? null) : null;

        $providerOpt = $this->option("{$arm}-provider");
        $modelOpt = $this->option("{$arm}-model");

        return [
            'provider' => $this->optionStringOrDefault($providerOpt, $defaultProvider),
            'model' => $this->optionStringOrDefault($modelOpt, $defaultModel),
        ];
    }

    protected function optionStringOrDefault(mixed $optionValue, mixed $default): ?string
    {
        if (is_string($optionValue) && trim($optionValue) !== '') {
            return trim($optionValue);
        }

        if (is_string($default) && trim($default) !== '') {
            return trim($default);
        }

        return null;
    }

    /**
     * @return Collection<int, array{sample_id: string, assessment_id: string, question_id: string, question_text: string, user_response: string, follow_up_prompts: array}>
     */
    protected function loadSamples(int $sampleSize, string $seed): Collection
    {
        $poolSize = max($sampleSize * 8, 200);

        $pool = Answer::query()
            ->with('question:id,question_text,follow_up_prompts')
            ->where(function ($query) {
                $query->whereNotNull('audio_transcript')
                    ->orWhereNotNull('response_text');
            })
            ->latest('created_at')
            ->limit($poolSize)
            ->get()
            ->filter(fn (Answer $answer) => $answer->question !== null)
            ->values();

        return $pool
            ->sortBy(function (Answer $answer) use ($seed) {
                return sprintf('%u', crc32($answer->id.'|'.$seed));
            })
            ->take($sampleSize)
            ->map(function (Answer $answer) {
                $userResponse = $answer->audio_transcript ?: $answer->response_text;

                return [
                    'sample_id' => $answer->id,
                    'assessment_id' => $answer->assessment_id,
                    'question_id' => $answer->question_id,
                    'question_text' => $answer->question->question_text,
                    'user_response' => $userResponse,
                    'follow_up_prompts' => $answer->question->follow_up_prompts ?? [],
                ];
            })
            ->values();
    }

    /**
     * @param  array{provider: ?string, model: ?string}  $armConfig
     * @param  Collection<int, array{sample_id: string, assessment_id: string, question_id: string, question_text: string, user_response: string, follow_up_prompts: array}>  $samples
     * @return array<string, mixed>
     */
    protected function evaluateArm(string $armName, array $armConfig, Collection $samples): array
    {
        $runs = [];
        $schemaPasses = 0;
        $qualityTotal = 0.0;
        $latencyTotalMs = 0.0;
        $successCount = 0;
        $failureCount = 0;

        $this->newLine();
        $this->info(sprintf('Running %s arm...', $armName));

        foreach ($samples as $sample) {
            $agent = new ConversationAgent(
                questionText: $sample['question_text'],
                userResponse: $sample['user_response'],
                followUpPrompts: $sample['follow_up_prompts'],
                previousTurns: [],
            );

            $started = hrtime(true);

            try {
                $response = $agent->prompt(
                    $agent->buildPrompt(),
                    provider: $armConfig['provider'],
                    model: $armConfig['model'],
                );
                $latencyMs = (hrtime(true) - $started) / 1_000_000;

                /** @var array<string, mixed> $structured */
                $structured = is_array($response->structured) ? $response->structured : [];
                $evaluation = $this->evaluateStructuredOutput($structured);

                $successCount++;
                $schemaPasses += $evaluation['schema_pass'] ? 1 : 0;
                $qualityTotal += $evaluation['quality_score'];
                $latencyTotalMs += $latencyMs;

                $runs[] = [
                    'sample_id' => $sample['sample_id'],
                    'status' => 'ok',
                    'latency_ms' => round($latencyMs, 2),
                    'schema_pass' => $evaluation['schema_pass'],
                    'quality_score' => round($evaluation['quality_score'], 2),
                    'issues' => $evaluation['issues'],
                    'output' => $structured,
                ];
            } catch (Throwable $e) {
                $failureCount++;
                $runs[] = [
                    'sample_id' => $sample['sample_id'],
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'arm' => $armName,
            'provider' => $armConfig['provider'],
            'model' => $armConfig['model'],
            'sample_count' => $samples->count(),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'schema_pass_count' => $schemaPasses,
            'success_rate' => $samples->count() > 0 ? $successCount / $samples->count() : 0.0,
            'schema_pass_rate' => $samples->count() > 0 ? $schemaPasses / $samples->count() : 0.0,
            'avg_quality_score' => $successCount > 0 ? $qualityTotal / $successCount : 0.0,
            'avg_latency_ms' => $successCount > 0 ? $latencyTotalMs / $successCount : 0.0,
            'runs' => $runs,
        ];
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array{schema_pass: bool, quality_score: float, issues: array<int, string>}
     */
    protected function evaluateStructuredOutput(array $structured): array
    {
        $issues = [];
        $score = 0.0;

        $hasIsSufficient = array_key_exists('is_sufficient', $structured) && is_bool($structured['is_sufficient']);
        $hasReasoning = is_string($structured['reasoning'] ?? null) && trim($structured['reasoning']) !== '';
        $hasFollowUpField = array_key_exists('follow_up_question', $structured);
        $hasSynthField = array_key_exists('synthesized_answer', $structured);

        if ($hasIsSufficient) {
            $score += 20;
        } else {
            $issues[] = 'missing_or_invalid_is_sufficient';
        }

        if ($hasReasoning) {
            $reasoningLen = mb_strlen(trim((string) $structured['reasoning']));
            $score += $reasoningLen >= 24 ? 20 : 10;
            if ($reasoningLen < 24) {
                $issues[] = 'reasoning_too_short';
            }
        } else {
            $issues[] = 'missing_reasoning';
        }

        $followUp = $structured['follow_up_question'] ?? null;
        $synth = $structured['synthesized_answer'] ?? null;

        $isSufficient = $hasIsSufficient ? (bool) $structured['is_sufficient'] : null;

        if ($isSufficient === true) {
            if (is_string($synth) && trim($synth) !== '') {
                $synthLen = mb_strlen(trim($synth));
                $score += $synthLen >= 50 ? 40 : 20;
                if ($synthLen < 50) {
                    $issues[] = 'synthesized_answer_short';
                }
            } else {
                $issues[] = 'missing_synthesized_answer_for_sufficient_response';
            }

            if ($followUp === null || (is_string($followUp) && trim($followUp) === '')) {
                $score += 20;
            } else {
                $issues[] = 'follow_up_present_when_sufficient';
            }
        } elseif ($isSufficient === false) {
            if (is_string($followUp) && trim($followUp) !== '') {
                $followLen = mb_strlen(trim($followUp));
                $score += $followLen >= 15 ? 40 : 20;
                if (! str_ends_with(trim($followUp), '?')) {
                    $issues[] = 'follow_up_not_question_form';
                } else {
                    $score += 10;
                }
            } else {
                $issues[] = 'missing_follow_up_for_insufficient_response';
            }

            if ($synth === null || (is_string($synth) && trim($synth) === '')) {
                $score += 10;
            } else {
                $issues[] = 'synthesized_answer_present_when_insufficient';
            }
        }

        if (! $hasFollowUpField) {
            $issues[] = 'missing_follow_up_question_field';
        }
        if (! $hasSynthField) {
            $issues[] = 'missing_synthesized_answer_field';
        }

        $schemaPass = $hasIsSufficient && $hasReasoning && $hasFollowUpField && $hasSynthField && $issues === [];

        return [
            'schema_pass' => $schemaPass,
            'quality_score' => max(0.0, min(100.0, $score)),
            'issues' => $issues,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<int, string>
     */
    protected function summaryRow(array $summary): array
    {
        return [
            $summary['arm'],
            $summary['provider'] ?? '(default)',
            $summary['model'] ?? '(default)',
            (string) $summary['sample_count'],
            sprintf('%d/%d (%.1f%%)', $summary['success_count'], $summary['sample_count'], $summary['success_rate'] * 100),
            sprintf('%d/%d (%.1f%%)', $summary['schema_pass_count'], $summary['sample_count'], $summary['schema_pass_rate'] * 100),
            sprintf('%.2f', $summary['avg_quality_score']),
            sprintf('%.1f', $summary['avg_latency_ms']),
        ];
    }

    /**
     * @param  Collection<int, array{sample_id: string, assessment_id: string, question_id: string, question_text: string, user_response: string, follow_up_prompts: array}>  $samples
     * @param  array<string, mixed>  $control
     * @param  array<string, mixed>  $treatment
     */
    protected function writeJsonReport(string $outputPath, Collection $samples, array $control, array $treatment): void
    {
        $targetPath = str_starts_with($outputPath, '/')
            ? $outputPath
            : storage_path('app/'.$outputPath);

        File::ensureDirectoryExists(dirname($targetPath));

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'sample_count' => $samples->count(),
            'samples' => $samples->all(),
            'control' => $control,
            'treatment' => $treatment,
        ];

        File::put(
            $targetPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->info('Wrote report to '.$targetPath);
    }
}
