# Customization Gaps Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all 6 SaaS customization gaps so church admins can control copy, section visibility, donation amounts, social icons, and branding text from the dashboard without touching code.

**Architecture:** All copy overrides flow through the existing `Church.settings` JSON bag (Laravel) → `ChurchSettingsController` (persist + read) → public controllers (pass as Inertia props) → Vue components (render with neutral fallbacks). Section visibility flags follow the same path. Social icons only require a frontend change since `church.socials` already stores TikTok/LinkedIn/Spotify URLs. `config/church.php` defaults are neutralized to null/empty.

**Tech Stack:** Laravel 11 · Vue 3 + TypeScript + Inertia.js · Tailwind CSS

**Verification commands:** `npm run type-check` (TypeScript) · `php artisan test --filter=ChurchSettingsContentTest` (backend)

> ⚠️ **NO git commands** — never run git add, git commit, or git push. Verify only via `npm run type-check` and `php artisan test`.

---

## File Structure

**Modified files:**
- `config/church.php` — remove COP-specific fallback data
- `app/Http/Controllers/Dashboard/ChurchSettingsController.php` — extend `donations()`, `updateDonations()`, `aboutContent()`, `updateAboutContent()`, `homepage()`, `updateHomepage()`
- `app/Http/Controllers/Public/DonationController.php` — read `suggested_amounts` from settings
- `app/Http/Controllers/Public/AboutController.php` — pass `heroTitle`, `heroEyebrow`, `heroSubtitleOverride`, `leadershipSubtitle`
- `app/Http/Controllers/Public/SermonsController.php` — pass `sermonsSubtitle`
- `app/Http/Controllers/Public/HomeController.php` — pass copy strings + `sectionVisibility`
- `resources/js/Pages/Public/About.vue` — use dynamic hero + leadership props
- `resources/js/Pages/Public/Sermons.vue` — use dynamic subtitle prop
- `resources/js/Pages/Public/Home.vue` — use dynamic copy + visibility gates
- `resources/js/Components/Navigation/AppFooter.vue` — add TikTok/LinkedIn/Spotify icons
- `resources/js/Pages/Dashboard/Settings/AboutContent.vue` — add hero copy fields
- `resources/js/Pages/Dashboard/Settings/Donations.vue` — add suggested amounts card
- `resources/js/Pages/Dashboard/Settings/Homepage.vue` — add copy fields + visibility toggles

**Created files:**
- `tests/Feature/ChurchSettingsContentTest.php` — feature tests for tasks 3–6

---

### Task 1: Neutralize config/church.php defaults

**Files:**
- Modify: `config/church.php`

The file currently hard-codes Church of Pentecost stats (160+ nations, 5.4M members, 1953 founding), address, phone, and email. Any new church that hasn't configured their settings will display COP's data on their public homepage. Neutralize all to null/empty so unconfigured tenants see nothing rather than wrong data.

- [ ] **Step 1: Replace config/church.php with neutral defaults**

Open `config/church.php` and replace the entire file contents with:

```php
<?php

return [
    'name'          => env('CHURCH_NAME', null),
    'tagline'       => env('CHURCH_TAGLINE', null),
    'logo'          => env('CHURCH_LOGO', null),
    'primary_color' => env('CHURCH_PRIMARY_COLOR', '#6366f1'),
    'address'       => env('CHURCH_ADDRESS', null),
    'phone'         => env('CHURCH_PHONE', null),
    'email'         => env('CHURCH_EMAIL', null),
    'socials' => [
        'facebook'  => env('CHURCH_FACEBOOK'),
        'instagram' => env('CHURCH_INSTAGRAM'),
        'youtube'   => env('CHURCH_YOUTUBE'),
        'twitter'   => env('CHURCH_TWITTER'),
    ],
    'service_times' => [],

    /*
    |--------------------------------------------------------------------------
    | Homepage Stats
    |--------------------------------------------------------------------------
    | Leave empty — each church configures their own stats via
    | Settings → Homepage. An empty array hides the stats banner entirely.
    */
    'stats' => [],
];
```

- [ ] **Step 2: Verify type-check passes**

Run: `npm run type-check`
Expected: same 35 pre-existing errors, none in any modified file.

---

### Task 2: Social platform icons in AppFooter.vue

**Files:**
- Modify: `resources/js/Components/Navigation/AppFooter.vue`

`church.socials.tiktok`, `.linkedin`, and `.spotify` are already saved by Settings → Social Media and shared globally via `HandleInertiaRequests`. AppNav has no social icons — they live only in AppFooter. Lucide Vue Next ships `Linkedin`. TikTok and Spotify need inline SVG since Lucide doesn't have brand icons.

- [ ] **Step 1: Add Linkedin to the Lucide import**

In `resources/js/Components/Navigation/AppFooter.vue`, find:
```ts
import { MapPin, Phone, Mail, Facebook, Instagram, Youtube, Twitter } from 'lucide-vue-next'
```
Replace with:
```ts
import { MapPin, Phone, Mail, Facebook, Instagram, Youtube, Twitter, Linkedin } from 'lucide-vue-next'
```

- [ ] **Step 2: Add TikTok, LinkedIn, and Spotify link blocks after the Twitter link**

In the footer template, find the Twitter link block:
```html
<a
    v-if="church.socials.twitter"
    :href="church.socials.twitter"
    target="_blank" rel="noopener"
    class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
    aria-label="Twitter / X"
>
    <Twitter class="w-3.5 h-3.5" />
</a>
```

Add these three blocks immediately after it (before the closing `</div>` of the socials row):

```html
<a
    v-if="church.socials.tiktok"
    :href="church.socials.tiktok"
    target="_blank" rel="noopener"
    class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
    aria-label="TikTok"
>
    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
    </svg>
</a>
<a
    v-if="church.socials.linkedin"
    :href="church.socials.linkedin"
    target="_blank" rel="noopener"
    class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
    aria-label="LinkedIn"
>
    <Linkedin class="w-3.5 h-3.5" />
</a>
<a
    v-if="church.socials.spotify"
    :href="church.socials.spotify"
    target="_blank" rel="noopener"
    class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
    aria-label="Spotify"
>
    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
    </svg>
</a>
```

- [ ] **Step 3: Verify type-check passes**

Run: `npm run type-check`
Expected: 35 pre-existing errors only, none in AppFooter.vue.

---

### Task 3: Suggested donation amounts in Settings → Donations

