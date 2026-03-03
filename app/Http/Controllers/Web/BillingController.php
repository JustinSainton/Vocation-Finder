<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
                'assessments_limit' => $subscription ? (config("billing.plans")[$this->findPlanKey($subscription->stripe_price)]['credits_per_period'] ?? null) : null,
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
