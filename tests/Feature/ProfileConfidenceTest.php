<?php

namespace Tests\Feature;

use App\Enums\ConfidenceLevel;
use App\Jobs\AnalyzeAssessmentJob;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\VocationalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Confidence as it reaches the profile. Blueprint 11.4 never lets the level,
 * the reasoning, or the stated limitations sit behind payment, so they belong
 * on the profile row itself.
 */
class ProfileConfidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function assessmentWithAnswers(array $responses): Assessment
    {
        $category = QuestionCategory::create(['name' => 'Service', 'slug' => 'service', 'sort_order' => 1]);

        $assessment = Assessment::create([
            'mode' => 'written',
            'status' => 'in_progress',
            'guest_token' => Str::random(64),
            'started_at' => now(),
        ]);

        foreach ($responses as $index => $response) {
            $question = Question::create([
                'category_id' => $category->id,
                'question_text' => "Question {$index}",
                'sort_order' => $index,
                'is_beta' => false,
            ]);

            Answer::create([
                'assessment_id' => $assessment->id,
                'question_id' => $question->id,
                'response_text' => $response,
            ]);
        }

        return $assessment;
    }

    public function test_the_profile_casts_its_confidence_level_to_the_enum(): void
    {
        $profile = VocationalProfile::create([
            'assessment_id' => $this->assessmentWithAnswers([])->id,
            'confidence_level' => ConfidenceLevel::Emerging,
            'confidence_rationale' => 'Early.',
            'missing_evidence' => ['More detail.'],
        ]);

        $profile->refresh();

        $this->assertSame(ConfidenceLevel::Emerging, $profile->confidence_level);
        $this->assertSame(['More detail.'], $profile->missing_evidence);
        $this->assertTrue($profile->isLowConfidence());
    }

    public function test_a_profile_with_no_confidence_defaults_to_low_confidence(): void
    {
        $profile = VocationalProfile::create([
            'assessment_id' => $this->assessmentWithAnswers([])->id,
        ]);

        $this->assertTrue($profile->refresh()->isLowConfidence());
    }

    public function test_it_reads_the_confidence_of_a_single_category(): void
    {
        $profile = VocationalProfile::create([
            'assessment_id' => $this->assessmentWithAnswers([])->id,
            'category_scores' => [
                ['category' => 'Healing & Care', 'score' => 90, 'confidence' => 'strong'],
                ['category' => 'Teaching & Formation', 'score' => 40, 'confidence' => 'weak'],
            ],
        ]);

        $this->assertSame(ConfidenceLevel::Strong, $profile->confidenceFor('Healing & Care'));
        $this->assertSame(ConfidenceLevel::Weak, $profile->confidenceFor('Teaching & Formation'));
        $this->assertNull($profile->confidenceFor('Law & Policy'));
    }

    /**
     * The job reads the respondent's own words, and audio-mode assessments
     * carry theirs in the transcript rather than in response_text.
     */
    public function test_the_job_reads_audio_transcripts_as_the_respondents_words(): void
    {
        $assessment = $this->assessmentWithAnswers(['A written answer here.']);
        $category = QuestionCategory::first();

        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => 'Spoken question',
            'sort_order' => 99,
            'is_beta' => false,
        ]);

        Answer::create([
            'assessment_id' => $assessment->id,
            'question_id' => $question->id,
            'response_text' => null,
            'audio_transcript' => 'A spoken answer here.',
        ]);

        $method = new ReflectionMethod(AnalyzeAssessmentJob::class, 'respondentWords');
        $method->setAccessible(true);

        $this->assertSame(
            ['A written answer here.', 'A spoken answer here.'],
            $method->invoke(new AnalyzeAssessmentJob($assessment->refresh())),
        );
    }

    public function test_the_job_skips_blank_answers_when_gathering_evidence(): void
    {
        $assessment = $this->assessmentWithAnswers(['Real answer with several words in it.', '', '   ']);

        $method = new ReflectionMethod(AnalyzeAssessmentJob::class, 'respondentWords');
        $method->setAccessible(true);

        $this->assertSame(
            ['Real answer with several words in it.'],
            $method->invoke(new AnalyzeAssessmentJob($assessment)),
        );
    }
}