**Files:**
- Modify: `app/Http/Controllers/Dashboard/ChurchSettingsController.php` (lines 452–484)
- Modify: `app/Http/Controllers/Public/DonationController.php`
- Modify: `resources/js/Pages/Dashboard/Settings/Donations.vue`

`DonationController` currently hard-codes `[25, 50, 100, 250, 500]`. These amounts are wrong for non-USD currencies and non-Western contexts. Move them to `settings.donations.suggested_amounts`.

- [ ] **Step 1: Create ChurchSettingsContentTest.php with the suggested amounts test**

Create `tests/Feature/ChurchSettingsContentTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchSettingsContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Creates a church + church_admin user, binds the church as the resolved tenant,
     * and authenticates as that user. Returns [$church, $user].
     */
    private function makeChurchAdmin(): array
    {
        $church = Church::factory()->create([
            'name'      => 'Test Church',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['church_id' => $church->id]);
        $user->assignRole('church_admin');

        // Bypass subdomain-based ResolveTenant middleware in tests
        $this->app->instance('church',    $church);
        $this->app->instance('church.id', $church->id);

        $this->actingAs($user);

        return [$church, $user];
    }

    // ── Task 3: Suggested donation amounts ──────────────────────────────────────

    public function test_suggested_amounts_are_saved_to_donations_settings(): void
    {
        [$church] = $this->makeChurchAdmin();

        $response = $this->put('/dashboard/settings/donations', [
            'funds'             => [],
            'suggested_amounts' => [10, 25, 50, 100],
        ]);

        $response->assertRedirect();
        $church->refresh();
        $this->assertSame([10, 25, 50, 100], $church->settings['donations']['suggested_amounts']);
    }

    public function test_suggested_amounts_reject_non_integers(): void
    {
        $this->makeChurchAdmin();

        $response = $this->put('/dashboard/settings/donations', [
            'funds'             => [],
            'suggested_amounts' => ['not-a-number', 50],
        ]);

        $response->assertSessionHasErrors('suggested_amounts.0');
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

Run: `php artisan test --filter=ChurchSettingsContentTest::test_suggested_amounts_are_saved`
Expected: FAIL — validation does not yet accept `suggested_amounts`.

- [ ] **Step 3: Update ChurchSettingsController::donations() to include suggested_amounts**

In `ChurchSettingsController.php` at the `donations()` method, replace the `Inertia::render()` return:
```php
        return Inertia::render('Dashboard/Settings/Donations', [
            'settings' => [
                'funds'                     => $settings['funds'] ?? [],
                'stripe_publishable_key'    => $settings['stripe_publishable_key'] ?? null,
                'stripe_has_secret_key'     => ! empty($settings['stripe_secret_key']),
                'stripe_has_webhook_secret' => ! empty($settings['stripe_webhook_secret']),
            ],
        ]);
```
With:
```php
        return Inertia::render('Dashboard/Settings/Donations', [
            'settings' => [
                'funds'                     => $settings['funds']              ?? [],
                'suggested_amounts'         => $settings['suggested_amounts']  ?? [25, 50, 100, 250, 500],
                'stripe_publishable_key'    => $settings['stripe_publishable_key']    ?? null,
                'stripe_has_secret_key'     => ! empty($settings['stripe_secret_key']),
                'stripe_has_webhook_secret' => ! empty($settings['stripe_webhook_secret']),
            ],
        ]);
```

- [ ] **Step 4: Update ChurchSettingsController::updateDonations() to validate and save suggested_amounts**

In `updateDonations()`, replace the validation + saveSettings block:
```php
        $validated = $request->validate([
            'funds'               => ['nullable', 'array', 'max:10'],
            'funds.*.id'          => ['required', 'string', 'max:50'],
            'funds.*.name'        => ['required', 'string', 'max:100'],
            'funds.*.description' => ['nullable', 'string', 'max:300'],
            'funds.*.icon'        => ['nullable', 'string', 'max:30'],
        ]);

        $this->saveSettings('donations', [
            'funds' => $validated['funds'] ?? [],
        ]);
