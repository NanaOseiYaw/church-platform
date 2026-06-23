# Scheduling Navigation Fixes — Design Spec

**Date:** 2026-06-03
**Status:** Approved

---

## Problem

The Volunteer Scheduling module is functionally complete (all CRUD, policies, and pages exist) but two critical navigation links are missing, making the entire workflow inaccessible:

1. `/dashboard/scheduling/positions` (Position Library) has no nav item or link — no way to reach it
2. `/dashboard/scheduling/my-schedule` (volunteer's schedule) has no nav item
3. Plans/Show crashes when a ServingPosition's department is null (soft-deleted dept)
4. Plans/Show "Add position" modal shows an empty dropdown with no explanation when no positions exist yet

---

## What Is Being Fixed

Four surgical changes — no new pages, migrations, or components.

---

## Fix 1: Two New Sidebar Nav Items

**File:** `resources/js/Layouts/DashboardLayout.vue`

Add two nav items to the Work group, directly below the existing "Scheduling" item:

```typescript
{ label: 'Position Library', href: '/dashboard/scheduling/positions',  icon: Layers,       perm: 'scheduling.manage' },
{ label: 'My Schedule',      href: '/dashboard/scheduling/my-schedule', icon: CalendarDays, perm: 'scheduling.view' },
```

- `Layers` and `CalendarDays` are already available in lucide-vue-next; add to the existing import if not present.
- `scheduling.manage` gates Position Library (coordinators + admins only).
- `scheduling.view` gates My Schedule (all users who can see scheduling).

---

## Fix 2: Scheduling Dashboard — Position Library action card

**File:** `resources/js/Pages/Dashboard/Scheduling/Dashboard.vue`

For users with `scheduling.manage`, add a "Quick Actions" strip between the stats strip and the Upcoming Services section. This strip shows two action cards:

1. **Service Plans** → `/dashboard/scheduling/plans` (ClipboardList icon)
2. **Position Library** → `/dashboard/scheduling/positions` (Layers icon)

Only rendered when `auth.can('scheduling.manage')`.

This ensures admins/coordinators land on the dashboard and immediately see both entry points.

---

## Fix 3: Plans/Show — Empty position library state

**File:** `resources/js/Pages/Dashboard/Scheduling/Plans/Show.vue`

In the "Add Position from Library" modal (`showAddPosition`), when `availablePositions.length === 0`, replace the select dropdown with an empty state:

```html
<p class="text-sm text-gray-500">
  No positions in the library yet.
  <a href="/dashboard/scheduling/positions" class="text-indigo-600 hover:underline">
    Create positions first →
  </a>
</p>
```

When `availablePositions.length > 0`, show the existing select as before.

---

## Fix 4: Null-safe department access in ServicePlanController

**File:** `app/Http/Controllers/Dashboard/ServicePlanController.php`

Line ~104 in `show()`:
```php
// BEFORE (crashes if $p->department is null)
'department' => ['id' => $p->department->id, 'name' => $p->department->name],

// AFTER (null-safe)
'department' => $p->department
    ? ['id' => $p->department->id, 'name' => $p->department->name]
    : null,
```

---

## Files Changed

| File | Change |
|------|--------|
| `resources/js/Layouts/DashboardLayout.vue` | Add 2 nav items (Layers + CalendarDays icons) |
| `resources/js/Pages/Dashboard/Scheduling/Dashboard.vue` | Add quick-actions strip for scheduling.manage users |
| `resources/js/Pages/Dashboard/Scheduling/Plans/Show.vue` | Empty-library state in Add Position modal |
| `app/Http/Controllers/Dashboard/ServicePlanController.php` | Null-safe `$p->department` access |

---

## Testing

- Navigate to `/dashboard/scheduling` as admin → see Position Library + My Schedule in sidebar
- Navigate to `/dashboard/scheduling` as member → see My Schedule in sidebar, Position Library hidden
- Click Position Library → `/dashboard/scheduling/positions` loads, positions can be created
- Create a plan → go to plan detail → click Add Position → when library empty, see link to Position Library
- Create positions → go back to plan detail → Add Position dropdown shows positions grouped by dept
- Set a ServingPosition's department to null in test DB → `/dashboard/scheduling/plans/{id}` still loads (no 500)
