# Scheduling Navigation Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix four gaps that make the volunteer scheduling workflow inaccessible — two missing sidebar nav items, a missing empty-state prompt in the Add Position modal, and a PHP null-safety crash guard.

**Architecture:** Four surgical edits to existing files. No new pages, migrations, or components. The PHP fix guards ServicePlanController::show() against a null $p->department. The frontend fixes add Position Library + My Schedule nav items to DashboardLayout, an empty-library prompt to Plans/Show, and a quick-actions strip to the Scheduling Dashboard.

**Tech Stack:** Laravel 12, Vue 3 `<script setup lang="ts">`, Inertia, lucide-vue-next. No git repository — omit all commit steps.

---

## File Map

| File | Change |
|------|--------|
| `app/Http/Controllers/Dashboard/ServicePlanController.php` | Null-safe `$p->department` access in `show()`, filter out null-dept positions |
| `resources/js/Layouts/DashboardLayout.vue` | Add `Layers` import + 2 nav items in Work group |
| `resources/js/Pages/Dashboard/Scheduling/Plans/Show.vue` | Empty-library state in Add Position modal |
| `resources/js/Pages/Dashboard/Scheduling/Dashboard.vue` | Quick-actions strip for `scheduling.manage` users |

---

## Context for all tasks

- **Project root:** `C:\Users\osein\OneDrive\Desktop\Church webstie`
- **No git commits** — project has no git repository; omit all commit steps
- **DashboardLayout.vue nav structure:** `navGroups` is a computed array of `{ label, items }` objects. Items are filtered by `item.perm` via `auth.can()`. The Work group (lines 88–95) currently has: Tasks, Scheduling, Attendance, Media.
- **Existing lucide import in DashboardLayout.vue (line 17–22):**
  ```
  import {
      LayoutDashboard, CalendarDays, Megaphone, CheckSquare, Mic2,
      Users, Building2, BarChart3, Settings, LogOut,
      Menu, X, ChevronRight, ExternalLink, Upload, ChevronDown,
      Shield, Hash, CalendarCheck2, Search, Command, UserCog, ClipboardList, ShieldCheck
  } from 'lucide-vue-next'
  ```
  `Layers` is **not** present — must be added.
- **Dashboard.vue lucide import (line 10):** `import { ClipboardList, Layers, Clock3, Archive, Plus } from 'lucide-vue-next'` — `Layers` is already there, no change needed.
- **Feature tests:** `php artisan test tests/Feature/Scheduling/` runs all scheduling tests.

---

## Task 1: Null-safe department access in ServicePlanController::show()

**Files:**
- Modify: `app/Http/Controllers/Dashboard/ServicePlanController.php`

- [ ] **Step 1: Read the file**

Read `app/Http/Controllers/Dashboard/ServicePlanController.php`. Locate the `show()` method. Find the `$availablePositions->map(...)` block (around line 103). It currently reads:

```php
'availablePositions' => $availablePositions->map(fn ($p) => [
    'id'         => $p->id,
    'name'       => $p->name,
    'department' => ['id' => $p->department->id, 'name' => $p->department->name],
]),
```

- [ ] **Step 2: Replace with null-safe version**

Replace that exact block with:

```php
'availablePositions' => $availablePositions->map(fn ($p) => [
    'id'         => $p->id,
    'name'       => $p->name,
    'department' => $p->department
        ? ['id' => $p->department->id, 'name' => $p->department->name]
        : null,
])->filter(fn ($p) => $p['department'] !== null)->values(),
```

The `->filter()->values()` silently drops any position whose department relationship is null (e.g., soft-deleted department). This prevents a fatal error on page load.