```
With:
```php
        $validated = $request->validate([
            'funds'               => ['nullable', 'array', 'max:10'],
            'funds.*.id'          => ['required', 'string', 'max:50'],
            'funds.*.name'        => ['required', 'string', 'max:100'],
            'funds.*.description' => ['nullable', 'string', 'max:300'],
            'funds.*.icon'        => ['nullable', 'string', 'max:30'],
            'suggested_amounts'   => ['nullable', 'array', 'max:8'],
            'suggested_amounts.*' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $this->saveSettings('donations', [
            'funds'             => $validated['funds']             ?? [],
            'suggested_amounts' => $validated['suggested_amounts'] ?? [25, 50, 100, 250, 500],
        ]);
```

- [ ] **Step 5: Update DonationController to read suggested_amounts from settings**

In `app/Http/Controllers/Public/DonationController.php`, replace:
```php
        return Inertia::render('Public/Donate', [
            'funds'            => $funds,
            'suggestedAmounts' => [25, 50, 100, 250, 500],
            'stripeEnabled'    => ! empty($donationSettings['stripe_secret_key']),
        ]);
```
With:
```php
        return Inertia::render('Public/Donate', [
            'funds'            => $funds,
            'suggestedAmounts' => $donationSettings['suggested_amounts'] ?? [25, 50, 100, 250, 500],
            'stripeEnabled'    => ! empty($donationSettings['stripe_secret_key']),
        ]);
```

- [ ] **Step 6: Update Settings interface in Settings/Donations.vue**

In `resources/js/Pages/Dashboard/Settings/Donations.vue`, update the `Settings` interface (add `suggested_amounts`):
```ts
interface Settings {
    funds:                      DonationFund[]
    suggested_amounts:          number[]
    stripe_publishable_key:     string | null
    stripe_has_secret_key:      boolean
    stripe_has_webhook_secret:  boolean
}
```

Update `useForm` to include `suggested_amounts` (find the existing `useForm` call and add the field):
```ts
const form = useForm({
    funds:             props.settings.funds.map(f => ({ ...f })) as DonationFund[],
    suggested_amounts: [...(props.settings.suggested_amounts ?? [25, 50, 100, 250, 500])] as number[],
})
```

Add helper functions after `removeFund` (find `function removeFund`):
```ts
function addAmount() {
    if (form.suggested_amounts.length < 8) form.suggested_amounts.push(0)
}
function removeAmount(i: number) {
    form.suggested_amounts.splice(i, 1)
}
```

- [ ] **Step 7: Add the Suggested Amounts card to the Settings/Donations.vue template**

In the template, find the closing `<div class="flex items-center justify-between pt-1">` save-button row inside the funds form. Add this card immediately before that save-button row:

```html
<!-- ── Suggested Amounts ──────────────────────────────────────────────────── -->
<div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
    <div class="px-5 py-4 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-neutral-900">Suggested amounts</h3>
            <p class="text-xs text-neutral-500 mt-0.5">
                Quick-select amounts on the Give page. Up to 8 values.
                Leave empty to use defaults (25, 50, 100, 250, 500).
            </p>
        </div>
        <AppButton type="button" variant="outline" size="sm" @click="addAmount" :disabled="form.suggested_amounts.length >= 8">
            <Plus class="w-3.5 h-3.5" />
            Add
        </AppButton>
    </div>
    <div class="p-5">
        <div v-if="form.suggested_amounts.length === 0" class="text-sm text-neutral-400 text-center py-3">
            No custom amounts — defaults will be used.
        </div>
        <div v-else class="flex flex-wrap gap-2">
            <div
                v-for="(amt, i) in form.suggested_amounts"
                :key="i"
                class="flex items-center gap-1 bg-neutral-50 border border-neutral-200 rounded-lg px-2.5 py-1.5"
            >
                <span class="text-xs text-neutral-400 select-none">$</span>
                <input
                    v-model.number="form.suggested_amounts[i]"
                    type="number"
                    min="1"
                    max="1000000"
                    class="w-16 text-sm font-medium text-neutral-900 bg-transparent focus:outline-none"
                    :aria-label="`Amount ${i + 1}`"
                />
                <button
                    type="button"
                    @click="removeAmount(i)"
                    class="text-neutral-400 hover:text-rose-600 transition-colors"
                    :aria-label="`Remove amount`"
                >
                    <Trash2 class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>
        <p v-if="(form.errors as any)['suggested_amounts']" class="text-xs text-rose-500 mt-2">
            {{ (form.errors as any)['suggested_amounts'] }}
        </p>
    </div>
</div>
```

- [ ] **Step 8: Run tests**

Run: `php artisan test --filter=ChurchSettingsContentTest`
Expected: `test_suggested_amounts_are_saved_to_donations_settings` PASS, `test_suggested_amounts_reject_non_integers` PASS.

- [ ] **Step 9: Verify type-check**

Run: `npm run type-check`
Expected: 35 pre-existing errors only, none in Donations.vue.

---

### Task 4: About page hero + leadership copy in Settings → About Content

**Files:**
- Modify: `app/Http/Controllers/Dashboard/ChurchSettingsController.php` (lines 921–956)
- Modify: `app/Http/Controllers/Public/AboutController.php`
- Modify: `resources/js/Pages/Public/About.vue`
- Modify: `resources/js/Pages/Dashboard/Settings/AboutContent.vue`

Currently `About.vue` hard-codes `eyebrow="Our Story"`, `title="Built on faith. Grown in love."`, and the leadership subtitle. These are added as optional overrides in `settings.about`.

- [ ] **Step 1: Add the about hero copy test to ChurchSettingsContentTest.php**

Append to `tests/Feature/ChurchSettingsContentTest.php`:

```php
    // ── Task 4: About page hero copy ────────────────────────────────────────────

    public function test_about_hero_copy_is_saved_to_settings(): void
    {
        [$church] = $this->makeChurchAdmin();

        $response = $this->put('/dashboard/settings/about-content', [
            'team'                => [],
            'values'              => [],
            'hero_title'          => 'We exist to glorify God.',
            'hero_eyebrow'        => 'Our Story',
            'hero_subtitle'       => 'Founded in 2010, we are a community of believers.',
            'leadership_subtitle' => 'Our leaders serve with humility.',
        ]);

        $response->assertRedirect();
        $church->refresh();
        $this->assertSame('We exist to glorify God.',      $church->settings['about']['hero_title']);
        $this->assertSame('Our leaders serve with humility.', $church->settings['about']['leadership_subtitle']);
    }
```

- [ ] **Step 2: Run test to confirm it fails**

Run: `php artisan test --filter=ChurchSettingsContentTest::test_about_hero_copy_is_saved`
Expected: FAIL — fields not yet validated or saved.

- [ ] **Step 3: Update ChurchSettingsController::aboutContent() to pass the 4 hero copy fields**

In `ChurchSettingsController.php`, replace the entire `aboutContent()` method:
```php
    /** GET /dashboard/settings/about-content */
    public function aboutContent(): Response
    {
        $this->authorizeSettings();
        $settings = $this->getSettings('about');

        return Inertia::render('Dashboard/Settings/AboutContent', [
            'settings' => [
                'team'                => $settings['team']                ?? [],
                'values'              => $settings['values']              ?? [],
                'hero_title'          => $settings['hero_title']          ?? null,
                'hero_eyebrow'        => $settings['hero_eyebrow']        ?? null,
                'hero_subtitle'       => $settings['hero_subtitle']       ?? null,
                'leadership_subtitle' => $settings['leadership_subtitle'] ?? null,
            ],
        ]);
    }
```

- [ ] **Step 4: Update ChurchSettingsController::updateAboutContent() to validate and save the 4 fields**

In `ChurchSettingsController.php`, replace the entire `updateAboutContent()` method:
```php
    /** PUT /dashboard/settings/about-content */
    public function updateAboutContent(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'team'                  => ['nullable', 'array', 'max:20'],
            'team.*.name'           => ['required', 'string', 'max:100'],
            'team.*.role'           => ['required', 'string', 'max:100'],
            'team.*.bio'            => ['nullable', 'string', 'max:500'],
            'team.*.image'          => ['nullable', 'url', 'max:500'],
            'values'                => ['nullable', 'array', 'max:12'],
            'values.*.title'        => ['required', 'string', 'max:60'],
            'values.*.description'  => ['nullable', 'string', 'max:300'],
            'hero_title'            => ['nullable', 'string', 'max:120'],
            'hero_eyebrow'          => ['nullable', 'string', 'max:60'],
            'hero_subtitle'         => ['nullable', 'string', 'max:300'],
            'leadership_subtitle'   => ['nullable', 'string', 'max:200'],
        ]);

        $this->saveSettings('about', [
            'team'                => $validated['team']                ?? [],
            'values'              => $validated['values']              ?? [],
            'hero_title'          => $validated['hero_title']          ?? null,
            'hero_eyebrow'        => $validated['hero_eyebrow']        ?? null,
            'hero_subtitle'       => $validated['hero_subtitle']       ?? null,
            'leadership_subtitle' => $validated['leadership_subtitle'] ?? null,
        ]);

        return back()->with('success', 'About page content saved.');
    }
