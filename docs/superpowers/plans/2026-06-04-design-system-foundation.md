# Design System Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish the CSS token foundation and upgrade/create 8 core UI components (AppButton, AppInput, AppTextarea, AppBadge, AppCard, AppSkeleton, plus StatCard migration) that every page in the full UX overhaul will depend on.

**Architecture:** All changes are isolated to `resources/css/app.css` and `resources/js/Components/UI/` (plus one Dashboard component). Components maintain backward-compatible APIs — existing callers continue to work without modification. Verification is `npm run type-check` + browser, since this project has no automated test runner.

**Tech Stack:** Vue 3 + TypeScript, Tailwind CSS v4 (`@theme {}`), Lucide Vue Next icons.

---

> **SCOPE — This is Plan 1 of 4 in the UX Overhaul:**
> - **Plan 1 (this):** Design System Foundation — CSS tokens + UI component library
> - **Plan 2:** Layout & Navigation — DashboardLayout sidebar, mobile nav, PageHeader standardization, Settings layout
> - **Plan 3:** Dashboard Redesign — Home.vue as an operations command center
> - **Plan 4:** Module Pages — Apply new patterns to Members, Events, Tasks, Sermons, Communication, Attendance, Reports
>
> Execute Plan 1 fully before beginning Plan 2.

---

## File Map

**Modified:**
- `resources/css/app.css` — add shadow tokens, semantic color scale, dark-mode CSS var scaffolding
- `resources/js/Components/UI/AppButton.vue` — add `xs` size + `square` icon-only prop
- `resources/js/Components/UI/AppInput.vue` — add `hint`, `#leading`/`#trailing` icon slots, `disabled`, `sm` size
- `resources/js/Components/UI/AppTextarea.vue` — add `hint`, `disabled`, `maxlength` char-counter
- `resources/js/Components/UI/AppBadge.vue` — add `dot` prop + semantic color aliases (success/warning/error/info)
- `resources/js/Components/Dashboard/StatCard.vue` — adopt `shadow-card` token, `rounded-xl` consistency

**Created:**
- `resources/js/Components/UI/AppCard.vue` — standardized card wrapper (replaces ad-hoc `bg-white border border-neutral-100 rounded-xl/2xl` patterns everywhere)
- `resources/js/Components/UI/AppSkeleton.vue` — animated loading placeholder (lines, circle, rect modes)

---

## Task 1: Extend app.css design tokens

**Files:**
- Modify: `resources/css/app.css`

- [ ] **Step 1: Replace app.css with the extended token set**

The existing file already has the brand palette and gradients. Add shadow tokens, semantic status colors, dark-mode CSS variable scaffolding, and update `card-hover` to use the new shadow token. Complete replacement:

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.ts';
@source '../**/*.vue';

/* ── Design system tokens ──────────────────────────────────────────────────── */
@theme {
    /* Typography */
    --font-sans:  'Inter', ui-sans-serif, system-ui, sans-serif;
    --font-serif: 'DM Serif Display', ui-serif, Georgia, serif;

    /* Brand palette — Indigo/Violet (overridden at runtime via useBrandColor) */
    --color-brand-50:  #eef2ff;
    --color-brand-100: #e0e7ff;
    --color-brand-200: #c7d2fe;
    --color-brand-300: #a5b4fc;
    --color-brand-400: #818cf8;
    --color-brand-500: #6366f1;
    --color-brand-600: #4f46e5;
    --color-brand-700: #4338ca;
    --color-brand-800: #3730a3;
    --color-brand-900: #312e81;

    /* Semantic status colors */
    --color-success-50:  #f0fdf4;
    --color-success-100: #dcfce7;
    --color-success-500: #22c55e;
    --color-success-600: #16a34a;
    --color-success-700: #15803d;

    --color-warning-50:  #fffbeb;
    --color-warning-100: #fef3c7;
    --color-warning-500: #f59e0b;
    --color-warning-600: #d97706;
    --color-warning-700: #b45309;

    --color-error-50:    #fff1f2;
    --color-error-100:   #ffe4e6;
    --color-error-500:   #f43f5e;
    --color-error-600:   #e11d48;
    --color-error-700:   #be123c;

    --color-info-50:     #eff6ff;
    --color-info-100:    #dbeafe;
    --color-info-500:    #3b82f6;
    --color-info-600:    #2563eb;
    --color-info-700:    #1d4ed8;

    /* Surface & border */
    --color-surface:      #fafafa;
    --color-border:       #e5e7eb;
    --color-muted:        #6b7280;
    --color-muted-light:  #9ca3af;

    /* Shadow scale — used as `shadow-card`, `shadow-elevated`, `shadow-overlay` */
    --shadow-card:     0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.04);
    --shadow-elevated: 0 4px 16px -2px rgb(0 0 0 / 0.08), 0 2px 8px -2px rgb(0 0 0 / 0.04);
    --shadow-overlay:  0 20px 60px -8px rgb(0 0 0 / 0.18), 0 8px 20px -4px rgb(0 0 0 / 0.10);
}

