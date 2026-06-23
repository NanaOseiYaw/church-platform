# Super Admin Multi-Church Panel — Design Spec

**Date:** 2026-06-08
**Status:** Approved
**Sub-project:** #2 of HIGH PRIORITY improvements

---

## Goal

Give the platform owner a dedicated admin area to view and manage all church tenants — list every church, suspend or reactivate them, create new church workspaces, and temporarily impersonate a church to act as its admin without needing their credentials.

---

## Architecture

### Auth model

The `super_admin` role is already seeded in `config/permissions.php` with `'super_admin' => '*'` (all permissions). The `role` middleware alias is already registered in `bootstrap/app.php`. No migration, no new seeder run needed.

A super admin user has `church_id = null` in the `users` table. They have no church of their own.

### Route group

```php
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

All six actions live in one controller (`SuperAdminController`). No sub-resources needed.

---

## The Four Features

### 1. Church List

`GET /super-admin` → `SuperAdminController@index`

Queries `Church::withCount('users')->orderByDesc('created_at')->paginate(20)`.

The `Church` model has **no global scope**, so `Church::withCount(...)` returns all tenants. Renders `SuperAdmin/Index.vue` with a paginated table: Name + slug · Members · Plan · Active status · Actions (Impersonate, Suspend/Reactivate). "Create Church" button top-right. No search for MVP.

### 2. Suspend / Reactivate

`PATCH /super-admin/{church}/toggle-active` → `SuperAdminController@toggleActive`

Flips `$church->is_active` (boolean cast). Returns `back()->with('success', ...)`.

**MVP scope:** This records the suspension state. It does **not** force-logout active church users or block their future logins. Enforcement (redirecting `is_active = false` church users to a "suspended" page) is an explicit follow-up task outside this spec — the flag is the source of truth when that middleware is added later.

### 3. Create Church

`GET /super-admin/churches/create` → `SuperAdminController@create`
`POST /super-admin/churches` → `SuperAdminController@store`

Reuses the existing `OnboardingRequest` form request (same validation rules) and `OnboardChurch` action (same transaction: church → logo → admin user → role → departments). One change required to `OnboardChurch`: a `bool $shouldLogin = true` parameter so the super admin is not logged out when the action fires `Auth::login($user)`. Existing callers pass no second argument and get `true` — fully backwards-compatible.

The Create form is a single page (not the multi-step wizard). Required: church name, admin name, admin email, admin password. Optional: tagline, timezone (default UTC), denomination. No logo, no color picker — the church admin can set those via impersonation after creation.

After store: redirect to `/super-admin` with success flash.

### 4. Impersonate / Stop Impersonating

`POST /super-admin/{church}/impersonate` → sets `session('super_admin_impersonating', $church->id)`, redirects to `/dashboard`

`DELETE /super-admin/impersonate` → clears session key, redirects to `/super-admin`

While impersonating, every request resolves the target church as the tenant and an amber banner renders inside `DashboardLayout.vue` with a "Stop Impersonating" link.

---

## Data Flow — Impersonation

```
1. Super admin clicks "Switch to [Grace Baptist]"
   → POST /super-admin/{church}/impersonate
   → session()->put('super_admin_impersonating', $church->id)
   → redirect → /dashboard

2. Every dashboard request:
   → ResolveTenant reads session → binds Grace Baptist as app('church')
   → HandleInertiaRequests sees session → shares impersonating: "Grace Baptist"
   → DashboardLayout renders amber banner

3. Super admin clicks "Stop Impersonating"
   → DELETE /super-admin/impersonate
   → session()->forget('super_admin_impersonating')
   → redirect → /super-admin
```

The webhook and browser sides are independent: stopping impersonation never affects ongoing processes in the impersonated church.

---

## ResolveTenant — patch

```php
private function resolveChurch(Request $request): ?Church
{
    // 0. Impersonation — always takes priority
    if ($id = $request->session()->get('super_admin_impersonating')) {
        return Church::find($id);
    }

    // 1. Authenticated user — use their church
    if ($user = $request->user()) {
        if ($user->church_id) {
            return Church::find($user->church_id);
        }
        // Super admin has church_id = null — return null rather than falling through
        // to the single-tenant fallback (which would silently assign the first church).
        if ($user->hasRole('super_admin')) {
            return null;
        }
    }

    // 2. Subdomain — future
    // $host = $request->getHost(); ...

    // 3. Single-tenant fallback (unauthenticated guests)
    return Church::query()->first();
}
```

Without the `hasRole('super_admin')` guard, a super admin with `church_id = null` would silently get the first church via the fallback — a dangerous context leak.

---

## HandleInertiaRequests — impersonating prop

Add to `share()` after the `$church` variable is resolved:

```php
'impersonating' => $request->user()?->hasRole('super_admin')
    && $request->session()->get('super_admin_impersonating')
        ? ($church?->name ?? 'Unknown Church')
        : null,
