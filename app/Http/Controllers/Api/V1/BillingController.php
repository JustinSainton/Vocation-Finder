<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function checkoutIndividual(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => ['required', 'in:individual_monthly,individual_yearly'],
        ]);

        $plan = config("billing.plans.{$request->plan}");
        $user = $request->user();

        $checkout = $user->newSubscription('default', $plan['price_id'])
            ->checkout([
                'success_url' => url('/billing/success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/pricing'),
            ]);

        return response()->json(['checkout_url' => $checkout->url]);
    }

    public function checkoutOrganization(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => ['required', 'in:org_monthly,org_yearly'],
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
        ]);

        $plan = config("billing.plans.{$request->plan}");
        $org = $request->user()->organizations()->findOrFail($request->organization_id);

        $checkout = $org->newSubscription('default', $plan['price_id'])
            ->checkout([
                'success_url' => url('/billing/success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/pricing'),
            ]);

        return response()->json(['checkout_url' => $checkout->url]);
    }

    public function billingPortal(Request $request): JsonResponse
    {
        $url = $request->user()->billingPortalUrl(url('/dashboard'));

        return response()->json(['url' => $url]);
    }

    public function accessCheck(Request $request): JsonResponse
    {
        $user = $request->user();

        $orgQuota = null;
        $subscribedOrg = $user->organizations()
            ->where('subscription_status', 'active')
            ->first();

        if ($subscribedOrg) {
            $orgQuota = [
                'organization_id' => $subscribedOrg->id,
                'name' => $subscribedOrg->name,
                'assessments_per_period' => $subscribedOrg->assessmentsPerPeriod(),
                'assessments_used' => $subscribedOrg->assessmentsUsedThisPeriod(),
                'has_remaining' => $subscribedOrg->hasAssessmentQuotaRemaining(),
            ];
        }

        return response()->json([
            'has_access' => $user->hasAssessmentAccess(),
            'credits' => $user->assessment_credits ?? 0,
            'org_quota' => $orgQuota,
        ]);
    }

    public function billingUsage(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'credits' => $user->assessment_credits ?? 0,
            'total_assessments' => $user->assessments()->count(),
            'completed_assessments' => $user->assessments()->where('status', 'completed')->count(),
            'is_subscribed' => $user->subscribed(),
        ]);
    }
}