/* ── Base ──────────────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--font-sans);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Dark mode: CSS variable overrides — scaffolded here, values wired in a future plan */
:root {
    --ui-bg:      #ffffff;
    --ui-surface: #fafafa;
    --ui-border:  #f0f0f0;
    --ui-text:    #0f172a;
    --ui-muted:   #6b7280;
}

/* ── Scroll reveal ─────────────────────────────────────────────────────────── */
.reveal {
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.65s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.65s cubic-bezier(0.16, 1, 0.3, 1);
}
.reveal-visible { opacity: 1; transform: translateY(0); }
.reveal-delay-1 { transition-delay: 0.1s; }
.reveal-delay-2 { transition-delay: 0.2s; }
.reveal-delay-3 { transition-delay: 0.3s; }
.reveal-delay-4 { transition-delay: 0.4s; }

/* ── Gradients ─────────────────────────────────────────────────────────────── */
.gradient-brand {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}
.gradient-text {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.gradient-hero {
    background: radial-gradient(ellipse 90% 55% at 50% -5%, rgba(99,102,241,0.11) 0%, transparent 68%);
}
.gradient-mesh {
    background-color: #ffffff;
    background-image:
        radial-gradient(at 20% 10%, rgba(99,102,241,0.07) 0px, transparent 50%),
        radial-gradient(at 80% 60%, rgba(139,92,246,0.06) 0px, transparent 50%);
}

/* ── Card hover lift (uses new elevated shadow token) ──────────────────────── */
.card-hover {
    transition: box-shadow 0.25s ease, transform 0.25s ease;
}
.card-hover:hover {
    box-shadow: var(--shadow-elevated);
    transform: translateY(-2px);
}

/* ── Focus ring ────────────────────────────────────────────────────────────── */
:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
    border-radius: 4px;
}
```

- [ ] **Step 2: Verify build compiles**

```bash
npm run build
```

Expected: exits 0, no CSS errors. The new `shadow-card`, `shadow-elevated`, `shadow-overlay` classes are now available as Tailwind utilities. The new `success-*`, `warning-*`, `error-*`, `info-*` color utilities are available.

- [ ] **Step 3: Visual check**

Run `npm run dev`, open any dashboard page in the browser. The page should look identical to before — this step adds new utilities without removing anything.

---

## Task 2: AppButton.vue — xs size + square icon-only prop

**Files:**
- Modify: `resources/js/Components/UI/AppButton.vue`

The existing component has `sm | md | lg` sizes and 5 color variants. We add:
- `xs` size: `px-2.5 py-1.5 text-xs gap-1` (for tight toolbars and inline actions)
- `square?: boolean` prop: when `true`, replaces rectangle padding with equal padding on all sides, making icon-only buttons without visible text spacing

- [ ] **Step 1: Replace AppButton.vue with the upgraded version**

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Loader2 } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
    href?:     string
    variant?:  'primary' | 'secondary' | 'ghost' | 'outline' | 'danger'
    size?:     'xs' | 'sm' | 'md' | 'lg'
    /**
     * When true: renders a square button suitable for icon-only usage.
     * Replaces the asymmetric text-button padding with equal padding on all sides.
     * Example: <AppButton square size="sm" variant="ghost"><Pencil /></AppButton>
     */
    square?:   boolean
    external?: boolean
    disabled?: boolean
    loading?:  boolean
    type?:     'button' | 'submit' | 'reset'
}>(), {
    variant: 'primary',
    size:    'md',
    square:  false,
    type:    'button',
})

const base =
    'inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-200 ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 ' +
    'disabled:opacity-50 disabled:cursor-not-allowed select-none'

const variants: Record<string, string> = {
    primary:   'bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-800 shadow-sm',
    secondary: 'bg-brand-50 text-brand-700 hover:bg-brand-100 active:bg-brand-200',
    outline:   'border border-neutral-200 text-neutral-700 bg-white hover:bg-neutral-50 hover:border-neutral-300',
    ghost:     'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900',
    danger:    'border border-rose-200 text-rose-600 bg-white hover:bg-rose-50 hover:border-rose-300',
}

/** Padding for text buttons (asymmetric: more horizontal than vertical) */
const sizes: Record<string, string> = {
    xs: 'px-2.5 py-1.5 text-xs gap-1',
    sm: 'px-3.5 py-2 text-sm',
    md: 'px-5 py-2.5 text-sm',
    lg: 'px-7 py-3.5 text-base',
}

/** Padding for icon-only square buttons (equal on all sides) */
const squareSizes: Record<string, string> = {
    xs: 'p-1.5 text-xs',
    sm: 'p-2 text-sm',
    md: 'p-2.5 text-sm',
    lg: 'p-3 text-base',
}

const isDisabled = computed(() => props.disabled || props.loading)
const classes    = computed(() => [
    base,
    variants[props.variant],
    props.square ? squareSizes[props.size] : sizes[props.size],
])
</script>

<template>
    <!-- Inertia Link (internal href) -->
    <Link
        v-if="href && !external"
        :href="href"
        :class="classes"
    >
        <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
        <slot />
    </Link>

    <!-- Native anchor (external href) -->
    <a
        v-else-if="href && external"
        :href="href"
        target="_blank"
        rel="noopener noreferrer"
        :class="classes"
    >
        <slot />
    </a>

    <!-- Button -->
    <button
        v-else
        :type="type"
        :disabled="isDisabled"
        :class="classes"
    >
        <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
        <slot />
    </button>
</template>
```

