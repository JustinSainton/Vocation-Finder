<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\PathwayCoachAgent;
use App\Data\Coach\BrainstormInvitationData;
use App\Data\Coach\CoachActionData;
use App\Data\Coach\HabitData;
use App\Data\Coach\ReadinessData;
use App\Data\CrisisSupportData;
use App\Http\Controllers\Controller;
use App\Support\AccessPolicy;
use App\Support\ActionQueue;
use App\Support\BrainCapture;
use App\Support\BrainstormSchedule;
use App\Support\CoachOpening;
use App\Support\CoachStarters;
use App\Support\CoachStream;
use App\Support\CoachThread;
use App\Support\PathwayProfileReadiness;
use App\Support\ConversationLocale;
use App\Support\CrisisCheck;
use App\Support\FirstRunSequence;
use App\Support\HabitTracker;
use App\Support\ReadinessCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The coach: the front door, not a feature tab.
 *
 * Every response here carries the first-run step, computed rather than stored,
 * so the one question the interface has to answer — what happens next — has a
 * single source and cannot drift between screens.
 */
class PathwayCoachController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! AccessPolicy::canUseCoach($user)) {
            return redirect()->route('first-run')
                ->with('status', AccessPolicy::coachBlockedReason($user));
        }

        $portrait = new PathwayProfileReadiness;

        return Inertia::render('Coach/Index', [
            'firstRun' => FirstRunSequence::toArray($user),
            'portraitStatus' => $portrait->portraitStatus($user),
            'assessmentId' => $portrait->assessmentId($user),
            'awaitingPortraitMessage' => $portrait->awaitingMessage(),
            'currentAction' => CoachActionData::optional((new ActionQueue)->current($user)),
            /**
             * Readiness is shown here rather than being a step in
             * {@see FirstRunSequence}. The vision lists it between refinement
             * and the first action, but it is something a student *reads*, not
             * something they *complete* — making it a step would put a screen
             * with no action on it between them and the one thing that matters.
             */
            'readiness' => ReadinessData::from((new ReadinessCalculator)->explain($user)),
            /**
             * Words and a move, never the counts. The coach gets the counts
             * so it can tell that a habit is the wrong size; handing them to
             * a sixteen-year-old turns their week into a score.
             */
            'habits' => HabitData::collect((new HabitTracker)->forStudent($user)),
            /**
             * Roadmap 2.4. Null most of the time, and that is the point — the
             * return loop is voluntary, so the cadence is a ceiling on
             * contact rather than a schedule of it. When it does appear it
             * opens with something the student has said repeatedly, in their
             * own words, because an invitation with nothing in it is a nag.
             */
            'invitation' => BrainstormInvitationData::optional((new BrainstormSchedule)->invitation($user)),
            /*
             * The conversation itself, read from the same rows the model is
             * given, so reloading the page is never how a reply is lost.
             */
            'thread' => (new CoachThread)->items($user),
            'starters' => (new CoachStarters)->for($user),
            /*
             * When set, the page asks the coach to speak before the student
             * has to. Computed here rather than on the client, so two tabs or
             * a refresh cannot each decide to open.
             */
            'opening' => (new CoachOpening)->due($user),
        ]);
    }

    /**
     * The coach speaks first, streamed.
     *
     * Idempotent under a per-student lock: whoever loses the race gets the
     * thread as it stands instead of a second opener. When the model cannot
     * be reached the opener still happens, built from the portrait alone —
     * a student who finished the assessment should never land in an empty
     * room.
     */
    public function open(Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $opening = new CoachOpening;
        $kind = $opening->due($user);

        try {
            $agent = new PathwayCoachAgent($user);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $portrait = new PathwayProfileReadiness;

        if ($kind === CoachOpening::FIRST && ! $portrait->hasPortrait($user)) {
            return response()->json([
                'awaiting_portrait' => true,
                'portrait_status' => $portrait->portraitStatus($user),
                'message' => $portrait->awaitingMessage(),
                'assessment_id' => $portrait->assessmentId($user),
            ] + CoachStream::settled($user)->toArray());
        }

        $lock = Cache::lock("coach-opening:{$user->id}", 120);

        if ($kind === null || ! $lock->get()) {
            return response()->json(CoachStream::settled($user));
        }

        return (new CoachStream)->respond(
            $user,
            $agent,
            start: fn () => $agent->openingStream($kind),
            recover: function () use ($opening, $user, $kind) {
                $text = $opening->fallback($user, $kind);
                $opening->recordFallback($user, $kind, $text);

                return $text;
            },
            finally: fn () => $lock->release(),
            firstStatus: $kind === CoachOpening::FIRST ? 'Reading your portrait' : 'Catching up on where you left off',
        );
    }

    /**
     * One turn, streamed. The same path as {@see message()} — crisis check
     * first, capture before the model — with the reply arriving as it is
     * written instead of after a full page round trip.
     */
    public function stream(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();

        if ((new CrisisCheck)->standing($validated['message'])->isEscalation()) {
            (new BrainCapture)->captureCoachTurn($user, role: 'user', content: $validated['message']);

            return response()->json([
                'support' => CrisisSupportData::from((new CrisisCheck)->support(
                    ConversationLocale::normalize($user->assessments()->latest()->value('locale')),
                )),
            ]);
        }

        try {
            $agent = new PathwayCoachAgent($user);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return (new CoachStream)->respond(
            $user,
            $agent,
            start: fn () => $agent->streamReplyTo($validated['message']),
            after: fn () => (new BrainstormSchedule)->attended($user),
        );
    }

    /**
     * One turn of the conversation.
     *
     * Goes through {@see PathwayCoachAgent::respondTo()} rather than
     * `prompt()` so the student's words are captured to their brain before the
     * model is called. A conversation that is not captured as it happens
     * cannot be recovered afterwards.
     */
    public function message(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        /*
         | Before entitlement, before the model, before anything. A student
         | whose parent has not consented, or whose plan has lapsed, still gets
         | the phone number — the one thing in this product that is not a
         | feature is not behind a paywall either.
         |
         | Their words are still captured. The brain is never allowed to lose
         | what somebody said (see the persistence invariant), and this is the
         | sentence they would least want to have to write twice.
         */
        if ((new CrisisCheck)->standing($validated['message'])->isEscalation()) {
            (new BrainCapture)->captureCoachTurn(
                $request->user(),
                role: 'user',
                content: $validated['message'],
            );

            /*
             | The locale lives on the assessment rather than the user, so it
             | is read from the most recent one. Unknown falls back to English
             | rather than refusing: a message in the wrong language with the
             | right phone number in it still works.
             */
            return back()->with('support', (new CrisisCheck)->support(
                ConversationLocale::normalize($request->user()->assessments()->latest()->value('locale')),
            ));
        }

        try {
            $agent = new PathwayCoachAgent($request->user());
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        try {
            $agent->respondTo($validated['message']);
        } catch (Throwable $exception) {
            Log::error('pathway coach message failed', ['error' => $exception->getMessage()]);

            return back()->with('status', 'Something went wrong. What you wrote is kept — try again.');
        }

        /*
         * Turning up is what counts as attending, not clicking the
         * invitation. A student who came back on their own has told the
         * schedule everything it needed to know.
         */
        (new BrainstormSchedule)->attended($request->user());

        return back();
    }
}
