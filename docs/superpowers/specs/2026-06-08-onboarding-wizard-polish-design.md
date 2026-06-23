# Onboarding Wizard Polish — Design Spec

**Date:** 2026-06-08
**Priority:** Medium
**Approach:** Approach A — Targeted surgical fixes

---

## Overview

Polish the existing 3-step onboarding wizard across 4 areas. No new abstractions, no architectural changes — targeted fixes only.

**In scope:**
1. Form UX gaps (country dropdown, email/password confirmation, autofocus, validation hint)
2. Server error auto-navigation (after failed submission, jump to the step containing the error)
3. Logo upload drag-and-drop (drag-over state, invalid-type rejection)
4. Branding + Welcome screen fixes ("Church Platform" copy removal, Ziggy routes)

**Out of scope:** Terms of service checkbox, email-verified flow changes, onboarding analytics, any layout restructuring.

---

## Area 1: Form UX Gaps

### 1.1 Country field → select dropdown

**File:** `resources/js/Components/Onboarding/StepChurchInfo.vue`  
**File:** `app/Http/Controllers/Onboarding/OnboardingController.php`

- Add a `countries()` private method to `OnboardingController` returning a static array of ~50 common countries following the same `['value' => ..., 'label' => ...]` pattern as `timezoneOptions()`.
- Pass `countries` from `OnboardingController::show()` as a new Inertia prop alongside `timezones`, `colorPresets`, `denominations`.
- Update `Onboarding/Index.vue` props to include `countries: { value: string; label: string }[]`.
- In `StepChurchInfo.vue`, replace the `AppInput` for `country` with a native `<select>` matching the existing denomination select style (`w-full h-10 rounded-lg border ...`).
- First option: `<option value="">Select country (optional)…</option>`. Country remains nullable — no `required` attribute.
- Countries list must include at minimum: United States, United Kingdom, Canada, Australia, Nigeria, Ghana, Kenya, South Africa, India, Jamaica, and ~40 more covering the major English-speaking and African church markets.

### 1.2 Email confirmation field

**File:** `resources/js/Components/Onboarding/StepAdminAccount.vue`  
**File:** `resources/js/Pages/Onboarding/Index.vue`  
**File:** `app/Http/Requests/OnboardingRequest.php`

- Add `admin_email_confirmation: ''` to `useForm()` in `Index.vue`.
- Extend the form proxy type passed to `StepAdminAccount` to include `admin_email_confirmation: string`.
- Add `admin_email_confirmation` input in `StepAdminAccount.vue` immediately after the email input, using `AppInput` with `label="Confirm email address"`, `type="email"`, `placeholder="Confirm your email"`, `required`, `autocomplete="email"`.
- Show per-field error: `v-if="errors.admin_email_confirmation"`.
- In `OnboardingRequest::rules()`, add `'confirmed'` to the `admin_email` rule array. Laravel's `confirmed` rule automatically validates against the `admin_email_confirmation` field.
- Update `validateStep()` in `Index.vue` step-3 branch: add `&& form.admin_email_confirmation === form.admin_email`.

### 1.3 Password confirmation field

**File:** `resources/js/Components/Onboarding/StepAdminAccount.vue`  
**File:** `resources/js/Pages/Onboarding/Index.vue`  
**File:** `app/Http/Requests/OnboardingRequest.php`

- Add `admin_password_confirmation: ''` to `useForm()` in `Index.vue`.
- Add `admin_password_confirmation` to the form proxy type passed to `StepAdminAccount`.
- Add a password confirmation input in `StepAdminAccount.vue` below the strength meter:
  - Uses the same `<input type="password">` pattern with show/hide toggle (own `showPasswordConfirm` ref).
  - Label: "Confirm password". `autocomplete="new-password"`.
  - Inline match feedback shown when `form.admin_password_confirmation.length > 0`:
    - `✓ Passwords match` in `text-emerald-600` when they match.
    - `✗ Passwords don't match` in `text-rose-500` when they don't.