- [ ] **Step 2: Type-check**

```bash
npm run type-check
```

Expected: 0 errors. All existing callers pass because `xs` is additive and `square` defaults to `false`.

- [ ] **Step 3: Visual verification**

Navigate to `/dashboard/members`. Existing buttons should look identical. To test new features, temporarily add to any page:
```html
<AppButton size="xs">Extra small</AppButton>
<AppButton size="sm" :square="true" variant="ghost"><Pencil class="w-4 h-4" /></AppButton>
```
Then remove the test markup before committing.

---

## Task 3: AppInput.vue — hint text, icon slots, disabled state, sm size

**Files:**
- Modify: `resources/js/Components/UI/AppInput.vue`

Current: label, placeholder, type, error, required, id props only.

Additions:
- `hint?: string` — helper text shown below the field when there is no error (mutually exclusive with `error`)
- `disabled?: boolean` — makes input non-interactive with muted visual
- `readonly?: boolean` — same visual as disabled
- `size?: 'sm' | 'md'` — `sm` has 8px vertical padding and xs text (for compact forms), `md` is the existing default
- `#leading` slot — content absolutely positioned inside the left edge of the input (typically an icon)
- `#trailing` slot — content absolutely positioned inside the right edge

- [ ] **Step 1: Replace AppInput.vue**

