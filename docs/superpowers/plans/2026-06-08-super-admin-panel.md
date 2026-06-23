# Super Admin Multi-Church Panel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a dedicated `/super-admin` area that lets the platform owner list all church tenants, suspend/reactivate them, create new churches, and impersonate any church without leaving their session.

**Architecture:** Six routes behind `role:super_admin` middleware → `SuperAdminController` (one file, six actions) → four Vue pages in `resources/js/Pages/SuperAdmin/` using a new `SuperAdminLayout.vue`. Impersonation is session-based: `ResolveTenant` checks `session('super_admin_impersonating')` before normal tenant resolution; `HandleInertiaRequests` shares the church name (or null) so `DashboardLayout.vue` can render a persistent amber banner. `OnboardChurch` gets a `$shouldLogin` flag so creating a church via the panel doesn't log out the super admin.

**Tech Stack:** Laravel 11, Inertia.js, Vue 3, TypeScript, Tailwind CSS v4, Spatie Permissions (role already seeded), Lucide Vue Next icons.

**HARD CONSTRAINTS:**
- NO git — never run any git commands
- NO test runner beyond `php artisan test` + `npm run type-check`
- 35 pre-existing type errors in VueUse/socket.io — acceptable, NEVER in modified files

---

## Task 1: Patch OnboardChurch — add $shouldLogin parameter

**Files:**
- Modify: `app/Actions/OnboardChurch.php`

Context: `OnboardChurch::execute()` currently ends with `Auth::login($user)`. If a super admin calls this action, they get logged out. Adding `bool $shouldLogin = true` makes the login step optional. All existing callers (OnboardingController) pass no second argument and get `true` — no behaviour change for them.

- [ ] **Step 1: Open the file and locate the method signature (line 36) and the Auth::login call (line 81)**

```
file: app/Actions/OnboardChurch.php
```

- [ ] **Step 2: Change the method signature on line 36 to accept $shouldLogin**

Replace:
```php
    public function execute(array $data): User
```
With:
```php
    public function execute(array $data, bool $shouldLogin = true): User
```

- [ ] **Step 3: Wrap the Auth::login call (line 81) in a conditional**

Replace:
```php
            // ── 6. Authenticate the new admin immediately ────────────────────────
            Auth::login($user);
```
With:
```php
            // ── 6. Authenticate the new admin immediately (skip when called by super admin) ──
            if ($shouldLogin) {
                Auth::login($user);
            }
```

- [ ] **Step 4: Verify type-check passes**

Run:
```bash
npm run type-check
```
Expected: no new errors in `app/Actions/OnboardChurch.php` (it's PHP, so no TS output — this just confirms the build pipeline is healthy)

- [ ] **Step 5: Save — no commit (no git)**

---

## Task 2: Patch ResolveTenant — impersonation step + super admin null return

**Files:**
- Modify: `app/Http/Middleware/ResolveTenant.php`

Context: Currently `resolveChurch()` has three steps: (1) user's `church_id`, (2) subdomain (commented), (3) first church fallback. Problem: a super admin has `church_id = null`, so they'd silently get the first church via the fallback — a dangerous context leak. Fix: add step 0 (session impersonation) and a `hasRole` guard in step 1.

- [ ] **Step 1: Replace the entire resolveChurch() method**

Replace the existing `resolveChurch()` method (starting at `private function resolveChurch`) with:

```php
    private function resolveChurch(Request $request): ?Church
    {
        // 0. Impersonation — super admin context switch always takes priority
        if ($id = $request->session()->get('super_admin_impersonating')) {
            return Church::find($id);
        }

        // 1. Authenticated user — use their church
        if ($user = $request->user()) {
            if ($user->church_id) {
                return Church::find($user->church_id);
            }
            // Super admin has church_id = null — return null rather than falling
            // through to the single-tenant fallback, which would silently assign
            // the first church.
            if ($user->hasRole('super_admin')) {
                return null;
            }
        }

        // 2. Subdomain — future: resolve church by domain
        // $host = $request->getHost();
        // $church = Church::where('domain', $host)->first();
        // if ($church) return $church;

        // 3. Single-tenant fallback — used by unauthenticated guests
        return Church::query()->first();
    }
```

- [ ] **Step 2: Confirm the file has no syntax errors by running artisan**

Run:
```bash
php artisan route:list --name=dashboard --compact 2>&1 | head -5
```
Expected: route list output (no "Parse error" or "Fatal error")

- [ ] **Step 3: Save — no commit**

---

## Task 3: Add `impersonating` shared prop + TypeScript type

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/types/global.d.ts`

Context: The impersonation banner in DashboardLayout.vue needs to know the impersonated church name. We share it via Inertia's `share()` method. Since `ResolveTenant` already ran and bound the impersonated church as `app('church')`, no second DB query is needed. We add `impersonating: string | null` to the TypeScript `PageProps` interface so Vue templates get type-safe access.

- [ ] **Step 1: Open HandleInertiaRequests.php and locate the share() method's return array**

The `share()` return array starts at roughly line 26. Find the `'flash' => [...]` entry at the end.

- [ ] **Step 2: Add the impersonating key after the flash entry**

Locate this exact block at the end of the `share()` return:
```php
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
```

Replace it with:
```php
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],

            // Set when a super admin is impersonating a church. Contains the
            // church's display name so DashboardLayout can render the banner.
            // Null for all non-super-admin sessions and unauthenticated requests.
            'impersonating' => $request->user()?->hasRole('super_admin')
                && $request->session()->get('super_admin_impersonating')
                    ? ($church?->name ?? 'Unknown Church')
                    : null,