- In `OnboardingRequest::rules()`, add `'confirmed'` to the `admin_password` rule array.
- Update `validateStep()` step-3 branch: add `&& form.admin_password_confirmation === form.admin_password`.

### 1.4 Autofocus per step

**File:** `resources/js/Pages/Onboarding/Index.vue`

- Add a `ref="stepContainer"` to the `<div :key="currentStep">` wrapper inside the `<Transition>`.
- Add a `watch(currentStep, () => { nextTick(() => { (stepContainer.value?.querySelector('input:not([type="file"]):not([type="color"])') as HTMLInputElement | null)?.focus() }) })`.
- This focuses the first text/email/password input of the active step. Step 2 (Branding) has no text inputs so focus silently skips.

### 1.5 Validation hint below Continue button

**File:** `resources/js/Pages/Onboarding/Index.vue`

Add a `stepHint` computed that returns a human-readable string explaining why Continue is disabled, or `null` when validation passes:

```ts
const stepHint = computed<string | null>(() => {
    if (form.processing || validateStep()) return null
    if (currentStep.value === 1) {
        if (form.church_name.trim().length < 2) return 'Enter your church name to continue.'
    }
    if (currentStep.value === 3) {
        if (!form.admin_name.trim())                                    return 'Enter your full name to continue.'
        if (!form.admin_email.includes('@'))                            return 'Enter a valid email address.'
        if (form.admin_email_confirmation !== form.admin_email)         return 'Email addresses don\'t match.'
        if (form.admin_password.length < 8)                            return 'Password must be at least 8 characters.'
        if (form.admin_password_confirmation !== form.admin_password)   return 'Passwords don\'t match.'
    }
    return null
})
```

Render below the nav buttons:
```html
<p v-if="stepHint" class="text-xs text-neutral-400 text-center mt-2">
    {{ stepHint }}
</p>
```

The CTA button remains disabled when validation fails (existing behaviour). The hint simply explains why.

---

## Area 2: Server Error Auto-Navigation

**File:** `resources/js/Pages/Onboarding/Index.vue`

When `form.post('/onboarding')` returns server validation errors, the user currently stays on Step 3 even if the failing field belongs to Step 1 or 2. Fix: in the `onError` callback, inspect the error keys and navigate to the lowest-numbered step that owns at least one failing field.

Add a field-to-step map and helper:

```ts
const STEP_FIELDS = {
    1: ['church_name', 'church_tagline', 'timezone', 'denomination', 'country'],
    2: ['primary_color', 'logo'],
    3: ['admin_name', 'admin_email', 'admin_email_confirmation', 'admin_password', 'admin_password_confirmation'],
} as const

function firstStepWithErrors(errors: Record<string, string>): 1 | 2 | 3 | null {
    for (const step of [1, 2, 3] as const) {
        if (STEP_FIELDS[step].some(f => f in errors)) return step
    }
    return null
}
```

Update `submit()`:

```ts
function submit() {
    form.post('/onboarding', {
        forceFormData: true,
        onError(errors) {
            const step = firstStepWithErrors(errors)
            if (step) {
                direction.value = step < currentStep.value ? 'back' : 'forward'
                currentStep.value = step
            }
        },
    })
}
```

The existing `stepErrors` computed already filters errors to the visible step, so errors appear inline on the correct step without any additional UI changes.

---

## Area 3: Logo Upload Drag-and-Drop

**File:** `resources/js/Components/Onboarding/StepBranding.vue`

Add two reactive refs:

```ts
const isDragging = ref(false)
const dragError  = ref(false)
```

Add three event handlers:

