# Onboarding Wizard Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Polish the 3-step onboarding wizard across four areas: form UX gaps (country dropdown, email/password confirmation, autofocus, validation hint), server error auto-navigation, logo drag-and-drop, and branding copy removal.

**Architecture:** Surgical targeted fixes — no new abstractions. Backend adds a countries option list and `confirmed` validation rules. Frontend wires the new fields in Vue and adds client-side UX logic. All changes are backward-compatible and fully isolated to onboarding files.

**Tech Stack:** Laravel 11 (FormRequest, Inertia responses), Vue 3 + TypeScript + Inertia.js (`useForm`, `defineProps<>`), Tailwind CSS v4, Lucide icons, Ziggy (`route()`)

> ⚠️ **No git in this project** — skip all commit steps. Verify progress with `php artisan test` (backend) and `npm run type-check` (frontend) instead. Pre-existing type errors in VueUse / socket.io / paginator / realtime are acceptable and must NOT increase in modified files.

---

## Task 1: Feature Tests — OnboardingControllerTest

**Files:**
- Create: `tests/Feature/OnboardingControllerTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'church_name'                 => 'Grace Community Church',
            'church_tagline'              => 'A Place to Belong',
            'timezone'                    => 'America/New_York',
            'denomination'                => 'Baptist',
            'country'                     => 'United States',
            'primary_color'               => '#6366f1',
            'admin_name'                  => 'John Smith',
            'admin_email'                 => 'john@grace.org',
            'admin_email_confirmation'    => 'john@grace.org',
            'admin_password'              => 'password123',
            'admin_password_confirmation' => 'password123',
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────────

    public function test_onboarding_page_returns_all_required_props(): void
    {
        $response = $this->get('/onboarding');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Onboarding/Index')
                ->has('timezones')
                ->has('colorPresets')
                ->has('denominations')
                ->has('countries')
            );
    }

    public function test_valid_submission_creates_church_and_admin_and_redirects(): void
    {
        $response = $this->post('/onboarding', $this->validPayload());

        $response->assertRedirect(route('onboarding.welcome'));
        $this->assertDatabaseHas('churches', ['name' => 'Grace Community Church']);
        $this->assertDatabaseHas('users',    ['email' => 'john@grace.org']);
    }

    public function test_email_confirmation_mismatch_fails_validation(): void
    {
        $response = $this->post('/onboarding', $this->validPayload([
            'admin_email_confirmation' => 'typo@grace.org',
        ]));

        $response->assertSessionHasErrors('admin_email');
    }

    public function test_password_confirmation_mismatch_fails_validation(): void
    {
        $response = $this->post('/onboarding', $this->validPayload([
            'admin_password_confirmation' => 'different456',
        ]));

        $response->assertSessionHasErrors('admin_password');
    }

    public function test_duplicate_email_fails_validation(): void
    {
        $church = Church::create([
            'name'              => 'Existing Church',
            'subscription_plan' => 'free',
            'is_active'         => true,
            'settings'          => [],
        ]);
        User::factory()->create([
            'church_id' => $church->id,
            'email'     => 'taken@grace.org',
        ]);

        $response = $this->post('/onboarding', $this->validPayload([
            'admin_email'              => 'taken@grace.org',
            'admin_email_confirmation' => 'taken@grace.org',
        ]));

        $response->assertSessionHasErrors('admin_email');
    }
}
```

- [ ] **Step 2: Run tests — verify all 5 fail**

```
php artisan test --filter OnboardingControllerTest
```

Expected: 5 tests fail. `test_onboarding_page_returns_all_required_props` fails because `countries` prop is missing. `test_valid_submission_creates_church_and_admin_and_redirects` fails because `admin_email_confirmation` / `admin_password_confirmation` are unknown fields. The others fail for the same reason.

---

## Task 2: Backend — Controller + Request

**Files:**
- Modify: `app/Http/Controllers/Onboarding/OnboardingController.php`
- Modify: `app/Http/Requests/OnboardingRequest.php`

- [ ] **Step 1: Add `countryOptions()` to OnboardingController and pass `countries` prop**

Open `app/Http/Controllers/Onboarding/OnboardingController.php`.

Replace the `show()` method with:

