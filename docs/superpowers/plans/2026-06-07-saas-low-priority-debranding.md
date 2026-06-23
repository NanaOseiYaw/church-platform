# LOW Priority SaaS De-branding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove every remaining hardcoded COP/church-specific string from the public website so any church can use the platform without seeing another church's branding or names.

**Architecture:** Seven targeted fixes across `app.ts`, four layout/public Vue pages, five secondary public pages, one PHP controller, and one nav component. No new settings or DB columns needed — all fixes are de-hardcoding existing values by reading `church.name` from the already-shared Inertia `church` prop via `useChurch()`, or dropping fictitious data entirely.

**Tech Stack:** Laravel 11, Vue 3 + TypeScript, Inertia.js, Tailwind CSS v4. No git, no test runner — verify via `npm run type-check` then browser.

---

## Files Modified

| File | Change |
|------|--------|
| `resources/js/app.ts` | Remove hardcoded church name from title resolver + generic progress colour |
| `resources/js/Layouts/PublicLayout.vue` | Always emit full `<title>` with church name |
| `resources/js/Pages/Public/Announcements.vue` | Dynamic meta description |
| `resources/js/Pages/Public/Contact.vue` | Dynamic meta description |
| `resources/js/Pages/Public/Events.vue` | Dynamic meta description |
| `resources/js/Pages/Public/Livestream.vue` | Dynamic meta description |
| `resources/js/Pages/Public/Ministries.vue` | Dynamic meta description |
| `resources/js/Pages/Public/Sermons.vue` | Dynamic meta description |
| `resources/js/Pages/Public/About.vue` | Generic mission/description fallbacks |
| `app/Http/Controllers/Public/HomeController.php` | Query Sermon model instead of static fixture |
| `resources/js/Pages/Public/Donate.vue` | Remove fictitious 60%/25%/15% breakdown |
| `resources/js/Components/Navigation/AppNav.vue` | Update COP-labelled comments |

---

### Task 1: Fix app.ts title resolver + progress bar colour

**Files:**
- Modify: `resources/js/app.ts` (lines 13, 27)

The Inertia title callback runs once at boot, outside the reactive system, so it can't read the `church` Pinia store. Changing it to a passthrough (`title => title ?? ''`) moves the responsibility to the layouts where `useChurch()` is already available. Also change the progress bar colour from COP blue `#1e5aa8` to a neutral default `#6366f1`.

- [ ] **Step 1: Update app.ts**

Replace both hardcoded values in `resources/js/app.ts`:

```ts
import './bootstrap'
import '../css/app.css'

import { createApp, h, DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createPinia } from 'pinia'
import { ZiggyVue } from 'ziggy-js'

const pinia = createPinia()

createInertiaApp({
    // Title is assembled by each layout using the reactive church.name from useChurch().
    // Returning the raw title here avoids hardcoding any church name.
    title: (title) => title ?? '',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .mount(el)
    },
    progress: {
        // Neutral default — brand CSS vars override on page load once the app hydrates.
        color: '#6366f1',
        showSpinner: false,
    },
})
```

- [ ] **Step 2: Verify type-check passes**

```
npm run type-check
```

Expected: same pre-existing errors as before; no new errors in `app.ts`.

---

### Task 2: Fix PublicLayout.vue to always emit a full dynamic title

**Files:**
- Modify: `resources/js/Layouts/PublicLayout.vue` (Head block, lines 33–38)

Currently the layout only emits `<title>` when the page passes a `title` prop. After removing the `createInertiaApp` title function, pages without a prop would have a blank browser tab. Fix: always emit `title · church.name` (or just `church.name` when title is absent).

- [ ] **Step 1: Update the Head block in PublicLayout.vue**