```

- [ ] **Step 5: Update AboutController to pass the 4 new props**

In `app/Http/Controllers/Public/AboutController.php`, replace `__invoke()`:
```php
    public function __invoke(): Response
    {
        $church        = app('church');
        $aboutSettings = $church?->settings['about'] ?? [];

        return Inertia::render('Public/About', [
            'mission'              => $church?->mission      ?? null,
            'vision'               => $church?->vision       ?? null,
            'description'          => $church?->description  ?? null,
            'foundedYear'          => $church?->founded_year ?? null,
            'team'                 => $aboutSettings['team']                ?? $this->defaultTeam(),
            'values'               => $aboutSettings['values']              ?? $this->defaultValues(),
            'heroTitle'            => $aboutSettings['hero_title']          ?? null,
            'heroEyebrow'          => $aboutSettings['hero_eyebrow']        ?? null,
            'heroSubtitleOverride' => $aboutSettings['hero_subtitle']       ?? null,
            'leadershipSubtitle'   => $aboutSettings['leadership_subtitle'] ?? null,
        ]);
    }
```

- [ ] **Step 6: Update About.vue — add 4 new props to defineProps**

In `resources/js/Pages/Public/About.vue`, replace the existing `defineProps` block:
```ts
const props = defineProps<{
    mission:     string | null
    vision:      string | null
    description: string | null
    foundedYear: number | null
    team:        TeamMember[]
    values:      ChurchValue[]
}>()
```
With:
```ts
const props = defineProps<{
    mission:              string | null
    vision:               string | null
    description:          string | null
    foundedYear:          number | null
    team:                 TeamMember[]
    values:               ChurchValue[]
    heroTitle:            string | null
    heroEyebrow:          string | null
    heroSubtitleOverride: string | null
    leadershipSubtitle:   string | null
}>()
```

- [ ] **Step 7: Update About.vue — fix heroSubtitle computed to use override + neutral fallback**

In `About.vue`, replace the `heroSubtitle` computed:
```ts
const heroSubtitle = computed(() => {
    const year = props.foundedYear ? `Since ${props.foundedYear}, a` : 'A'
    return `${year} community where lives are changed, families are strengthened, and the hope of the gospel is shared.`
})
```
With:
```ts
const heroSubtitle = computed(() => {
    if (props.heroSubtitleOverride) return props.heroSubtitleOverride
    const year = props.foundedYear ? `Since ${props.foundedYear}, a` : 'A'
    return `${year} community united in faith, growing and serving together.`
})
```

- [ ] **Step 8: Update About.vue — make PageHero title and eyebrow dynamic**

In the template, find:
```html
eyebrow="Our Story"
title="Built on faith. Grown in love."
```
Replace with:
```html
:eyebrow="heroEyebrow ?? 'Our Story'"
:title="heroTitle ?? church.name"
```

- [ ] **Step 9: Update About.vue — make leadership subtitle dynamic**

In the template, find:
```html
subtitle="Servant leaders committed to guiding and growing our community with wisdom and grace."
```
Replace with:
```html
:subtitle="leadershipSubtitle ?? 'Meet the leaders who serve our community.'"
```

- [ ] **Step 10: Update Settings/AboutContent.vue — add Settings interface fields**

In `resources/js/Pages/Dashboard/Settings/AboutContent.vue`, replace the `Settings` interface:
```ts
interface Settings {
    team:   TeamMember[]
    values: ChurchValue[]
}
```
With:
```ts
interface Settings {
    team:                TeamMember[]
    values:              ChurchValue[]
    hero_title:          string | null
    hero_eyebrow:        string | null
    hero_subtitle:       string | null
    leadership_subtitle: string | null
}
```

Replace the `useForm` call:
```ts
const form = useForm({
    team:   props.settings.team.map(m => ({ ...m })) as TeamMember[],
    values: props.settings.values.map(v => ({ ...v })) as ChurchValue[],
})
```
With:
```ts
const form = useForm({
    team:                props.settings.team.map(m => ({ ...m })) as TeamMember[],
    values:              props.settings.values.map(v => ({ ...v })) as ChurchValue[],
    hero_title:          props.settings.hero_title          ?? '',
    hero_eyebrow:        props.settings.hero_eyebrow        ?? '',
    hero_subtitle:       props.settings.hero_subtitle       ?? '',
    leadership_subtitle: props.settings.leadership_subtitle ?? '',
})
```

- [ ] **Step 11: Add the About Page Hero card to Settings/AboutContent.vue template**

In the template, find the opening `<form @submit.prevent="submit"` tag. Insert a new card as the FIRST child inside the form (before the Leadership Team card):

```html
<!-- ── About Page Hero Copy ──────────────────────────────────────────────── -->
<div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
    <div class="px-5 py-4">
        <h3 class="text-sm font-semibold text-neutral-900">About page hero</h3>
        <p class="text-xs text-neutral-500 mt-0.5">
            Text shown at the top of the public About page. Leave blank to use defaults.
        </p>
    </div>
    <div class="p-5 space-y-4">
        <AppInput
            id="hero-eyebrow"
            v-model="form.hero_eyebrow"
            label="Eyebrow label"
            placeholder="Our Story"
            hint="Small label above the title — defaults to 'Our Story'"
            :error="form.errors.hero_eyebrow"
        />
        <AppInput
            id="hero-title"
            v-model="form.hero_title"
            label="Hero title"
            placeholder="About us"
            hint="Main heading — defaults to your church name if blank"
            :error="form.errors.hero_title"
        />
        <AppInput
            id="hero-subtitle"
            v-model="form.hero_subtitle"
            label="Hero subtitle"
            placeholder="A brief sentence about your church's story."
            hint="Overrides the auto-generated founding-year subtitle"
            :error="form.errors.hero_subtitle"
        />
        <AppInput
            id="leadership-subtitle"
            v-model="form.leadership_subtitle"
            label="Leadership section subtitle"
            placeholder="Meet the leaders who serve our community."
            hint="Shown under the 'Meet our team' heading"
            :error="form.errors.leadership_subtitle"
        />
    </div>