- [ ] **Step 3: Verify PHP syntax**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php -l app/Http/Controllers/Dashboard/ServicePlanController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Run scheduling feature tests**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php artisan test tests/Feature/Scheduling/ --stop-on-failure
```

Expected: All tests pass.

---

## Task 2: Add Position Library + My Schedule nav items

**Files:**
- Modify: `resources/js/Layouts/DashboardLayout.vue`

- [ ] **Step 1: Read the file**

Read `resources/js/Layouts/DashboardLayout.vue`. Confirm:
- The lucide import is on lines 17–22
- The Work group items array is around lines 88–95
- The current Scheduling item is: `{ label: 'Scheduling', href: '/dashboard/scheduling', icon: ClipboardList, perm: 'scheduling.view' }`

- [ ] **Step 2: Add `Layers` to the lucide import**

Find the exact import block (it spans multiple lines). Replace it with:

```typescript
import {
    LayoutDashboard, CalendarDays, Megaphone, CheckSquare, Mic2,
    Users, Building2, BarChart3, Settings, LogOut,
    Menu, X, ChevronRight, ExternalLink, Upload, ChevronDown,
    Shield, Hash, CalendarCheck2, Search, Command, UserCog, ClipboardList, ShieldCheck,
    Layers,
} from 'lucide-vue-next'
```

- [ ] **Step 3: Add two nav items after the existing Scheduling item**

In the Work group items array, find:

```typescript
{ label: 'Scheduling', href: '/dashboard/scheduling', icon: ClipboardList, perm: 'scheduling.view' },
```

Replace it with these three items:

```typescript
{ label: 'Scheduling',       href: '/dashboard/scheduling',               icon: ClipboardList, perm: 'scheduling.view' },
{ label: 'Position Library', href: '/dashboard/scheduling/positions',      icon: Layers,        perm: 'scheduling.manage' },
{ label: 'My Schedule',      href: '/dashboard/scheduling/my-schedule',    icon: CalendarDays,  perm: 'scheduling.view' },
```

`scheduling.manage` hides Position Library from regular members (only coordinators + admins see it). `scheduling.view` shows My Schedule to all scheduling users. The existing nav filter in `navGroups` handles this automatically — no other code changes needed.

- [ ] **Step 4: Verify the file has no obvious issues**

The `isActive()` function at line 127 uses `url.startsWith(href)`, so:
- On `/dashboard/scheduling/positions`: both "Scheduling" and "Position Library" highlight (expected — sub-page of the module)
- On `/dashboard/scheduling/my-schedule`: both "Scheduling" and "My Schedule" highlight (expected)
- On `/dashboard/scheduling`: only "Scheduling" highlights (correct)

No changes needed to `isActive()`.

---

## Task 3: Empty-library state in Plans/Show Add Position modal

**Files:**
- Modify: `resources/js/Pages/Dashboard/Scheduling/Plans/Show.vue`

- [ ] **Step 1: Read the file**

Read `resources/js/Pages/Dashboard/Scheduling/Plans/Show.vue`. Find the second `<Teleport to="body">` block — the "Add Position Modal" (around line 206). The inner `<div class="px-5 py-4">` block currently contains a `<label>`, `<select>`, and action buttons.

- [ ] **Step 2: Replace the modal body**

Find and replace the entire `<div class="px-5 py-4">` block inside the Add Position modal with:

```html
<div class="px-5 py-4">
    <!-- Empty library state -->
    <div v-if="availablePositions.length === 0" class="rounded-lg bg-gray-50 px-4 py-6 text-center">
        <p class="text-sm text-gray-600">No positions in the library yet.</p>
        <a
            href="/dashboard/scheduling/positions"
            class="mt-1 inline-block text-sm text-indigo-600 hover:underline"
        >
            Create positions first →
        </a>
        <div class="mt-4 flex justify-end">
            <AppButton variant="ghost" type="button" @click="showAddPosition = false">Close</AppButton>
        </div>
    </div>

    <!-- Normal state: position select -->
    <template v-else>
        <label class="mb-1 block text-sm font-medium text-gray-700">Select Position</label>
        <select
            v-model="selectedPositionId"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
        >
            <option :value="null" disabled>Choose a position…</option>
            <optgroup
                v-for="dept in [...new Map(availablePositions.map(p => [p.department!.id, p.department!])).values()]"
                :key="dept.id"
                :label="dept.name"
            >
                <option
                    v-for="pos in availablePositions.filter(p => p.department!.id === dept.id)"
                    :key="pos.id"
                    :value="pos.id"
                >
                    {{ pos.name }}
                </option>
            </optgroup>
        </select>
        <div class="mt-4 flex justify-end gap-3">
            <AppButton variant="ghost" type="button" @click="showAddPosition = false">Cancel</AppButton>
            <AppButton variant="primary" type="button" :disabled="!selectedPositionId" @click="addPosition">
                <Plus class="h-4 w-4" />
                Add to Plan
            </AppButton>
        </div>
    </template>