Change the `<Head>` block from:
```html
<Head>
    <title v-if="title">{{ title }}</title>
    <meta name="description" :content="description ?? church.seo?.meta_description ?? ''" />
    <meta property="og:title" :content="title ? `${title} · ${church.name}` : church.name" />
    <meta v-if="church.seo?.meta_title" property="og:site_name" :content="church.seo.meta_title" />
</Head>
```

To:
```html
<Head>
    <title>{{ title ? `${title} · ${church.name}` : church.name }}</title>
    <meta name="description" :content="description ?? church.seo?.meta_description ?? ''" />
    <meta property="og:title" :content="title ? `${title} · ${church.name}` : church.name" />
    <meta v-if="church.seo?.meta_title" property="og:site_name" :content="church.seo.meta_title" />
</Head>
```

- [ ] **Step 2: Verify type-check**

```
npm run type-check
```

Expected: no new errors in `PublicLayout.vue`.

---

### Task 3: Fix meta descriptions on 6 public pages

**Files:**
- Modify: `resources/js/Pages/Public/Announcements.vue`
- Modify: `resources/js/Pages/Public/Contact.vue`
- Modify: `resources/js/Pages/Public/Events.vue`
- Modify: `resources/js/Pages/Public/Livestream.vue`
- Modify: `resources/js/Pages/Public/Ministries.vue`
- Modify: `resources/js/Pages/Public/Sermons.vue`

Each page passes a static `description="...The Church of Pentecost..."` string to `PublicLayout`. The fix: add `useChurch()` to each page's `<script setup>` and switch to a `:description` binding with a template literal.

**Important**: `useChurch()` is imported from `@/composables/useChurch`. Each page may already import things from `vue`; add the composable import to the existing block.

- [ ] **Step 1: Fix Announcements.vue**

In `<script setup>`:
```ts
import { useChurch } from '@/composables/useChurch'
const { church } = useChurch()
```