```php
public function show(): Response
{
    return Inertia::render('Onboarding/Index', [
        'timezones'    => $this->timezoneOptions(),
        'colorPresets' => $this->colorPresets(),
        'denominations'=> $this->denominationOptions(),
        'countries'    => $this->countryOptions(),
    ]);
}
```

Then add the `countryOptions()` method after the `denominationOptions()` method (before the closing brace of the class):

```php
private function countryOptions(): array
{
    $countries = [
        'Australia', 'Austria', 'Bahamas', 'Barbados', 'Belgium',
        'Brazil', 'Cameroon', 'Canada', 'Chile', 'Colombia',
        'Denmark', 'Dominican Republic', 'Ethiopia', 'Fiji', 'France',
        'Germany', 'Ghana', 'Guyana', 'Haiti', 'India',
        'Ireland', 'Israel', 'Italy', 'Jamaica', 'Kenya',
        'Liberia', 'Mexico', 'Netherlands', 'New Zealand', 'Nigeria',
        'Norway', 'Papua New Guinea', 'Peru', 'Philippines', 'Portugal',
        'Rwanda', 'Sierra Leone', 'Singapore', 'South Africa', 'South Korea',
        'Spain', 'Sweden', 'Switzerland', 'Tanzania', 'Trinidad and Tobago',
        'Uganda', 'United Kingdom', 'United States', 'Zambia', 'Zimbabwe',
        'Other',
    ];

    return array_map(
        fn ($name) => ['value' => $name, 'label' => $name],
        $countries,
    );
}
```

- [ ] **Step 2: Add `confirmed` rules + messages to OnboardingRequest**

Open `app/Http/Requests/OnboardingRequest.php`.

In the `rules()` method, replace the `admin_email` and `admin_password` lines:

```php
// BEFORE:
'admin_email'    => ['required', 'email', 'max:150', 'unique:users,email'],
'admin_password' => ['required', 'string', 'min:8'],

// AFTER:
'admin_email'    => ['required', 'email', 'max:150', 'unique:users,email', 'confirmed'],
'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
```

In the `messages()` method, add these two entries (append before the closing bracket):

```php
'admin_email.confirmed'    => 'Email addresses do not match.',
'admin_password.confirmed' => 'Passwords do not match.',
```

- [ ] **Step 3: Run tests — verify all 5 pass**

```
php artisan test --filter OnboardingControllerTest
```

Expected: 5 tests, 5 passing.

---

## Task 3: Country Dropdown — StepChurchInfo + Index.vue props

**Files:**
- Modify: `resources/js/Components/Onboarding/StepChurchInfo.vue`
- Modify: `resources/js/Pages/Onboarding/Index.vue`

- [ ] **Step 1: Update StepChurchInfo.vue props to accept `countries`**

Open `resources/js/Components/Onboarding/StepChurchInfo.vue`.

Replace the entire `<script setup lang="ts">` block with:

```typescript
<script setup lang="ts">
import AppInput from '@/Components/UI/AppInput.vue'

const props = defineProps<{
    form: {
        church_name:    string
        church_tagline: string
        timezone:       string
        denomination:   string
        country:        string
    }
    errors:        Record<string, string>
    timezones:     { value: string; label: string }[]
    denominations: string[]
    countries:     { value: string; label: string }[]
}>()
</script>
```

- [ ] **Step 2: Replace the country AppInput with a select in StepChurchInfo.vue**

In the template, find the `<AppInput` block for country:

```html
<!-- Country -->
<AppInput
    id="country"
    :model-value="form.country"
    label="Country"
    placeholder="e.g. United States"
    :error="errors.country"
    @update:model-value="form.country = $event"
/>
```

Replace it with:

```html
<!-- Country -->
<div>
    <label class="block text-sm font-medium text-neutral-700 mb-1.5">
        Country
        <span class="text-neutral-400 font-normal">(optional)</span>
    </label>
    <select
        :value="form.country"
        class="w-full h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
        @change="form.country = ($event.target as HTMLSelectElement).value"
    >
        <option value="">Select country (optional)…</option>
        <option v-for="c in countries" :key="c.value" :value="c.value">{{ c.label }}</option>
    </select>
    <p v-if="errors.country" class="text-xs text-rose-500 mt-1.5">{{ errors.country }}</p>
</div>
```

- [ ] **Step 3: Update Index.vue defineProps to include `countries`**