```ts
function onDragOver(e: DragEvent) {
    e.preventDefault()
    isDragging.value = true
}

function onDragLeave(e: DragEvent) {
    // Only clear if leaving the entire zone (not a child element)
    if (!(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node | null)) {
        isDragging.value = false
    }
}

function onDrop(e: DragEvent) {
    e.preventDefault()
    isDragging.value = false
    const file = e.dataTransfer?.files[0] ?? null
    if (!file) return
    const VALID_TYPES = ['image/png', 'image/jpeg', 'image/webp']
    if (!VALID_TYPES.includes(file.type)) {
        dragError.value = true
        setTimeout(() => { dragError.value = false }, 2000)
        return
    }
    // Reuse existing file-processing logic
    props.form.logo = file
    const reader = new FileReader()
    reader.onload = ev => { logoPreviewUrl.value = ev.target?.result as string }
    reader.readAsDataURL(file)
}
```

Update the dropzone button's `:class` binding to reflect all three states:

```html
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
```

Update inner icon and text to reflect drag states:

- When `dragError`: red icon background + "Not an image file" / "PNG, JPG, or WebP only"
- When `isDragging`: brand icon background + "Drop to upload" / "PNG, JPG, WebP up to 2 MB"
- Normal: unchanged

---

## Area 4: Branding + Welcome Screen Fixes

### 4.1 OnboardingLayout.vue

**File:** `resources/js/Layouts/OnboardingLayout.vue`

Three changes:

1. **Desktop left panel logo:** Remove the `<span class="text-white font-semibold text-lg tracking-tight">Church Platform</span>` text node from the logo `<Link>`. Keep the "CP" initials box unchanged.

2. **Mobile logo bar:** Remove the `<span class="text-sm font-semibold text-neutral-900">Church Platform</span>` text node. Keep the "CP" box.

3. **Browser `<title>` template:** Change `{{ title }} — Church Platform` → `{{ title }}`. The plain title is enough; adding "— Church Platform" was SaaS marketing copy.

### 4.2 Welcome.vue

**File:** `resources/js/Pages/Onboarding/Welcome.vue`

Two changes:

1. **Head title:** `<Head title="Welcome to Church Platform" />` → `<Head title="Welcome!" />`

2. **Quick-start links:** Add `import { route } from 'ziggy-js'` to the script setup. Replace the 4 hardcoded `href` strings with Ziggy `route()` calls:
   - `/dashboard/members` → `route('dashboard.members.index')`
   - `/dashboard/departments` → `route('dashboard.departments.index')`
   - `/dashboard/events/create` → `route('dashboard.events.create')`
   - `/dashboard/announcements/create` → `route('dashboard.announcements.create')`

---

## Testing

**New feature test:** `tests/Feature/OnboardingControllerTest.php`

Cover:
1. `GET /onboarding` returns 200 with `timezones`, `colorPresets`, `denominations`, `countries` props
2. Valid full submission creates Church + User, redirects to `onboarding.welcome`
3. `admin_email_confirmation` mismatch returns validation error on `admin_email_confirmation`
4. `admin_password_confirmation` mismatch returns validation error on `admin_password_confirmation`
5. Duplicate `admin_email` returns validation error on `admin_email`

Drag-and-drop and autofocus are client-side only — no backend tests needed.

---

## Files Modified

| File | Change |
|------|--------|
| `app/Http/Controllers/Onboarding/OnboardingController.php` | Add `countries()` method; pass `countries` prop |
| `app/Http/Requests/OnboardingRequest.php` | Add `confirmed` rule to `admin_email` and `admin_password` |
| `resources/js/Pages/Onboarding/Index.vue` | Add confirmation fields to form; `stepHint`; `firstStepWithErrors`; autofocus watch; `countries` prop |
| `resources/js/Components/Onboarding/StepChurchInfo.vue` | Country: free-text → select; add `countries` prop |
| `resources/js/Components/Onboarding/StepAdminAccount.vue` | Add email confirmation + password confirmation fields |
| `resources/js/Components/Onboarding/StepBranding.vue` | Add drag-and-drop handlers + state classes |
| `resources/js/Layouts/OnboardingLayout.vue` | Remove "Church Platform" text × 2; strip title suffix |
| `resources/js/Pages/Onboarding/Welcome.vue` | Update Head title; use Ziggy route() for quick-start links |
| `tests/Feature/OnboardingControllerTest.php` | New — 5 feature tests |