```

- [ ] **Step 3: Open resources/js/types/global.d.ts**

Locate the existing `interface PageProps` block:
```ts
declare module '@inertiajs/vue3' {
    interface PageProps {
        church: import('./index').ChurchBranding
        flash:  import('./index').FlashMessages
        auth:   import('./index').SharedProps['auth']
    }
}
```

Replace it with:
```ts
declare module '@inertiajs/vue3' {
    interface PageProps {
        church:        import('./index').ChurchBranding
        flash:         import('./index').FlashMessages
        auth:          import('./index').SharedProps['auth']
        impersonating: string | null
    }
}
```

- [ ] **Step 4: Verify type-check**

Run:
```bash
npm run type-check
```
Expected: 0 new errors. (Existing 35 pre-existing errors in VueUse/socket.io are acceptable.)

- [ ] **Step 5: Save — no commit**

---

## Task 4: Create SuperAdminController + register routes

**Files:**
- Create: `app/Http/Controllers/SuperAdmin/SuperAdminController.php`
- Modify: `routes/web.php`

Context: One controller with 6 actions. Uses `OnboardingRequest` (existing form request) and `OnboardChurch` (patched in Task 1). Route model binding on `{church}` works correctly because `Church` has no global scope — any church can be resolved by ID regardless of tenant context.

- [ ] **Step 1: Create the directory and controller file**

Create `app/Http/Controllers/SuperAdmin/SuperAdminController.php` with full content:

```php
<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\OnboardChurch;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingRequest;
use App\Models\Church;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    public function __construct(private OnboardChurch $onboardChurch) {}

    // ── GET /super-admin ─────────────────────────────────────────────────────────
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Index', [
            'churches' => Church::withCount('users')
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    // ── GET /super-admin/churches/create ─────────────────────────────────────────
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Create', [
            'timezones'    => $this->timezoneOptions(),
            'denominations' => $this->denominationOptions(),
        ]);
    }

    // ── POST /super-admin/churches ───────────────────────────────────────────────
    public function store(OnboardingRequest $request): RedirectResponse
    {
        // shouldLogin: false — super admin must NOT be logged out after church creation
        $this->onboardChurch->execute($request->validated(), shouldLogin: false);

        return redirect()->route('super-admin.index')
            ->with('success', 'Church created successfully.');
    }

    // ── PATCH /super-admin/{church}/toggle-active ────────────────────────────────
    public function toggleActive(Church $church): RedirectResponse
    {
        $church->update(['is_active' => ! $church->is_active]);

        // After update(), $church->is_active reflects the NEW value.
        $label = $church->is_active ? 'reactivated' : 'suspended';

        return back()->with('success', "Church {$label} successfully.");
    }

    // ── POST /super-admin/{church}/impersonate ───────────────────────────────────
    public function impersonate(Church $church): RedirectResponse
    {
        session()->put('super_admin_impersonating', $church->id);

        return redirect()->route('dashboard');
    }

    // ── DELETE /super-admin/impersonate ──────────────────────────────────────────
    public function stopImpersonating(): RedirectResponse
    {
        session()->forget('super_admin_impersonating');

        return redirect()->route('super-admin.index');
    }

    // ── Option lists (intentionally duplicated from OnboardingController) ─────────
    // These are copied rather than shared to avoid coupling two unrelated controllers.

    private function timezoneOptions(): array
    {
        $zones = [
            'UTC'                 => 'UTC — Coordinated Universal Time',
            'America/New_York'    => 'Eastern Time (ET)',
            'America/Chicago'     => 'Central Time (CT)',
            'America/Denver'      => 'Mountain Time (MT)',
            'America/Los_Angeles' => 'Pacific Time (PT)',
            'America/Anchorage'   => 'Alaska Time (AKT)',
            'Pacific/Honolulu'    => 'Hawaii Time (HT)',
            'Europe/London'       => 'London (GMT / BST)',
            'Europe/Paris'        => 'Central European Time (CET)',
            'Europe/Berlin'       => 'Berlin (CET)',
            'Africa/Lagos'        => 'West Africa Time (WAT)',
            'Africa/Nairobi'      => 'East Africa Time (EAT)',
            'Africa/Johannesburg' => 'South Africa Time (SAST)',
            'Africa/Accra'        => 'Ghana Standard Time (GST)',
            'Africa/Cairo'        => 'Egypt Standard Time (EET)',
            'Asia/Dubai'          => 'Gulf Standard Time (GST)',
            'Asia/Kolkata'        => 'India Standard Time (IST)',
            'Asia/Singapore'      => 'Singapore Time (SGT)',
            'Asia/Tokyo'          => 'Japan Standard Time (JST)',
            'Australia/Sydney'    => 'Australian Eastern Time (AEST)',
        ];

        return array_map(
            fn ($label, $value) => ['value' => $value, 'label' => $label],
            $zones,
            array_keys($zones),
        );
    }

    private function denominationOptions(): array
    {
        return [
            'Non-denominational',
            'Baptist',
            'Pentecostal / Charismatic',
            'Methodist',
            'Presbyterian',
            'Anglican / Episcopal',
            'Lutheran',
            'Catholic',
            'Seventh-day Adventist',
            'Reformed / Calvinist',
            'Evangelical',
            'Assembly of God',
            'Church of Christ',
            'Orthodox',
            'Other',
        ];
    }
}
```

- [ ] **Step 2: Add the route group to routes/web.php**

Open `routes/web.php`. At the top, add the import after the last existing `use` statement (after line 45 which has `use App\Http\Controllers\Dashboard\BroadcastAudienceController;`):

```php
use App\Http\Controllers\SuperAdmin\SuperAdminController;
```

Then, at the very end of `routes/web.php` (after the closing `});` of the authenticated routes block at line 408), add:

```php

