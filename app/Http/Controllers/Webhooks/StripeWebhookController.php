<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeWebhookController extends WebhookController
{
    public function handleCheckoutSessionCompleted(array $payload): void
    {
        $session = $payload['data']['object'];
        $customerId = $session['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $user = User::where('stripe_id', $customerId)->first();
        if ($user) {
            $this->provisionIndividualCredits($user, $session);

            return;
        }

        $org = Organization::where('stripe_id', $customerId)->first();
        if ($org) {
            $org->update(['subscription_status' => 'active']);
        }
    }

    public function handleCustomerSubscriptionUpdated(array $payload): void
    {
        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'] ?? null;
        $priceId = $subscription['items']['data'][0]['price']['id'] ?? null;
        $status = $subscription['status'] ?? null;

        $org = Organization::where('stripe_id', $customerId)->first();
        if ($org) {
            $org->update([
                'subscription_status' => $status,
                'current_price_id' => $priceId,
            ]);
        }
    }

    public function handleCustomerSubscriptionDeleted(array $payload): void
    {
        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'] ?? null;

        $org = Organization::where('stripe_id', $customerId)->first();
        if ($org) {
            $org->update([
                'subscription_status' => null,
                'current_price_id' => null,
            ]);
        }

        $user = User::where('stripe_id', $customerId)->first();
        if ($user) {
            $user->update(['assessment_credits' => 0]);
        }
    }

    public function handleInvoicePaymentFailed(array $payload): void
    {
        $invoice = $payload['data']['object'];
        $customerId = $invoice['customer'] ?? null;

        $user = User::where('stripe_id', $customerId)->first();
        if ($user) {
            $user->notify(new PaymentFailedNotification);
        }
    }

    public function handleInvoicePaymentSucceeded(array $payload): void
    {
        $invoice = $payload['data']['object'];
        $customerId = $invoice['customer'] ?? null;
        $priceId = $invoice['lines']['data'][0]['price']['id'] ?? null;

        if (! $priceId) {
            return;
        }

        $user = User::where('stripe_id', $customerId)->first();
        if ($user) {
            $credits = $this->creditsForPriceId($priceId);
            if ($credits > 0) {
                $user->update(['assessment_credits' => $credits]);
            }
        }
    }

    private function provisionIndividualCredits(User $user, array $session): void
    {
        $lineItems = $session['line_items']['data'] ?? [];
        $priceId = $lineItems[0]['price']['id'] ?? null;

        if (! $priceId) {
            return;
        }

        $credits = $this->creditsForPriceId($priceId);
        if ($credits > 0) {
            $user->update(['assessment_credits' => $credits]);
        }
    }

    private function creditsForPriceId(string $priceId): int
    {
        $plans = config('billing.plans');

        foreach ($plans as $plan) {
            if (($plan['price_id'] ?? null) === $priceId && isset($plan['credits_per_period'])) {
                return $plan['credits_per_period'];
            }
        }

        return 0;
    }
}