</div>
```

- [ ] **Step 12: Run tests**

Run: `php artisan test --filter=ChurchSettingsContentTest::test_about_hero_copy_is_saved`
Expected: PASS.

- [ ] **Step 13: Verify type-check**

Run: `npm run type-check`
Expected: 35 pre-existing errors only, none in About.vue or AboutContent.vue.

---

### Task 5: Homepage + Sermons section copy in Settings → Homepage

**Files:**
- Modify: `app/Http/Controllers/Dashboard/ChurchSettingsController.php` (homepage methods)
- Modify: `app/Http/Controllers/Public/HomeController.php`
- Modify: `app/Http/Controllers/Public/SermonsController.php`
- Modify: `resources/js/Pages/Public/Home.vue`
- Modify: `resources/js/Pages/Public/Sermons.vue`
- Modify: `resources/js/Pages/Dashboard/Settings/Homepage.vue`

**Keys added to `settings.homepage`:**
| Key | Current hardcoded value replaced |
|---|---|
| `events_subtitle` | "Join us for worship, community, and service. There is always something happening." (Home.vue:146) |
| `ministry_heading` | "Find your place in our community." (Home.vue:176) |
| `ministry_body` | "From worship to outreach...family of God." (Home.vue:179–180) |
| `sermons_subtitle` | "Grow in faith with teaching...your life." (Home.vue:208) |
| `testimonials_subtitle` | "Hear from people...power of community." (Home.vue:232) |
| `livestream_cta` | "Experience our Sunday services from anywhere in the world." (Home.vue:296) |
| `sermons_page_subtitle` | "Deep, scripture-rooted teaching..." (Sermons.vue:41) |

- [ ] **Step 1: Add test to ChurchSettingsContentTest.php**

Append:
```php
    // ── Task 5: Homepage section copy ───────────────────────────────────────────

    public function test_homepage_copy_strings_are_saved(): void
    {
        [$church] = $this->makeChurchAdmin();

        $response = $this->put('/dashboard/settings/homepage', [
            'hero_description'      => null,
            'stats'                 => [],
            'testimonials'          => [],
            'events_subtitle'       => 'Something always happening here.',
            'ministry_heading'      => 'Find your community.',
            'ministry_body'         => 'We have a place for everyone.',
            'sermons_subtitle'      => 'Teaching every Sunday.',
            'testimonials_subtitle' => 'Real stories of transformation.',
            'livestream_cta'        => 'Watch from home this Sunday.',
            'sermons_page_subtitle' => 'Browse our sermon archive.',
        ]);

        $response->assertRedirect();
        $church->refresh();
        $this->assertSame('Find your community.',    $church->settings['homepage']['ministry_heading']);
        $this->assertSame('Browse our sermon archive.', $church->settings['homepage']['sermons_page_subtitle']);
    }
```

- [ ] **Step 2: Run test to confirm it fails**

Run: `php artisan test --filter=ChurchSettingsContentTest::test_homepage_copy_strings_are_saved`
Expected: FAIL.

- [ ] **Step 3: Update ChurchSettingsController::homepage() to pass the 7 copy fields**

In `ChurchSettingsController.php`, find the `homepage()` method and replace its `Inertia::render()` return:
```php
        return Inertia::render('Dashboard/Settings/Homepage', [
            'settings' => [
                'hero_description' => $settings['hero_description'] ?? null,
                'hero_image'       => $settings['hero_image']       ?? null,
                'stats'            => $settings['stats']            ?? [],
                'testimonials'     => $settings['testimonials']     ?? [],
            ],
        ]);
```
With:
```php
        return Inertia::render('Dashboard/Settings/Homepage', [
            'settings' => [
                'hero_description'      => $settings['hero_description']      ?? null,
                'hero_image'            => $settings['hero_image']             ?? null,
                'stats'                 => $settings['stats']                  ?? [],
                'testimonials'          => $settings['testimonials']           ?? [],
                'events_subtitle'       => $settings['events_subtitle']        ?? null,
                'ministry_heading'      => $settings['ministry_heading']       ?? null,
                'ministry_body'         => $settings['ministry_body']          ?? null,
                'sermons_subtitle'      => $settings['sermons_subtitle']       ?? null,
                'testimonials_subtitle' => $settings['testimonials_subtitle']  ?? null,
                'livestream_cta'        => $settings['livestream_cta']         ?? null,
                'sermons_page_subtitle' => $settings['sermons_page_subtitle']  ?? null,
            ],
        ]);
```

- [ ] **Step 4: Update ChurchSettingsController::updateHomepage() — add validation rules for 7 copy fields**

In `updateHomepage()`, add to the `$request->validate([...])` array (after the existing testimonials rules):
```php
            'events_subtitle'       => ['nullable', 'string', 'max:200'],
            'ministry_heading'      => ['nullable', 'string', 'max:100'],
            'ministry_body'         => ['nullable', 'string', 'max:300'],
            'sermons_subtitle'      => ['nullable', 'string', 'max:200'],
            'testimonials_subtitle' => ['nullable', 'string', 'max:200'],
            'livestream_cta'        => ['nullable', 'string', 'max:200'],
            'sermons_page_subtitle' => ['nullable', 'string', 'max:200'],
```

And add to the `saveSettings` call:
```php
            'events_subtitle'       => $validated['events_subtitle']       ?? null,
            'ministry_heading'      => $validated['ministry_heading']      ?? null,
            'ministry_body'         => $validated['ministry_body']         ?? null,
            'sermons_subtitle'      => $validated['sermons_subtitle']      ?? null,
            'testimonials_subtitle' => $validated['testimonials_subtitle'] ?? null,
            'livestream_cta'        => $validated['livestream_cta']        ?? null,
            'sermons_page_subtitle' => $validated['sermons_page_subtitle'] ?? null,
```

- [ ] **Step 5: Update HomeController to pass the 6 copy props**

In `app/Http/Controllers/Public/HomeController.php`, add to the `Inertia::render()` array:
```php
            'eventsSubtitle'       => $homepageSettings['events_subtitle']        ?? null,
            'ministryHeading'      => $homepageSettings['ministry_heading']       ?? null,
            'ministryBody'         => $homepageSettings['ministry_body']          ?? null,
            'sermonsSubtitle'      => $homepageSettings['sermons_subtitle']       ?? null,
            'testimonialsSubtitle' => $homepageSettings['testimonials_subtitle']  ?? null,
            'livestreamCta'        => $homepageSettings['livestream_cta']         ?? null,