// ── Super Admin — platform owner panel ────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/',                         [SuperAdminController::class, 'index'])->name('index');
        Route::get('/churches/create',          [SuperAdminController::class, 'create'])->name('churches.create');
        Route::post('/churches',                [SuperAdminController::class, 'store'])->name('churches.store');
        Route::patch('/{church}/toggle-active', [SuperAdminController::class, 'toggleActive'])->name('churches.toggle-active');
        Route::post('/{church}/impersonate',    [SuperAdminController::class, 'impersonate'])->name('churches.impersonate');
        Route::delete('/impersonate',           [SuperAdminController::class, 'stopImpersonating'])->name('impersonate.stop');
    });
```

- [ ] **Step 3: Verify routes registered correctly**

Run:
```bash
php artisan route:list --name=super-admin --compact
```
Expected output (6 routes):
```
  GET|HEAD   super-admin ......................... super-admin.index
  GET|HEAD   super-admin/churches/create ........ super-admin.churches.create
  POST       super-admin/churches ............... super-admin.churches.store
  PATCH      super-admin/{church}/toggle-active . super-admin.churches.toggle-active
  POST       super-admin/{church}/impersonate ... super-admin.churches.impersonate
  DELETE     super-admin/impersonate ............. super-admin.impersonate.stop
```

- [ ] **Step 4: Save — no commit**

---

## Task 5: Patch DashboardLayout — impersonation banner

**Files:**
- Modify: `resources/js/Layouts/DashboardLayout.vue`

Context: The current root element is `<div class="h-screen flex overflow-hidden bg-neutral-50">`. Adding a banner above it while keeping `h-screen` would cause overflow. The fix: wrap everything in a new `flex-col h-screen overflow-hidden` container, add the banner as `shrink-0` first child, and demote the existing shell to `flex flex-1 overflow-hidden`. When the banner is hidden (the default for all non-super-admin users), `flex-1` fills the full viewport — identical to the original layout.

The `Link` component from `@inertiajs/vue3` is already imported on line 3.

- [ ] **Step 1: In the template, find the comment and outer shell div on line 159-160**

Locate this exact block:
```vue
    <!-- ── Root shell ──────────────────────────────────────────────────────── -->
    <div class="h-screen flex overflow-hidden bg-neutral-50">