</div>
```

Note: `p.department!` uses the TypeScript non-null assertion because positions with null departments have already been filtered out server-side by Task 1's PHP fix.

---

## Task 4: Quick-actions strip on Scheduling Dashboard

**Files:**
- Modify: `resources/js/Pages/Dashboard/Scheduling/Dashboard.vue`

- [ ] **Step 1: Read the file**

Read `resources/js/Pages/Dashboard/Scheduling/Dashboard.vue`. Confirm:
- Line 3: `import { Link } from '@inertiajs/vue3'` — `Link` already imported ✓
- Line 10: `import { ClipboardList, Layers, ... } from 'lucide-vue-next'` — `Layers` already imported ✓
- Line 9: `import { useAuthStore } from '@/stores/useAuthStore'` — `auth` store available ✓
- The stats strip div ends with `</div>` around line 87
- The `<!-- Upcoming published plans -->` section begins immediately after

- [ ] **Step 2: Add the quick-actions strip**

In the `<template>`, find the closing `</div>` of the stats strip (the `<!-- Stats strip -->` section) and the start of the upcoming section (`<!-- Upcoming published plans -->`). Insert this block between them:

```html
<!-- Quick actions (admin / coordinator only) -->
<div v-if="auth.can('scheduling.manage')" class="mb-8 grid grid-cols-2 gap-4">
    <Link
        href="/dashboard/scheduling/plans"
        class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50/30"
    >
        <div class="rounded-lg bg-indigo-50 p-2">
            <ClipboardList class="h-5 w-5 text-indigo-600" />
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-900">Service Plans</p>
            <p class="text-xs text-gray-500">Create and manage schedules</p>
        </div>
    </Link>
    <Link
        href="/dashboard/scheduling/positions"
        class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50/30"
    >
        <div class="rounded-lg bg-purple-50 p-2">
            <Layers class="h-5 w-5 text-purple-600" />
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-900">Position Library</p>
            <p class="text-xs text-gray-500">Define roles for each team</p>
        </div>
    </Link>
</div>
```

- [ ] **Step 3: Run all scheduling tests one final time**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php artisan test tests/Feature/Scheduling/
```

Expected: All tests pass. This confirms the PHP side is intact after all edits.

---

## Self-Review Checklist

- [x] **Fix 1 (PHP null-safety):** Task 1 covers `$p->department` null check + filter out null-dept positions ✓
- [x] **Fix 2 (nav items):** Task 2 adds `Position Library` (`scheduling.manage`) + `My Schedule` (`scheduling.view`) to Work group ✓
- [x] **Fix 3 (empty library state):** Task 3 shows link to `/dashboard/scheduling/positions` when `availablePositions.length === 0` ✓
- [x] **Fix 4 (dashboard quick actions):** Task 4 adds Service Plans + Position Library action cards, gated by `scheduling.manage` ✓
- [x] **`Layers` in DashboardLayout:** Added to lucide import in Task 2. Already present in Dashboard.vue (no change needed in Task 4) ✓
- [x] **`CalendarDays` in DashboardLayout:** Already in the import, no change needed ✓
- [x] **Permission gates correct:** Position Library = `scheduling.manage` (coordinators + admins); My Schedule = `scheduling.view` (all scheduling users) ✓
- [x] **No placeholders:** All steps contain complete code ✓
- [x] **No git commits:** No repository, all commit steps omitted ✓
- [x] **isActive() behaviour:** `/dashboard/scheduling/positions` correctly highlights both "Scheduling" and "Position Library" — expected sub-page behaviour, no change needed ✓