```vue
<script setup lang="ts">
import { computed, useSlots } from 'vue'

const props = withDefaults(defineProps<{
    modelValue:   string | number
    label?:       string
    placeholder?: string
    type?:        string
    error?:       string
    hint?:        string
    required?:    boolean
    disabled?:    boolean
    readonly?:    boolean
    size?:        'sm' | 'md'
    id?:          string
}>(), {
    type: 'text',
    size: 'md',
})

const emit  = defineEmits<{ 'update:modelValue': [value: string] }>()
const slots = useSlots()

const inputClasses = computed(() => [
    'w-full bg-white border rounded-lg text-neutral-900 placeholder:text-neutral-400',
    'transition-colors duration-150 focus:outline-none',
    // Size
    props.size === 'sm'
        ? 'px-3 py-1.5 text-xs'
        : 'px-3.5 py-2.5 text-sm',
    // Leading/trailing icon padding adjustments
    slots.leading  ? (props.size === 'sm' ? 'pl-8'  : 'pl-10') : '',
    slots.trailing ? (props.size === 'sm' ? 'pr-8'  : 'pr-10') : '',
    // Border & focus ring
    props.error
        ? 'border-rose-400 focus:border-rose-400 focus:ring-3 focus:ring-rose-500/10'
        : 'border-neutral-200 focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10',
    // Disabled / readonly visual
    (props.disabled || props.readonly)
        ? 'bg-neutral-50 opacity-60 cursor-not-allowed'
        : '',
])
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <!-- Label -->
        <label v-if="label" :for="id" class="text-sm font-medium text-neutral-700">
            {{ label }}<span v-if="required" class="text-rose-500 ml-0.5">*</span>
        </label>

        <!-- Input wrapper (position:relative for icon slots) -->
        <div class="relative">
            <!-- Leading icon -->
            <div
                v-if="$slots.leading"
                :class="[
                    'absolute inset-y-0 left-0 flex items-center pointer-events-none text-neutral-400',
                    size === 'sm' ? 'pl-2.5' : 'pl-3.5',
                ]"
            >
                <slot name="leading" />
            </div>

            <input
                :id="id"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :readonly="readonly"
                :class="inputClasses"
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            />

            <!-- Trailing icon -->
            <div
                v-if="$slots.trailing"
                :class="[
                    'absolute inset-y-0 right-0 flex items-center pointer-events-none text-neutral-400',
                    size === 'sm' ? 'pr-2.5' : 'pr-3.5',
                ]"
            >
                <slot name="trailing" />
            </div>
        </div>

        <!-- Error or hint (mutually exclusive: error takes priority) -->
        <p v-if="error" class="text-xs text-rose-500">{{ error }}</p>
        <p v-else-if="hint" class="text-xs text-neutral-400">{{ hint }}</p>
    </div>
</template>
```

- [ ] **Step 2: Type-check**

```bash
npm run type-check
```

Expected: 0 errors. Existing callers (which pass only `modelValue`, `label`, `error`, etc.) continue to work because all new props default to `undefined`/`'md'`.

- [ ] **Step 3: Visual verification**

Navigate to any form page (e.g. `/dashboard/events/create`). All existing inputs should look exactly the same. To test new features, temporarily add to any form:
```html
<!-- Hint text -->
<AppInput v-model="val" label="Church Name" hint="This appears on your public website" />

<!-- Leading icon -->
<AppInput v-model="search" placeholder="Search...">
    <template #leading><Search class="w-4 h-4" /></template>
</AppInput>

<!-- Disabled -->
<AppInput v-model="val" label="Email" disabled />
```
Remove test markup before moving on.

---

## Task 4: AppTextarea.vue — hint, disabled, maxlength character counter

**Files:**
- Modify: `resources/js/Components/UI/AppTextarea.vue`

- [ ] **Step 1: Replace AppTextarea.vue**

