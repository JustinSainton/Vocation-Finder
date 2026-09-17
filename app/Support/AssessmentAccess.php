<?php

namespace App\Support;

use App\Enums\OrganizationRole;
use App\Models\Assessment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Who may read an assessment and the portrait written from it.
 *
 * Until now the web results page authorized nobody: route-model binding
 * resolved the assessment and rendered it, so anyone holding a UUID could read
 * a minor's verbatim answers and full narrative. The four API endpoints each
 * carried their own copy of the check, which is how the web one came to be
 * missing — a rule written four times is a rule that will be written three
 * times eventually.
 *
 * ## Two ways in, and no expiry
 *
 * **Ownership.** The signed-in owner, always. `GuestUpgradeService` clears the
 * guest token when a guest registers, so an upgraded assessment is reachable
 * only this way, which is what we want.
 *
 * **The token.** A guest assessment carries a 64-character secret. Holding it
 * *is* the authorization, and that deliberately does not expire: a student who
 * bookmarks their result in September must still be able to open it in March.
 * The link is the only copy some of them will ever have.
 *
 * Possession of the secret authorizes regardless of who is signed in. The
 * older API rule required the caller to be a *guest* as well as hold the
 * token, which meant a student signed into a second account could not open
 * their own bookmarked link. Being logged in somewhere else is not a reason to
 * revoke a secret someone holds.
 *
 * ## Why the token may travel in the query string
 *
 * It has to survive a bookmark, so it cannot live only in a session. That
 * makes the results URL bearer-capable: a student who forwards it has shared
 * their portrait. That is their decision to make, and it is a decision — which
 * a guessable UUID never was.
 *
 * ## Reading is not the same as acting
 *
 * There are two questions, and they have different answers. *Is this yours?*
 * is {@see permits()} and gates everything that writes — answering questions,
 * completing an assessment, filing feedback on the portrait, mailing it
 * somewhere. *May you read this?* is {@see permitsReading()} and is wider: it
 * also admits organization staff, subject to that organization's flag.
 *
 * The narrow rule keeps the plain name. A call site that reaches for
 * `authorize()` without thinking gets the closed answer, and widening has to
 * be typed out on purpose.
 */
class AssessmentAccess
{
    /**
     * The query parameter carrying the token on a bookmarkable link.
     */
    public const TOKEN_PARAM = 't';

    public static function permits(Request $request, Assessment $assessment): bool
    {
        if ($assessment->user_id !== null && $request->user()?->id === $assessment->user_id) {
            return true;
        }

        return static::tokenMatches($request, $assessment);
    }

    /**
     * Who may *read* the assessment and the portrait written from it: the
     * subject, or staff of an organization the subject belongs to that has
     * not closed the flag.
     */
    public static function permitsReading(Request $request, Assessment $assessment): bool
    {
        return static::permits($request, $assessment)
            || static::staffMayRead($request->user(), $assessment);
    }

    /**
     * @throws HttpException
     */
    public static function authorize(Request $request, Assessment $assessment): void
    {
        abort_unless(static::permits($request, $assessment), 403, 'Unauthorized access to assessment.');
    }

    /**
     * @throws HttpException
     */
    public static function authorizeReading(Request $request, Assessment $assessment): void
    {
        abort_unless(static::permitsReading($request, $assessment), 403, 'Unauthorized access to assessment.');
    }

    /**
     * Membership, not ownership of the record.
     *
     * An assessment carries a nullable `organization_id`, but a student can
     * sit an assessment before a roster import or outside a cohort link and
     * still be the school's student. The relationship that matters is the one
     * between the two people: the viewer is staff somewhere the subject is a
     * member. The flag is then read per organization, because a student in
     * two programmes may be covered by one policy and not the other, and the
     * permissive one is enough.
     */
    protected static function staffMayRead(?User $viewer, Assessment $assessment): bool
    {
        if ($viewer === null || $assessment->user_id === null || $viewer->id === $assessment->user_id) {
            return false;
        }

        return Organization::query()
            ->whereHas('users', fn ($query) => $query
                ->whereKey($viewer->id)
                ->whereIn('organization_user.role', OrganizationRole::staff()))
            ->whereHas('users', fn ($query) => $query
                ->whereKey($assessment->user_id)
                ->where('organization_user.role', OrganizationRole::Member->value))
            ->get()
            ->contains(fn (Organization $organization) => $organization->staffMayReadPortraits());
    }

    /**
     * Every place the secret is allowed to arrive from.
     *
     * The header is what the SPA and the mobile client send; the query
     * parameter is what a bookmark carries; the body is what a form post
     * carries; the session is what lets a student move around inside the app
     * without the secret riding on every link. All four are the same secret,
     * compared the same way.
     */
    protected static function tokenMatches(Request $request, Assessment $assessment): bool
    {
        if (blank($assessment->guest_token)) {
            return false;
        }

        $candidates = [
            $request->header('X-Guest-Token'),
            $request->query(static::TOKEN_PARAM),
            $request->input('guest_token'),
            $request->hasSession() ? $request->session()->get(static::sessionKey($assessment)) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && hash_equals($assessment->guest_token, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remember a token for this browser, so in-app navigation does not need to
     * carry it. Convenience only — the bookmarkable link is the durable path,
     * because sessions expire and the student's link must not.
     */
    public static function remember(Request $request, Assessment $assessment): void
    {
        if ($request->hasSession() && filled($assessment->guest_token)) {
            $request->session()->put(static::sessionKey($assessment), $assessment->guest_token);
        }
    }

    protected static function sessionKey(Assessment $assessment): string
    {
        return 'assessment_token.'.$assessment->id;
    }
}