In the template, change:
```html
<PublicLayout title="Announcements" description="Stay up to date with what's happening at The Church of Pentecost.">
```
To:
```html
<PublicLayout title="Announcements" :description="`Stay up to date with what's happening at ${church.name}.`">
```

- [ ] **Step 2: Fix Contact.vue**

In `<script setup>`:
```ts
import { useChurch } from '@/composables/useChurch'
const { church } = useChurch()
```

In the template, change:
```html
<PublicLayout title="Contact" description="Get in touch with The Church of Pentecost.">
```
To:
```html
<PublicLayout title="Contact" :description="`Get in touch with ${church.name}.`">
```

- [ ] **Step 3: Fix Events.vue**

In `<script setup>`:
```ts
import { useChurch } from '@/composables/useChurch'
const { church } = useChurch()
```

In the template, change:
```html
<PublicLayout title="Events" description="Upcoming events and gatherings at The Church of Pentecost.">
```
To:
```html
<PublicLayout title="Events" :description="`Upcoming events and gatherings at ${church.name}.`">
```

- [ ] **Step 4: Fix Livestream.vue**

In `<script setup>` (already imports several things):
```ts
import { useChurch } from '@/composables/useChurch'
const { church } = useChurch()
```

In the template, change:
```html
<PublicLayout title="Watch Live" description="Watch The Church of Pentecost live online every Sunday.">
```
To:
```html
<PublicLayout title="Watch Live" :description="`Watch ${church.name} live online.`">
```

- [ ] **Step 5: Fix Ministries.vue**

In `<script setup>`:
```ts
import { useChurch } from '@/composables/useChurch'
const { church } = useChurch()
```

In the template, change:
```html
<PublicLayout title="Ministries" description="Explore our ministries and find where you belong at The Church of Pentecost.">
```
To:
```html
<PublicLayout title="Ministries" :description="`Explore our ministries and find where you belong at ${church.name}.`">
```

- [ ] **Step 6: Fix Sermons.vue**

In `<script setup>`:
```ts
import { useChurch } from '@/composables/useChurch'
const { church } = useChurch()
```

In the template, change:
```html
<PublicLayout title="Sermons" description="Browse our library of sermons and teaching series at The Church of Pentecost.">
```
To:
```html
<PublicLayout title="Sermons" :description="`Browse our library of sermons and teaching series at ${church.name}.`">
```

- [ ] **Step 7: Verify type-check**

```
npm run type-check
```

Expected: no new errors in any of the six pages.

---

### Task 4: Fix About.vue generic fallback text

**Files:**
- Modify: `resources/js/Pages/Public/About.vue` (lines 52, 57)

Two fallback strings are COP theology: the mission fallback ("Glorifying God. Making disciples. Possessing the Nations.") and the description fallback (also COP-specific). Replace both with generic church-appropriate text.

- [ ] **Step 1: Update the mission fallback (line 52)**

Change:
```html
{{ mission ?? 'Glorifying God.\nMaking disciples.\nPossessing the Nations.' }}
```
To:
```html
{{ mission ?? 'Glorifying God and\nserving our community.' }}
```

- [ ] **Step 2: Update the description fallback (line 57)**

Change:
```html
{{ description ?? 'We exist to glorify God by making disciples who love God, love one another, and serve the world. Every program, every service, every relationship is filtered through this singular purpose.' }}
```
To:
```html
{{ description ?? 'We exist to know God and make Him known — through worship, community, and service to the world around us.' }}
```

- [ ] **Step 3: Verify type-check**

```
npm run type-check
```

Expected: no new errors in `About.vue`.

---

### Task 5: Replace static sermon fixture with live DB query

**Files:**
- Modify: `app/Http/Controllers/Public/HomeController.php`

The `getStaticSermons()` private method returns three hardcoded sermons with COP speaker names ("Pastor James Osei", "Elder Sarah Mensah"). Replace it with `getLatestSermons()` that queries `Sermon::publiclyVisible()`. The `BelongsToChurch` global scope on the Sermon model automatically filters to the current tenant's church — no manual `church_id` filter needed. If no sermons exist, an empty array is returned and Home.vue already renders the section only when `latestSermons.length > 0`.

- [ ] **Step 1: Add Sermon model import and rename+replace the method**

In `HomeController.php`, the `use` block at the top already has Department. Add:
```php
use App\Models\Sermon;
```

Then replace the entire `getStaticSermons()` method:

```php
/**
 * Query the 3 most recent publicly-visible sermons for this church.
 * BelongsToChurch global scope auto-filters by church_id.
 * Returns [] when no sermons have been published yet.
 */
