<?php

namespace Tests\Feature;

use App\Enums\BrainEntrySource;
use App\Enums\GapType;
use App\Enums\HabitCadence;
use App\Enums\StudentPlace;
use App\Models\BrainEntry;
use App\Models\FeatureFlag;
use App\Models\Gap;
use App\Models\User;
use App\Support\BrainRetrieval;
use App\Support\HabitTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roadmap 3.1 — the five places as first-class navigation.
 *
 * The thing being guarded is not that a menu renders. It is that the list of
 * places has exactly one source, that nothing in it is a link to nowhere, and
 * that the two places carrying hard invariants — the brain, which may never
 * be gated, and habits, which nobody but the student may answer for — keep
 * them once they have a front door.
 */
class StudentPlacesTest extends TestCase
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

    /**
     * A student the coach will actually open for. Eighteen rather than a
     * consented junior because the point here is the navigation, and the
     * consent path has its own tests.
     */
    protected function entitled(): User
    {
        return User::factory()->create(['birthdate' => now()->subYears(19)]);
    }

    /**
     * @return list<string>
     */
    protected function placeKeys(User $user, string $from = '/dashboard'): array
    {
        $response = $this->actingAs($user)->get($from);
        $response->assertOk();

        return collect($response->viewData('page')['props']['places'] ?? [])
            ->pluck('key')
            ->all();
    }

    /**
     * The vision names five. If someone quietly drops one to four because it
     * was never built, that is a product decision and it should cost a test
     * edit, not a component edit.
     */
    #[Test]
    public function the_five_places_the_vision_names_are_all_declared(): void
    {
        $this->assertSame(
            ['coach', 'brain', 'habits', 'locker', 'plan'],
            array_map(fn (StudentPlace $place) => $place->value, StudentPlace::ordered()),
        );
    }

    /**
     * Availability is asked of the router, not of a hand-maintained flag, so
     * the navigation cannot advertise a place that does not exist. The locker
     * and the plan are 3.3 and 3.2; they appear the day they get a route.
     */
    #[Test]
    public function nothing_offered_in_the_navigation_is_a_link_to_nowhere(): void
    {
        $this->enableCoach();
        $user = $this->entitled();

        $places = $this->placeKeys($user);

        $this->assertNotEmpty($places, 'A navigation with nothing in it would pass this vacuously.');

        foreach ($places as $key) {
            $place = StudentPlace::from($key);

            $this->assertTrue(
                $place->isOpen(),
                "The navigation offers '{$key}', which has no route.",
            );

            $this->actingAs($user)->get($place->path())->assertOk();
        }
    }

    /**
     * The navigation is the router's answer, not a hand-kept list. All five
     * places are built as of 3.3, and each one appeared the day its route was
     * registered without `PlaceNav.tsx` being touched.
     */
    #[Test]
    public function every_place_appears_because_it_has_a_route(): void
    {
        $this->enableCoach();

        $places = $this->placeKeys(User::factory()->create());

        /*
         * Every place the vision names is now built, so the guarantee has to
         * be asserted the other way round: each one appears because it has a
         * route, and a place whose route is taken away leaves on its own.
         * `plan` and `locker` both arrived here in 3.2 and 3.3 without this
         * component being edited.
         */
        $this->assertSame(
            ['coach', 'brain', 'habits', 'locker', 'plan'],
            $places,
            'A declared place is missing from the navigation, or arrived out of order.',
        );

        foreach ($places as $key) {
            $this->assertTrue(StudentPlace::from($key)->isOpen());
        }
    }

    /**
     * First-class means the places are not a property of the page you happen
     * to be standing on.
     */
    #[Test]
    public function the_places_travel_with_every_page_not_just_the_coach(): void
    {
        $this->enableCoach();
        $user = $this->entitled();

        foreach (['/dashboard', '/brain', '/habits', '/coach'] as $from) {
            $this->assertContains('brain', $this->placeKeys($user, $from), "No places on {$from}.");
        }
    }

    /**
     * The shared prop is only half of it. If no layout renders the list, the
     * places are first-class in the payload and invisible on the screen —
     * which is exactly the failure this item exists to prevent, and exactly
     * the failure a props-only assertion cannot see.
     */
    #[Test]
    public function the_layout_actually_draws_the_places(): void
    {
        $layout = (string) file_get_contents(base_path('resources/js/Layouts/AppLayout.tsx'));

        $this->assertStringContainsString('PlaceNav', $layout, 'No layout renders the places.');
        $this->assertStringContainsString('<PlaceNav />', $layout);

        /*
         * And no page hand-writes one, because a second nav is a second list
         * of places, and the second list is the one that goes stale.
         */
        $nav = (string) file_get_contents(base_path('resources/js/Components/PlaceNav.tsx'));

        foreach (StudentPlace::ordered() as $place) {
            $this->assertStringNotContainsString(
                '"'.$place->path().'"',
                $nav,
                'PlaceNav hard-codes a path instead of using the list the server sends.',
            );
        }
    }

    #[Test]
    public function a_signed_out_visitor_is_offered_no_places(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $this->assertSame([], $response->viewData('page')['props']['places'] ?? []);
    }

    /**
     * The hard one. A lapse freezes the brain, it does not seize it — so the
     * read surface sits outside `pathway_coach` exactly as the export does.
     * Habits do not, because a habit only exists because the coach prescribed
     * it.
     */
    #[Test]
    public function the_brain_is_readable_with_the_coach_switched_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/brain')->assertOk();
        $this->actingAs($user)->get('/brain/export')->assertOk();
        $this->actingAs($user)->get('/habits')->assertNotFound();

        $this->assertContains('brain', $this->placeKeys($user));
        $this->assertNotContains('habits', $this->placeKeys($user));
    }

    /**
     * Verbatim or it is not the brain. The page may add a date and a source
     * label; the sentence itself arrives untouched.
     */
    #[Test]
    public function the_brain_hands_back_the_sentence_the_student_wrote(): void
    {
        $user = User::factory()->create();
        $said = 'I liked fixing the bike more than I liked riding it.';

        BrainEntry::create([
            'user_id' => $user->id,
            'source' => BrainEntrySource::Coach,
            'content' => $said,
            'context' => 'Talking about the summer',
            'occurred_at' => Carbon::parse('2026-07-04'),
        ]);

        $response = $this->actingAs($user)->get('/brain');
        $entries = $response->viewData('page')['props']['entries'];

        $this->assertCount(1, $entries);
        $this->assertSame($said, $entries[0]['in_their_words']);
        $this->assertSame('2026-07-04', $entries[0]['said_on']);
    }

    /**
     * Search narrows by the student's own word and returns rows unaltered —
     * no model, no summary, no theme. Cf. {@see BrainRetrieval}.
     */
    #[Test]
    public function the_brain_narrows_to_the_word_the_student_used(): void
    {
        $user = User::factory()->create();

        foreach (['I liked fixing the bike.', 'Chemistry was the worst hour of my week.'] as $index => $content) {
            BrainEntry::create([
                'user_id' => $user->id,
                'source' => BrainEntrySource::Direct,
                'content' => $content,
                'occurred_at' => Carbon::parse('2026-07-0'.($index + 1)),
            ]);
        }

        $entries = $this->actingAs($user)
            ->get('/brain?topic=bike')
            ->viewData('page')['props']['entries'];

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('bike', $entries[0]['in_their_words']);
    }

    /**
     * Habits get a place of their own, and it says a word and a next move.
     * Nothing numeric reaches this page — no streak, no rate, no count.
     */
    #[Test]
    public function the_habits_place_shows_words_and_a_move_and_no_numbers(): void
    {
        $this->enableCoach();
        $user = $this->entitled();

        $gap = Gap::create([
            'user_id' => $user->id,
            'type' => GapType::Habits,
            'summary' => 'They have never tried it on an ordinary day.',
        ]);

        (new HabitTracker)->prescribe(
            $user,
            $gap,
            'Write down one thing you noticed at work.',
            HabitCadence::Daily,
            'So the next conversation has something real in it.',
        );

        $props = $this->actingAs($user)->get('/habits')->viewData('page')['props'];

        $this->assertCount(1, $props['habits']);
        $habit = $props['habits'][0];

        $this->assertSame('Write down one thing you noticed at work.', $habit['title']);
        $this->assertNotEmpty($habit['standing']);
        $this->assertNotEmpty($habit['next_move']);

        foreach (['kept', 'expected', 'streak', 'ratio', 'percent'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $habit);
        }
    }
}
