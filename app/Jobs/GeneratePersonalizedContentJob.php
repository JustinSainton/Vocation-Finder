<?php

namespace App\Jobs;

use App\Ai\Agents\CoursePersonalization;
use App\Models\Assessment;
use App\Models\CourseModule;
use App\Models\PersonalizedContent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePersonalizedContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;

    public int $tries = 2;

    public function __construct(
        public PersonalizedContent $personalizedContent,
    ) {
        $this->onQueue('ai-analysis');
    }

    public function handle(): void
    {
        $this->personalizedContent->update(['status' => 'generating']);

        $user = $this->personalizedContent->user;
        $module = $this->personalizedContent->courseModule;
        $assessment = $this->personalizedContent->assessment;

        $profile = $assessment->vocationalProfile;
        if (! $profile) {
            $this->fail('Assessment has no vocational profile — cannot personalize.');

            return;
        }

        $userContext = $this->buildUserContext($user, $assessment, $profile);

        $templateBlocks = $module->content_blocks ?? [];
        $personalizationPrompts = $module->personalization_prompts ?? [];

        // If no personalization prompts defined, generate default prompts
        if (empty($personalizationPrompts)) {
            $personalizationPrompts = $this->generateDefaultPrompts($templateBlocks, $userContext);
        }

        $agent = new CoursePersonalization($templateBlocks, $personalizationPrompts, $userContext);

        $response = $agent->prompt(
            $agent->buildPrompt(),
            model: config('vocation.ai.model', 'claude-sonnet-4-20250514'),
        );

        $personalizedBlocks = $this->parseResponse($response->text);

        $this->personalizedContent->update([
            'status' => 'ready',
            'content_blocks' => $personalizedBlocks,
            'personalization_context' => [
                'user_name' => $user->name,
                'primary_domain' => $profile->primary_domain,
                'mode_of_work' => $profile->mode_of_work,
                'assessment_id' => $assessment->id,
                'generated_model' => config('vocation.ai.model', 'claude-sonnet-4-20250514'),
            ],
            'generated_at' => now(),
        ]);
    }

    protected function buildUserContext(User $user, Assessment $assessment, $profile): array
    {
        $answers = $assessment->answers()
            ->with('question:id,question_text,category_id')
            ->get()
            ->map(fn ($a) => [
                'question' => $a->question?->question_text,
                'response' => $a->response_text ?? $a->audio_transcript,
            ])
            ->filter(fn ($a) => $a['question'] && $a['response'])
            ->values()
            ->toArray();

        return [
            'name' => $user->name,
            'primary_domain' => $profile->primary_domain,
            'mode_of_work' => $profile->mode_of_work,
            'secondary_orientation' => $profile->secondary_orientation,
            'primary_pathways' => $profile->primary_pathways,
            'category_scores' => $profile->category_scores,
            'opening_synthesis' => $profile->opening_synthesis,
            'vocational_orientation' => $profile->vocational_orientation,
            'ministry_integration' => $profile->ministry_integration,
            'specific_considerations' => $profile->specific_considerations,
            'assessment_answers' => array_slice($answers, 0, 10), // Limit to keep prompt reasonable
        ];
    }

    protected function generateDefaultPrompts(array $blocks, array $context): array
    {
        $name = $context['name'] ?? 'the learner';
        $domain = $context['primary_domain'] ?? 'their vocational domain';

        return array_map(function ($block) use ($name, $domain) {
            return match ($block['type'] ?? 'text') {
                'text' => "Personalize this content for {$name}, connecting concepts to their primary domain of {$domain}. Reference their assessment insights where relevant.",
                'reflection' => "Rewrite this reflection prompt to speak directly to {$name}'s specific vocational situation, referencing their primary domain and mode of work.",
                'checkpoint' => "Adapt this checkpoint question to reference {$name}'s vocational context and make the options relevant to their career pathways.",
                'video' => 'Keep the video URL unchanged. Personalize the title/description if present.',
                default => 'Personalize naturally for the user.',
            };
        }, $blocks);
    }

    protected function parseResponse(string $response): array
    {
        // Strip markdown code fences if present
        $cleaned = preg_replace('/^```(?:json)?\s*\n?/m', '', $response);
        $cleaned = preg_replace('/\n?```\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $blocks = json_decode($cleaned, true);

        if (! is_array($blocks)) {
            throw new \RuntimeException('Failed to parse personalized content response as JSON.');
        }

        // Validate each block has a type
        foreach ($blocks as $block) {
            if (! isset($block['type'])) {
                throw new \RuntimeException('Personalized content block missing required "type" field.');
            }
        }

        return $blocks;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Personalized content generation failed', [
            'personalized_content_id' => $this->personalizedContent->id,
            'module_id' => $this->personalizedContent->course_module_id,
            'user_id' => $this->personalizedContent->user_id,
            'error' => $exception->getMessage(),
        ]);

        $this->personalizedContent->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