private function getLatestSermons(): array
{
    return Sermon::publiclyVisible()
        ->whereNotNull('preached_at')
        ->latest('preached_at')
        ->limit(3)
        ->get()
        ->map(fn (Sermon $s) => [
            'id'        => $s->id,
            'title'     => $s->title,
            'speaker'   => $s->speaker ?? '',
            'date'      => $s->preached_at?->format('M j, Y') ?? '',
            'series'    => $s->series ?? ($s->sermonSeries?->title ?? ''),
            'duration'  => $s->duration,      // human-readable accessor on Sermon model
            'thumbnail' => $s->thumbnail_url ?? $s->thumbnail,
        ])
        ->all();
}
```

- [ ] **Step 2: Update the `__invoke` call**

In `HomeController::__invoke()`, change:
```php
'latestSermons' => $this->getStaticSermons(),
```
To:
```php
'latestSermons' => $this->getLatestSermons(),
```

- [ ] **Step 3: Verify via browser**

Load `/` in the browser. If the church has no sermons yet, the Latest Sermons section should render 0 cards (the `v-for` produces nothing; confirm that the section heading still appears — or wraps in a `v-if` if needed). If sermons exist in DB, they display correctly.

---

### Task 6: Remove fictitious giving breakdown from Donate.vue

**Files:**
- Modify: `resources/js/Pages/Public/Donate.vue` (lines 93–116)

The "Where your gift goes" section (60% Local Ministry / 25% Community Outreach / 15% Global Missions) is specific to The Church of Pentecost and would be incorrect for any other church. Remove the entire `<SectionWrapper>` block containing it.

- [ ] **Step 1: Delete the "Where your gift goes" section**

Remove from `Donate.vue`:
```html
<!-- Why give -->
<SectionWrapper bg="surface" centered>
    <h2 class="text-3xl font-serif text-neutral-900 mb-4">Where your gift goes</h2>
    <p class="text-neutral-500 mb-10 max-w-lg mx-auto">
        100% of your gift goes toward ministry — no overhead surprises.
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-left max-w-3xl mx-auto">
        <div class="bg-white rounded-2xl border border-neutral-100 p-6">
            <p class="text-3xl font-serif text-brand-600 mb-2">60%</p>
            <p class="font-semibold text-neutral-800 text-sm mb-1">Local Ministry</p>
            <p class="text-xs text-neutral-500">Worship, teaching, small groups, pastoral care.</p>
        </div>
        <div class="bg-white rounded-2xl border border-neutral-100 p-6">
            <p class="text-3xl font-serif text-emerald-600 mb-2">25%</p>
            <p class="font-semibold text-neutral-800 text-sm mb-1">Community Outreach</p>
            <p class="text-xs text-neutral-500">Food pantry, benevolence, community programs.</p>
        </div>
        <div class="bg-white rounded-2xl border border-neutral-100 p-6">
            <p class="text-3xl font-serif text-amber-600 mb-2">15%</p>
            <p class="font-semibold text-neutral-800 text-sm mb-1">Global Missions</p>
            <p class="text-xs text-neutral-500">Supporting missionaries and international ministry.</p>
        </div>
    </div>
</SectionWrapper>
```

- [ ] **Step 2: Verify type-check**

```
npm run type-check
```

Expected: no new errors. Also verify `SectionWrapper` import is still used by the remaining fund-selector block — if not, remove the import.

---

### Task 7: Clean COP labels from AppNav.vue code comments

**Files:**
- Modify: `resources/js/Components/Navigation/AppNav.vue` (lines 15, 38)

Code comments only — no user-visible impact, but COP branding in comments is misleading for other developers using the platform.

- [ ] **Step 1: Update comment on line 15**

Change:
```ts
// Scroll only adds elevation — background is always solid COP Dark Blue
```
To:
```ts
// Scroll only adds a shadow for elevation — the brand-600 background is always solid
```

- [ ] **Step 2: Update HTML comment starting at line 38**

Change:
```html
<!--
    Navbar is always COP Dark Blue #1E5AA8 (bg-brand-600).
    Scrolling only adds a shadow for elevation context.
    Text is always white — no contrast risk regardless of page background.
-->
```
To:
```html
<!--
    Navbar background is always solid brand-600 (set by the church's primary colour).
    Scrolling only adds a drop-shadow for elevation context.
    Text is always white — safe contrast regardless of brand colour.
-->
```

- [ ] **Step 3: Final type-check**

```
npm run type-check
```

Expected: same pre-existing errors; zero new errors across all changed files.

---

## Self-Review

**Spec coverage:**
- L1 app.ts title hardcode → Task 1 ✅
- L2 progress bar colour → Task 1 ✅
- L3 six public page descriptions → Task 3 ✅
- L4 About.vue mission/description fallbacks → Task 4 ✅
- L5 static sermon fixture → Task 5 ✅
- L6 fictitious giving breakdown → Task 6 ✅
- L7 nav comments → Task 7 ✅

**Placeholder scan:** All steps contain exact file paths and complete code. No TBDs.

**Type consistency:** `church.name` accessed the same way (`church.value.name` in script, `church.name` in template) across all tasks. `Sermon` model `duration` accessor used uniformly. `useChurch()` returns `{ church: ComputedRef<ChurchBranding> }` — `.name` is always a string on `ChurchBranding`.