```vue
<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    modelValue:   string
    label?:       string
    placeholder?: string
    rows?:        number
    error?:       string
    hint?:        string
    required?:    boolean
    disabled?:    boolean
    maxlength?:   number
    id?:          string
}>(), {
    rows: 4,
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const charCount    = computed(() => props.modelValue?.length ?? 0)
const isOverLimit  = computed(() => props.maxlength !== undefined && charCount.value > props.maxlength)
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <!-- Label row — label on left, char counter on right -->
        <div class="flex items-center justify-between gap-2">
            <label v-if="label" :for="id" class="text-sm font-medium text-neutral-700">
                {{ label }}<span v-if="required" class="text-rose-500 ml-0.5">*</span>
            </label>
            <span
                v-if="maxlength"
                :class="[
                    'text-xs tabular-nums shrink-0',
                    isOverLimit ? 'text-rose-500 font-medium' : 'text-neutral-400',
                ]"
            >
                {{ charCount }}/{{ maxlength }}
            </span>
        </div>

        <textarea
            :id="id"
            :value="modelValue"
            :placeholder="placeholder"
            :rows="rows"
            :required="required"
            :disabled="disabled"
            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 transition-colors duration-150 focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10 resize-none"
            :class="{
                'border-rose-400 focus:ring-rose-500/10 focus:border-rose-400': error || isOverLimit,
                'bg-neutral-50 opacity-60 cursor-not-allowed': disabled,
            }"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />

        <p v-if="error" class="text-xs text-rose-500">{{ error }}</p>
        <p v-else-if="hint" class="text-xs text-neutral-400">{{ hint }}</p>
    </div>
</template>
```

- [ ] **Step 2: Type-check**

```bash
npm run type-check
```

Expected: 0 errors.

- [ ] **Step 3: Visual verification**

Navigate to `/dashboard/announcements/create` or `/dashboard/events/create` — any page with a textarea. Body field should look identical. To test char count: temporarily add `:maxlength="280"` to any textarea and type into it; the counter should appear top-right and turn red at 280 chars.

---

## Task 5: AppBadge.vue — dot variant + semantic color aliases

**Files:**
- Modify: `resources/js/Components/UI/AppBadge.vue`

Add:
- `dot?: boolean` — shows a small colored circle before the text (useful for status indicators like "• Active", "• Pending")
- Semantic color names: `success`, `warning`, `error`, `info` as aliases for `emerald`, `amber`, `rose`, `blue`
- Original color names remain for backward compatibility

- [ ] **Step 1: Replace AppBadge.vue**

```vue
<script setup lang="ts">
withDefaults(defineProps<{
    /**
     * Color variant.
     * Prefer semantic names: success | warning | error | info | brand | neutral
     * Legacy Tailwind color names still accepted: emerald | amber | rose | blue | violet
     */
    color?: 'brand' | 'success' | 'warning' | 'error' | 'info' | 'neutral'
          | 'emerald' | 'amber' | 'rose' | 'blue' | 'violet'
    size?:  'sm' | 'md'
    /** Prepend a small colored dot — useful for status labels like "● Active" */
    dot?:   boolean
}>(), {
    color: 'neutral',
    size:  'md',
    dot:   false,
})

const colorMap: Record<string, string> = {
    // Semantic (preferred)
    brand:   'bg-brand-50   text-brand-700',
    success: 'bg-emerald-50 text-emerald-700',
    warning: 'bg-amber-50   text-amber-700',
    error:   'bg-rose-50    text-rose-700',
    info:    'bg-blue-50    text-blue-700',
    neutral: 'bg-neutral-100 text-neutral-600',
    // Legacy aliases
    emerald: 'bg-emerald-50 text-emerald-700',
    amber:   'bg-amber-50   text-amber-700',
    rose:    'bg-rose-50    text-rose-700',
    blue:    'bg-blue-50    text-blue-700',
    violet:  'bg-violet-50  text-violet-700',
}

const dotColorMap: Record<string, string> = {
    brand:   'bg-brand-500',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    error:   'bg-rose-500',
    info:    'bg-blue-500',
    neutral: 'bg-neutral-400',
    emerald: 'bg-emerald-500',
    amber:   'bg-amber-500',
    rose:    'bg-rose-500',
    blue:    'bg-blue-500',
    violet:  'bg-violet-500',
}

const sizeMap: Record<string, string> = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-xs',
}
</script>

<template>
    <span :class="['inline-flex items-center gap-1.5 font-medium rounded-full', colorMap[color], sizeMap[size]]">
        <span v-if="dot" :class="['w-1.5 h-1.5 rounded-full shrink-0', dotColorMap[color]]" />
        <slot />
    </span>
</template>
```