```

- [ ] **Step 6: Update SermonsController to pass sermonsSubtitle**

In `app/Http/Controllers/Public/SermonsController.php`, add at the top of `__invoke()` (after any existing `$church` binding):
```php
$church = app('church');
```
(Only if `$church` is not already assigned in the method. If it is, skip this line.)

Then add to the `Inertia::render()` array:
```php
            'sermonsSubtitle' => $church?->settings['homepage']['sermons_page_subtitle'] ?? null,
```

- [ ] **Step 7: Update Home.vue — add 6 new props to defineProps**

In `resources/js/Pages/Public/Home.vue`, replace the existing `defineProps` block:
```ts
const props = defineProps<{
    serviceTimes:       ServiceTime[]
    featuredEvents:     Event[]
    latestSermons:      Sermon[]
    announcements:      Announcement[]
    stats:              Stat[]
    testimonials:       Testimonial[]
    ministryHighlights: MinistryHighlight[]
    heroDescription:    string | null
    heroImage:          string | null
    hasLivestream:      boolean
}>()
```
With:
```ts
const props = defineProps<{
    serviceTimes:         ServiceTime[]
    featuredEvents:       Event[]
    latestSermons:        Sermon[]
    announcements:        Announcement[]
    stats:                Stat[]
    testimonials:         Testimonial[]
    ministryHighlights:   MinistryHighlight[]
    heroDescription:      string | null
    heroImage:            string | null
    hasLivestream:        boolean
    eventsSubtitle:       string | null
    ministryHeading:      string | null
    ministryBody:         string | null
    sermonsSubtitle:      string | null
    testimonialsSubtitle: string | null
    livestreamCta:        string | null
}>()
```

- [ ] **Step 8: Replace the 6 hardcoded strings in Home.vue template**

**Events subtitle** (Home.vue line ~146): find:
```html
subtitle="Join us for worship, community, and service. There is always something happening."
```
Replace with:
```html
:subtitle="eventsSubtitle ?? 'Join us for worship, community, and service.'"
```

**Ministry heading** (Home.vue line ~176): find the hardcoded text node:
```
                            Find your place<br />in our community.
```
Replace with:
```html
                            {{ ministryHeading ?? 'Find your place in our community.' }}
```
(Remove the `<br />` — it was purely cosmetic line-breaking and doesn't work inside `{{ }}`.)

**Ministry body** (Home.vue lines ~179–180): find:
```
                            From worship to outreach, from youth to women's ministry — there is a place
                            for every person in the family of God.
```
Replace with:
```html
                            {{ ministryBody ?? 'From worship to outreach — there is a place for everyone here.' }}
```

**Sermons subtitle** (Home.vue line ~208): find:
```html
subtitle="Grow in faith with teaching that is rooted in scripture and relevant to your life."
```
Replace with:
```html
:subtitle="sermonsSubtitle ?? 'Teaching to strengthen your faith and equip you for life.'"
```

**Testimonials subtitle** (Home.vue line ~232): find:
```html
subtitle="Hear from people whose lives have been transformed by the grace of God and the power of community."
```
Replace with:
```html
:subtitle="testimonialsSubtitle ?? 'Stories of transformation from our community.'"
```

**Livestream CTA** (Home.vue line ~296): find:
```
                        Experience our Sunday services from anywhere in the world.
```
Replace with:
```html
                        {{ livestreamCta ?? 'Watch our services live from anywhere.' }}
```

- [ ] **Step 9: Update Sermons.vue — add sermonsSubtitle prop**

In `resources/js/Pages/Public/Sermons.vue`, replace `defineProps`:
```ts
const props = defineProps<{
    featured: PublicSermon | null
    recent:   PublicSermon[]
    series:   Array<{ id: number | null; title: string; slug: string | null; sermon_count?: number }>
}>()
```
With:
```ts
const props = defineProps<{
    featured:        PublicSermon | null
    recent:          PublicSermon[]
    series:          Array<{ id: number | null; title: string; slug: string | null; sermon_count?: number }>
    sermonsSubtitle: string | null
}>()
```

In the template, find:
```html
subtitle="Deep, scripture-rooted teaching to strengthen your faith and equip you for daily life."
```
Replace with:
```html
:subtitle="sermonsSubtitle ?? 'Sermons and teaching to grow your faith.'"
```

- [ ] **Step 10: Update Settings/Homepage.vue — Settings interface**

In `resources/js/Pages/Dashboard/Settings/Homepage.vue`, replace the `Settings` interface:
```ts
interface Settings {
    hero_description: string | null
    hero_image:       string | null
    stats:            StatItem[]
    testimonials:     Testimonial[]
}
```
With:
```ts
interface Settings {
    hero_description:      string | null
    hero_image:            string | null
    stats:                 StatItem[]
    testimonials:          Testimonial[]
    events_subtitle:       string | null
    ministry_heading:      string | null
    ministry_body:         string | null
    sermons_subtitle:      string | null
    testimonials_subtitle: string | null
    livestream_cta:        string | null
    sermons_page_subtitle: string | null
}
```

Replace the `useForm` call:
```ts
const form = useForm({
    hero_description: props.settings.hero_description ?? '',
    stats:            props.settings.stats.map(s => ({ ...s }))        as StatItem[],
    testimonials:     props.settings.testimonials.map(t => ({ ...t })) as Testimonial[],
})
```
With:
```ts
const form = useForm({
    hero_description:      props.settings.hero_description      ?? '',
    stats:                 props.settings.stats.map(s => ({ ...s }))        as StatItem[],
    testimonials:          props.settings.testimonials.map(t => ({ ...t })) as Testimonial[],
    events_subtitle:       props.settings.events_subtitle       ?? '',
    ministry_heading:      props.settings.ministry_heading      ?? '',
    ministry_body:         props.settings.ministry_body         ?? '',
    sermons_subtitle:      props.settings.sermons_subtitle      ?? '',
    testimonials_subtitle: props.settings.testimonials_subtitle ?? '',
    livestream_cta:        props.settings.livestream_cta        ?? '',
    sermons_page_subtitle: props.settings.sermons_page_subtitle ?? '',
})
```

- [ ] **Step 11: Add the Section Copy card to Settings/Homepage.vue template**

In the template, find the hero description card (the one with `<AlignLeft ...>` icon and label "Hero description"). Insert the following new card BEFORE it:

```html
<!-- ── Section copy ──────────────────────────────────────────────────────── -->
<div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
    <div class="px-5 py-4 flex items-center gap-2">
        <AlignLeft class="w-4 h-4 text-neutral-400 shrink-0" />
        <div>
            <h3 class="text-sm font-semibold text-neutral-900">Section copy</h3>
            <p class="text-xs text-neutral-500 mt-0.5">
                Override taglines for each homepage section and the Sermons page.
                Leave blank to use the built-in defaults.
            </p>
        </div>
    </div>
    <div class="p-5 space-y-4">
        <AppInput
            id="events-subtitle"
            v-model="form.events_subtitle"
            label="Events section tagline"
            placeholder="Join us for worship, community, and service."
            :error="form.errors.events_subtitle"
        />
        <AppInput
            id="ministry-heading"
            v-model="form.ministry_heading"
            label="Ministry section heading"
            placeholder="Find your place in our community."
            :error="form.errors.ministry_heading"
        />
        <AppInput
            id="ministry-body"
            v-model="form.ministry_body"
            label="Ministry section body"
            placeholder="A short description of your ministries."
            :error="form.errors.ministry_body"
        />
        <AppInput
            id="sermons-subtitle"
            v-model="form.sermons_subtitle"
            label="Sermons section tagline (homepage)"
            placeholder="Teaching to strengthen your faith."
            :error="form.errors.sermons_subtitle"
        />
        <AppInput
            id="testimonials-subtitle"
            v-model="form.testimonials_subtitle"
            label="Testimonials section subtitle"
            placeholder="Stories from our community."
            :error="form.errors.testimonials_subtitle"
        />
        <AppInput
            id="livestream-cta"
            v-model="form.livestream_cta"
            label="Livestream CTA text"
            placeholder="Watch our services from anywhere."
            :error="form.errors.livestream_cta"
        />
        <AppInput
            id="sermons-page-subtitle"
            v-model="form.sermons_page_subtitle"
            label="Sermons page subtitle"
            placeholder="Browse our sermon library."
            :error="form.errors.sermons_page_subtitle"
        />
    </div>
