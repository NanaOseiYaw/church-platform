# HIGH Priority SaaS De-branding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the five highest-impact issues that make the platform look broken or COP-specific for any second church: empty homepage sections, always-on Livestream CTA, no hero image customization, broken About layout when values are empty, and hardcoded YouTube copy in the Livestream page.

**Architecture:** Five targeted changes across two public Vue pages, one settings Vue page, two PHP controllers, one route, and one Inertia middleware addition. No new migrations — hero image stored in `settings['homepage']['hero_image']` alongside the existing `hero_description` key. All props flow through the established HomeController → Inertia → Vue chain.

**Tech Stack:** Laravel 11, Vue 3 + TypeScript, Inertia.js, Tailwind CSS v4. No git, no test runner — verify via `npm run type-check` then browser.

---

## Files Modified

| File | Change |
|------|--------|
| `resources/js/Pages/Public/Home.vue` | Add `v-if` guards on Events/Ministries/Sermons/Announcements sections; gate Livestream CTA on `hasLivestream` prop; render `heroImage` as background |
| `app/Http/Controllers/Public/HomeController.php` | Pass `hasLivestream` + `heroImage` props |
| `resources/js/Pages/Dashboard/Settings/Homepage.vue` | Add hero image upload card |
| `app/Http/Controllers/Dashboard/ChurchSettingsController.php` | Add `uploadHeroImage()` method; expose `hero_image` in `homepage()` render |
| `routes/web.php` | Add `POST /dashboard/settings/homepage/hero-image` route |
| `resources/js/Pages/Public/About.vue` | Conditionally render values grid; make mission full-width when values empty |
| `resources/js/Pages/Public/Livestream.vue` | Generalize "Subscribe on YouTube" CTA copy |

---

### Task 1: Home.vue — Guard empty sections with v-if

**Files:**
- Modify: `resources/js/Pages/Public/Home.vue`

Four sections (Events, Ministry Highlights, Sermons, Announcements) always render their `<SectionWrapper>` and `<SectionHeader>` even when the underlying data arrays are empty. For a new church that hasn't published any content yet, the homepage shows 4 section headings with no cards beneath them — it looks broken.

Fix: wrap each section with `v-if` so it disappears entirely when its data is empty.

- [ ] **Step 1: Guard the Featured Events section**

In `Home.vue`, find the Events section (the `<SectionWrapper bg="white">` that contains "Upcoming Events"). Wrap the entire `<SectionWrapper>` with `v-if="featuredEvents.length > 0"`:

```html
<!-- ── Featured Events ────────────────────────────────────────────────── -->
<SectionWrapper v-if="featuredEvents.length > 0" bg="white">
```

- [ ] **Step 2: Guard the Ministry Highlights section**

Find the `<section class="gradient-dark-mesh py-24 ...">` that contains "Find your place in our community." and the ministry grid. Wrap the entire `<section>` with `v-if="ministryHighlights.length > 0"`:

```html
<!-- ── Ministry Highlights ────────────────────────────────────────────── -->
<section v-if="ministryHighlights.length > 0" class="gradient-dark-mesh py-24 md:py-32 relative overflow-hidden">
```

- [ ] **Step 3: Guard the Latest Sermons section**

Find the `<SectionWrapper bg="surface">` that contains "Recent Sermons". Add `v-if`:

```html
<!-- ── Latest Sermons ──────────────────────────────────────────────────── -->
<SectionWrapper v-if="latestSermons.length > 0" bg="surface">
```

- [ ] **Step 4: Guard the Announcements section**

Find the `<SectionWrapper bg="surface" size="sm">` that contains "Latest Announcements". Add `v-if`:

```html
<!-- ── Announcements ──────────────────────────────────────────────────── -->
<SectionWrapper v-if="announcements.length > 0" bg="surface" size="sm">
```

- [ ] **Step 5: Verify type-check**

```
npm run type-check
```

Expected: no new errors.

---

### Task 2: Home.vue — Gate Livestream CTA on church stream config

**Files:**
- Modify: `app/Http/Controllers/Public/HomeController.php`
- Modify: `resources/js/Pages/Public/Home.vue`

The Livestream CTA (`"Can't join us in person? Watch online."`) is permanently visible on the homepage for every church regardless of whether they have configured a stream URL. A church that doesn't livestream sees a prominent CTA pointing to a page with no video.

Fix: HomeController computes `hasLivestream` (true when `embed_url` or `stream_url` is non-empty), passes it to Home.vue as a boolean prop, and Home.vue gates the section with `v-if`.

- [ ] **Step 1: Pass `hasLivestream` from HomeController**

In `HomeController::__invoke()`, after reading `$homepageSettings`, add:

```php
$lsSettings = $church?->settings['livestream'] ?? [];
```

Then add to the `Inertia::render()` props array:

```php
'hasLivestream' => !empty($lsSettings['embed_url']) || !empty($lsSettings['stream_url']),
```

- [ ] **Step 2: Add `hasLivestream` prop to Home.vue**