- [ ] **Step 2: Type-check**

```bash
npm run type-check
```

Expected: 0 errors. All existing usages (which pass color names like 'brand', 'emerald', etc.) continue working.

- [ ] **Step 3: Visual verification**

Navigate to `/dashboard/members` — role badges should look identical (they use `bg-violet-50 text-violet-700` inline classes, not AppBadge). Any page using AppBadge should look the same. Temporarily test:
```html
<AppBadge color="success" dot>Active</AppBadge>
<AppBadge color="warning" dot>Pending</AppBadge>
<AppBadge color="error" dot>Failed</AppBadge>
```

---

## Task 6: AppCard.vue — create standardized card wrapper

**Files:**
- Create: `resources/js/Components/UI/AppCard.vue`

This is the most impactful component in this plan. It replaces the ad-hoc pattern used on every single dashboard page:
```html
<!-- Before (scattered, inconsistent rounded-xl vs rounded-2xl): -->
<div class="bg-white border border-neutral-100 rounded-2xl p-6">
<div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">

<!-- After (one component, one decision): -->
<AppCard>
<AppCard padding="none">  <!-- for tables that need full-bleed -->
```

- [ ] **Step 1: Create `resources/js/Components/UI/AppCard.vue`**

```vue
<script setup lang="ts">
/**
 * AppCard — the standard surface container for all dashboard modules.
 *
 * Replaces the ad-hoc `bg-white border border-neutral-100 rounded-xl/2xl` pattern
 * used throughout the codebase with a single, consistent component.
 *
 * Variants:
 *   default  — white bg, subtle border, card shadow (standard dashboard card)
 *   flat     — white bg, border only, no shadow (table wrappers, filter panels)
 *   elevated — white bg, stronger shadow, no border (modals, popovers)
 *   ghost    — off-white bg, no border, no shadow (secondary content areas)
 *
 * Padding presets:
 *   none — no padding (use for tables, images, full-bleed content)
 *   sm   — p-4 (compact cards)
 *   md   — p-5 lg:p-6 (standard — matches current spacing in most pages)
 *   lg   — p-6 lg:p-8 (spacious hero/feature cards)
 *
 * Usage:
 *   <AppCard>Standard card</AppCard>
 *   <AppCard padding="none" class="overflow-hidden">Table card</AppCard>
 *   <AppCard variant="elevated" padding="lg">Featured content</AppCard>
 *   <AppCard :hover="true" as="button" @click="handleClick">Clickable card</AppCard>
 */
withDefaults(defineProps<{
    variant?: 'default' | 'flat' | 'elevated' | 'ghost'
    padding?: 'none' | 'sm' | 'md' | 'lg'
    hover?:   boolean
    /** Rendered HTML element or component — useful for `as="button"` or `as="article"` */
    as?:      string
}>(), {
    variant: 'default',
    padding: 'md',
    hover:   false,
    as:      'div',
})
</script>

<template>
    <component
        :is="as"
        :class="[
            'rounded-xl transition-all duration-200',
            // Variant
            variant === 'default'  && 'bg-white border border-neutral-100 shadow-card',
            variant === 'flat'     && 'bg-white border border-neutral-100',
            variant === 'elevated' && 'bg-white shadow-elevated',
            variant === 'ghost'    && 'bg-neutral-50/80',
            // Padding
            padding === 'none' && '',
            padding === 'sm'   && 'p-4',
            padding === 'md'   && 'p-5 lg:p-6',
            padding === 'lg'   && 'p-6 lg:p-8',
            // Hover lift — for clickable cards
            hover && 'hover:shadow-elevated hover:-translate-y-0.5 cursor-pointer',
        ]"
    >
        <slot />
    </component>
</template>
```

- [ ] **Step 2: Type-check**

```bash
npm run type-check
```

Expected: 0 errors. (This is a new file — no existing callers to break.)

- [ ] **Step 3: Visual verification**

