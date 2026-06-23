# Stripe Online Giving — Design Spec

**Date:** 2026-06-08  
**Status:** Approved  
**Sub-project:** #1 of HIGH PRIORITY improvements

---

## Goal

Enable visitors on each church's public `/give` page to donate online via Stripe Checkout. Each church connects their own Stripe account; money flows directly from donor to church — the platform never touches funds.

---

## Architecture

Five moving parts:

1. **Settings → Donations** — admin stores Stripe publishable key, (encrypted) secret key, and webhook signing secret
2. **`POST /give/checkout`** — creates a Stripe Checkout Session and returns its URL
3. **Stripe hosted checkout** — Stripe handles card entry, Apple Pay, Google Pay
4. **`/give/thank-you`** — confirmation page shown after successful payment
5. **`POST /stripe/webhook`** — server-side confirmation; records the donation to the `donations` table

---

## Data Flow

```
Visitor picks fund + amount on /give
  → POST /give/checkout  { fund_id, amount_cents }
    → GiveController resolves church Stripe secret key from settings
    → Stripe API: create CheckoutSession
      → { url: "https://checkout.stripe.com/..." }
  → browser window.location = url

Stripe hosted checkout (card entry, Apple Pay, Google Pay)
  → payment succeeds
    → Stripe → POST /stripe/webhook      (server confirms, records Donation row)
    → browser → /give/thank-you          (visitor sees confirmation)
```

The webhook and browser redirect are **independent**. Even if the visitor closes the tab mid-redirect, the webhook fires and the donation is recorded.

---

## Database — `donations` table

```
id               bigint PK
church_id        FK → churches (index)
fund_id          string            — matches fund slug from settings['donations']['funds']
stripe_session_id string UNIQUE    — idempotency key; duplicate webhook events are skipped
amount_cents     integer           — e.g. $50.00 → 5000
currency         string default 'usd'
donor_email      string nullable   — populated from Stripe session customer_details
donor_name       string nullable   — populated from Stripe session customer_details
status           enum(pending, completed, refunded) default 'pending'
created_at
updated_at
```

---

## Files

### Create

| file | purpose |
|------|---------|
| `database/migrations/2026_06_08_create_donations_table.php` | schema above |
| `app/Models/Donation.php` | Eloquent model; `BelongsToChurch` scope; `amount_cents` cast to int |
| `app/Http/Controllers/Public/GiveController.php` | `checkout()` + `success()` methods |
| `app/Http/Controllers/Webhook/StripeWebhookController.php` | `handle()` — verifies signature, upserts Donation |
| `resources/js/Pages/Public/GiveSuccess.vue` | thank-you page |

### Modify

| file | change |
|------|--------|
| `app/Http/Controllers/Dashboard/ChurchSettingsController.php` | `donations()` render + `updateDonations()` validation add Stripe key fields |
| `routes/web.php` | 4 new routes (see Routes section) |
| `resources/js/Pages/Public/Donate.vue` | replace "coming soon" panel with Give Now button + fetch to `/give/checkout` |
| `resources/js/Pages/Dashboard/Settings/Donations.vue` | add Stripe keys card |

### Install

```bash
composer require stripe/stripe-php
```

---

## Routes

```php
// Public — inside ResolveTenant middleware group
Route::post('/give/checkout', [GiveController::class, 'checkout'])->name('give.checkout');
Route::get('/give/thank-you', [GiveController::class, 'success'])->name('give.success');

// Webhook — OUTSIDE ResolveTenant (Stripe calls from stripe.com, not the church's domain)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook')
    ->withoutMiddleware(['web', 'auth', 'verified']);
// Must be excluded from CSRF verification in App\Http\Middleware\VerifyCsrfToken::$except
```

---

## Settings Storage

Stored in `settings['donations']` JSON bag alongside the existing `funds` array:

```php
[
    'funds'                    => [...],          // existing
    'stripe_publishable_key'   => 'pk_live_...',  // plain — public-facing
    'stripe_secret_key'        => Crypt::encryptString('sk_live_...'),  // encrypted
    'stripe_webhook_secret'    => Crypt::encryptString('whsec_...'),    // encrypted
]
```

`updateDonations()` calls `Crypt::encryptString()` before save; `donations()` render and `GiveController` call `Crypt::decryptString()` on read.

The publishable key is passed to the Inertia render (safe for the browser). The secret and webhook secret never leave the server.

---

## GiveController — `checkout()`