In `<script setup>`, add `hasLivestream: boolean` to the `defineProps` block:

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
    hasLivestream:      boolean
}>()
```

- [ ] **Step 3: Gate the Livestream CTA section**

Find the `<section class="gradient-dark-mesh relative overflow-hidden">` that contains "Can't join us in person? Watch online." Add `v-if`:

```html
<!-- ── Livestream CTA ─────────────────────────────────────────────────── -->
<section v-if="props.hasLivestream" class="gradient-dark-mesh relative overflow-hidden">
```

- [ ] **Step 4: Verify type-check**

```
npm run type-check
```

Expected: no new errors.

---

### Task 3: Homepage hero image — upload, store, render

**Files:**
- Modify: `app/Http/Controllers/Dashboard/ChurchSettingsController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Dashboard/Settings/Homepage.vue`
- Modify: `app/Http/Controllers/Public/HomeController.php`
- Modify: `resources/js/Pages/Public/Home.vue`

Every church currently has the same dark gradient hero. Adding a hero image upload (stored in `settings['homepage']['hero_image']`) lets each church brand their homepage with their own photography. When set, the hero renders the image beneath a dark overlay; when absent, the existing `gradient-dark-mesh` is used as before — zero regression.

- [ ] **Step 1: Add `uploadHeroImage()` to ChurchSettingsController**

In `ChurchSettingsController.php`, add this method after `uploadFavicon()`:

```php
/** POST /dashboard/settings/homepage/hero-image */
public function uploadHeroImage(Request $request): RedirectResponse
{
    $this->authorizeSettings();
    $request->validate([
        'hero_image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
    ]);

    $path = $request->file('hero_image')->store('hero-images', 'public');
    $url  = Storage::disk('public')->url($path);

    $this->saveSettings('homepage', ['hero_image' => $url]);

    return back()->with('success', 'Hero image uploaded.');
}
```

Also expose `hero_image` in the `homepage()` render:

```php
'settings' => [
    'hero_description' => $settings['hero_description'] ?? null,
    'hero_image'       => $settings['hero_image']       ?? null,   // ADD THIS LINE
    'stats'            => $settings['stats']            ?? [],
    'testimonials'     => $settings['testimonials']     ?? [],
],
```

- [ ] **Step 2: Add route in web.php**

After the existing `Route::post('/branding/favicon', ...)` line, add the homepage-level upload route. The homepage route group already has `GET + PUT /homepage`. Add:

```php
Route::post('/homepage/hero-image', [ChurchSettingsController::class, 'uploadHeroImage'])->name('homepage.hero-image');
```

- [ ] **Step 3: Add hero image upload card to Settings/Homepage.vue**

In the `<script setup>` block of `Settings/Homepage.vue`:

a) Add `ImagePlus` and `Upload` to the lucide imports:
```ts
import { Plus, Trash2, GripVertical, BarChart2, MessageSquare, AlignLeft, ImagePlus, Upload } from 'lucide-vue-next'
```

b) Add `hero_image` to the `Settings` interface:
```ts
interface Settings {
    hero_description: string | null
    hero_image:       string | null   // ADD
    stats:            StatItem[]
    testimonials:     Testimonial[]
}
```

c) Add hero image upload state after the `submit` function:
```ts
// ── Hero image upload ─────────────────────────────────────────────────────────
import { ref } from 'vue'
import { useForm as useInertiaForm } from '@inertiajs/vue3'

const heroImageFileRef    = ref<HTMLInputElement | null>(null)
const heroImageForm       = useForm({ hero_image: null as File | null })
const heroImagePreviewUrl = ref<string | null>(props.settings.hero_image)

function triggerHeroImageUpload() {
    heroImageFileRef.value?.click()
}

function onHeroImageFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    heroImagePreviewUrl.value = URL.createObjectURL(file)
    heroImageForm.hero_image = file
    heroImageForm.post('/dashboard/settings/homepage/hero-image', {
        preserveScroll: true,
        onSuccess: () => {
            heroImageForm.reset()
            if (heroImageFileRef.value) heroImageFileRef.value.value = ''
        },
    })
}
```

Note: `useForm` is already imported from `@inertiajs/vue3` at the top of the file — use that directly. `ref` also needs to be imported from `vue`. The file already has `import { useForm } from '@inertiajs/vue3'` — so `heroImageForm` uses `useForm`, not a separate alias.

d) Add the hero image card to the template, between the page header `<div class="mb-7">` block and the `<!-- ── Hero description ───` card:

```html
<!-- ── Hero image ────────────────────────────────────────────────────── -->
<div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
    <div class="px-5 py-4 flex items-center gap-2">
        <ImagePlus class="w-4 h-4 text-neutral-400 shrink-0" />
        <div>
            <h3 class="text-sm font-semibold text-neutral-900">Hero image</h3>
            <p class="text-xs text-neutral-500 mt-0.5">
                Background photo shown in the homepage hero. Leave blank to use the default dark gradient.
                Recommended: landscape, at least 1920×1080 px.
            </p>
        </div>
    </div>
    <div class="p-5">
        <!-- Preview -->
        <div v-if="heroImagePreviewUrl" class="mb-4 rounded-xl overflow-hidden aspect-video bg-neutral-100 relative">
            <img :src="heroImagePreviewUrl" class="w-full h-full object-cover" alt="Hero preview" />
        </div>
        <!-- Upload -->
        <input
            ref="heroImageFileRef"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="sr-only"
            @change="onHeroImageFileChange"
        />
        <AppButton
            variant="outline"
            size="sm"
            type="button"
            :loading="heroImageForm.processing"
            @click="triggerHeroImageUpload"
        >
            <Upload class="w-3.5 h-3.5 mr-1.5" />
            {{ heroImagePreviewUrl ? 'Replace image' : 'Upload image' }}
        </AppButton>
        <p class="text-xs text-neutral-400 mt-1.5">JPG, PNG or WebP · max 5 MB</p>
        <p v-if="heroImageForm.errors.hero_image" class="text-xs text-rose-500 mt-1">
            {{ heroImageForm.errors.hero_image }}
        </p>
    </div>
