# Stripe Online Giving — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable visitors on each church's public `/give` page to complete real Stripe payments, with per-church API keys stored in Settings → Donations.

**Architecture:** Stripe Checkout (hosted page). Visitor picks fund + amount → `POST /give/checkout` creates a Checkout Session → browser redirects to Stripe → webhook records confirmed donation → visitor lands on `/give/thank-you`. Each church provides their own Stripe keys; the platform never handles funds.

**Tech Stack:** Laravel 11, Stripe PHP SDK (`stripe/stripe-php`), Vue 3 + TypeScript + Inertia.js, Tailwind CSS v4.

**Constraints:** No test runner. Verify via `npm run type-check` and browser only.

---

## File Map

| File | Action |
|------|--------|
| `database/migrations/2026_06_08_create_donations_table.php` | Create |
| `app/Models/Donation.php` | Create |
| `app/Http/Controllers/Public/DonationController.php` | Modify — add `stripeEnabled` to render |
| `app/Http/Controllers/Public/GiveController.php` | Create |
| `app/Http/Controllers/Webhook/StripeWebhookController.php` | Create |
| `app/Http/Controllers/Dashboard/ChurchSettingsController.php` | Modify — `donations()` + add `updateStripeKeys()` |
| `routes/web.php` | Modify — 2 new public routes |
| `routes/api.php` | Modify — 1 webhook route |
| `resources/js/Pages/Public/Donate.vue` | Modify — replace "coming soon" panel |
| `resources/js/Pages/Public/GiveSuccess.vue` | Create |
| `resources/js/Pages/Dashboard/Settings/Donations.vue` | Modify — add Stripe keys card |

---

## Task 1: Install Stripe PHP SDK

**Files:**
- Modify: `composer.json` (via composer)

- [ ] **Step 1: Install the package**

```bash
composer require stripe/stripe-php
```

Expected output: `stripe/stripe-php` appears in `vendor/` and `composer.json`.

- [ ] **Step 2: Verify**

```bash
php -r "require 'vendor/autoload.php'; echo \Stripe\Stripe::VERSION . PHP_EOL;"
```

Expected: prints a version string like `14.x.x`.

---

## Task 2: Create `donations` migration

**Files:**
- Create: `database/migrations/2026_06_08_000001_create_donations_table.php`

- [ ] **Step 1: Create the migration file**

```bash
php artisan make:migration create_donations_table
```

Rename the generated file to `2026_06_08_000001_create_donations_table.php` if artisan uses a different timestamp.

- [ ] **Step 2: Write the migration**

