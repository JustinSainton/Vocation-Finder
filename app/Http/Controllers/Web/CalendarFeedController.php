<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\User;
use App\Support\IcsFeed;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The plan as a subscribable calendar.
 *
 * Outside `auth` because a calendar client cannot hold a session, and outside
 * the pathway coach flag and every entitlement check for the same reason the
 * brain export is: these are the student's own dates, and a lapse freezes the
 * product rather than seizing what they put into it.
 *
 * The token is the whole of the authorisation, which is why it is a 64
 * character random string the student can rotate, and why this responds to a
 * bad one with a 404 rather than anything that distinguishes "wrong token"
 * from "no such student".
 */
class CalendarFeedController extends Controller
{
    public function __construct(protected IcsFeed $feed = new IcsFeed) {}

    public function __invoke(string $token): Response
    {
        $student = User::query()->where('calendar_token', $token)->first();

        if (! $student) {
            throw new NotFoundHttpException;
        }

        $milestones = Milestone::query()
            ->where('user_id', $student->id)
            ->whereNotNull('due_on')
            ->orderBy('due_on')
            ->get();

        return response($this->feed->render($student, $milestones), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="plan.ics"',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