```

`$church` is already the impersonated church at this point (bound by `ResolveTenant`). No second query.

---

## EnsureUserIsActive — no change needed

The middleware checks `$request->user()->is_active` (the user's flag, not the church's). Super admin users will be active. The middleware is also already null-safe via `?? true`. No modifications required.

`HandleInertiaRequests` already null-guards `$churchId` in `safeUnreadAnnouncementsCount` and `safeOverdueTasksCount` — super admin browsing their own area with a null church is handled. ✅

---

## Files

### Create

| File | Purpose |
|---|---|
| `app/Http/Controllers/SuperAdmin/SuperAdminController.php` | All 6 actions; injects `OnboardChurch` |
| `resources/js/Layouts/SuperAdminLayout.vue` | Platform-admin shell (no church branding) |
| `resources/js/Pages/SuperAdmin/Index.vue` | Paginated church list with actions |
| `resources/js/Pages/SuperAdmin/Create.vue` | Single-page church creation form |

### Modify

| File | Change |
|---|---|
| `app/Actions/OnboardChurch.php` | Add `bool $shouldLogin = true` parameter |
| `app/Http/Middleware/ResolveTenant.php` | Add step 0 (session) + step 1.5 (super admin null) |
| `app/Http/Middleware/HandleInertiaRequests.php` | Add `impersonating` to `share()` |
| `resources/js/Layouts/DashboardLayout.vue` | Add amber impersonation banner at top |
| `resources/js/types/global.d.ts` | Add `impersonating: string \| null` to `PageProps` |
| `routes/web.php` | Add super-admin route group |

---

## SuperAdminController — full outline

```php
namespace App\Http\Controllers\SuperAdmin;

use App\Actions\OnboardChurch;
use App\Http\Requests\OnboardingRequest;
use App\Models\Church;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    public function __construct(private OnboardChurch $onboardChurch) {}

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Index', [
            'churches' => Church::withCount('users')
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Create', [
            'timezones'    => $this->timezoneOptions(),
            'denominations' => $this->denominationOptions(),
        ]);
    }

    public function store(OnboardingRequest $request): RedirectResponse
    {
        $this->onboardChurch->execute($request->validated(), shouldLogin: false);
        return redirect()->route('super-admin.index')->with('success', 'Church created successfully.');
    }

    public function toggleActive(Church $church): RedirectResponse
    {
        $church->update(['is_active' => ! $church->is_active]);
        $label = $church->is_active ? 'reactivated' : 'suspended';
        return back()->with('success', "Church {$label} successfully.");
    }

    public function impersonate(Church $church): RedirectResponse
    {
        session()->put('super_admin_impersonating', $church->id);
        return redirect()->route('dashboard');
    }

    public function stopImpersonating(): RedirectResponse
    {
        session()->forget('super_admin_impersonating');
        return redirect()->route('super-admin.index');
    }

    // Same lists as OnboardingController — duplicated intentionally (no cross-namespace coupling).
    private function timezoneOptions(): array { /* ... 20-entry array ... */ }
    private function denominationOptions(): array { /* ... 15-entry array ... */ }
}
```

---

## Vue Component Props

### SuperAdmin/Index.vue

```ts
defineProps<{
  churches: {
    data: Array<{
      id:                number
      name:              string
      slug:              string
      users_count:       number
      subscription_plan: string
      is_active:         boolean
      created_at:        string
    }>
    links: Array<{ url: string | null; label: string; active: boolean }>
    meta:  { current_page: number; last_page: number; total: number }
  }
}>()
```

### SuperAdmin/Create.vue

```ts
defineProps<{
  timezones:    Array<{ value: string; label: string }>
  denominations: string[]
}>()
```

---

## SuperAdminLayout.vue — structure

```
┌──────────────────────────────────────────────┐
│  ⬡ Platform Admin           [User] [Sign out] │  ← fixed header, slate-900 bg
├──────────────────────────────────────────────┤
│  [Churches]                                   │  ← top nav link
├──────────────────────────────────────────────┤
│                                              │
│                <slot />                      │
│                                              │
└──────────────────────────────────────────────┘
```

No church branding. No sidebar drawer. No notifications. Completely isolated from `DashboardLayout`.

---

## DashboardLayout.vue — impersonation banner

Placed as the very first element inside the layout wrapper, above the sidebar/header:

```vue
<div
  v-if="$page.props.impersonating"
  class="bg-amber-500 text-white text-sm flex items-center justify-center gap-4 py-2 px-4 z-50"
>
  <span>⚠ Viewing as <strong>{{ $page.props.impersonating }}</strong></span>
  <Link
    :href="route('super-admin.impersonate.stop')"
    method="delete"
    as="button"
    class="underline font-semibold"
  >
    Stop Impersonating
  </Link>
</div>
```

---

## TypeScript — global.d.ts addition

```ts
declare module '@inertiajs/vue3' {
  interface PageProps {
    church:        import('./index').ChurchBranding
    flash:         import('./index').FlashMessages
    auth:          import('./index').SharedProps['auth']
    impersonating: string | null   // ← add
  }
}
```

---

## Out of Scope (not in this spec)

- Enforcing church suspension (blocking dashboard login for `is_active = false` church users)
- Church detail / show page (`/super-admin/{church}`)
- Edit church metadata from the super admin panel
- Donation analytics across all churches
- Billing / subscription management
- Super admin audit log
- Role assignment UI (assigning `super_admin` role is done via `php artisan tinker` for now)