Open `resources/js/Pages/Onboarding/Index.vue`.

Find the `defineProps<{...}>()` block (around line 26) and replace it with:

```typescript
const props = defineProps<{
    timezones:     { value: string; label: string }[]
    colorPresets:  { color: string; name: string }[]
    denominations: string[]
    countries:     { value: string; label: string }[]
}>()
```

- [ ] **Step 4: Pass `countries` to StepChurchInfo in Index.vue template**

In the template, find the `<StepChurchInfo` usage (around line 147) and add `:countries="countries"`:

```html
<StepChurchInfo
    v-if="currentStep === 1"
    :form="form"
    :errors="stepErrors"
    :timezones="timezones"
    :denominations="denominations"
    :countries="countries"
/>
```

- [ ] **Step 5: Run type-check — verify no new errors in modified files**

```
npm run type-check
```

Expected: type-check completes. Pre-existing errors (VueUse / socket.io / paginator / realtime) are fine. No new errors in `StepChurchInfo.vue` or `Index.vue`.

---

## Task 4: Confirmation Fields — StepAdminAccount + Index.vue form state

**Files:**
- Modify: `resources/js/Components/Onboarding/StepAdminAccount.vue`
- Modify: `resources/js/Pages/Onboarding/Index.vue`

- [ ] **Step 1: Update StepAdminAccount.vue script — add confirmation fields to props and add `showPasswordConfirm` ref**

Open `resources/js/Components/Onboarding/StepAdminAccount.vue`.

Replace the `<script setup lang="ts">` block with:

```typescript
<script setup lang="ts">
import { ref, computed } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'
import AppInput from '@/Components/UI/AppInput.vue'

const props = defineProps<{
    form: {
        admin_name:                  string
        admin_email:                 string
        admin_email_confirmation:    string
        admin_password:              string
        admin_password_confirmation: string
    }
    errors: Record<string, string>
}>()

const showPassword        = ref(false)
const showPasswordConfirm = ref(false)

// ── Password strength ────────────────────────────────────────────────────────────
function passwordStrength(pw: string): { score: number; label: string; color: string } {
    if (pw.length === 0) return { score: 0, label: '', color: '' }
    let score = 0
    if (pw.length >= 8)           score++
    if (pw.length >= 12)          score++
    if (/[A-Z]/.test(pw))         score++
    if (/[0-9]/.test(pw))         score++
    if (/[^A-Za-z0-9]/.test(pw))  score++

    if (score <= 1) return { score, label: 'Weak',   color: 'bg-rose-500' }
    if (score <= 3) return { score, label: 'Fair',   color: 'bg-amber-500' }
    if (score <= 4) return { score, label: 'Good',   color: 'bg-emerald-500' }
    return               { score, label: 'Strong', color: 'bg-emerald-600' }
}

const strength = computed(() => passwordStrength(props.form.admin_password))
</script>
```

- [ ] **Step 2: Add email confirmation input to StepAdminAccount.vue template**

In the template, find the `<!-- Email -->` AppInput block:

```html
<!-- Email -->
<AppInput
    id="admin_email"
    :model-value="form.admin_email"
    label="Email address"
    type="email"
    placeholder="you@yourchurch.org"
    required
    :error="errors.admin_email"
    @update:model-value="form.admin_email = $event"
/>
```

Add the email confirmation input **immediately after** it (before the password block):

```html
<!-- Confirm email -->
<AppInput
    id="admin_email_confirmation"
    :model-value="form.admin_email_confirmation"
    label="Confirm email address"
    type="email"
    placeholder="Confirm your email"
    required
    autocomplete="email"
    :error="errors.admin_email_confirmation"
    @update:model-value="form.admin_email_confirmation = $event"
/>
```

- [ ] **Step 3: Add password confirmation input to StepAdminAccount.vue template**

Find the closing `</div>` of the password strength meter block. After it (before the closing `</div>` of the overall `<div class="space-y-5">`), add:

```html
<!-- Confirm password -->
<div>
    <label for="admin_password_confirmation" class="block text-sm font-medium text-neutral-700 mb-1.5">
        Confirm password
    </label>
    <div class="relative">
        <input
            id="admin_password_confirmation"
            :value="form.admin_password_confirmation"
            :type="showPasswordConfirm ? 'text' : 'password'"
            placeholder="Repeat your password"
            autocomplete="new-password"
            class="w-full h-10 rounded-lg border px-3 pr-10 text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
            :class="errors.admin_password_confirmation ? 'border-rose-400 bg-rose-50' : 'border-neutral-200 bg-white'"
            @input="form.admin_password_confirmation = ($event.target as HTMLInputElement).value"
        />
        <button
            type="button"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 text-neutral-400 hover:text-neutral-600 transition-colors"
            @click="showPasswordConfirm = !showPasswordConfirm"
        >
            <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
            <Eye v-else class="w-4 h-4" />
        </button>
    </div>

    <!-- Error -->
    <p v-if="errors.admin_password_confirmation" class="text-xs text-rose-500 mt-1.5">
        {{ errors.admin_password_confirmation }}
    </p>

    <!-- Match feedback -->
    <p
        v-if="form.admin_password_confirmation.length > 0"
        class="text-xs mt-1.5"
        :class="form.admin_password_confirmation === form.admin_password ? 'text-emerald-600' : 'text-rose-500'"
    >
        <span v-if="form.admin_password_confirmation === form.admin_password">✓ Passwords match</span>
        <span v-else>✗ Passwords don't match</span>
    </p>
</div>
```

- [ ] **Step 4: Add confirmation fields to `useForm()` in Index.vue**

Open `resources/js/Pages/Onboarding/Index.vue`.

Find the `useForm({...})` call (around line 40) and replace it with:

```typescript
const form = useForm({
    // Step 1
    church_name:                  '',
    church_tagline:               '',
    timezone:                     'UTC',
    denomination:                 '',
    country:                      '',
    // Step 2
    primary_color:                '#1e5aa8',
    logo:                         null as File | null,
    // Step 3
    admin_name:                   '',
    admin_email:                  '',
    admin_email_confirmation:     '',
    admin_password:               '',
    admin_password_confirmation:  '',
})
```

- [ ] **Step 5: Pass confirmation fields to StepAdminAccount in Index.vue template**

The `StepAdminAccount` component receives the entire `form` proxy via `:form="form"` — no template change needed since the form proxy now includes the new fields. Verify the existing usage looks like this (no change required):

```html
<StepAdminAccount
    v-else
    :form="form"
    :errors="stepErrors"
/>
```

- [ ] **Step 6: Run type-check**

```
npm run type-check
```

Expected: no new errors in `StepAdminAccount.vue` or `Index.vue`.

---

## Task 5: Index.vue Orchestration — stepErrors, validateStep, stepHint, firstStepWithErrors, submit, autofocus

**Files:**
- Modify: `resources/js/Pages/Onboarding/Index.vue`

- [ ] **Step 1: Add `onMounted` and `nextTick` to imports**

Find the existing Vue import line near the top:

```typescript
import { ref, computed } from 'vue'
```

Replace with:

```typescript
import { ref, computed, watch, nextTick, onMounted } from 'vue'
```

- [ ] **Step 2: Add `stepContainer` ref**

After the `const direction = ref<'forward' | 'back'>('forward')` line, add:

```typescript
const stepContainer = ref<HTMLElement | null>(null)
```

- [ ] **Step 3: Update `stepErrors` computed to include new confirmation fields**

Find the `stepErrors` computed (around line 58). Replace the final `return pick(...)` line (the step-3 branch) so the full computed reads:

```typescript
const stepErrors = computed<Record<string, string>>(() => {
    const all = form.errors as Record<string, string>
    if (currentStep.value === 1) {
        return pick(all, ['church_name', 'church_tagline', 'timezone', 'denomination', 'country'])
    }
    if (currentStep.value === 2) {
        return pick(all, ['primary_color', 'logo'])
    }
    return pick(all, ['admin_name', 'admin_email', 'admin_email_confirmation', 'admin_password', 'admin_password_confirmation'])
})
```

- [ ] **Step 4: Update `validateStep()` step-3 branch to require confirmations**

Find `validateStep()` (around line 75). Replace it entirely with:

```typescript
function validateStep(): boolean {
    if (currentStep.value === 1) return form.church_name.trim().length >= 2
    if (currentStep.value === 2) return /^#[0-9a-fA-F]{6}$/.test(form.primary_color)
    return (
        form.admin_name.trim().length > 0 &&
        form.admin_email.includes('@') &&
        form.admin_email_confirmation === form.admin_email &&
        form.admin_password.length >= 8 &&
        form.admin_password_confirmation === form.admin_password
    )
}
```

- [ ] **Step 5: Add `STEP_FIELDS` map + `firstStepWithErrors` helper**

After the `validateStep()` function, add:

```typescript
// ── Server-error step routing ──────────────────────────────────────────────────

const STEP_FIELDS = {
    1: ['church_name', 'church_tagline', 'timezone', 'denomination', 'country'],
    2: ['primary_color', 'logo'],
    3: ['admin_name', 'admin_email', 'admin_email_confirmation', 'admin_password', 'admin_password_confirmation'],
} as const

function firstStepWithErrors(errors: Record<string, string>): 1 | 2 | 3 | null {
    for (const step of [1, 2, 3] as const) {
        if ((STEP_FIELDS[step] as readonly string[]).some((f) => f in errors)) return step
    }
    return null
}
```

- [ ] **Step 6: Update `submit()` to auto-navigate on server errors**

Find the `submit()` function (around line 105). Replace it entirely with:

```typescript
function submit() {
    form.post('/onboarding', {
        forceFormData: true,
        onError(errors) {
            const step = firstStepWithErrors(errors)
            if (step !== null) {
                direction.value = step < currentStep.value ? 'back' : 'forward'
                currentStep.value = step
            }
        },
    })
}
```

- [ ] **Step 7: Add `stepHint` computed**

After the `ctaDisabled` computed, add:

```typescript
const stepHint = computed<string | null>(() => {
    if (form.processing || validateStep()) return null
    if (currentStep.value === 1) {
        if (form.church_name.trim().length < 2) return 'Enter your church name to continue.'
    }
    if (currentStep.value === 3) {
        if (!form.admin_name.trim())                                        return 'Enter your full name to continue.'
        if (!form.admin_email.includes('@'))                                return 'Enter a valid email address.'
        if (form.admin_email_confirmation !== form.admin_email)             return "Email addresses don't match."
        if (form.admin_password.length < 8)                                return 'Password must be at least 8 characters.'
        if (form.admin_password_confirmation !== form.admin_password)       return "Passwords don't match."
    }
    return null
})
```

- [ ] **Step 8: Add autofocus helper + `onMounted` + `watch`**

After the `stepHint` computed, add:

```typescript
// ── Autofocus first input on step change ───────────────────────────────────────

function focusFirstInput() {
    nextTick(() => {
        (stepContainer.value?.querySelector(
            'input:not([type="file"]):not([type="color"])'
        ) as HTMLInputElement | null)?.focus()
    })
}

onMounted(focusFirstInput)
watch(currentStep, focusFirstInput)
```

- [ ] **Step 9: Wire `ref="stepContainer"` in the template**

In the template, find the `<div :key="currentStep">` wrapper inside the `<Transition>` block:

```html
<div :key="currentStep">
```

Replace with:

```html
<div :key="currentStep" ref="stepContainer">
```

- [ ] **Step 10: Add `stepHint` display in the template**

Find the step counter paragraph at the bottom of the template:

```html
<!-- Step counter -->
<p class="text-xs text-neutral-400 text-center mt-4">
    Step {{ currentStep }} of {{ STEPS.length }}
</p>
```

Add the hint **above** it:

```html
<!-- Validation hint -->
<p v-if="stepHint" class="text-xs text-neutral-400 text-center mt-2">
    {{ stepHint }}
</p>

<!-- Step counter -->
<p class="text-xs text-neutral-400 text-center mt-4">
    Step {{ currentStep }} of {{ STEPS.length }}
</p>
```

- [ ] **Step 11: Run type-check**

```
npm run type-check
```

Expected: no new errors in `Index.vue`.

---

## Task 6: Drag-and-Drop — StepBranding.vue

**Files:**
- Modify: `resources/js/Components/Onboarding/StepBranding.vue`

- [ ] **Step 1: Add `isDragging` and `dragError` refs + drag handlers**

Open `resources/js/Components/Onboarding/StepBranding.vue`.

Replace the entire `<script setup lang="ts">` block with:

```typescript
<script setup lang="ts">
import { ref } from 'vue'
import { Upload, X } from 'lucide-vue-next'

const props = defineProps<{
    form: {
        primary_color: string
        logo:          File | null
    }
    errors:       Record<string, string>
    colorPresets: { color: string; name: string }[]
}>()

// ── Logo preview ────────────────────────────────────────────────────────────────
const logoPreviewUrl = ref<string | null>(null)
const fileInput      = ref<HTMLInputElement | null>(null)

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    if (!file) return
    processFile(file)
}

function processFile(file: File) {
    props.form.logo = file
    const reader = new FileReader()
    reader.onload = ev => { logoPreviewUrl.value = ev.target?.result as string }
    reader.readAsDataURL(file)
}

function removeLogo() {
    props.form.logo = null
    logoPreviewUrl.value = null
    if (fileInput.value) fileInput.value.value = ''
}

// ── Drag-and-drop ───────────────────────────────────────────────────────────────
const isDragging = ref(false)
const dragError  = ref(false)

const VALID_TYPES = ['image/png', 'image/jpeg', 'image/webp']

function onDragOver(e: DragEvent) {
    e.preventDefault()
    isDragging.value = true
}

function onDragLeave(e: DragEvent) {
    // Only clear when leaving the zone itself, not when entering a child element
    if (!(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node | null)) {
        isDragging.value = false
    }
}

function onDrop(e: DragEvent) {
    e.preventDefault()
    isDragging.value = false
    const file = e.dataTransfer?.files[0] ?? null
    if (!file) return
    if (!VALID_TYPES.includes(file.type)) {
        dragError.value = true
        setTimeout(() => { dragError.value = false }, 2000)
        return
    }
    processFile(file)
}

// ── Accent color ────────────────────────────────────────────────────────────────
function pickPreset(color: string) {
    props.form.primary_color = color
}
</script>
```

- [ ] **Step 2: Replace the upload dropzone button in the template**

Find the existing `<button v-else ...>` dropzone in the template (the one that shows when `!logoPreviewUrl`). Replace it entirely with:

```html
<!-- Upload dropzone -->
<button
    v-else
    type="button"
    :class="[
        'w-full h-28 border-2 border-dashed rounded-2xl flex flex-col items-center justify-center gap-2 transition-all group',
        dragError  && 'border-rose-300 bg-rose-50 cursor-not-allowed',
        isDragging && !dragError && 'border-brand-400 bg-brand-50/50',
        !isDragging && !dragError && 'border-neutral-200 hover:border-brand-300 hover:bg-brand-50/30',
    ]"
    @click="fileInput?.click()"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
>
    <!-- Error state -->
    <template v-if="dragError">
        <div class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center">
            <X class="w-[18px] h-[18px] text-rose-500" />
        </div>
        <div class="text-center">
            <p class="text-sm font-medium text-rose-600">Not an image file</p>
            <p class="text-xs text-rose-400">PNG, JPG, or WebP only</p>
        </div>
    </template>

    <!-- Drag-over state -->
    <template v-else-if="isDragging">
        <div class="w-10 h-10 bg-brand-100 rounded-xl flex items-center justify-center">
            <Upload class="w-[18px] h-[18px] text-brand-600" />
        </div>
        <div class="text-center">
            <p class="text-sm font-medium text-brand-700">Drop to upload</p>
            <p class="text-xs text-brand-400">PNG, JPG, WebP up to 2 MB</p>
        </div>
    </template>

    <!-- Normal state -->
    <template v-else>
        <div class="w-10 h-10 bg-neutral-100 group-hover:bg-brand-100 rounded-xl flex items-center justify-center transition-colors">
            <Upload class="w-[18px] h-[18px] text-neutral-400 group-hover:text-brand-600 transition-colors" />
        </div>
        <div class="text-center">
            <p class="text-sm font-medium text-neutral-600 group-hover:text-brand-700 transition-colors">Upload logo</p>
            <p class="text-xs text-neutral-400">PNG, JPG, WebP up to 2 MB</p>
        </div>
    </template>
</button>
```

- [ ] **Step 3: Run type-check**

```
npm run type-check
```

Expected: no new errors in `StepBranding.vue`.

---

## Task 7: Branding Fixes — OnboardingLayout + Welcome + Final Verification