</div>
```

- [ ] **Step 4: Pass `heroImage` from HomeController**

In `HomeController::__invoke()`, add to the Inertia props:

```php
'heroImage' => $homepageSettings['hero_image'] ?? null,
```

- [ ] **Step 5: Render heroImage in Home.vue**

a) Add `heroImage: string | null` to the `defineProps` block in `Home.vue`.

b) Find the hero `<section>` opening tag:
```html
<section class="relative min-h-screen flex flex-col gradient-dark-mesh overflow-hidden">
```

Change it to apply the custom image as a background when present:
```html
<section
    class="relative min-h-screen flex flex-col overflow-hidden"
    :class="props.heroImage ? '' : 'gradient-dark-mesh'"
    :style="props.heroImage ? {
        backgroundImage: `url(${props.heroImage})`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
    } : {}"
>
```

c) The dark overlay div is already present as the first child (`Top accent line` aria-hidden div). Add a full-bleed dark overlay immediately after the accent line div and before the main content div, so it sits between the background image and the text:

```html
<!-- Dark overlay for legibility when a hero image is set -->
<div v-if="props.heroImage" class="absolute inset-0 bg-black/55 pointer-events-none" aria-hidden="true"></div>
```

- [ ] **Step 6: Verify type-check**

```
npm run type-check
```

Expected: no new errors.

---

### Task 4: About.vue — Fix empty values layout

**Files:**
- Modify: `resources/js/Pages/Public/About.vue`

When a church has no values configured in Settings → About Content, the Mission + Values section renders a two-column grid where the left column has the mission text and the right column is empty. This leaves a visually broken half-page blank space. Fix: when `values` is empty, make the mission section span the full width.

- [ ] **Step 1: Make the grid conditional**

In `About.vue`, find the Mission + Values `<SectionWrapper>` inner div:

```html
<div class="grid lg:grid-cols-2 gap-16 items-start">
```

Change it to switch between 1- and 2-column depending on whether there are values:

```html
<div :class="values.length > 0 ? 'grid lg:grid-cols-2 gap-16 items-start' : 'max-w-2xl'">
```

- [ ] **Step 2: Add v-if on the values grid column**

The values grid `<div class="grid grid-cols-2 gap-4 reveal reveal-delay-2">` is the right column. Wrap it with `v-if`:

```html
<div v-if="values.length > 0" class="grid grid-cols-2 gap-4 reveal reveal-delay-2">
```

- [ ] **Step 3: Verify type-check**

```
npm run type-check
```

Expected: no new errors.

---

### Task 5: Livestream.vue — Generalize "Subscribe on YouTube" copy

**Files:**
- Modify: `resources/js/Pages/Public/Livestream.vue`

The Subscribe CTA at the bottom of the Livestream page reads "Subscribe on YouTube to get notified when we go live every Sunday." This assumes YouTube as the provider and Sunday as the service day. Generic copy works for all churches.

- [ ] **Step 1: Update the Subscribe CTA text**

In `Livestream.vue`, find:

```html
<p class="text-neutral-500 mb-7 max-w-sm mx-auto">Subscribe on YouTube to get notified when we go live every Sunday.</p>
```

Change to:

```html
<p class="text-neutral-500 mb-7 max-w-sm mx-auto">Explore our archive of past messages and sermon series.</p>
```

(The button below already links to `/sermons`, so this copy now accurately describes the action.)

- [ ] **Step 2: Final type-check**

```
npm run type-check
```

Expected: same pre-existing errors; zero new errors across all changed files.

---

## Self-Review

**Spec coverage:**
- H1 Empty sections → Task 1 ✅
- H2 Livestream CTA always visible → Task 2 ✅
- H3 No hero image customization → Task 3 ✅
- H4 Broken About layout when values empty → Task 4 ✅
- H5 Hardcoded YouTube copy → Task 5 ✅

**Placeholder scan:** All steps contain exact file paths and complete code snippets. No TBDs.

**Type consistency:** `heroImage: string | null` prop added to both HomeController render and Home.vue `defineProps`. `hero_image: string | null` added to `Settings` interface in Homepage.vue. `hasLivestream: boolean` added to HomeController render and Home.vue `defineProps`. All existing types (`ServiceTime`, `Stat`, etc.) unchanged.
