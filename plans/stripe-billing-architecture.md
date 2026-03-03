# Stripe Billing Architecture — Vocational Discernment Assessment Platform

**Type:** Billing Design Document
**Date:** 2026-03-02
**Status:** Planning
**Applies Skill:** `stripe-best-practices`

---

## Table of Contents

1. [Guiding Principles (from stripe-best-practices)](#1-guiding-principles)
2. [Stripe Product & Price Structure](#2-stripe-product--price-structure)
3. [Laravel Cashier Configuration](#3-laravel-cashier-configuration)
4. [Checkout Flow Design](#4-checkout-flow-design)
5. [Webhook Handling](#5-webhook-handling)
6. [Subscription Lifecycle Management](#6-subscription-lifecycle-management)
7. [Metered Billing — Assessments Per Period](#7-metered-billing--assessments-per-period)
8. [Free Tier vs Paid Tier Gates](#8-free-tier-vs-paid-tier-gates)
9. [Database Schema Additions](#9-database-schema-additions)
10. [Implementation Checklist](#10-implementation-checklist)
11. [Go-Live Checklist](#11-go-live-checklist)

---

## 1. Guiding Principles

Per the `stripe-best-practices` skill, every decision in this document follows these rules:

| Principle | Decision |
|-----------|----------|
| **Primary API** | Stripe Checkout Sessions API for all payment flows. Never use the legacy Charges API. |
| **Frontend surface** | Stripe-hosted Checkout (redirect). No custom card forms, no legacy Card Element. |
| **Payment methods** | Dynamic payment methods enabled in the Stripe Dashboard. Do NOT pass `payment_method_types` explicitly -- let Stripe optimize per user location and wallet. |
| **Saving payment methods** | Use the SetupIntent API (via Cashier) for saving cards. Never use the Sources API or Tokens API. |
| **Recurring revenue model** | Use Stripe Billing APIs (Products, Prices, Subscriptions) combined with Checkout Sessions. This is a SaaS billing use case. |
| **API version** | Always use the latest Stripe API version. Pin via `STRIPE_API_VERSION` in `.env` and update deliberately. |
| **No deprecated endpoints** | No Sources API, no Charges API, no Tokens API, no legacy Card Element. |

**References:**
- [Stripe Integration Options](https://docs.stripe.com/payments/payment-methods/integration-options)
- [Stripe Checkout](https://docs.stripe.com/payments/checkout)
- [Subscription Use Cases](https://docs.stripe.com/billing/subscriptions/use-cases)
- [SaaS Billing Guide](https://docs.stripe.com/saas)
- [Designing a Billing Integration](https://docs.stripe.com/billing/subscriptions/designing-integration)
- [Go-Live Checklist](https://docs.stripe.com/get-started/checklist/go-live)

---

## 2. Stripe Product & Price Structure

### 2.1 Products

Create these Products in the Stripe Dashboard (or via API during seeding):

```
Product 1: "Individual Assessment"
  - Description: "Single vocational discernment assessment with AI-powered analysis"
  - Type: One-time (used for a la carte purchases)
  - Metadata: { "tier": "individual", "type": "assessment" }

Product 2: "Organization Plan"
  - Description: "Vocational assessment platform for churches, universities, and organizations"
  - Type: Recurring (subscription)
  - Metadata: { "tier": "organization", "type": "subscription" }
```

### 2.2 Prices

```
── Product: "Individual Assessment"
   ├── Price: "Individual Assessment - Single"
   │   - Billing: One-time
   │   - Amount: $29.00 (or your chosen price)
   │   - Lookup key: "individual_assessment_single"
   │   - Metadata: { "assessments_included": "1" }
   │
   └── Price: "Individual Assessment - Bundle of 3"
       - Billing: One-time
       - Amount: $69.00 (saves ~$18)
       - Lookup key: "individual_assessment_bundle_3"
       - Metadata: { "assessments_included": "3" }

── Product: "Organization Plan"
   ├── Price: "Organization Monthly - Small" (up to 25 members)
   │   - Billing: Recurring / Monthly
   │   - Amount: $99.00/month
   │   - Lookup key: "org_monthly_small"
   │   - Metadata: { "member_limit": "25", "assessments_per_period": "25" }
   │
   ├── Price: "Organization Annual - Small" (up to 25 members)
   │   - Billing: Recurring / Yearly
   │   - Amount: $990.00/year (saves ~$198)
   │   - Lookup key: "org_annual_small"
   │   - Metadata: { "member_limit": "25", "assessments_per_period": "300" }
   │
   ├── Price: "Organization Monthly - Medium" (up to 100 members)
   │   - Billing: Recurring / Monthly
   │   - Amount: $249.00/month
   │   - Lookup key: "org_monthly_medium"
   │   - Metadata: { "member_limit": "100", "assessments_per_period": "100" }
   │
   ├── Price: "Organization Annual - Medium" (up to 100 members)
   │   - Billing: Recurring / Yearly
   │   - Amount: $2,490.00/year
   │   - Lookup key: "org_annual_medium"
   │   - Metadata: { "member_limit": "100", "assessments_per_period": "1200" }
   │
   ├── Price: "Organization Monthly - Large" (up to 500 members)
   │   - Billing: Recurring / Monthly
   │   - Amount: $499.00/month
   │   - Lookup key: "org_monthly_large"
   │   - Metadata: { "member_limit": "500", "assessments_per_period": "500" }
   │
   └── Price: "Organization Annual - Large" (up to 500 members)
       - Billing: Recurring / Yearly
       - Amount: $4,990.00/year
       - Lookup key: "org_annual_large"
       - Metadata: { "member_limit": "500", "assessments_per_period": "6000" }
```

### 2.3 Why This Structure

- **Products map to your business concepts** (individual purchase vs. organization subscription).
- **Prices map to billing variants** (one-time vs. recurring, monthly vs. annual, tier sizes).
- **Lookup keys** allow you to reference prices in code without hardcoding `price_xxx` IDs. When you change pricing, create a new Price with the same lookup key and Stripe transfers the key automatically.
- **Metadata on Prices** carries business logic (member limits, assessment quotas) that your application reads to enforce gates.
- **No metered price line items** at this stage. Assessment counting is handled application-side (see Section 7). This is simpler and avoids the complexity of Stripe's metered billing for a quota-based (not usage-based) model.

---

## 3. Laravel Cashier Configuration

### 3.1 Installation & Setup

```bash
cd api/
composer require laravel/cashier
php artisan vendor:publish --tag="cashier-migrations"
php artisan migrate
```

### 3.2 Environment Variables

```env
# .env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Pin to the latest API version at time of integration
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en

# Lookup keys for prices (avoid hardcoding price IDs)
STRIPE_PRICE_INDIVIDUAL_SINGLE=individual_assessment_single
STRIPE_PRICE_ORG_MONTHLY_SMALL=org_monthly_small
STRIPE_PRICE_ORG_ANNUAL_SMALL=org_annual_small
STRIPE_PRICE_ORG_MONTHLY_MEDIUM=org_monthly_medium
STRIPE_PRICE_ORG_ANNUAL_MEDIUM=org_annual_medium
STRIPE_PRICE_ORG_MONTHLY_LARGE=org_monthly_large
STRIPE_PRICE_ORG_ANNUAL_LARGE=org_annual_large
```

### 3.3 Billable Models

Two models are billable: `User` (for individual purchases) and `Organization` (for subscriptions).

```php
// app/Models/User.php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;

    // Individual users buy single assessments.
    // Their stripe_id, pm_type, pm_last_four columns live on the users table.
}
```

```php
// app/Models/Organization.php
use Laravel\Cashier\Billable;

class Organization extends Model
{
    use Billable;

    // Organizations hold subscriptions.
    // Their stripe_id, pm_type, pm_last_four columns live on the organizations table.

    /**
     * Get the member limit from the active subscription's price metadata.
     */
    public function memberLimit(): int
    {
        $subscription = $this->subscription('default');

        if (!$subscription || !$subscription->valid()) {
            return 0;
        }

        // Read from local config mapping, NOT from Stripe API on every call
        return config("billing.plans.{$subscription->stripe_price}.member_limit", 0);
    }

    /**
     * Get assessments allowed per billing period.
     */
    public function assessmentsPerPeriod(): int
    {
        $subscription = $this->subscription('default');

        if (!$subscription || !$subscription->valid()) {
            return 0;
        }

        return config("billing.plans.{$subscription->stripe_price}.assessments_per_period", 0);
    }

    /**
     * Count assessments used in the current billing period.
     */
    public function assessmentsUsedThisPeriod(): int
    {
        $subscription = $this->subscription('default');

        if (!$subscription) {
            return 0;
        }

        $periodStart = $subscription->asStripeSubscription()->current_period_start;

        return $this->users()
            ->withCount(['assessments' => function ($query) use ($periodStart) {
                $query->where('created_at', '>=', Carbon::createFromTimestamp($periodStart));
            }])
            ->get()
            ->sum('assessments_count');
    }

    public function hasAssessmentQuotaRemaining(): bool
    {
        return $this->assessmentsUsedThisPeriod() < $this->assessmentsPerPeriod();
    }
}
```

### 3.4 Billing Config File

```php
// config/billing.php
return [
    'plans' => [
        // These keys are Stripe Price IDs. Populated after creating prices in Stripe.
        // Use php artisan stripe:sync-prices to populate from Stripe metadata.
        'price_org_monthly_small' => [
            'name' => 'Organization Small (Monthly)',
            'member_limit' => 25,
            'assessments_per_period' => 25,
            'interval' => 'month',
        ],
        'price_org_annual_small' => [
            'name' => 'Organization Small (Annual)',
            'member_limit' => 25,
            'assessments_per_period' => 300,
            'interval' => 'year',
        ],
        'price_org_monthly_medium' => [
            'name' => 'Organization Medium (Monthly)',
            'member_limit' => 100,
            'assessments_per_period' => 100,
            'interval' => 'month',
        ],
        'price_org_annual_medium' => [
            'name' => 'Organization Medium (Annual)',
            'member_limit' => 100,
            'assessments_per_period' => 1200,
            'interval' => 'year',
        ],
        'price_org_monthly_large' => [
            'name' => 'Organization Large (Monthly)',
            'member_limit' => 500,
            'assessments_per_period' => 500,
            'interval' => 'month',
        ],
        'price_org_annual_large' => [
            'name' => 'Organization Large (Annual)',
            'member_limit' => 500,
            'assessments_per_period' => 6000,
            'interval' => 'year',
        ],
    ],

    'individual' => [
        'free_assessments' => 1,  // Every individual gets 1 free assessment
    ],
];
```

### 3.5 Add Cashier Columns to Organizations Table

Since `Organization` is the billable model for subscriptions (not `User`), you must add Cashier's columns to the `organizations` table:

```php
// database/migrations/xxxx_add_cashier_columns_to_organizations_table.php
Schema::table('organizations', function (Blueprint $table) {
    $table->string('stripe_id')->nullable()->index();
    $table->string('pm_type')->nullable();
    $table->string('pm_last_four', 4)->nullable();
    $table->timestamp('trial_ends_at')->nullable();
});
```

The default Cashier migration adds columns to the `users` table, which handles individual billing. Keep both.

---

## 4. Checkout Flow Design

Per the stripe-best-practices skill: **always use Stripe Checkout Sessions** (Stripe-hosted). Never build custom card forms or use the legacy Card Element.

### 4.1 Individual Assessment Purchase (One-Time)

The individual purchases a single assessment via Stripe-hosted Checkout.

```php
// app/Http/Controllers/Api/BillingController.php

/**
 * Create a Checkout Session for an individual assessment purchase.
 * Uses Stripe-hosted Checkout (redirect) per best practices.
 */
public function checkoutIndividualAssessment(Request $request)
{
    $user = $request->user();

    // Check if user already has unused purchased assessments
    $unusedCredits = $user->assessment_credits_remaining;
    if ($unusedCredits > 0) {
        return response()->json([
            'message' => 'You already have unused assessment credits.',
            'credits_remaining' => $unusedCredits,
        ], 422);
    }

    // Use Stripe Checkout Session via Cashier
    // Do NOT pass payment_method_types -- let Stripe use dynamic payment methods
    return $user->checkout(['price_individual_single' => 1], [
        'success_url' => config('app.frontend_url') . '/assessment/start?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => config('app.frontend_url') . '/pricing?canceled=true',
        'metadata' => [
            'purchase_type' => 'individual_assessment',
            'user_id' => $user->id,
        ],
    ]);
}
```

**For the mobile app (Expo React Native):**

The mobile app cannot redirect to Stripe-hosted Checkout in a traditional sense. Instead:

1. The API creates the Checkout Session and returns the `url`.
2. The mobile app opens the Checkout URL in an in-app browser (WebBrowser from Expo or a WebView).
3. On success, Stripe redirects to your `success_url`. Use a deep link (`yourapp://assessment/start`) or a web URL that redirects back to the app.

```php
// For mobile clients, return the session URL instead of a redirect
public function createCheckoutSession(Request $request)
{
    $user = $request->user();

    $checkout = $user->checkout(['price_individual_single' => 1], [
        'success_url' => config('app.mobile_deep_link') . '/assessment/start?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => config('app.mobile_deep_link') . '/pricing?canceled=true',
        'metadata' => [
            'purchase_type' => 'individual_assessment',
            'user_id' => $user->id,
        ],
    ]);

    // Return the URL for the mobile app to open in a browser
    return response()->json([
        'checkout_url' => $checkout->url,
        'session_id' => $checkout->id,
    ]);
}
```

### 4.2 Organization Subscription (Recurring)

Organizations subscribe via Stripe Checkout with `mode: 'subscription'`.

```php
/**
 * Create a Checkout Session for an organization subscription.
 * The Organization model is the Billable entity here.
 */
public function checkoutOrganizationSubscription(Request $request)
{
    $request->validate([
        'price_lookup_key' => 'required|string|in:org_monthly_small,org_annual_small,org_monthly_medium,org_annual_medium,org_monthly_large,org_annual_large',
    ]);

    $organization = $request->user()->organization;

    if (!$organization) {
        return response()->json(['message' => 'You must belong to an organization.'], 403);
    }

    if ($request->user()->role !== 'admin') {
        return response()->json(['message' => 'Only organization admins can manage billing.'], 403);
    }

    // Already subscribed? Redirect to billing portal for plan changes
    if ($organization->subscribed('default')) {
        return response()->json([
            'portal_url' => $organization->billingPortalUrl(
                config('app.frontend_url') . '/org/settings/billing'
            ),
        ]);
    }

    // Use lookup keys to resolve prices (avoids hardcoding price IDs)
    // Do NOT pass payment_method_types -- dynamic payment methods via Dashboard
    return $organization->newSubscription('default', [])
        ->checkout([
            'success_url' => config('app.frontend_url') . '/org/settings/billing?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url') . '/pricing?canceled=true',
            'metadata' => [
                'purchase_type' => 'organization_subscription',
                'organization_id' => $organization->id,
            ],
        ]);
}
```

### 4.3 Stripe Billing Portal (Self-Service)

For subscription management (upgrade, downgrade, cancel, update payment method), use the Stripe Billing Portal. This avoids building custom UI for these flows and is the recommended approach.

```php
/**
 * Redirect to Stripe Billing Portal for subscription management.
 */
public function billingPortal(Request $request)
{
    $organization = $request->user()->organization;

    return response()->json([
        'portal_url' => $organization->billingPortalUrl(
            config('app.frontend_url') . '/org/settings/billing'
        ),
    ]);
}
```

Configure the Billing Portal in the Stripe Dashboard:
- Allow customers to update payment methods
- Allow plan switching (between small/medium/large and monthly/annual)
- Allow cancellation (with optional cancellation reason survey)
- Show invoice history

### 4.4 Checkout Flow Diagram

```
INDIVIDUAL PURCHASE FLOW:
========================
User clicks "Take Assessment" (no free credits remaining)
    → Frontend calls POST /api/billing/checkout/individual
    → API creates Checkout Session (mode: "payment", one-time)
    → API returns checkout URL
    → Web: Redirect to Stripe-hosted Checkout
      Mobile: Open in-app browser to Stripe-hosted Checkout
    → User completes payment on Stripe's page
    → Stripe redirects to success_url
    → Webhook: checkout.session.completed fires
    → API grants assessment credit to user
    → User starts assessment


ORGANIZATION SUBSCRIPTION FLOW:
================================
Org admin clicks "Subscribe" on pricing page
    → Frontend calls POST /api/billing/checkout/organization
    → API creates Checkout Session (mode: "subscription")
    → API returns checkout URL
    → Redirect to Stripe-hosted Checkout
    → Admin completes payment on Stripe's page
    → Stripe redirects to success_url
    → Webhook: checkout.session.completed + customer.subscription.created fire
    → API activates organization subscription
    → Members can now take assessments within quota


SUBSCRIPTION MANAGEMENT FLOW:
==============================
Org admin clicks "Manage Billing"
    → Frontend calls POST /api/billing/portal
    → API returns Stripe Billing Portal URL
    → Redirect to Stripe Billing Portal
    → Admin upgrades/downgrades/cancels/updates payment method
    → Stripe handles everything
    → Webhook events fire for any changes
    → API updates local subscription state
```

---

## 5. Webhook Handling

### 5.1 Webhook Setup

```bash
# Create the webhook endpoint in Stripe (use Cashier's artisan command)
php artisan cashier:webhook --url "https://yourdomain.com/stripe/webhook"

# For local development with Stripe CLI
stripe listen --forward-to localhost:8000/stripe/webhook
```

Register the webhook route (Cashier does this automatically, but verify):

```php
// routes/web.php  (NOT api.php -- webhooks must not require Sanctum auth)
// Cashier automatically registers POST /stripe/webhook
// No additional route registration needed if using Cashier's default.
```

### 5.2 Webhook Events to Handle

Extend Cashier's `WebhookController` to handle platform-specific logic:

```php
// app/Http/Controllers/Webhooks/StripeWebhookController.php

namespace App\Http\Controllers\Webhooks;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeWebhookController extends WebhookController
{
    /**
     * Handle checkout.session.completed for one-time individual purchases.
     * This is the ONLY reliable way to grant assessment credits after payment.
     */
    protected function handleCheckoutSessionCompleted(array $payload): void
    {
        $session = $payload['data']['object'];
        $metadata = $session['metadata'] ?? [];

        if (($metadata['purchase_type'] ?? '') === 'individual_assessment') {
            $user = User::find($metadata['user_id']);

            if ($user) {
                // Grant assessment credit
                $user->increment('assessment_credits', 1);

                Log::info('Assessment credit granted', [
                    'user_id' => $user->id,
                    'session_id' => $session['id'],
                ]);
            }
        }

        // For subscription checkouts, Cashier handles this automatically
        // via customer.subscription.created
    }

    /**
     * Handle subscription updated (plan change, quantity change).
     * Cashier handles the subscription record update automatically.
     * We add organization-specific logic here.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): void
    {
        parent::handleCustomerSubscriptionUpdated($payload);

        $subscription = $payload['data']['object'];
        $stripeCustomerId = $subscription['customer'];

        $organization = Organization::where('stripe_id', $stripeCustomerId)->first();

        if ($organization) {
            // Update cached plan metadata (member limits, etc.)
            $priceId = $subscription['items']['data'][0]['price']['id'] ?? null;
            $organization->update([
                'subscription_status' => $subscription['status'],
                'current_price_id' => $priceId,
            ]);

            Log::info('Organization subscription updated', [
                'organization_id' => $organization->id,
                'status' => $subscription['status'],
                'price_id' => $priceId,
            ]);
        }
    }

    /**
     * Handle subscription deleted (cancellation completed).
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): void
    {
        parent::handleCustomerSubscriptionDeleted($payload);

        $subscription = $payload['data']['object'];
        $stripeCustomerId = $subscription['customer'];

        $organization = Organization::where('stripe_id', $stripeCustomerId)->first();

        if ($organization) {
            $organization->update([
                'subscription_status' => 'canceled',
            ]);

            // Do NOT delete assessment data. Members retain read access to
            // their past results. They just cannot take new assessments.
            Log::info('Organization subscription canceled', [
                'organization_id' => $organization->id,
            ]);
        }
    }

    /**
     * Handle invoice payment failed (subscription at risk).
     */
    protected function handleInvoicePaymentFailed(array $payload): void
    {
        $invoice = $payload['data']['object'];
        $stripeCustomerId = $invoice['customer'];

        $organization = Organization::where('stripe_id', $stripeCustomerId)->first();

        if ($organization) {
            $organization->update([
                'subscription_status' => 'past_due',
            ]);

            // Notify org admin about payment failure
            $admins = $organization->users()->where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\PaymentFailedNotification($organization));
            }

            Log::warning('Organization payment failed', [
                'organization_id' => $organization->id,
                'invoice_id' => $invoice['id'],
            ]);
        }
    }

    /**
     * Handle invoice payment succeeded (subscription renewed or recovered).
     */
    protected function handleInvoicePaymentSucceeded(array $payload): void
    {
        $invoice = $payload['data']['object'];
        $stripeCustomerId = $invoice['customer'];

        $organization = Organization::where('stripe_id', $stripeCustomerId)->first();

        if ($organization && $organization->subscription_status === 'past_due') {
            $organization->update([
                'subscription_status' => 'active',
            ]);

            Log::info('Organization payment recovered', [
                'organization_id' => $organization->id,
            ]);
        }
    }
}
```

### 5.3 Register the Custom Webhook Controller

```php
// routes/web.php
use App\Http\Controllers\Webhooks\StripeWebhookController;

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
```

### 5.4 Webhook Best Practices

1. **Idempotency:** All webhook handlers must be idempotent. The same event may be delivered multiple times. Use the Stripe event ID to deduplicate if needed.
2. **Signature verification:** Cashier handles this automatically using `STRIPE_WEBHOOK_SECRET`. Never skip verification.
3. **Respond quickly:** Return 200 before doing heavy processing. Use queued jobs for slow operations.
4. **Log everything:** Log all webhook events with relevant IDs for debugging.
5. **Handle out-of-order delivery:** Webhooks may arrive out of order. Always read the current state from the event payload, not from assumptions about event order.

### 5.5 Full Webhook Event List

| Event | Handler | Purpose |
|-------|---------|---------|
| `checkout.session.completed` | Custom | Grant individual assessment credits for one-time purchases |
| `customer.subscription.created` | Cashier (auto) | Create local subscription record |
| `customer.subscription.updated` | Cashier + Custom | Update subscription record + sync org plan metadata |
| `customer.subscription.deleted` | Cashier + Custom | Mark subscription as canceled + update org status |
| `invoice.payment_succeeded` | Custom | Confirm renewal, recover from past_due state |
| `invoice.payment_failed` | Custom | Mark subscription past_due, notify org admins |
| `customer.updated` | Cashier (auto) | Sync customer metadata |
| `payment_method.automatically_updated` | Cashier (auto) | Card network updated card on file |

---

## 6. Subscription Lifecycle Management

### 6.1 Lifecycle States

```
                    ┌─────────────┐
                    │   No Plan   │
                    │  (visitor)  │
                    └──────┬──────┘
                           │ checkout.session.completed
                           ▼
                    ┌─────────────┐
             ┌──────│   Active    │──────┐
             │      └──────┬──────┘      │
             │             │             │
    invoice.payment_failed │    user cancels via portal
             │             │             │
             ▼             │             ▼
      ┌─────────────┐     │      ┌──────────────┐
      │  Past Due   │     │      │  Canceling   │
      │ (grace)     │     │      │ (end of      │
      └──────┬──────┘     │      │  period)     │
             │             │      └──────┬───────┘
    payment recovered     │             │ period ends
             │             │             │
             ▼             │             ▼
      ┌─────────────┐     │      ┌─────────────┐
      │   Active    │     │      │  Canceled   │
      │ (recovered) │     │      │  (expired)  │
      └─────────────┘     │      └─────────────┘
                           │
                    invoice.payment_succeeded
                    (auto-renewal)
                           │
                           ▼
                    ┌─────────────┐
                    │   Active    │
                    │ (renewed)   │
                    └─────────────┘
```

### 6.2 Checking Subscription State in Code

```php
// app/Http/Middleware/EnsureOrganizationSubscribed.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOrganizationSubscribed
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $organization = $user->organization;

        if (!$organization) {
            return response()->json(['message' => 'No organization found.'], 403);
        }

        // Cashier's subscribed() checks: active, on trial, on grace period
        if (!$organization->subscribed('default')) {
            return response()->json([
                'message' => 'Organization subscription required.',
                'subscription_status' => $organization->subscription_status ?? 'none',
            ], 402);
        }

        return $next($request);
    }
}
```

### 6.3 Handling Plan Changes (Upgrade/Downgrade)

Do NOT build custom upgrade/downgrade UI. Use the Stripe Billing Portal, which handles:
- Proration calculations
- Immediate vs. end-of-period changes
- Payment collection for upgrades
- Credit application for downgrades

Configure proration behavior in the Billing Portal settings in the Stripe Dashboard.

### 6.4 Handling Cancellation

```php
// Cancellation is handled by Stripe Billing Portal.
// Cashier automatically tracks the grace period.

// Check if on grace period (canceled but still has access until period end):
$organization->subscription('default')->onGracePeriod();

// Check if truly expired:
$organization->subscription('default')->canceled();

// In your access gate:
public function canTakeAssessment(User $user): bool
{
    $org = $user->organization;

    // Active subscription or on grace period = access allowed
    if ($org && $org->subscribed('default')) {
        return $org->hasAssessmentQuotaRemaining();
    }

    return false;
}
```

### 6.5 Trial Periods

If you want to offer a trial for organization subscriptions:

```php
// Option A: Trial on the subscription (card required upfront via Checkout)
$organization->newSubscription('default', 'price_org_monthly_small')
    ->trialDays(14)
    ->checkout([
        'success_url' => '...',
        'cancel_url' => '...',
    ]);

// Option B: Generic trial (no card required, managed in your app)
// Set trial_ends_at directly on the organization
$organization->update([
    'trial_ends_at' => now()->addDays(14),
]);

// Then check:
$organization->onGenericTrial(); // true during trial period
```

---

## 7. Metered Billing -- Assessments Per Period

### 7.1 Approach: Application-Side Quota Tracking

Per the plan, organizations have a fixed number of assessments per billing period. This is a **quota model** (not true metered/usage-based billing), so we track it application-side rather than using Stripe's metered billing APIs.

Rationale:
- Stripe's metered billing is designed for pay-per-unit models where you report usage and Stripe bills accordingly.
- Your model is "N assessments included in the plan" -- a fixed quota that gates access. This is enforced in your application, not billed per unit.
- Simpler to implement, debug, and reason about.

### 7.2 Quota Enforcement

```php
// app/Actions/Assessment/StartAssessmentAction.php

namespace App\Actions\Assessment;

use App\Models\User;
use App\Exceptions\AssessmentQuotaExceededException;
use App\Exceptions\NoAssessmentCreditsException;

class StartAssessmentAction
{
    public function execute(User $user): Assessment
    {
        $this->authorizeAssessment($user);

        return Assessment::create([
            'user_id' => $user->id,
            'mode' => 'written', // or 'conversation'
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    private function authorizeAssessment(User $user): void
    {
        // Path 1: User belongs to a subscribed organization
        if ($user->organization && $user->organization->subscribed('default')) {
            if (!$user->organization->hasAssessmentQuotaRemaining()) {
                throw new AssessmentQuotaExceededException(
                    'Your organization has used all assessments for this billing period.'
                );
            }
            return;
        }

        // Path 2: Individual user with free tier or purchased credits
        $freeAllowance = config('billing.individual.free_assessments', 1);
        $completedAssessments = $user->assessments()->where('status', 'completed')->count();
        $purchasedCredits = $user->assessment_credits ?? 0;

        $totalAllowed = $freeAllowance + $purchasedCredits;

        if ($completedAssessments >= $totalAllowed) {
            throw new NoAssessmentCreditsException(
                'You have used all your assessment credits. Purchase additional assessments to continue.'
            );
        }
    }
}
```

### 7.3 Decrementing Credits on Completion

```php
// app/Actions/Assessment/CompleteAssessmentAction.php

public function execute(Assessment $assessment): void
{
    $assessment->update([
        'status' => 'analyzing',
        'completed_at' => now(),
    ]);

    $user = $assessment->user;

    // If this is a purchased assessment (not free tier, not org),
    // decrement the credit
    $freeAllowance = config('billing.individual.free_assessments', 1);
    $completedCount = $user->assessments()
        ->where('status', 'completed')
        ->orWhere('status', 'analyzing')
        ->count();

    if (!$user->organization && $completedCount >= $freeAllowance && $user->assessment_credits > 0) {
        $user->decrement('assessment_credits');
    }

    // Dispatch AI analysis job
    AnalyzeAssessmentJob::dispatch($assessment);
}
```

### 7.4 Usage Dashboard for Org Admins

```php
// app/Http/Controllers/Api/OrganizationController.php

public function billingUsage(Request $request)
{
    $org = $request->user()->organization;
    $subscription = $org->subscription('default');

    if (!$subscription) {
        return response()->json(['message' => 'No active subscription.'], 404);
    }

    return response()->json([
        'plan' => config("billing.plans.{$subscription->stripe_price}.name"),
        'member_limit' => $org->memberLimit(),
        'current_members' => $org->users()->count(),
        'assessments_per_period' => $org->assessmentsPerPeriod(),
        'assessments_used' => $org->assessmentsUsedThisPeriod(),
        'assessments_remaining' => max(0, $org->assessmentsPerPeriod() - $org->assessmentsUsedThisPeriod()),
        'current_period_end' => $subscription->asStripeSubscription()->current_period_end,
        'subscription_status' => $subscription->stripe_status,
        'on_grace_period' => $subscription->onGracePeriod(),
    ]);
}
```

---

## 8. Free Tier vs Paid Tier Gates

### 8.1 Tier Definitions

```
FREE TIER (Individual):
  - 1 assessment (no payment required)
  - Full AI analysis
  - PDF download of results
  - Results accessible indefinitely
  - No organization features

PAID INDIVIDUAL:
  - Additional assessments via one-time purchase ($29 each or $69 for 3)
  - Same features as free tier
  - Re-assessment to track changes over time

ORGANIZATION (Subscription):
  - Everything in free tier for each member
  - Admin dashboard with aggregate insights
  - Member invitation and management
  - Assessment quota per billing period
  - Organization reports and exports
  - Bulk PDF generation
  - (Future) Course assignment and tracking
```

### 8.2 Gate Middleware

```php
// app/Http/Middleware/CheckAssessmentAccess.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAssessmentAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Org members: check org subscription + quota
        if ($user->organization) {
            if (!$user->organization->subscribed('default') && !$user->organization->onGenericTrial()) {
                return response()->json([
                    'message' => 'Organization subscription required.',
                    'action' => 'subscribe',
                ], 402);
            }

            if (!$user->organization->hasAssessmentQuotaRemaining()) {
                return response()->json([
                    'message' => 'Organization assessment quota reached for this period.',
                    'action' => 'upgrade_or_wait',
                    'quota' => $user->organization->assessmentsPerPeriod(),
                    'used' => $user->organization->assessmentsUsedThisPeriod(),
                ], 402);
            }

            return $next($request);
        }

        // Individual: check free allowance + purchased credits
        $freeAllowance = config('billing.individual.free_assessments', 1);
        $completedAssessments = $user->assessments()
            ->whereIn('status', ['completed', 'analyzing'])
            ->count();
        $purchasedCredits = $user->assessment_credits ?? 0;

        if ($completedAssessments >= ($freeAllowance + $purchasedCredits)) {
            return response()->json([
                'message' => 'No assessment credits remaining.',
                'action' => 'purchase',
                'completed' => $completedAssessments,
                'free_allowance' => $freeAllowance,
                'purchased_credits' => $purchasedCredits,
            ], 402);
        }

        return $next($request);
    }
}
```

### 8.3 Route Protection

```php
// routes/api.php

Route::middleware(['auth:sanctum'])->group(function () {

    // Always accessible (view past results, profile, etc.)
    Route::get('/assessments', [AssessmentController::class, 'index']);
    Route::get('/assessments/{assessment}/results', [ResultsController::class, 'show']);
    Route::get('/assessments/{assessment}/results/pdf', [ResultsController::class, 'pdf']);

    // Gated: starting a new assessment requires credits or subscription
    Route::middleware(['check.assessment.access'])->group(function () {
        Route::post('/assessments', [AssessmentController::class, 'store']);
        Route::post('/conversations/start', [AudioConversationController::class, 'start']);
    });

    // Saving answers to an in-progress assessment (no additional gate)
    Route::post('/assessments/{assessment}/answers', [AssessmentController::class, 'saveAnswer']);
    Route::patch('/assessments/{assessment}/answers/{answer}', [AssessmentController::class, 'updateAnswer']);
    Route::post('/assessments/{assessment}/complete', [AssessmentController::class, 'complete']);

    // Billing
    Route::prefix('billing')->group(function () {
        Route::post('/checkout/individual', [BillingController::class, 'checkoutIndividualAssessment']);
        Route::post('/checkout/organization', [BillingController::class, 'checkoutOrganizationSubscription']);
        Route::post('/portal', [BillingController::class, 'billingPortal']);
        Route::get('/usage', [OrganizationController::class, 'billingUsage']);
    });

    // Organization admin routes
    Route::middleware(['ensure.org.subscribed'])->prefix('org')->group(function () {
        Route::get('/members', [OrganizationController::class, 'members']);
        Route::post('/invite', [OrganizationController::class, 'invite']);
        Route::get('/reports', [OrganizationController::class, 'reports']);
    });
});
```

### 8.4 Frontend Gate Check (API Response Pattern)

```typescript
// mobile/services/billing.ts (Expo React Native)

interface BillingStatus {
  canTakeAssessment: boolean;
  reason?: 'free_available' | 'credits_available' | 'org_subscription' | 'no_credits' | 'quota_exceeded' | 'no_subscription';
  action?: 'none' | 'purchase' | 'subscribe' | 'upgrade_or_wait';
  creditsRemaining?: number;
  quotaRemaining?: number;
}

export async function checkAssessmentAccess(): Promise<BillingStatus> {
  try {
    // Attempt to hit the gated endpoint with a preflight-style check
    const response = await api.get('/api/billing/access-check');
    return {
      canTakeAssessment: true,
      reason: response.data.reason,
      action: 'none',
      creditsRemaining: response.data.credits_remaining,
    };
  } catch (error) {
    if (error.response?.status === 402) {
      return {
        canTakeAssessment: false,
        reason: error.response.data.action === 'purchase' ? 'no_credits' : 'quota_exceeded',
        action: error.response.data.action,
        creditsRemaining: 0,
        quotaRemaining: 0,
      };
    }
    throw error;
  }
}
```

---

## 9. Database Schema Additions

These migrations supplement the schema from the main plan:

```php
// Migration: Add assessment_credits to users table
Schema::table('users', function (Blueprint $table) {
    $table->integer('assessment_credits')->default(0)->after('role');
    // Cashier columns (added by cashier migration, listed for reference):
    // $table->string('stripe_id')->nullable()->index();
    // $table->string('pm_type')->nullable();
    // $table->string('pm_last_four', 4)->nullable();
    // $table->timestamp('trial_ends_at')->nullable();
});

// Migration: Add billing columns to organizations table
Schema::table('organizations', function (Blueprint $table) {
    $table->string('stripe_id')->nullable()->index();
    $table->string('pm_type')->nullable();
    $table->string('pm_last_four', 4)->nullable();
    $table->timestamp('trial_ends_at')->nullable();
    $table->string('subscription_status')->default('none')->after('settings');
    $table->string('current_price_id')->nullable()->after('subscription_status');
});

// Cashier creates these tables automatically:
// - subscriptions (id, organization_id, type, stripe_id, stripe_status, stripe_price, quantity, trial_ends_at, ends_at, timestamps)
// - subscription_items (id, subscription_id, stripe_id, stripe_product, stripe_price, quantity, timestamps)
```

---

## 10. Implementation Checklist

### Stripe Dashboard Setup
- [ ] Create "Individual Assessment" Product with one-time Prices
- [ ] Create "Organization Plan" Product with recurring Prices (monthly + annual for each tier)
- [ ] Set lookup keys on all Prices
- [ ] Enable dynamic payment methods in Dashboard settings
- [ ] Configure Billing Portal (allow plan switching, cancellation, payment method updates)
- [ ] Configure webhook endpoint pointing to `/stripe/webhook`
- [ ] Add webhook signing secret to `.env`

### Laravel Backend
- [ ] Install Laravel Cashier (`composer require laravel/cashier`)
- [ ] Run Cashier migrations
- [ ] Add Cashier columns to `organizations` table
- [ ] Add `assessment_credits` column to `users` table
- [ ] Add `Billable` trait to `User` and `Organization` models
- [ ] Create `config/billing.php` with plan metadata
- [ ] Implement `BillingController` with checkout and portal methods
- [ ] Implement `StripeWebhookController` extending Cashier's controller
- [ ] Implement `CheckAssessmentAccess` middleware
- [ ] Implement `EnsureOrganizationSubscribed` middleware
- [ ] Wire up routes in `api.php` and `web.php`
- [ ] Implement quota tracking methods on `Organization` model
- [ ] Add billing gate to `StartAssessmentAction`

### Testing
- [ ] Test individual checkout flow end-to-end with Stripe CLI
- [ ] Test organization subscription flow end-to-end
- [ ] Test webhook handling with `stripe trigger` commands
- [ ] Test free tier access (first assessment without payment)
- [ ] Test quota enforcement (org hitting assessment limit)
- [ ] Test grace period behavior (canceled but not yet expired)
- [ ] Test past_due behavior (failed payment)
- [ ] Test plan upgrade/downgrade via Billing Portal

---

## 11. Go-Live Checklist

Per the stripe-best-practices skill, follow the [Stripe Go-Live Checklist](https://docs.stripe.com/get-started/checklist/go-live) before launching:

- [ ] Switch from test API keys (`pk_test_`, `sk_test_`) to live keys (`pk_live_`, `sk_live_`)
- [ ] Update webhook endpoint to production URL with new signing secret
- [ ] Verify all webhook events are being received and processed
- [ ] Enable HTTPS on all endpoints (Stripe requires it for webhooks in production)
- [ ] Test a real payment with a real card in live mode
- [ ] Set up Stripe Radar for fraud protection
- [ ] Configure receipt emails in Stripe Dashboard
- [ ] Set up Stripe tax settings if applicable
- [ ] Review and accept Stripe's Services Agreement
- [ ] Set up monitoring/alerting for failed webhooks and payment failures
- [ ] Ensure PCI compliance (using Stripe-hosted Checkout means you qualify for SAQ-A, the simplest level)
- [ ] Add refund policy and terms of service to your application
- [ ] Test the Billing Portal in live mode

---

## Key Decisions Summary

| Decision | Choice | Rationale (per stripe-best-practices) |
|----------|--------|---------------------------------------|
| Payment collection | Stripe-hosted Checkout Sessions | Skill mandates prioritizing Checkout Sessions. Simplest PCI compliance (SAQ-A). |
| Payment methods | Dynamic (Dashboard-configured) | Skill says never pass `payment_method_types` explicitly. Let Stripe optimize. |
| Subscription management | Stripe Billing Portal | Avoid building custom upgrade/downgrade/cancel UI. Portal handles proration. |
| Metered billing | Application-side quota tracking | Quota model (N included) is simpler than Stripe metered billing (pay-per-unit). |
| Saving cards | SetupIntent via Cashier | Skill forbids Sources API and Tokens API. |
| Card input | None (Stripe-hosted page) | Skill forbids legacy Card Element and custom card forms. |
| Billing model | Stripe Billing APIs + Checkout | Skill says SaaS/recurring revenue must use Billing APIs with Checkout frontend. |
| Webhook controller | Extend Cashier's WebhookController | Cashier handles signature verification and common events. We extend for custom logic. |
| Two billable models | User (one-time) + Organization (subscription) | Maps cleanly to the business: individuals buy assessments, orgs subscribe. |