```

Replace it with:
```vue
    <!-- ── Impersonation banner (super admin only) ──────────────────────────── -->
    <div
        v-if="$page.props.impersonating"
        class="bg-amber-500 text-white text-sm flex items-center justify-center gap-4 py-2 px-4 shrink-0 z-50"
    >
        <span>⚠ Viewing as <strong>{{ $page.props.impersonating }}</strong></span>
        <Link
            :href="route('super-admin.impersonate.stop')"
            method="delete"
            as="button"
            class="underline font-semibold hover:no-underline transition-all"
        >
            Stop Impersonating
        </Link>
    </div>

    <!-- ── Root shell ──────────────────────────────────────────────────────── -->
    <div class="flex flex-1 overflow-hidden bg-neutral-50">
```

- [ ] **Step 2: Wrap the entire template content in a new h-screen flex-col container**

Currently the template root is `<Head>` + the div + toast/nav/search. We need to wrap the div + its siblings in a new container.

Find the opening `<template>` tag. The first child is `<Head>` (line 155). We need to add a wrapping div.

At the very beginning of the template, after `<template>`, find:
```vue
<template>
    <Head>
```

The structure needs to become:
```vue
<template>
    <Head>
        <title v-if="title">{{ title }}</title>
    </Head>

    <div class="h-screen flex flex-col overflow-hidden">

        <!-- ── Impersonation banner ... -->
        ...

        <!-- ── Root shell ... -->
        <div class="flex flex-1 overflow-hidden bg-neutral-50">
            ...all existing sidebar + main area content...
        </div>
    </div>

    <!-- Global toast notifications -->
    <ToastContainer />

    <!-- ── Mobile bottom tab bar ... -->
    ...

    <!-- Global search modal ... -->
    <GlobalSearchModal />
</template>
```

To do this precisely:

**2a.** Find the original `<Head>` block on lines 155-157:
```vue
    <Head>
        <title v-if="title">{{ title }}</title>
    </Head>
```
Leave it exactly as-is.

**2b.** After the `</Head>` closing tag and before the impersonation banner, add an opening `<div class="h-screen flex flex-col overflow-hidden">`.

**2c.** Find the closing `</div>` that closes the original `<div class="flex flex-1 overflow-hidden bg-neutral-50">` shell (this was previously `h-screen flex overflow-hidden bg-neutral-50`, now renamed in Step 1). This closing `</div>` is at approximately line 363 (just before `<!-- Global toast notifications -->`):
```vue
    </div>

    <!-- Global toast notifications -->
    <ToastContainer />
```

After that `</div>`, add a closing `</div>` for the new wrapper.

The final template structure after the change should look like:

```vue
<template>
    <Head>
        <title v-if="title">{{ title }}</title>
    </Head>

    <div class="h-screen flex flex-col overflow-hidden">

        <!-- ── Impersonation banner (super admin only) ──────────────────────────── -->
        <div
            v-if="$page.props.impersonating"
            class="bg-amber-500 text-white text-sm flex items-center justify-center gap-4 py-2 px-4 shrink-0 z-50"
        >
            <span>⚠ Viewing as <strong>{{ $page.props.impersonating }}</strong></span>
            <Link
                :href="route('super-admin.impersonate.stop')"
                method="delete"
                as="button"
                class="underline font-semibold hover:no-underline transition-all"
            >
                Stop Impersonating
            </Link>
        </div>

        <!-- ── Root shell ──────────────────────────────────────────────────────── -->
        <div class="flex flex-1 overflow-hidden bg-neutral-50">

            <!-- ── Mobile backdrop ──────────────────────────────────────────────── -->
            ...

            <!-- ── Sidebar ───────────────────────────────────────────────────────── -->
            ...

            <!-- ── Main area ─────────────────────────────────────────────────────── -->
            ...

        </div>

    </div>

    <!-- Global toast notifications -->
    <ToastContainer />

    <!-- ── Mobile bottom tab bar ─────────────────────────────────────────────── -->
    ...

    <!-- Global search modal ... -->
    <GlobalSearchModal />