**Files:**
- Modify: `resources/js/Layouts/OnboardingLayout.vue`
- Modify: `resources/js/Pages/Onboarding/Welcome.vue`

- [ ] **Step 1: Remove "Church Platform" from the desktop left panel**

Open `resources/js/Layouts/OnboardingLayout.vue`.

Find the desktop logo `<Link>` block:

```html
<Link href="/" class="inline-flex items-center gap-3">
    <div class="w-10 h-10 bg-white/15 backdrop-blur-sm border border-white/20 rounded-xl flex items-center justify-center">
        <span class="text-white font-bold text-sm">CP</span>
    </div>
    <span class="text-white font-semibold text-lg tracking-tight">Church Platform</span>
</Link>
```

Replace with (remove the `<span>` wordmark, keep the "CP" box):

```html
<Link href="/" class="inline-flex items-center gap-3">
    <div class="w-10 h-10 bg-white/15 backdrop-blur-sm border border-white/20 rounded-xl flex items-center justify-center">
        <span class="text-white font-bold text-sm">CP</span>
    </div>
</Link>
```

- [ ] **Step 2: Remove "Church Platform" from the mobile logo bar**

Find the mobile logo bar block:

```html
<div class="lg:hidden flex items-center gap-2.5 px-6 py-4 border-b border-neutral-100">
    <div class="w-8 h-8 gradient-brand rounded-lg flex items-center justify-center">
        <span class="text-white text-xs font-bold">CP</span>
    </div>
    <span class="text-sm font-semibold text-neutral-900">Church Platform</span>
</div>
```

Replace with (remove the text span):

```html
<div class="lg:hidden flex items-center gap-2.5 px-6 py-4 border-b border-neutral-100">
    <div class="w-8 h-8 gradient-brand rounded-lg flex items-center justify-center">
        <span class="text-white text-xs font-bold">CP</span>
    </div>
</div>
```

- [ ] **Step 3: Strip "— Church Platform" from the `<title>` template**

Find the `<Head>` block in `OnboardingLayout.vue`:

```html
<Head>
    <title v-if="title">{{ title }} — Church Platform</title>
    <title v-else>Church Platform</title>
</Head>
```

Replace with:

```html
<Head>
    <title v-if="title">{{ title }}</title>
    <title v-else>Church Setup</title>
</Head>
```

- [ ] **Step 4: Update Welcome.vue Head title**

Open `resources/js/Pages/Onboarding/Welcome.vue`.

Find:

```html
<Head title="Welcome to Church Platform" />
```

Replace with:

```html
<Head title="Welcome!" />
```

- [ ] **Step 5: Add Ziggy `route()` import to Welcome.vue and replace hardcoded hrefs**

Find the existing import block in `<script setup lang="ts">`:

```typescript
import { Link, usePage } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
```

Replace with:

```typescript
import { Head, Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
```

Then find the `quickstarts` array (around line 27). Replace the 4 `href` values with Ziggy route calls:

```typescript
const quickstarts = [
    {
        icon:  Users,
        label: 'Invite team members',
        desc:  'Add staff, coordinators and volunteers',
        href:  route('dashboard.members.index'),
        color: 'bg-brand-50 text-brand-600',
    },
    {
        icon:  Building2,
        label: 'Set up departments',
        desc:  'We created 4 starter departments for you',
        href:  route('dashboard.departments.index'),
        color: 'bg-brand-50 text-brand-600',
    },
    {
        icon:  CalendarDays,
        label: 'Create your first event',
        desc:  'Services, meetings, outreach programs',
        href:  route('dashboard.events.create'),
        color: 'bg-emerald-50 text-emerald-600',
    },
    {
        icon:  Megaphone,
        label: 'Post an announcement',
        desc:  'Keep your church community informed',
        href:  route('dashboard.announcements.create'),
        color: 'bg-amber-50 text-amber-600',
    },
]
```

- [ ] **Step 6: Final type-check**

```
npm run type-check
```

Expected: no new errors in `OnboardingLayout.vue` or `Welcome.vue`.

- [ ] **Step 7: Final test run**

```
php artisan test --filter OnboardingControllerTest
```

Expected: 5 tests, 5 passing.

- [ ] **Step 8: Full test suite**

```
php artisan test
```

Expected: all tests pass (including all pre-existing test suites). No regressions.