AppCard is not yet used by any page, so no regressions are possible. To test it, temporarily add to any dashboard page:
```html
<AppCard class="mt-4">
    <p class="text-sm">Default card</p>
</AppCard>
<AppCard variant="ghost" padding="sm" class="mt-2">
    <p class="text-sm">Ghost card</p>
</AppCard>
<AppCard padding="none" class="mt-2 overflow-hidden">
    <table class="w-full text-sm">
        <tr><td class="px-5 py-3">Table in card</td></tr>
    </table>
</AppCard>
```
Verify all 3 look correct, then remove.

---

## Task 7: AppSkeleton.vue — create loading placeholder

**Files:**
- Create: `resources/js/Components/UI/AppSkeleton.vue`

The existing DataTable component has its own skeleton rows but there's no standalone, reusable skeleton primitive for use in custom cards and list items.

- [ ] **Step 1: Create `resources/js/Components/UI/AppSkeleton.vue`**

```vue
<script setup lang="ts">
/**
 * AppSkeleton — animated loading placeholder.
 *
 * Used to show the shape of content while data loads, preventing layout shift
 * and providing better perceived performance than a spinner.
 *
 * Types:
 *   line   — a text line (default). Use `lines` prop for a paragraph.
 *   circle — circular placeholder (avatars, icons).
 *   rect   — rectangular placeholder (images, thumbnails, charts).
 *
 * Sizing: control via Tailwind class props on the parent or the component itself.
 *   Circle: <AppSkeleton type="circle" class="w-10 h-10" />
 *   Rect:   <AppSkeleton type="rect" class="h-32 w-full" />
 *   Lines:  <AppSkeleton :lines="3" />  → full / full / 3/4
 *
 * Usage in a loading card:
 *   <AppCard v-if="loading">
 *     <AppSkeleton class="h-5 w-1/3 mb-4" />
 *     <AppSkeleton :lines="3" />
 *   </AppCard>
 */
withDefaults(defineProps<{
    type?:  'line' | 'circle' | 'rect'
    /** Number of stacked text lines. Only used when type === 'line'. */
    lines?: number
}>(), {
    type:  'line',
    lines: 1,
})
</script>

<template>
    <!-- Circle (avatar/icon placeholder) -->
    <div
        v-if="type === 'circle'"
        class="bg-neutral-100 animate-pulse rounded-full"
    />

    <!-- Rect (image/card body placeholder) -->
    <div
        v-else-if="type === 'rect'"
        class="bg-neutral-100 animate-pulse rounded-lg"
    />

    <!-- Line(s) — default -->
    <div v-else class="space-y-2.5">
        <div
            v-for="i in lines"
            :key="i"
            class="bg-neutral-100 animate-pulse rounded h-4"
            :class="i === lines && lines > 1 ? 'w-3/4' : 'w-full'"
        />
    </div>
</template>
```

- [ ] **Step 2: Type-check**

```bash
npm run type-check
```

Expected: 0 errors.

- [ ] **Step 3: Visual verification**

Temporarily add to any page to see the skeleton in context:
```html
<div class="p-6 space-y-4 max-w-sm">
    <!-- Avatar + line -->
    <div class="flex items-center gap-3">
        <AppSkeleton type="circle" class="w-10 h-10 shrink-0" />
        <div class="flex-1 space-y-2">
            <AppSkeleton class="h-3 w-1/2" />
            <AppSkeleton class="h-3 w-3/4" />
        </div>
    </div>
    <!-- Paragraph -->
    <AppSkeleton :lines="4" />
    <!-- Rect -->
    <AppSkeleton type="rect" class="h-32 w-full" />
</div>
```
All three shapes should pulse with `bg-neutral-100`. Remove test markup when done.

---

## Task 8: StatCard.vue — adopt shadow-card token, standardize to rounded-xl

**Files:**
- Modify: `resources/js/Components/Dashboard/StatCard.vue`

The existing component uses `rounded-2xl` while the rest of the dashboard will standardize on `rounded-xl`. It also has an inline skeleton that can now use AppSkeleton, and the card wrapper can adopt the new `shadow-card` token.

Note: the external API (props, emits) is unchanged — only the card's visual shell is updated.

