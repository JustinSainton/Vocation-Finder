<?php

namespace App\Jobs;

use App\Ai\Agents\NarrativeSynthesis;
use App\Ai\Agents\VocationalAnalysis;
use App\Models\Assessment;
use App\Models\VocationalProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeAssessmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(
        public Assessment $assessment,
    ) {
        $this->onQueue('ai-analysis');
    }

    public function handle(): void
    {
        $model = $this->resolveModel();

        // Phase A: Structured pattern analysis
        $agent = new VocationalAnalysis($this->assessment);
        $analysisResponse = $agent->prompt(
            $agent->buildPrompt(),
            model: $model,
        );

        $analysisData = $analysisResponse->structured;

        // Validate Phase A output
        $this->validateAnalysis($analysisData);

        // Phase B: Narrative synthesis
        $narrativeAgent = new NarrativeSynthesis($analysisData);
        $narrativeResponse = $narrativeAgent->prompt(
            $narrativeAgent->buildPrompt(),
            model: $model,
        );

        $narrative = $narrativeResponse->text;

        // Validate Phase B output
        $this->validateNarrative($narrative);

        // Parse narrative into sections
        $sections = $this->parseNarrativeSections($narrative);

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
                'ai_analysis_raw' => $analysisData,
            ],
        );

        $this->assessment->update(['status' => 'completed']);
    }

    protected function resolveModel(): string
    {
        $user = $this->assessment->user;

        if (! $user || ! $user->subscribed()) {
            return config('vocation.free_tier.analysis_model');
        }

        return config('vocation.ai.model');
    }

    protected function validateAnalysis(array $data): void
    {
        // Layer 1: Structure check (JSON schema enforced by SDK, but verify critical fields)
        $required = ['dimensions', 'category_scores', 'primary_domain', 'mode_of_work', 'secondary_orientation'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \RuntimeException("Analysis missing required field: {$field}");
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
                    // Parse bullet points or numbered items into arrays
                    $items = preg_split('/\n\s*(?:[-•*]|\d+[.)]\s)/', $content, -1, PREG_SPLIT_NO_EMPTY);
                    $sections[$currentSection] = array_values(array_filter(array_map('trim', $items)));
                } else {
                    $sections[$currentSection] .= $content;
                }
            }
        }

        return $sections;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Assessment analysis failed', [
            'assessment_id' => $this->assessment->id,
            'error' => $exception->getMessage(),
        ]);

        $this->assessment->update(['status' => 'failed']);
    }
}