</template>
```

- [ ] **Step 3: Verify type-check**

Run:
```bash
npm run type-check
```
Expected: 0 new errors in `DashboardLayout.vue`.

- [ ] **Step 4: Save — no commit**

---

## Task 6: Create SuperAdminLayout.vue

**Files:**
- Create: `resources/js/Layouts/SuperAdminLayout.vue`

Context: Completely separate from DashboardLayout. No church branding, no sidebar, no notifications. Provides a minimal platform-admin shell. Uses `useAuthStore` for user name and sign-out.

- [ ] **Step 1: Create the file with full content**

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { useAuthStore } from '@/stores/useAuthStore'
import { ShieldCheck } from 'lucide-vue-next'

withDefaults(defineProps<{
    title?: string
}>(), {})

const auth = useAuthStore()

function logout() {
    router.post('/logout')
}
</script>

<template>
    <Head>
        <title v-if="title">{{ title }} · Platform Admin</title>
        <title v-else>Platform Admin</title>
    </Head>

    <div class="min-h-screen bg-neutral-100 flex flex-col">

        <!-- ── Header ────────────────────────────────────────────────────────── -->
        <header class="bg-slate-900 text-white shrink-0">
            <div class="max-w-7xl mx-auto px-4 lg:px-6 h-14 flex items-center justify-between">

                <!-- Brand -->
                <Link href="/super-admin" class="flex items-center gap-2.5 group">
                    <div class="w-7 h-7 rounded-lg bg-indigo-500 flex items-center justify-center shrink-0">
                        <ShieldCheck class="w-4 h-4 text-white" />
                    </div>
                    <span class="text-sm font-semibold text-white">Platform Admin</span>
                </Link>

                <!-- Nav + user -->
                <div class="flex items-center gap-4">
                    <Link
                        href="/super-admin"
                        class="text-sm text-slate-300 hover:text-white transition-colors"
                        :class="{ 'text-white font-medium': $page.url.startsWith('/super-admin') }"
                    >
                        Churches
                    </Link>

                    <div class="flex items-center gap-3 pl-4 border-l border-slate-700">
                        <span class="text-xs text-slate-400">{{ auth.user?.name }}</span>
                        <button
                            class="text-xs text-slate-300 hover:text-white transition-colors"
                            @click="logout"
                        >
                            Sign out
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- ── Page content ──────────────────────────────────────────────────── -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 lg:px-6 py-8">
            <slot />
        </main>

    </div>
</template>
```

- [ ] **Step 2: Verify type-check**

Run:
```bash
npm run type-check
```
Expected: 0 new errors.

- [ ] **Step 3: Save — no commit**

---

## Task 7: Create SuperAdmin/Index.vue

**Files:**
- Create: `resources/js/Pages/SuperAdmin/Index.vue`

Context: Paginated table of all churches. Each row has: Name + slug, Members count, Plan, Active status badge, Impersonate button (POST), Suspend/Reactivate button (PATCH). "Create Church" button top-right. Uses `SuperAdminLayout`.

- [ ] **Step 1: Create the file**

```vue
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { PlusCircle, LogIn, PowerOff, Power } from 'lucide-vue-next'

interface Church {
    id:                number
    name:              string
    slug:              string
    users_count:       number
    subscription_plan: string
    is_active:         boolean
    created_at:        string
}

interface PaginationLink {
    url:    string | null
    label:  string
    active: boolean
}

defineProps<{
    churches: {
        data:  Church[]
        links: PaginationLink[]
        meta:  { current_page: number; last_page: number; total: number }
    }
}>()
</script>

<template>
    <SuperAdminLayout title="Churches">

        <!-- ── Page header ─────────────────────────────────────────────────── -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">All Churches</h1>
                <p class="text-sm text-neutral-500 mt-0.5">
                    {{ churches.meta.total }} church{{ churches.meta.total !== 1 ? 'es' : '' }} registered
                </p>
            </div>
            <Link
                :href="route('super-admin.churches.create')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusCircle class="w-4 h-4" />
                Create Church
            </Link>
        </div>

        <!-- ── Table ──────────────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-100 bg-neutral-50">
                        <th class="text-left px-4 py-3 font-medium text-neutral-500">Church</th>
                        <th class="text-left px-4 py-3 font-medium text-neutral-500">Members</th>
                        <th class="text-left px-4 py-3 font-medium text-neutral-500">Plan</th>
                        <th class="text-left px-4 py-3 font-medium text-neutral-500">Status</th>
                        <th class="text-left px-4 py-3 font-medium text-neutral-500">Created</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-50">
                    <tr
                        v-for="church in churches.data"
                        :key="church.id"
                        class="hover:bg-neutral-50 transition-colors"
                    >
                        <!-- Name + slug -->
                        <td class="px-4 py-3">
                            <p class="font-medium text-neutral-900">{{ church.name }}</p>
                            <p class="text-xs text-neutral-400 mt-0.5">{{ church.slug }}</p>
                        </td>

                        <!-- Members -->
                        <td class="px-4 py-3 text-neutral-600">{{ church.users_count }}</td>

                        <!-- Plan -->
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-neutral-100 text-neutral-700 capitalize">
                                {{ church.subscription_plan }}
                            </span>
                        </td>

                        <!-- Status badge -->
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                    church.is_active
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-rose-50 text-rose-700',
                                ]"
                            >
                                {{ church.is_active ? 'Active' : 'Suspended' }}
                            </span>
                        </td>

                        <!-- Created -->
                        <td class="px-4 py-3 text-neutral-400 text-xs">
                            {{ new Date(church.created_at).toLocaleDateString() }}
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <!-- Impersonate -->
                                <Link
                                    :href="route('super-admin.churches.impersonate', church.id)"
                                    method="post"
                                    as="button"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors"
                                >
                                    <LogIn class="w-3.5 h-3.5" />
                                    Switch to
                                </Link>

                                <!-- Suspend / Reactivate -->
                                <Link
                                    :href="route('super-admin.churches.toggle-active', church.id)"
                                    method="patch"
                                    as="button"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors',
                                        church.is_active
                                            ? 'bg-rose-50 text-rose-700 hover:bg-rose-100'
                                            : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
                                    ]"
                                >
                                    <PowerOff v-if="church.is_active" class="w-3.5 h-3.5" />
                                    <Power v-else class="w-3.5 h-3.5" />
                                    {{ church.is_active ? 'Suspend' : 'Reactivate' }}
                                </Link>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty state -->
                    <tr v-if="churches.data.length === 0">
                        <td colspan="6" class="px-4 py-12 text-center text-neutral-400 text-sm">
                            No churches found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination ──────────────────────────────────────────────────── -->
        <div v-if="churches.meta.last_page > 1" class="mt-4 flex items-center justify-center gap-1">
            <template v-for="link in churches.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-sm transition-colors',
                        link.active
                            ? 'bg-indigo-600 text-white font-medium'
                            : 'text-neutral-500 hover:bg-neutral-100',
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="px-3 py-1.5 text-sm text-neutral-300"
                    v-html="link.label"
                />
            </template>
        </div>

    </SuperAdminLayout>
</template>
```

