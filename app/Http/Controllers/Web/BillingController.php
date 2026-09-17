<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccessPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function pricing(): Response
    {
        return Inertia::render('Pricing', [
            'plans' => config('billing.plans'),
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $subscription = $user->subscription();

        $planName = null;
        if ($subscription) {
            foreach (config('billing.plans') as $name => $plan) {
                if ($plan['price_id'] === $subscription->stripe_price) {
                    $planName = str_replace('_', ' ', ucfirst($name));
                    break;
                }
            }
        }

        return Inertia::render('Billing/Index', [
            'subscription' => $subscription ? [
                'stripe_status' => $subscription->stripe_status,
                'stripe_price' => $subscription->stripe_price,
                'current_period_end' => $subscription->asStripeSubscription()->current_period_end
                    ? date('c', $subscription->asStripeSubscription()->current_period_end)
                    : null,
            ] : null,
            'credits' => $user->assessment_credits ?? 0,
            'usage' => [
                'assessments_this_period' => $user->assessments()->where('created_at', '>=', now()->startOfMonth())->count(),
                'assessments_limit' => $subscription ? (config('billing.plans')[$this->findPlanKey($subscription->stripe_price)]['credits_per_period'] ?? null) : null,
            ],
            'plan_name' => $planName,
        ]);
    }

    public function checkoutIndividual(Request $request): RedirectResponse
    {
        $plan = $request->validate(['plan' => 'required|string'])['plan'];
        $priceId = config("billing.plans.{$plan}.price_id");

        if (! $priceId) {
            return back()->with('error', 'Invalid plan selected.');
        }

        return redirect(
            $request->user()
                ->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => route('billing.success'),
                    'cancel_url' => route('pricing'),
                ])
                ->url
        );
    }

    /**
     * The student's subscription, paid for by their parent.
     *
     * The subscription belongs to the *student's* record because the
     * entitlement is theirs — the coach, the brain and everything in it stay
     * with them. Only the billing identity is the parent's, via
     * {@see User::stripeEmail()}, so receipts and card management
     * reach the person who actually paid.
     *
     * Consent is required before this runs. Taking a parent's money before
     * they have said yes would make the payment the permission, and they are
     * not the same thing.
     */
    public function checkoutStudent(Request $request): RedirectResponse
    {
        $plan = $request->validate(['plan' => 'required|string'])['plan'];
        $priceId = config("billing.plans.{$plan}.price_id");
        $user = $request->user();

        if (! $priceId) {
            return back()->with('error', 'Invalid plan selected.');
        }

        if (! AccessPolicy::tier($user)->hasCoach()) {
            return back()->with('error', AccessPolicy::coachBlockedReason($user));
        }

        if (AccessPolicy::requiresParentConsent($user) && ! AccessPolicy::hasParentConsent($user)) {
            return back()->with('error', 'We need a parent or guardian to say yes before anyone pays for anything.');
        }

        return redirect(
            $user->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => route('first-run'),
                    'cancel_url' => route('first-run'),
                    'metadata' => [
                        'student_id' => $user->id,
                        'paid_by' => AccessPolicy::requiresParentCheckout($user) ? 'parent' : 'self',
                    ],
                ])
                ->url
        );
    }

    public function checkoutOrganization(Request $request): RedirectResponse
    {
        $plan = $request->validate(['plan' => 'required|string'])['plan'];
        $priceId = config("billing.plans.{$plan}.price_id");

        if (! $priceId) {
            return back()->with('error', 'Invalid plan selected.');
        }

        $organization = $request->user()->organizations()->first();
        if (! $organization) {
            return back()->with('error', 'You must belong to an organization to subscribe to an org plan.');
        }

        return redirect(
            $organization
                ->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => route('billing.success'),
                    'cancel_url' => route('pricing'),
                ])
                ->url
        );
    }

    public function checkoutSuccess(): Response
    {
        return Inertia::render('Billing/Success');
    }

    public function billingPortal(Request $request): RedirectResponse
    {
        return redirect($request->user()->billingPortalUrl(url('/dashboard')));
    }

    private function findPlanKey(string $priceId): ?string
    {
        foreach (config('billing.plans') as $key => $plan) {
            if ($plan['price_id'] === $priceId) {
                return $key;
            }
        }

        return null;
    }
}
