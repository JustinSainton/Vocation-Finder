<?php

namespace Tests\Feature;

use App\Ai\Agents\PathwayCoachAgent;
use App\Ai\Tools\GetLockerTool;
use App\Enums\ArtifactKind;
use App\Models\Artifact;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Support\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request as ToolRequest;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Roadmap 3.3 — the locker.
 *
 * Two things are guarded hardest, because both have already gone wrong once in
 * this product's history or would be catastrophic the first time: a minor's
 * uploaded work must not be readable by anyone holding a URL, and the locker
 * must store rather than produce.
 */
class LockerTest extends TestCase
{
    use RefreshDatabase;

    protected function enableCoach(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'pathway_coach'],
            ['name' => 'Pathway Coach', 'is_enabled' => true],
        );

        Cache::forget('feature_flag:pathway_coach');
    }

    protected function student(): User
    {
        return User::factory()->paying()->create(['birthdate' => now()->subYears(19), 'grade_level' => 12]);
    }

    /**
     * An artifact is a file or a link, and exactly one of the two. Neither is
     * an empty row the student will click on; both is two artifacts wearing
     * one title.
     */
    #[Test]
    public function an_artifact_is_a_file_or_a_link_and_never_neither_or_both(): void
    {
        $user = $this->student();

        foreach ([
            'neither' => ['link_url' => null],
            'both' => ['link_url' => 'https://example.com/x', 'disk' => 'local', 'path' => 'artifacts/x.pdf'],
        ] as $case => $attributes) {
            try {
                Artifact::create([
                    'user_id' => $user->id,
                    'kind' => ArtifactKind::Essay,
                    'title' => 'The essay',
                    ...$attributes,
                ]);

                $this->fail("An artifact with {$case} was saved.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('exactly one', $exception->getMessage());
            }
        }

        $this->assertSame(0, Artifact::count());
    }

    /**
     * The results page shipped once with no authorization at all — a minor's
     * vocational portrait readable by anyone who guessed a UUID. An uploaded
     * essay is the same category of thing, so the file never goes anywhere
     * near `public/` and comes back only through an owner check.
     */
    #[Test]
    public function another_students_file_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $this->enableCoach();

        $owner = $this->student();
        $stranger = $this->student();

        $artifact = (new Locker)->put(
            $owner,
            ArtifactKind::Essay,
            'The essay about my grandfather\'s shop',
            UploadedFile::fake()->create('essay.pdf', 12, 'application/pdf'),
        );

        // Signed out first: `actingAs` persists for the rest of the test, so
        // a guest request made after one is not a guest request at all.
        $this->get("/locker/{$artifact->id}/download")->assertRedirect('/login');
        $this->actingAs($stranger)->get("/locker/{$artifact->id}/download")->assertForbidden();
        $this->actingAs($owner)->get("/locker/{$artifact->id}/download")->assertOk();

        $this->assertStringNotContainsString(
            'public',
            $artifact->path,
            'The file was written somewhere the web server serves directly.',
        );
    }

    /**
     * Downloaded, never rendered. A document containing markup that executes
     * inside this application's origin is a stored cross-site scripting
     * payload with the student's name on it.
     */
    #[Test]
    public function a_stored_file_is_handed_back_as_a_download_and_never_as_a_page(): void
    {
        Storage::fake('local');
        $this->enableCoach();
        $owner = $this->student();

        $artifact = (new Locker)->put(
            $owner,
            ArtifactKind::Portfolio,
            'The zine',
            UploadedFile::fake()->create('zine.pdf', 12, 'application/pdf'),
        );

        $response = $this->actingAs($owner)->get("/locker/{$artifact->id}/download");

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }

    /**
     * An uploaded filename is attacker-controlled text. It is kept in a
     * column, where it is only ever printed, and never used as a path.
     *
     * The first version of this test asserted only that the path had no `..`
     * in it and started with the student's folder — both of which Symfony
     * already guarantees by taking the basename, so the assertion held even
     * when the code was changed to write the browser's name straight to disk.
     * It now asserts the thing this code is actually responsible for: the
     * stored name is generated, so two students uploading `essay.pdf` cannot
     * collide and a filename cannot carry anything into the filesystem at all.
     */
    #[Test]
    public function the_stored_name_is_generated_and_not_the_browsers(): void
    {
        Storage::fake('local');
        $user = $this->student();

        $artifact = (new Locker)->put(
            $user,
            ArtifactKind::Essay,
            'The essay',
            UploadedFile::fake()->create('../../../../etc/passwd.pdf', 4, 'application/pdf'),
        );

        $this->assertStringNotContainsString('..', $artifact->path);
        $this->assertStringStartsWith('artifacts/'.$user->id.'/', $artifact->path);
        $this->assertStringNotContainsString(
            'passwd',
            $artifact->path,
            'The name the browser sent was written to disk.',
        );
        $this->assertStringContainsString('passwd', (string) $artifact->original_name);
    }

    /**
     * An allowlist, not a denylist. A denylist is a guess about what is
     * dangerous, and the guess is wrong the first time somebody invents a
     * format. SVG and HTML are documents that execute; neither is on it.
     */
    #[Test]
    public function only_the_allowed_kinds_of_file_go_in(): void
    {
        $this->assertNotContains('image/svg+xml', Locker::MIME_TYPES);
        $this->assertNotContains('text/html', Locker::MIME_TYPES);

        Storage::fake('local');
        $this->enableCoach();
        $user = $this->student();

        $this->actingAs($user)
            ->post('/locker', [
                'kind' => 'portfolio',
                'title' => 'The drawing',
                'file' => UploadedFile::fake()->create('drawing.svg', 4, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Artifact::count());
    }

    #[Test]
    public function a_file_too_large_is_refused(): void
    {
        Storage::fake('local');
        $this->enableCoach();

        $this->actingAs($this->student())
            ->post('/locker', [
                'kind' => 'essay',
                'title' => 'The long one',
                'file' => UploadedFile::fake()->create('long.pdf', Locker::MAX_KILOBYTES + 1, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Artifact::count());
    }

    /**
     * The student may take their own work out, and only they may. This is not
     * the brain — the brain is what they said, and that is never destroyed.
     * This is a draft they thought better of.
     */
    #[Test]
    public function the_student_may_take_their_own_work_out_and_nobody_else_may(): void
    {
        Storage::fake('local');
        $this->enableCoach();

        $owner = $this->student();
        $stranger = $this->student();
        $artifact = (new Locker)->put(
            $owner,
            ArtifactKind::Essay,
            'The draft I hate',
            UploadedFile::fake()->create('draft.pdf', 4, 'application/pdf'),
        );
        $path = $artifact->path;

        $this->actingAs($stranger)->delete("/locker/{$artifact->id}")->assertForbidden();
        $this->assertSame(1, Artifact::count());

        $this->actingAs($owner)->delete("/locker/{$artifact->id}")->assertRedirect();
        $this->assertSame(0, Artifact::count());

        Storage::disk('local')->assertMissing($path);
    }

    /**
     * The locker stores; it does not produce. There is no tool that writes to
     * it, and adding one would be the clearest possible breach of "the tool
     * never does the student's work for them".
     */
    #[Test]
    public function no_coach_tool_can_put_anything_in_the_locker(): void
    {
        $tools = collect((new PathwayCoachAgent($this->student()))->tools())
            ->map(fn ($tool) => $tool::class);

        $this->assertContains(GetLockerTool::class, $tools);

        foreach ($tools as $tool) {
            if ($tool === GetLockerTool::class) {
                continue;
            }

            $this->assertStringNotContainsString('Locker', $tool);
        }

    }

    /**
     * Read only, and it hands back titles rather than contents — plus an
     * instruction not to offer to rewrite anything.
     */
    #[Test]
    public function the_coach_sees_titles_and_is_told_not_to_take_over(): void
    {
        $user = $this->student();
        Artifact::factory()->for($user)->create(['title' => 'The essay about my grandfather\'s shop']);

        $result = json_decode((new GetLockerTool($user))->handle(new ToolRequest([])), true);

        $this->assertSame(
            ['The essay about my grandfather\'s shop'],
            array_column($result['artifacts'], 'title'),
        );
        $this->assertArrayNotHasKey('link_url', $result['artifacts'][0]);
        $this->assertStringContainsString('rewrite', $result['guidance']);
    }

    /**
     * No locker route takes another person as a parameter, the same structural
     * guarantee the coach and the brain carry. The counsellor flag on an
     * organisation governs the portrait and nothing else.
     */
    #[Test]
    public function no_locker_route_addresses_another_person(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'locker'));

        $this->assertNotEmpty($routes, 'No locker routes at all would pass this vacuously.');

        foreach ($routes as $route) {
            foreach (['{user}', '{student}', '{member}'] as $parameter) {
                $this->assertStringNotContainsString($parameter, $route->uri());
            }
        }
    }

    #[Test]
    public function the_locker_is_a_place_now_that_it_has_a_route(): void
    {
        $this->enableCoach();

        $response = $this->actingAs($this->student())->get('/locker');
        $response->assertOk();

        $this->assertContains(
            'locker',
            collect($response->viewData('page')['props']['places'])->pluck('key')->all(),
        );
    }
}
