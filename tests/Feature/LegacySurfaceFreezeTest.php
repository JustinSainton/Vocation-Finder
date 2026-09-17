<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\CurriculumPathway;
use App\Models\FeatureFlag;
use App\Models\User;
use Database\Seeders\FeatureFlagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The legacy surfaces are frozen, and freezing is a flag that defaults off.
 *
 * Courses, curriculum, job discovery, resumes, cover letters and application
 * tracking were built for adults navigating a career change. They are not
 * deleted — several fold into Phase 3.3 and 4.3 later — but none of them are
 * part of what a student is offered in V1.
 *
 * Courses and the learning pathway were the exception: they had no flag at
 * all, so they were reachable by every student on day one of a product that
 * ships no published courses. A live run surfaced the same coupling from the
 * other side, where curriculum generation fired on every completed assessment
 * and could only fail.
 */
class LegacySurfaceFreezeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function legacyFlags(): array
    {
        return [
            'courses' => ['courses'],
            'job discovery' => ['job_discovery'],
            'resume builder' => ['resume_builder'],
            'cover letter builder' => ['cover_letter_builder'],
            'application tracking' => ['application_tracking'],
            'career profile' => ['career_profile'],
            'career coach' => ['career_coach'],
        ];
    }

    #[DataProvider('legacyFlags')]
    #[Test]
    public function a_legacy_surface_is_seeded_and_off(string $key): void
    {
        $this->seed(FeatureFlagSeeder::class);

        $flag = FeatureFlag::where('key', $key)->first();

        $this->assertNotNull($flag, "[{$key}] is not a known feature flag.");
        $this->assertFalse((bool) $flag->is_enabled, "[{$key}] ships enabled.");
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function courseRoutes(): array
    {
        return [
            'enrolling in a course' => ['post', '/courses/00000000-0000-0000-0000-000000000000/enroll'],
            'recording progress' => ['patch', '/courses/00000000-0000-0000-0000-000000000000/progress'],
            'viewing a learning pathway' => ['get', '/pathway/00000000-0000-0000-0000-000000000000'],
        ];
    }

    #[DataProvider('courseRoutes')]
    #[Test]
    public function a_student_cannot_reach_the_course_surface_while_it_is_frozen(string $verb, string $uri): void
    {
        $this->seed(FeatureFlagSeeder::class);

        $response = $this->actingAs(User::factory()->create())->{$verb}($uri);

        $this->assertNotContains(
            $response->status(),
            [200, 201, 302],
            "[{$uri}] is reachable with the courses flag off.",
        );
    }

    /**
     * Frozen is not deleted. Turning the flag on has to bring the surface
     * back, or this is a removal wearing a flag's clothes.
     *
     * Asserted against a pathway the student actually owns, because a missing
     * record 404s for the same reason a frozen flag does, and a test that
     * cannot tell those apart would pass on a deleted route.
     */
    #[Test]
    public function turning_the_flag_on_unfreezes_the_surface(): void
    {
        $this->seed(FeatureFlagSeeder::class);

        $student = User::factory()->create();

        $assessment = Assessment::create([
            'user_id' => $student->id,
            'mode' => 'written',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $pathway = CurriculumPathway::create([
            'user_id' => $student->id,
            'assessment_id' => $assessment->id,
            'status' => 'completed',
            'phases' => [],
        ]);

        $this->actingAs($student)->get("/pathway/{$pathway->id}")->assertNotFound();

        FeatureFlag::where('key', 'courses')->update(['is_enabled' => true]);
        Cache::flush();

        $this->actingAs($student)->get("/pathway/{$pathway->id}")->assertOk();
    }
}