Replace the generated file content with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('fund_id', 50)->default('general');
            $table->string('stripe_session_id')->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 10)->default('usd');
            $table->string('donor_email')->nullable();
            $table->string('donor_name')->nullable();
            $table->enum('status', ['pending', 'completed', 'refunded'])->default('pending');
            $table->timestamps();

            $table->index('church_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: `donations` table created. No errors.

---

## Task 3: Create `Donation` model

**Files:**
- Create: `app/Models/Donation.php`

- [ ] **Step 1: Create the file**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    protected $fillable = [
        'church_id',
        'fund_id',
        'stripe_session_id',
        'amount_cents',
        'currency',
        'donor_email',
        'donor_name',
        'status',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
```

- [ ] **Step 2: Verify the model loads**

```bash
php artisan tinker --execute="echo App\Models\Donation::count();"
```

Expected: `0` (no error).

---

## Task 4: Modify `DonationController` — pass `stripeEnabled`

**Files:**
- Modify: `app/Http/Controllers/Public/DonationController.php`

The public Give page needs to know whether Stripe is configured so it can show the payment panel (or the "coming soon" fallback).

- [ ] **Step 1: Read the current file**

Open `app/Http/Controllers/Public/DonationController.php`. The `__invoke()` method currently returns:
```php
return Inertia::render('Public/Donate', [
    'funds'            => $funds,
    'suggestedAmounts' => [25, 50, 100, 250, 500],
]);
```

- [ ] **Step 2: Add `stripeEnabled` to the render**

Replace the `__invoke()` method body with:

```php
public function __invoke(): Response
{
    $church           = app('church');
    $donationSettings = $church?->settings['donations'] ?? [];
    $funds            = $donationSettings['funds'] ?? self::DEFAULT_FUNDS;

    return Inertia::render('Public/Donate', [
        'funds'            => $funds,
        'suggestedAmounts' => [25, 50, 100, 250, 500],
        'stripeEnabled'    => ! empty($donationSettings['stripe_secret_key']),
    ]);
}
```

---

## Task 5: Modify `ChurchSettingsController` — Stripe keys methods

**Files:**
- Modify: `app/Http/Controllers/Dashboard/ChurchSettingsController.php`

Two changes: (a) expose key existence indicators in `donations()` render; (b) add `updateStripeKeys()` method.

- [ ] **Step 1: Add `Crypt` import**

At the top of the controller, add to the existing `use` block:

```php
use Illuminate\Support\Facades\Crypt;
```

- [ ] **Step 2: Update `donations()` render**

Find the `donations()` method. Replace the return statement:

```php
// BEFORE
return Inertia::render('Dashboard/Settings/Donations', [
    'settings' => [
        'funds' => $settings['funds'] ?? [],
    ],
]);

// AFTER
return Inertia::render('Dashboard/Settings/Donations', [
    'settings' => [
        'funds'                     => $settings['funds'] ?? [],
        'stripe_publishable_key'    => $settings['stripe_publishable_key'] ?? null,
        'stripe_has_secret_key'     => ! empty($settings['stripe_secret_key']),
        'stripe_has_webhook_secret' => ! empty($settings['stripe_webhook_secret']),
    ],
]);
```

- [ ] **Step 3: Add `updateStripeKeys()` method**

Add this method after `updateDonations()` in the Donations section (section 10):

```php
/** POST /dashboard/settings/donations/stripe */
public function updateStripeKeys(Request $request): RedirectResponse
{
    $this->authorizeSettings();

    $validated = $request->validate([
        'stripe_publishable_key' => ['nullable', 'string', 'max:200'],
        'stripe_secret_key'      => ['nullable', 'string', 'max:200'],
        'stripe_webhook_secret'  => ['nullable', 'string', 'max:200'],
    ]);

    $toSave = [];

    // Publishable key is safe to store plain (it's public-facing)
    if (array_key_exists('stripe_publishable_key', $validated)) {
        $toSave['stripe_publishable_key'] = $validated['stripe_publishable_key'] ?? '';
    }

    // Secret + webhook secret: only overwrite if a non-empty value was submitted
    if (! empty($validated['stripe_secret_key'])) {
        $toSave['stripe_secret_key'] = Crypt::encryptString($validated['stripe_secret_key']);
    }

    if (! empty($validated['stripe_webhook_secret'])) {
        $toSave['stripe_webhook_secret'] = Crypt::encryptString($validated['stripe_webhook_secret']);
    }

    if (! empty($toSave)) {
        $this->saveSettings('donations', $toSave);
    }

    return back()->with('success', 'Stripe settings saved.');
}
```

---

## Task 6: Modify `Donations.vue` — add Stripe keys card

**Files:**
- Modify: `resources/js/Pages/Dashboard/Settings/Donations.vue`

- [ ] **Step 1: Update the `Settings` interface and imports**

In `<script setup lang="ts">`, find the existing imports and interfaces. Apply these changes:

Add `CreditCard` to the lucide import line:
```ts
import { Plus, Trash2, GripVertical, CreditCard } from 'lucide-vue-next'
```

Add `computed` to the vue import:
```ts
import { computed } from 'vue'
```

Update the `Settings` interface to include Stripe fields:
```ts
interface Settings {
    funds: DonationFund[]
    stripe_publishable_key:    string | null
    stripe_has_secret_key:     boolean
    stripe_has_webhook_secret: boolean
}
```

- [ ] **Step 2: Add `stripeForm` and `webhookUrl` after existing form**

After the existing `form` declaration and helpers, add:

```ts
// ── Stripe keys form ──────────────────────────────────────────────────────────
const stripeForm = useForm({
    stripe_publishable_key: props.settings.stripe_publishable_key ?? '',
    stripe_secret_key:      '',
    stripe_webhook_secret:  '',
})

const webhookUrl = computed(() =>
    (typeof window !== 'undefined' ? window.location.origin : '') + '/api/stripe/webhook'
)

function submitStripe() {
    stripeForm.post('/dashboard/settings/donations/stripe', {
        preserveScroll: true,
    })
}
```

- [ ] **Step 3: Add Stripe keys card to the template**

In `<template>`, after the closing `</form>` tag of the funds form (the one that ends with the Save button), add a new `<form>` block:

```html
<!-- ── Stripe Integration ────────────────────────────────────────── -->
<form @submit.prevent="submitStripe" class="max-w-2xl mt-8 space-y-6">

    <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
        <div class="px-5 py-4 flex items-center gap-2">
            <CreditCard class="w-4 h-4 text-neutral-400 shrink-0" />
            <div>
                <h3 class="text-sm font-semibold text-neutral-900">Stripe Integration</h3>
                <p class="text-xs text-neutral-500 mt-0.5">
                    Connect your Stripe account to accept online donations.
                    Get your keys at
                    <a href="https://dashboard.stripe.com/apikeys" target="_blank"
                       class="text-brand-500 underline hover:text-brand-700">dashboard.stripe.com/apikeys</a>.
                </p>
            </div>
        </div>

        <div class="p-5 space-y-4">
            <AppInput
                id="publishable_key"
                v-model="stripeForm.stripe_publishable_key"
                label="Publishable Key"
                placeholder="pk_live_..."
                :error="(stripeForm.errors as any).stripe_publishable_key"
            />

            <div>
                <AppInput
                    id="secret_key"
                    v-model="stripeForm.stripe_secret_key"
                    label="Secret Key"
                    type="password"
                    :placeholder="props.settings.stripe_has_secret_key
                        ? '(saved — leave blank to keep existing)'
                        : 'sk_live_...'"
                    :error="(stripeForm.errors as any).stripe_secret_key"
                />
                <p class="text-xs text-neutral-400 mt-1">
                    <span v-if="props.settings.stripe_has_secret_key" class="text-emerald-600 font-medium">✓ Key saved.</span>
                    Leave blank to keep existing key.
                </p>
            </div>

            <div>
                <AppInput
                    id="webhook_secret"
                    v-model="stripeForm.stripe_webhook_secret"
                    label="Webhook Signing Secret"
                    type="password"
                    :placeholder="props.settings.stripe_has_webhook_secret
                        ? '(saved — leave blank to keep existing)'
                        : 'whsec_...'"
                    :error="(stripeForm.errors as any).stripe_webhook_secret"
                />
                <p class="text-xs text-neutral-400 mt-1">
                    <span v-if="props.settings.stripe_has_webhook_secret" class="text-emerald-600 font-medium">✓ Secret saved.</span>
                    In Stripe Dashboard → Developers → Webhooks → Add endpoint.
                    Register URL: <code class="text-[11px] bg-neutral-100 px-1.5 py-0.5 rounded font-mono">{{ webhookUrl }}</code>
                    · Event: <code class="text-[11px] bg-neutral-100 px-1.5 py-0.5 rounded font-mono">checkout.session.completed</code>
                </p>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between pt-1">
        <p v-if="stripeForm.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
        <span v-else />
        <AppButton type="submit" :loading="stripeForm.processing">Save Stripe settings</AppButton>
    </div>
</form>
```

- [ ] **Step 4: Type-check**

```bash
npm run type-check
```

Expected: no new errors in `Donations.vue`.

---

## Task 7: Create `GiveController`

**Files:**
- Create: `app/Http/Controllers/Public/GiveController.php`

- [ ] **Step 1: Create the file**

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class GiveController extends Controller
{
    /**
     * POST /give/checkout
     *
     * Creates a Stripe Checkout Session for the selected fund + amount.
     * Returns JSON { url } which the frontend uses to redirect the browser.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fund_id'      => ['required', 'string', 'max:50'],
            'amount_cents' => ['required', 'integer', 'min:100', 'max:99999900'],
        ]);

        $church           = app('church');
        $donationSettings = $church?->settings['donations'] ?? [];

        $rawSecretKey = $donationSettings['stripe_secret_key'] ?? null;
        if (! $rawSecretKey) {
            return response()->json(['error' => 'Online giving is not configured for this church.'], 422);
        }

        try {
            $secretKey = Crypt::decryptString($rawSecretKey);
        } catch (\Throwable) {
            return response()->json(['error' => 'Online giving configuration error.'], 422);
        }

        Stripe::setApiKey($secretKey);

        // Resolve the fund name for the Stripe line item
        $funds    = $donationSettings['funds'] ?? [];
        $fund     = collect($funds)->firstWhere('id', $validated['fund_id']);
        $fundName = $fund['name'] ?? 'Donation';

        $session = Session::create([
            'mode'              => 'payment',
            'customer_creation' => 'if_required',
            'line_items'        => [[
                'price_data' => [
                    'currency'     => 'usd',
                    'unit_amount'  => $validated['amount_cents'],
                    'product_data' => [
                        'name' => $fundName . ' — ' . ($church?->name ?? 'Church'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'metadata'    => [
                'church_id' => $church?->id,
                'fund_id'   => $validated['fund_id'],
            ],
            'success_url' => url('/give/thank-you') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => url('/give'),
        ]);

        return response()->json(['url' => $session->url]);
    }

    /**
     * GET /give/thank-you
     *
     * Shown after Stripe redirects back on a successful payment.
     * Stripe passes ?session_id=... but we don't need it — the webhook already
     * confirmed and recorded the donation server-side.
     */
    public function success(): Response
    {
        return Inertia::render('Public/GiveSuccess');
    }
}
```

---

## Task 8: Create `StripeWebhookController`

**Files:**
- Create: `app/Http/Controllers/Webhook/StripeWebhookController.php`

- [ ] **Step 1: Create the directory (if not exists) and file**

```bash
mkdir -p app/Http/Controllers/Webhook
```

Create `app/Http/Controllers/Webhook/StripeWebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Stripe\Event;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        // Parse the event payload WITHOUT signature verification first.
        // We need church_id from metadata to look up the right webhook secret.
        $data = json_decode($payload, true);
        if (! $data) {
            return response('Invalid payload', 400);
        }

        $event = Event::constructFrom($data);

        // Only handle completed checkout sessions
        if ($event->type !== 'checkout.session.completed') {
            return response('Ignored', 200);
        }

        $session  = $event->data->object;
        $churchId = $session->metadata->church_id ?? null;

        if (! $churchId) {
            return response('No church context', 200);
        }

        $church = Church::find((int) $churchId);
        if (! $church) {
            return response('Church not found', 200);
        }

        // Retrieve + decrypt the church's webhook signing secret
        $rawSecret = $church->settings['donations']['stripe_webhook_secret'] ?? null;
        if (! $rawSecret) {
            // Webhook not yet configured — skip signature check to avoid silent failures
            // during initial Stripe setup; log for visibility
            logger()->warning('Stripe webhook received but no webhook secret configured', [
                'church_id' => $church->id,
            ]);
            return response('Webhook secret not configured', 200);
        }

        try {
            $webhookSecret = Crypt::decryptString($rawSecret);
            // This throws SignatureVerificationException if invalid
            Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Stripe\Exception\SignatureVerificationException) {
            return response('Invalid signature', 400);
        } catch (\Throwable $e) {
            logger()->error('Stripe webhook verification failed', ['error' => $e->getMessage()]);
            return response('Configuration error', 400);
        }

        // Idempotent: skip if already recorded (duplicate Stripe delivery)
        Donation::firstOrCreate(
            ['stripe_session_id' => $session->id],
            [
                'church_id'    => $church->id,
                'fund_id'      => $session->metadata->fund_id ?? 'general',
                'amount_cents' => $session->amount_total   ?? 0,
                'currency'     => $session->currency       ?? 'usd',
                'donor_email'  => $session->customer_details?->email ?? null,
                'donor_name'   => $session->customer_details?->name  ?? null,
                'status'       => 'completed',
            ]
        );

        return response('OK', 200);
    }
}
```

---

## Task 9: Register new routes

**Files:**
- Modify: `routes/web.php`
- Modify: `routes/api.php`

### `routes/web.php`

- [ ] **Step 1: Add `GiveController` import**

At the top of `routes/web.php`, in the existing `use` imports block, add:

```php
use App\Http\Controllers\Public\GiveController;
```

- [ ] **Step 2: Add give routes inside the `privacy` middleware group**

Find the `Route::middleware('privacy')->group(function () {` block. Currently it ends with:
```php
    Route::get('/live', LivestreamController::class)->name('livestream');
});
```

Replace that closing section with:
```php
    Route::get('/live', LivestreamController::class)->name('livestream');

    // Stripe Checkout
    Route::post('/give/checkout',  [GiveController::class, 'checkout'])->name('give.checkout');
    Route::get('/give/thank-you',  [GiveController::class, 'success'])->name('give.success');
});
```

### `routes/api.php`

- [ ] **Step 3: Add webhook route outside the authenticated `v1` group**

Open `routes/api.php`. Add the webhook route BEFORE the existing `Route::prefix('v1')` block:

```php
use App\Http\Controllers\Webhook\StripeWebhookController;

// Stripe webhook — no authentication, no CSRF (called by Stripe's servers)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');
```

The API middleware group (`api`) does not include CSRF verification, so no additional exclusion is needed in `bootstrap/app.php`.

---

## Task 10: Modify `Donate.vue` — replace "coming soon" panel

**Files:**
- Modify: `resources/js/Pages/Public/Donate.vue`

- [ ] **Step 1: Update props and add script logic**

In `<script setup lang="ts">`, find the existing:
```ts
defineProps<{
    funds: DonationFund[]
    suggestedAmounts: number[]
}>()
```

Replace with:
```ts
const props = defineProps<{
    funds:          DonationFund[]
    suggestedAmounts: number[]
    stripeEnabled:  boolean
}>()
```

**Note:** `ref` and `computed` are already imported at the top of the existing `Donate.vue` — do NOT add duplicate imports.

After the existing reactive state (`selectedFund`, `selectedAmount`, `customAmount`), add:

```ts
const checkingOut = ref(false)
const checkoutError = ref<string | null>(null)

// The active fund object for display
const activeFund = computed(() =>
    props.funds.find(f => f.id === selectedFund.value) ?? props.funds[0] ?? null
)

// Dollar amount shown (custom input takes precedence over preset button)
const displayAmount = computed(() => {
    const custom = parseFloat(customAmount.value)
    return isNaN(custom) || custom <= 0 ? selectedAmount.value : custom
})

// Amount in cents for the API call (Stripe requires integers)
const amountCents = computed(() => Math.round(displayAmount.value * 100))

async function startCheckout() {
    if (amountCents.value < 100) return // Stripe minimum $1
    checkingOut.value    = true
    checkoutError.value  = null

    try {
        const resp = await fetch('/give/checkout', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({
                fund_id:      selectedFund.value,
                amount_cents: amountCents.value,
            }),
        })

        const data = await resp.json()

        if (!resp.ok || !data.url) {
            checkoutError.value = data.error ?? 'Something went wrong. Please try again.'
            return
        }

        window.location.href = data.url
    } catch {
        checkoutError.value = 'Network error. Please check your connection and try again.'
    } finally {
        checkingOut.value = false
    }
}
```

- [ ] **Step 2: Replace the "coming soon" panel in the template**

Find this block in `<template>`:
```html
<!-- Online giving — coming soon -->
<div class="reveal reveal-delay-2">
    <div class="bg-neutral-50 border border-neutral-100 rounded-2xl p-7 flex flex-col items-center text-center">
        ...
        <AppButton href="/contact" variant="primary">
            Contact Us
        </AppButton>
    </div>
</div>
```

Replace the entire block with:

```html
<!-- Payment panel -->
<div class="reveal reveal-delay-2">

    <!-- Stripe configured — show payment form -->
    <template v-if="stripeEnabled">
        <h2 class="text-xl font-semibold text-neutral-900 mb-2">Complete Your Gift</h2>
        <p class="text-sm text-neutral-500 mb-6">
            Giving to: <strong>{{ activeFund?.name ?? 'General Fund' }}</strong>
        </p>

        <!-- Suggested amounts -->
        <div class="mb-5">
            <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-3">Select an amount</p>
            <div class="grid grid-cols-3 gap-2 mb-3">
                <button
                    v-for="amt in suggestedAmounts"
                    :key="amt"
                    type="button"
                    class="py-2.5 rounded-xl border text-sm font-semibold transition-all duration-150"
                    :class="selectedAmount === amt && !customAmount
                        ? 'border-brand-400 bg-brand-50 text-brand-700 ring-2 ring-brand-300/30'
                        : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:bg-neutral-50'"
                    @click="selectedAmount = amt; customAmount = ''"
                >
                    ${{ amt }}
                </button>
            </div>
            <input
                v-model="customAmount"
                type="number"
                min="1"
                step="1"
                placeholder="Other amount ($)"
                class="w-full px-3.5 py-2.5 text-sm border border-neutral-200 rounded-xl focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-300/20 placeholder:text-neutral-400"
                @focus="selectedAmount = 0"
            />
        </div>

        <!-- Amount summary -->
        <div class="bg-brand-50 border border-brand-100 rounded-xl p-4 mb-5 flex justify-between items-center">
            <span class="text-sm text-neutral-600">Your gift</span>
            <span class="text-2xl font-bold text-brand-700">${{ displayAmount.toFixed(2) }}</span>
        </div>

        <!-- Error message -->
        <p v-if="checkoutError" class="text-sm text-rose-600 mb-3 text-center">{{ checkoutError }}</p>

        <AppButton
            variant="primary"
            size="lg"
            class="w-full"
            type="button"
            :loading="checkingOut"
            :disabled="amountCents < 100"
            @click="startCheckout"
        >
            <Heart class="w-4 h-4 mr-2" />
            Give Now — Secure Checkout
        </AppButton>
        <p class="text-xs text-neutral-400 text-center mt-3">
            Secured by Stripe · 256-bit SSL encryption
        </p>
    </template>

    <!-- Stripe not yet configured — show placeholder -->
    <template v-else>
        <div class="bg-neutral-50 border border-neutral-100 rounded-2xl p-7 flex flex-col items-center text-center">
            <div class="w-14 h-14 rounded-2xl bg-brand-50 flex items-center justify-center mb-4">
                <Heart class="w-7 h-7 text-brand-500" />
            </div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-600 text-xs font-semibold mb-3">
                Online Giving — Coming Soon
            </span>
            <h3 class="text-base font-semibold text-neutral-900 mb-2">Give Online</h3>
            <p class="text-sm text-neutral-500 leading-relaxed mb-6 max-w-xs">
                Online giving is being set up. In the meantime, please give in person
                during a service or contact us for bank transfer details.
            </p>
            <AppButton href="/contact" variant="primary">
                Contact Us
            </AppButton>
        </div>
    </template>

</div>
```

- [ ] **Step 3: Type-check**

```bash
npm run type-check
```

Expected: no new errors in `Donate.vue`.

---

## Task 11: Create `GiveSuccess.vue`

**Files:**
- Create: `resources/js/Pages/Public/GiveSuccess.vue`

- [ ] **Step 1: Create the file**

```vue
<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { useChurch } from '@/composables/useChurch'
import { CheckCircle } from 'lucide-vue-next'

const { church } = useChurch()
</script>

<template>
    <PublicLayout
        title="Thank You"
        description="Your gift has been received. Thank you for your generosity."
    >
        <div class="min-h-[70vh] flex items-center justify-center bg-white px-6 py-24">
            <div class="text-center max-w-md reveal">

                <div class="w-20 h-20 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-6">
                    <CheckCircle class="w-10 h-10 text-emerald-500" />
                </div>

                <h1 class="text-3xl font-serif font-normal text-neutral-900 mb-3 leading-tight">
                    Thank you for your generosity!
                </h1>

                <p class="text-neutral-500 leading-relaxed mb-8">
                    Your gift to {{ church.name }} has been received and will be put to work
                    for the ministry. You'll receive a receipt at your email shortly.
                </p>

                <div class="flex gap-3 justify-center">
                    <AppButton href="/give" variant="outline">Give Again</AppButton>
                    <AppButton href="/" variant="primary">Return Home</AppButton>
                </div>

            </div>
        </div>
    </PublicLayout>
</template>
```

- [ ] **Step 2: Type-check**

```bash
npm run type-check
```

Expected: no new errors in `GiveSuccess.vue`.

---

## Task 12: Final verification

- [ ] **Step 1: Full type-check**

```bash
npm run type-check
```

Expected: zero new errors (there were 35 pre-existing errors from VueUse/socket.io/paginator — those are unchanged and acceptable).

- [ ] **Step 2: Browser — Settings → Donations**

1. Open `Dashboard → Settings → Donations`
2. Scroll to the new Stripe Integration card
3. Enter a Stripe test publishable key (`pk_test_...`) and secret key (`sk_test_...`) and a test webhook secret (`whsec_...`)
4. Click "Save Stripe settings"
5. Confirm the "✓ Saved" flash appears
6. Reload the page — confirm the publishable key field is pre-filled and the "✓ Key saved" / "✓ Secret saved" indicators appear

- [ ] **Step 3: Browser — public Give page (Stripe configured)**

1. Open `/give`
2. Confirm the right-side panel shows the amount picker + "Give Now" button (not "Coming Soon")
3. Select a fund and an amount
4. Click "Give Now — Secure Checkout"
5. Confirm the browser redirects to `checkout.stripe.com` (Stripe's hosted page)
6. Complete the test payment using Stripe test card `4242 4242 4242 4242` · any future expiry · any CVC
7. Confirm redirect to `/give/thank-you` with the success screen

- [ ] **Step 4: Browser — public Give page (Stripe not configured)**

1. On a church with no Stripe keys, open `/give`
2. Confirm the "Online Giving — Coming Soon" panel is shown (no regression)

- [ ] **Step 5: Verify webhook (optional — requires Stripe CLI)**

```bash
# Install Stripe CLI if not present: https://stripe.com/docs/stripe-cli
stripe listen --forward-to localhost/api/stripe/webhook
# Trigger a test event
stripe trigger checkout.session.completed
```

Expected: webhook handler responds `200 OK`; a row appears in the `donations` table.
```bash
php artisan tinker --execute="echo App\Models\Donation::latest()->first()?->toJson(JSON_PRETTY_PRINT);"
```