- [ ] **Step 2: Verify type-check**

Run:
```bash
npm run type-check
```
Expected: 0 new errors in `SuperAdmin/Index.vue`.

- [ ] **Step 3: Save — no commit**

---

## Task 8: Create SuperAdmin/Create.vue

**Files:**
- Create: `resources/js/Pages/SuperAdmin/Create.vue`

Context: Single-page form (not a wizard). Required fields: church_name, admin_name, admin_email, admin_password. Optional: church_tagline, timezone, denomination. No logo, no color picker (admin can configure via impersonation). Uses `useForm` from Inertia for POST submission with error display. `OnboardingRequest` does NOT have `confirmed` on admin_password — no confirmation field needed.

- [ ] **Step 1: Create the file**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { ArrowLeft } from 'lucide-vue-next'

defineProps<{
    timezones:    Array<{ value: string; label: string }>
    denominations: string[]
}>()

const form = useForm({
    church_name:    '',
    church_tagline: '',
    timezone:       'UTC',
    denomination:   '',
    country:        '',
    primary_color:  '#6366f1',
    logo:           null as File | null,
    admin_name:     '',
    admin_email:    '',
    admin_password: '',
})

function submit() {
    form.post(route('super-admin.churches.store'))
}
</script>

<template>
    <SuperAdminLayout title="Create Church">

        <!-- Back link -->
        <div class="mb-6">
            <Link
                :href="route('super-admin.index')"
                class="inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-700 transition-colors"
            >
                <ArrowLeft class="w-4 h-4" />
                Back to Churches
            </Link>
        </div>

        <div class="max-w-2xl">
            <h1 class="text-xl font-semibold text-neutral-900 mb-1">Create New Church</h1>
            <p class="text-sm text-neutral-500 mb-8">
                Creates a church workspace and a founding admin account. The admin can log in and complete setup via Settings.
            </p>

            <form @submit.prevent="submit" class="space-y-8">

                <!-- ── Church Info ──────────────────────────────────────────── -->
                <section class="bg-white rounded-xl border border-neutral-200 p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-neutral-900">Church Details</h2>

                    <!-- Church name -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Church Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.church_name"
                            type="text"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Grace Baptist Church"
                        />
                        <p v-if="form.errors.church_name" class="mt-1.5 text-xs text-rose-500">
                            {{ form.errors.church_name }}
                        </p>
                    </div>

                    <!-- Tagline -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Tagline <span class="text-neutral-400 font-normal">(optional)</span>
                        </label>
                        <input
                            v-model="form.church_tagline"
                            type="text"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="A Place to Belong"
                        />
                        <p v-if="form.errors.church_tagline" class="mt-1.5 text-xs text-rose-500">
                            {{ form.errors.church_tagline }}
                        </p>
                    </div>

                    <!-- Timezone + Denomination row -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-neutral-700 mb-1.5">Timezone</label>
                            <select
                                v-model="form.timezone"
                                class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
                            >
                                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                                    {{ tz.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-neutral-700 mb-1.5">Denomination</label>
                            <select
                                v-model="form.denomination"
                                class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
                            >
                                <option value="">— Select —</option>
                                <option v-for="d in denominations" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- ── Admin Account ────────────────────────────────────────── -->
                <section class="bg-white rounded-xl border border-neutral-200 p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-neutral-900">Founding Admin Account</h2>
                    <p class="text-xs text-neutral-400 -mt-3">
                        This person will have full control of the church workspace.
                    </p>

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Full Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.admin_name"
                            type="text"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="John Mensah"
                        />
                        <p v-if="form.errors.admin_name" class="mt-1.5 text-xs text-rose-500">
                            {{ form.errors.admin_name }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Email Address <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.admin_email"
                            type="email"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="admin@gracechurch.org"
                        />
                        <p v-if="form.errors.admin_email" class="mt-1.5 text-xs text-rose-500">
                            {{ form.errors.admin_email }}
                        </p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Password <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.admin_password"
                            type="password"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Min. 8 characters"
                        />
                        <p v-if="form.errors.admin_password" class="mt-1.5 text-xs text-rose-500">
                            {{ form.errors.admin_password }}
                        </p>
                    </div>
                </section>

                <!-- ── Submit ───────────────────────────────────────────────── -->
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span v-if="form.processing">Creating…</span>
                        <span v-else>Create Church</span>
                    </button>
                    <Link
                        :href="route('super-admin.index')"
                        class="px-5 py-2.5 text-sm font-medium text-neutral-600 hover:text-neutral-900 transition-colors"
                    >
                        Cancel
                    </Link>
                </div>

            </form>
        </div>

    </SuperAdminLayout>
</template>
```

- [ ] **Step 2: Verify type-check**

Run:
```bash
npm run type-check
```
Expected: 0 new errors in `SuperAdmin/Create.vue`.

- [ ] **Step 3: Save — no commit**

---

## Task 9: Feature test — SuperAdminControllerTest

**Files:**
- Create: `tests/Feature/SuperAdminControllerTest.php`

Context: Uses `RefreshDatabase` and `RolesAndPermissionsSeeder`. Super admin has `church_id = null`. Test setup pattern mirrors `AuditLogControllerTest`. We bind `app()->instance('church.id', ...)` where needed for Inertia responses.

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User   $superAdmin;
    private User   $churchAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Existing church with its own admin (is_active: true explicitly for reliable assertions)
        $this->church     = Church::create(['name' => 'Grace Church', 'slug' => 'grace-' . uniqid(), 'is_active' => true]);
        $this->churchAdmin = User::factory()->create(['church_id' => $this->church->id]);
        $this->churchAdmin->assignRole('church_admin');

        // Super admin — no church
        $this->superAdmin = User::factory()->create(['church_id' => null]);
        $this->superAdmin->assignRole('super_admin');

        // Bind default church context (some helpers in HandleInertiaRequests need this)
        app()->instance('church.id', null);
        app()->instance('church', null);
    }

    // ── Auth gate ────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_super_admin(): void
    {
        $response = $this->get('/super-admin');
        $response->assertRedirect('/login');
    }

    public function test_church_admin_cannot_access_super_admin(): void
    {
        $this->actingAs($this->churchAdmin)
            ->get('/super-admin')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_index(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/super-admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('SuperAdmin/Index'));
    }

    // ── Church list ──────────────────────────────────────────────────────────────

    public function test_index_returns_all_churches_paginated(): void
    {
        // Create a second church so we have more than one
        Church::create(['name' => 'Hope Church', 'slug' => 'hope-' . uniqid()]);

        $this->actingAs($this->superAdmin)
            ->get('/super-admin')
            ->assertOk()
            ->assertInertia(fn ($page) =>
                $page->component('SuperAdmin/Index')
                     ->has('churches.data', 2)
            );
    }

    // ── Toggle active ────────────────────────────────────────────────────────────

    public function test_super_admin_can_suspend_a_church(): void
    {
        $this->assertTrue($this->church->is_active);

        $this->actingAs($this->superAdmin)
            ->patch("/super-admin/{$this->church->id}/toggle-active")
            ->assertRedirect();

        $this->assertFalse($this->church->fresh()->is_active);
    }

    public function test_super_admin_can_reactivate_a_suspended_church(): void
    {
        $this->church->update(['is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->patch("/super-admin/{$this->church->id}/toggle-active")
            ->assertRedirect();

        $this->assertTrue($this->church->fresh()->is_active);
    }

    // ── Impersonation ────────────────────────────────────────────────────────────

    public function test_super_admin_can_start_impersonating(): void
    {
        $this->actingAs($this->superAdmin)
            ->post("/super-admin/{$this->church->id}/impersonate")
            ->assertRedirect('/dashboard');

        $this->assertEquals(
            $this->church->id,
            session('super_admin_impersonating')
        );
    }

    public function test_super_admin_can_stop_impersonating(): void
    {
        // Manually put the impersonation session key
        session()->put('super_admin_impersonating', $this->church->id);

        $this->actingAs($this->superAdmin)
            ->delete('/super-admin/impersonate')
            ->assertRedirect('/super-admin');

        $this->assertNull(session('super_admin_impersonating'));
    }

    // ── Create church ────────────────────────────────────────────────────────────

    public function test_super_admin_can_view_create_form(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/super-admin/churches/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('SuperAdmin/Create'));
    }

    public function test_super_admin_can_create_a_new_church(): void
    {
        $initialChurchCount = Church::count();

        $this->actingAs($this->superAdmin)
            ->post('/super-admin/churches', [
                'church_name'    => 'Harvest Church',
                'church_tagline' => 'Growing Together',
                'timezone'       => 'America/New_York',
                'denomination'   => 'Baptist',
                'country'        => '',
                'primary_color'  => '#6366f1',
                'admin_name'     => 'Jane Doe',
                'admin_email'    => 'jane@harvest.org',
                'admin_password' => 'secret1234',
            ])
            ->assertRedirect('/super-admin');

        // New church was created
        $this->assertEquals($initialChurchCount + 1, Church::count());
        $this->assertDatabaseHas('churches', ['name' => 'Harvest Church']);
        $this->assertDatabaseHas('users', ['email' => 'jane@harvest.org']);

        // Super admin is still authenticated (not logged out by OnboardChurch)
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/super-admin/churches', [])
            ->assertSessionHasErrors(['church_name', 'admin_name', 'admin_email', 'admin_password']);
    }
}
```

- [ ] **Step 2: Run the tests**

Run:
```bash
php artisan test tests/Feature/SuperAdminControllerTest.php --stop-on-failure
```
Expected: All tests pass (green).

If a test fails, read the failure message carefully:
- **"Role super_admin not found"** → Run `php artisan db:seed --class=RolesAndPermissionsSeeder` in your dev environment; the test seeds it via `RefreshDatabase` + `$this->seed(...)`, so this should not happen.
- **assertInertia fails** → The `inertiajs/inertia-laravel` testing helper requires `joshembling/inertia-data-tester` or the built-in Inertia assertion. Check that your test suite has Inertia assertions available. If not, replace `->assertInertia(...)` with `->assertOk()`.
- **Session assertion fails** → The `session()` helper in tests works on the same request cycle. If `assertNull(session('super_admin_impersonating'))` fails, use `$this->assertNull($this->app['session.store']->get('super_admin_impersonating'))`.

- [ ] **Step 3: Save — no commit**

---

## Task 10: Final verification

**Files:** None (verification only)

- [ ] **Step 1: Run full type-check**

Run:
```bash
npm run type-check
```
Expected: 0 errors in any file modified in this plan. (35 pre-existing errors in VueUse/socket.io/paginator/realtime remain and are acceptable.)

If you see errors in modified files, fix them before proceeding.

- [ ] **Step 2: Run the feature test**

Run:
```bash
php artisan test tests/Feature/SuperAdminControllerTest.php
```
Expected: All 11 tests pass.

- [ ] **Step 3: Confirm all 6 super-admin routes are registered**

Run:
```bash
php artisan route:list --name=super-admin --compact
```
Expected: 6 routes listed.

- [ ] **Step 4: Verify that existing tests still pass**

Run:
```bash
php artisan test tests/Feature/AuditLogControllerTest.php
```
Expected: Still passes (we only added to OnboardChurch with a backwards-compatible default, so nothing should break).

- [ ] **Step 5: Final checklist**

Confirm all files are saved and correct:

| File | Status |
|---|---|
| `app/Actions/OnboardChurch.php` | `$shouldLogin = true` param added |
| `app/Http/Middleware/ResolveTenant.php` | Step 0 (session) + step 1.5 (super admin null) added |
| `app/Http/Middleware/HandleInertiaRequests.php` | `impersonating` key in `share()` |
| `app/Http/Controllers/SuperAdmin/SuperAdminController.php` | Created with 6 actions |
| `resources/js/types/global.d.ts` | `impersonating: string \| null` in `PageProps` |
| `resources/js/Layouts/DashboardLayout.vue` | Wrapper + amber banner added |
| `resources/js/Layouts/SuperAdminLayout.vue` | Created |
| `resources/js/Pages/SuperAdmin/Index.vue` | Created |
| `resources/js/Pages/SuperAdmin/Create.vue` | Created |
| `routes/web.php` | Super-admin route group added |
| `tests/Feature/SuperAdminControllerTest.php` | Created, all tests passing |