</div>
```

- [ ] **Step 12: Run tests**

Run: `php artisan test --filter=ChurchSettingsContentTest`
Expected: All 3 tests PASS.

- [ ] **Step 13: Verify type-check**

Run: `npm run type-check`
Expected: 35 pre-existing errors only, none in Home.vue, Sermons.vue, or Homepage.vue.

---

### Task 6: Homepage section visibility toggles

**Files:**
- Modify: `app/Http/Controllers/Dashboard/ChurchSettingsController.php` (homepage methods)
- Modify: `app/Http/Controllers/Public/HomeController.php`
- Modify: `resources/js/Pages/Public/Home.vue`
- Modify: `resources/js/Pages/Dashboard/Settings/Homepage.vue`

Section visibility flags are stored as `settings.homepage.section_visibility` (an object of 6 booleans). All default to `true` — existing behaviour is preserved for unconfigured churches. When an admin sets a flag to `false`, that section is force-hidden even when it has content.

Current `v-if` conditions guard sections by data availability. After this task they guard by BOTH data AND the admin's visibility flag.

- [ ] **Step 1: Add visibility test to ChurchSettingsContentTest.php**

Append:
```php
    // ── Task 6: Section visibility ───────────────────────────────────────────────

    public function test_section_visibility_is_saved_and_defaults_to_true(): void
    {
        [$church] = $this->makeChurchAdmin();

        $this->put('/dashboard/settings/homepage', [
            'hero_description' => null,
            'stats'            => [],
            'testimonials'     => [],
            'section_visibility' => [
                'events'        => false,
                'ministry'      => true,
                'sermons'       => true,
                'testimonials'  => true,
                'livestream'    => false,
                'announcements' => true,
            ],
        ]);

        $church->refresh();
        $this->assertFalse($church->settings['homepage']['section_visibility']['events']);
        $this->assertFalse($church->settings['homepage']['section_visibility']['livestream']);
        $this->assertTrue($church->settings['homepage']['section_visibility']['ministry']);
    }
```

- [ ] **Step 2: Run test to confirm it fails**

Run: `php artisan test --filter=ChurchSettingsContentTest::test_section_visibility_is_saved`
Expected: FAIL.

- [ ] **Step 3: Update ChurchSettingsController::homepage() to include section_visibility**

In `homepage()`, add to the settings array (after `sermons_page_subtitle`):
```php
                'section_visibility' => $settings['section_visibility'] ?? [
                    'events'        => true,
                    'ministry'      => true,
                    'sermons'       => true,
                    'testimonials'  => true,
                    'livestream'    => true,
                    'announcements' => true,
                ],
```

- [ ] **Step 4: Update ChurchSettingsController::updateHomepage() — add section_visibility validation**

In `updateHomepage()`, add to the validation array:
```php
            'section_visibility'                => ['nullable', 'array'],
            'section_visibility.events'         => ['nullable', 'boolean'],
            'section_visibility.ministry'       => ['nullable', 'boolean'],
            'section_visibility.sermons'        => ['nullable', 'boolean'],
            'section_visibility.testimonials'   => ['nullable', 'boolean'],
            'section_visibility.livestream'     => ['nullable', 'boolean'],
            'section_visibility.announcements'  => ['nullable', 'boolean'],
```

And add to the `saveSettings` call:
```php
            'section_visibility' => [
                'events'        => $validated['section_visibility']['events']        ?? true,
                'ministry'      => $validated['section_visibility']['ministry']      ?? true,
                'sermons'       => $validated['section_visibility']['sermons']       ?? true,
                'testimonials'  => $validated['section_visibility']['testimonials']  ?? true,
                'livestream'    => $validated['section_visibility']['livestream']    ?? true,
                'announcements' => $validated['section_visibility']['announcements'] ?? true,
            ],
```

- [ ] **Step 5: Update HomeController to pass sectionVisibility prop**

In `app/Http/Controllers/Public/HomeController.php`, inside `__invoke()`, add before the `Inertia::render()` call:
```php
        $visibilityDefaults = [
            'events'        => true,
            'ministry'      => true,
            'sermons'       => true,
            'testimonials'  => true,
            'livestream'    => true,
            'announcements' => true,
        ];
        $visibility = array_merge(
            $visibilityDefaults,
            $homepageSettings['section_visibility'] ?? []
        );
```

Add to the `Inertia::render()` array:
```php
            'sectionVisibility' => $visibility,
```

- [ ] **Step 6: Update Home.vue — add sectionVisibility to defineProps**

In `resources/js/Pages/Public/Home.vue`, add to `defineProps` (after `livestreamCta`):
```ts
    sectionVisibility: {
        events:        boolean
        ministry:      boolean
        sermons:       boolean
        testimonials:  boolean
        livestream:    boolean
        announcements: boolean
    }