```php
public function checkout(Request $request): JsonResponse
{
    $request->validate([
        'fund_id'     => ['required', 'string', 'max:50'],
        'amount_cents'=> ['required', 'integer', 'min:100', 'max:99999900'], // $1–$999,999
    ]);

    $church          = app('church');
    $donationSettings = $church->settings['donations'] ?? [];
    $secretKey       = Crypt::decryptString($donationSettings['stripe_secret_key'] ?? '');

    Stripe::setApiKey($secretKey);

    $session = Session::create([
        'mode'        => 'payment',
        'line_items'  => [[
            'price_data' => [
                'currency'     => 'usd',
                'unit_amount'  => $request->amount_cents,
                'product_data' => ['name' => $this->fundName($donationSettings, $request->fund_id)],
            ],
            'quantity' => 1,
        ]],
        'metadata'         => [
            'church_id' => $church->id,
            'fund_id'   => $request->fund_id,
        ],
        'customer_creation' => 'if_required',
        'success_url'      => route('give.success') . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'       => url('/give'),
    ]);

    return response()->json(['url' => $session->url]);
}
```

---

## StripeWebhookController — `handle()`

```php
public function handle(Request $request): Response
{
    $payload   = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');

    // Parse without verification first to get church_id from metadata
    $event = \Stripe\Event::constructFrom(json_decode($payload, true));

    if ($event->type !== 'checkout.session.completed') {
        return response('Ignored', 200);
    }

    $session   = $event->data->object;
    $churchId  = $session->metadata->church_id ?? null;
    $church    = $churchId ? Church::find($churchId) : null;

    if (! $church) {
        return response('Church not found', 200); // 200 to stop Stripe retries
    }

    // Now verify signature with the church's webhook secret
    $webhookSecret = Crypt::decryptString($church->settings['donations']['stripe_webhook_secret'] ?? '');
    try {
        \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
    } catch (\Stripe\Exception\SignatureVerificationException) {
        return response('Invalid signature', 400);
    }

    // Idempotent upsert
    Donation::firstOrCreate(
        ['stripe_session_id' => $session->id],
        [
            'church_id'    => $church->id,
            'fund_id'      => $session->metadata->fund_id,
            'amount_cents' => $session->amount_total,
            'currency'     => $session->currency,
            'donor_email'  => $session->customer_details?->email,
            'donor_name'   => $session->customer_details?->name,
            'status'       => 'completed',
        ]
    );

    return response('OK', 200);
}
```

---

## Donate.vue — payment panel replacement

Replace the "coming soon" `<div>` with:

```vue
<div class="reveal reveal-delay-2">
    <h2 class="text-xl font-semibold text-neutral-900 mb-2">Complete Your Gift</h2>
    <p class="text-sm text-neutral-500 mb-6">You're giving to <strong>{{ activeFundName }}</strong>.</p>

    <!-- Amount display -->
    <div class="bg-brand-50 border border-brand-100 rounded-xl p-5 mb-6 text-center">
        <p class="text-4xl font-bold text-brand-700">${{ displayAmount }}</p>
        <p class="text-sm text-neutral-500 mt-1">{{ activeFundName }}</p>
    </div>

    <AppButton
        variant="primary"
        size="lg"
        class="w-full"
        :loading="checkingOut"
        @click="startCheckout"
    >
        <Heart class="w-4 h-4 mr-2" />
        Give Now — Secure Checkout
    </AppButton>
    <p class="text-xs text-neutral-400 text-center mt-3">Powered by Stripe · 256-bit encryption</p>
</div>
```

`startCheckout` sends `POST /give/checkout` with `{ fund_id: selectedFund, amount_cents: amountInCents }` and redirects to the returned URL.

---

## GiveSuccess.vue

Simple, warm thank-you screen using `useChurch()` for the church name. No props needed — the Stripe session ID is in the query string but we don't need to re-fetch it (webhook already confirmed server-side).

---

## Admin Setup — one-time per church

1. Log in to [dashboard.stripe.com](https://dashboard.stripe.com)
2. Developers → API keys → copy **Publishable key** and **Secret key**
3. Developers → Webhooks → Add endpoint
   - URL: `https://[church-domain]/stripe/webhook`
   - Event: `checkout.session.completed`
   - Copy the **Signing secret** (`whsec_...`)
4. Dashboard → Settings → Donations → paste all three keys → Save

---

## Out of Scope (not in this spec)

- Donation history UI in the dashboard (view past donations)
- Refund processing
- Recurring / subscription giving
- Stripe Connect (platform-level accounts)
- Multiple currencies
- Tax receipt emails (can follow as a future spec)