- [ ] **Step 1: Replace StatCard.vue**

```vue
<script setup lang="ts">
import type { Component } from 'vue'
import AppSkeleton from '@/Components/UI/AppSkeleton.vue'

defineProps<{
    label:    string
    value:    string | number
    icon?:    Component
    trend?:   { value: string; direction: 'up' | 'down' | 'flat' }
    color?:   'brand' | 'emerald' | 'amber' | 'rose' | 'violet' | 'blue'
    loading?: boolean
}>()

const colorMap: Record<string, string> = {
    brand:   'bg-brand-50   text-brand-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber:   'bg-amber-50   text-amber-600',
    rose:    'bg-rose-50    text-rose-600',
    violet:  'bg-violet-50  text-violet-600',
    blue:    'bg-blue-50    text-blue-600',
}
</script>

<template>
    <!-- rounded-xl (standardized), shadow-card (new token) -->
    <div class="bg-white border border-neutral-100 rounded-xl shadow-card p-5">
        <!-- Loading skeleton -->
        <template v-if="loading">
            <div class="flex items-center justify-between mb-4">
                <AppSkeleton type="rect" class="w-9 h-9 rounded-xl" />
            </div>
            <AppSkeleton class="h-7 w-2/3 mb-2" />
            <AppSkeleton class="h-3 w-1/2" />
        </template>

        <!-- Content -->
        <template v-else>
            <div class="flex items-center justify-between mb-4">
                <div
                    v-if="icon"
                    :class="['w-9 h-9 rounded-xl flex items-center justify-center', colorMap[color ?? 'brand']]"
                >
                    <component :is="icon" class="w-[18px] h-[18px]" />
                </div>
                <span
                    v-if="trend"
                    :class="[
                        'text-xs font-semibold px-2 py-0.5 rounded-full',
                        trend.direction === 'up'   ? 'bg-emerald-50 text-emerald-600' :
                        trend.direction === 'down' ? 'bg-rose-50    text-rose-600'    :
                                                     'bg-neutral-100 text-neutral-500',
                    ]"
                >
                    {{ trend.direction === 'up' ? '↑' : trend.direction === 'down' ? '↓' : '→' }} {{ trend.value }}
                </span>
            </div>
            <p class="text-2xl font-semibold text-neutral-900 tracking-tight mb-0.5">{{ value }}</p>
            <p class="text-xs text-neutral-500">{{ label }}</p>
        </template>
    </div>
</template>
```

- [ ] **Step 2: Type-check**

```bash
npm run type-check
```

Expected: 0 errors.

- [ ] **Step 3: Visual verification**

Navigate to any page that uses StatCard (e.g., Reports page at `/dashboard/reports`). The stat cards should look essentially the same — corners slightly less round (12px vs 16px) and with a very subtle shadow. Verify the loading state by temporarily passing `:loading="true"`.

---

## Self-Review

**Spec coverage:**
- ✅ CSS shadow tokens: `shadow-card`, `shadow-elevated`, `shadow-overlay`
- ✅ Semantic color tokens: success, warning, error, info scales
- ✅ Dark mode CSS var scaffolding
- ✅ AppButton: xs size, square icon-only prop
- ✅ AppInput: hint, leading/trailing icon slots, disabled, sm size
- ✅ AppTextarea: hint, disabled, maxlength char counter
- ✅ AppBadge: dot variant, semantic aliases (success/warning/error/info)
- ✅ AppCard: new component (default/flat/elevated/ghost, 4 padding presets, hover lift, `as` prop)
- ✅ AppSkeleton: new component (line/circle/rect types)
- ✅ StatCard: updated to use shadow-card, rounded-xl

**No placeholders:** All tasks contain complete replacement code.

**API backward compatibility:** Every modified component adds new optional props only. All existing callers remain valid TypeScript.

**What this plan does NOT include** (deferred to Plan 2+):
- Updating individual dashboard pages to use AppCard (happens in Plan 2 per module)
- Navigation redesign (Plan 2)
- Dashboard Home.vue redesign (Plan 3)
- Module page redesigns (Plan 4)