```

- [ ] **Step 7: Update each section v-if in Home.vue to include the visibility flag**

Six replacements in the template (match exact strings from current file):

**Events** (line ~142):
```html
<SectionWrapper v-if="featuredEvents.length > 0" bg="white">
```
→
```html
<SectionWrapper v-if="featuredEvents.length > 0 && sectionVisibility.events" bg="white">
```

**Ministry** (line ~166):
```html
<section v-if="ministryHighlights.length > 0" class="gradient-dark-mesh py-24 md:py-32 relative overflow-hidden">
```
→
```html
<section v-if="ministryHighlights.length > 0 && sectionVisibility.ministry" class="gradient-dark-mesh py-24 md:py-32 relative overflow-hidden">
```

**Sermons** (line ~204):
```html
<SectionWrapper v-if="latestSermons.length > 0" bg="surface">
```
→
```html
<SectionWrapper v-if="latestSermons.length > 0 && sectionVisibility.sermons" bg="surface">
```

**Testimonials** (line ~228):
```html
<SectionWrapper v-if="testimonials.length > 0" bg="white" centered>
```
→
```html
<SectionWrapper v-if="testimonials.length > 0 && sectionVisibility.testimonials" bg="white" centered>
```

**Announcements** (line ~259):
```html
<SectionWrapper v-if="announcements.length > 0" bg="surface" size="sm">
```
→
```html
<SectionWrapper v-if="announcements.length > 0 && sectionVisibility.announcements" bg="surface" size="sm">
```

**Livestream** (line ~281):
```html
<section v-if="props.hasLivestream" class="gradient-dark-mesh relative overflow-hidden">
```
→
```html
<section v-if="props.hasLivestream && sectionVisibility.livestream" class="gradient-dark-mesh relative overflow-hidden">
```

- [ ] **Step 8: Update Settings/Homepage.vue — add SectionVisibility type and form field**

In `resources/js/Pages/Dashboard/Settings/Homepage.vue`, add a type alias before the `Settings` interface:
```ts
interface SectionVisibility {
    events:        boolean
    ministry:      boolean
    sermons:       boolean
    testimonials:  boolean
    livestream:    boolean
    announcements: boolean
}
```

Add to the `Settings` interface (after `sermons_page_subtitle`):
```ts
    section_visibility: SectionVisibility
```

Add to `useForm` (after `sermons_page_subtitle`):
```ts
    section_visibility: {
        events:        props.settings.section_visibility?.events        ?? true,
        ministry:      props.settings.section_visibility?.ministry      ?? true,
        sermons:       props.settings.section_visibility?.sermons       ?? true,
        testimonials:  props.settings.section_visibility?.testimonials  ?? true,
        livestream:    props.settings.section_visibility?.livestream    ?? true,
        announcements: props.settings.section_visibility?.announcements ?? true,
    },
```

Add helper functions after the existing `removeTestimonial` function:
```ts
function toggleSection(key: keyof SectionVisibility): void {
    form.section_visibility[key] = !form.section_visibility[key]
}
```

- [ ] **Step 9: Add the Section Visibility card to Settings/Homepage.vue template**

Insert this card immediately AFTER the Section Copy card added in Task 5 (before the Stats card):

```html
<!-- ── Section visibility ────────────────────────────────────────────────── -->
<div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
    <div class="px-5 py-4">
        <h3 class="text-sm font-semibold text-neutral-900">Section visibility</h3>
        <p class="text-xs text-neutral-500 mt-0.5">
            Force-hide homepage sections. Sections with no content are always hidden regardless of this setting.
        </p>
    </div>
    <div class="p-5 space-y-1">
        <div
            v-for="item in ([
                { label: 'Events',           key: 'events' },
                { label: 'Ministry highlights', key: 'ministry' },
                { label: 'Sermons',          key: 'sermons' },
                { label: 'Testimonials',     key: 'testimonials' },
                { label: 'Announcements',    key: 'announcements' },
                { label: 'Livestream CTA',   key: 'livestream' },
            ] as const)"
            :key="item.key"
            class="flex items-center justify-between py-2"
        >
            <span class="text-sm text-neutral-700">{{ item.label }}</span>
            <button
                type="button"
                role="switch"
                :aria-checked="form.section_visibility[item.key]"
                :class="[
                    'relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-1',
                    form.section_visibility[item.key] ? 'bg-brand-500' : 'bg-neutral-200',
                ]"
                @click="toggleSection(item.key)"
            >
                <span
                    :class="[
                        'inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform',
                        form.section_visibility[item.key] ? 'translate-x-4' : 'translate-x-0.5',
                    ]"
                />
            </button>
        </div>
    </div>
</div>
```

- [ ] **Step 10: Run all tests**

Run: `php artisan test --filter=ChurchSettingsContentTest`
Expected: All 4 tests PASS.

- [ ] **Step 11: Verify type-check**

Run: `npm run type-check`
Expected: 35 pre-existing errors only, none in Home.vue or Homepage.vue.

---

## Self-Review

**Spec coverage:**
- Gap 1 (denomination mismatch copy): ✅ Tasks 4 + 5 — all 13 hardcoded text strings replaced with settings-driven props and neutral fallbacks
- Gap 2 (section visibility): ✅ Task 6
- Gap 3 (footer frozen nav): ⚠️ **Deferred** — requires adding footer links to `HandleInertiaRequests` shared props, which is a separate concern. Tracked as a follow-up.
- Gap 4 (About hero locked): ✅ Task 4
- Gap 5 (donation amounts): ✅ Task 3
- Gap 6 (social platforms): ✅ Task 2
- Gap 7 (hero fallback generic): ✅ Task 4 (About subtitle), Task 5 (Home fallback via props)
- config/church.php COP defaults: ✅ Task 1

**Placeholder scan:** None found — all steps contain exact code.

**Type consistency:**
- `hero_title` (PHP settings key) → `heroTitle` (Inertia prop) → used in `About.vue` ✅
- `suggested_amounts` (PHP) → `suggestedAmounts` (Inertia) → used in `Donate.vue` ✅
- `section_visibility` (PHP) → `sectionVisibility` (Inertia) → used in `Home.vue` ✅
- `sermons_page_subtitle` (settings key) → `sermonsSubtitle` (Inertia prop in Sermons.vue) ✅
- `toggleSection(key: keyof SectionVisibility)` matches `SectionVisibility` interface defined in same file ✅
