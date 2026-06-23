# Member Directory & CRM Foundation — Design Spec
**Date:** 2026-06-01  
**Status:** Approved  

---

## Overview

Extend the existing Member Directory with a full Church CRM foundation. The `User` model is left untouched for auth concerns. All church-specific member data lives in a new `member_profiles` table — a 1:1 optional extension of `User`. The existing `Members/Index.vue` and `Members/Show.vue` are upgraded rather than replaced.

---

## Goals

- Keep the existing Users / auth / Spatie-roles system working without modification.
- Add a `MemberProfile` CRM layer (separate table, optional per user).
- Upgrade the Member Directory with department filtering and a stats strip.
- Refactor the Member Profile page to a sidebar + tabs layout.
- Surface attendance history, assigned tasks, and a unified activity timeline per member.
- Enforce split permissions: members can edit personal/emergency fields; church journey fields are admin-only.
- Make the architecture extensible for future CRM features (e.g. follow-ups, groups, giving records).

---

## Out of Scope (this phase)

- Invite / create member flow (members join via registration).
- Member deactivation / deletion.
- Bulk operations (export, bulk role change).
- Giving / donation records.
- Custom profile fields.

---

## Architecture

### Membership status model

Membership status is **role-only**. The Spatie roles (`super_admin`, `church_admin`, `coordinator`, `assistant_coordinator`, `member`) are the single source of truth for a member's standing. No separate status enum is added to either `users` or `member_profiles`.

### `member_profiles` table

One row per user, created on first save. Scoped to `church_id` for multi-tenant safety.

| Column | Type | Permission |
|--------|------|------------|
| `id` | bigint PK | — |
| `user_id` | FK → users (unique) | — |
| `church_id` | FK → churches | — |
| `date_of_birth` | date nullable | **self + admin** |
| `gender` | string nullable | **self + admin** |
| `marital_status` | string nullable | **self + admin** |
| `address` | text nullable | **self + admin** |
| `emergency_contact_name` | string nullable | **self + admin** |
| `emergency_contact_relationship` | string nullable | **self + admin** |
| `emergency_contact_phone` | string nullable | **self + admin** |
| `membership_date` | date nullable | **admin-only** |
| `baptism_date` | date nullable | **admin-only** |
| `salvation_date` | date nullable | **admin-only** |
| `created_at`, `updated_at` | timestamps | — |

**`gender` allowed values:** `male`, `female`, `other`, `prefer_not_to_say`  
**`marital_status` allowed values:** `single`, `married`, `widowed`, `divorced`

### `MemberProfile` model

```php
// app/Models/MemberProfile.php
class MemberProfile extends Model {
    protected $fillable = [
        'user_id', 'church_id',
        'date_of_birth', 'gender', 'marital_status', 'address',
        'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
        'membership_date', 'baptism_date', 'salvation_date',
    ];

    protected $casts = [
        'date_of_birth'   => 'date',
        'membership_date' => 'date',
        'baptism_date'    => 'date',
        'salvation_date'  => 'date',
    ];

    public function user(): BelongsTo { ... }
    public function church(): BelongsTo { ... }
}
```

### `User` model change

Add one relationship — nothing else changes:

```php
public function profile(): HasOne
{
    return $this->hasOne(MemberProfile::class);
}
```

---

## Activity Timeline

The timeline is **computed at query time** — no dedicated storage table. The controller aggregates from three sources, merges, sorts descending by timestamp, and limits to 40 entries. Vue receives a flat `TimelineEntry[]` prop.

### Sources

| Type | Source table | Trigger |
|------|-------------|---------|
| `attendance` | `attendances` + `attendance_sessions` | Existing records |
| `task_assigned` | `tasks.created_at` | Existing records |
| `task_completed` | `tasks.completed_at` | Existing records |
| `dept_joined` | `audit_logs` (action = `member.added`) | Written by `DepartmentController` |
| `dept_left` | `audit_logs` (action = `member.removed`) | Written by `DepartmentController` |
| `role_changed` | `audit_logs` (action = `role.assigned`) | **Future phase only** — no role management UI exists yet. The type is defined now so it renders when audit entries exist. |

### DepartmentController audit writes (new)

`addMember()`, `removeMember()`, and `updateMemberRole()` each call:

```php
AuditLog::record(
    churchId: $this->resolvedChurchId(),
    userId:   $request->user()->id,
    action:   'member.added' | 'member.removed' | 'member.role_updated',
    modelType: User::class,
    modelId:   $user->id,
    newValues: ['department_id' => $department->id, 'department_name' => $department->name, 'role' => $pivotRole],
    request:  $request,
);
```

### TimelineEntry DTO (PHP array, serialised to JSON)

```php
[
    'type'       => 'attendance' | 'task_assigned' | 'task_completed' | 'dept_joined' | 'dept_left' | 'role_changed',
    'label'      => string,          // human-readable, pre-formatted
    'meta'       => string | null,   // secondary line (e.g. session name, task title)
    'timestamp'  => ISO8601 string,
    'formatted'  => string,          // e.g. "3 days ago" | "12 Jan 2026"
]
```

---

## Member Directory — `Index.vue` Upgrades

### New filter: department

Add a `dept_id` query parameter. Controller builds:

```php
->when($request->dept_id, function ($q, $id) {
    $q->whereHas('departments', fn($q2) => $q2->where('departments.id', $id));
})
```

### New prop: `stats`

```php
'stats' => [
    'total'       => $total,
    'admins'      => $adminCount,
    'coordinators'=> $coordinatorCount,
    'members'     => $memberCount,
],
```

Displayed as a compact strip above the table: `"142 members · 4 admins · 12 coordinators"`

### New prop: `departments`

Minimal list for populating the filter dropdown:

```php
'departments' => Department::where('church_id', $churchId)
    ->where('is_active', true)
    ->orderBy('name')
    ->get(['id', 'name', 'icon', 'color']),
```

---

## Member Profile Page — `Show.vue` Refactor

### Layout: Sidebar + Tabs

```
┌─────────────────┬──────────────────────────────────────┐
│  SIDEBAR (1/3)  │  MAIN AREA (2/3)                     │
│                 │  ┌────────────────────────────────┐  │
│  Avatar         │  │ Departments │ Attendance │ ... │  │
│  Name + role    │  ├────────────────────────────────┤  │
│  Email / phone  │  │  Tab content                   │  │
│  Joined date    │  │                                │  │
│  ─────────────  │  └────────────────────────────────┘  │
│  CRM FIELDS     │                                      │
│  (read-only)    │                                      │
│  ─────────────  │                                      │
│  Edit button    │                                      │
└─────────────────┴──────────────────────────────────────┘
```

**Sidebar** renders the profile fields as read-only info rows when a profile exists. A single "Edit profile" button toggles an inline form below the info rows (no separate route needed).

### Tabs

| Tab | Content | Source |
|-----|---------|--------|
| **Departments** | Existing department list (moved from current layout) | `member.departments` |
| **Attendance** | Last 20 attendance records: date, session name, status badge | `member.recentAttendances` |
| **Tasks** | Last 20 tasks assigned to member: title, priority badge, status, due date | `member.recentTasks` |
| **Timeline** | Unified feed, 40 entries max, newest-first | `member.timeline` |

### Controller `show()` loads

`recentAttendances`, `recentTasks`, and `timeline` are passed as **separate Inertia props** directly from the controller — not serialised through `MemberResource`. `MemberResource` only handles the `member` prop (user + profile + departments + roles).

```php
$user->load(['roles', 'departments.coordinator:id,name', 'profile']);
$user->departments->loadCount('members');

$recentAttendances = Attendance::where('user_id', $user->id)
    ->with('session:id,name,held_at,type')
    ->latest('checked_in_at')
    ->limit(20)
    ->get();

$recentTasks = Task::where('assigned_to', $user->id)
    ->latest('updated_at')
    ->limit(20)
    ->get(['id','title','priority','status','due_at','completed_at']);

// buildTimeline(): queries audit_logs for this user_id, merges with
// attendance + task timestamps, sorts desc, returns typed DTOs (see Timeline section above).
$timeline = $this->buildTimeline($user, $churchId, $recentAttendances, $recentTasks);

return Inertia::render('Dashboard/Members/Show', [
    'member'             => MemberResource::make($user)->toArray(request()),
    'recentAttendances'  => AttendanceResource::collection($recentAttendances),
    'recentTasks'        => TaskResource::collection($recentTasks),
    'timeline'           => $timeline,
]);
```

### `MemberResource` — new `profile` key

```php
'profile' => $this->whenLoaded('profile', fn() => [
    'date_of_birth'                  => $this->profile->date_of_birth?->toDateString(),
    'gender'                         => $this->profile->gender,
    'marital_status'                 => $this->profile->marital_status,
    'address'                        => $this->profile->address,
    'emergency_contact_name'         => $this->profile->emergency_contact_name,
    'emergency_contact_relationship' => $this->profile->emergency_contact_relationship,
    'emergency_contact_phone'        => $this->profile->emergency_contact_phone,
    'membership_date'                => $this->profile->membership_date?->toDateString(),
    'baptism_date'                   => $this->profile->baptism_date?->toDateString(),
    'salvation_date'                 => $this->profile->salvation_date?->toDateString(),
]),
```

---

## Permissions

### `UserPolicy`

| Action | Who |
|--------|-----|
| `viewAny` | Any authenticated church member |
| `view` | Any authenticated church member (same church) |
| `update` | `church_admin`, `super_admin`, or the member themselves |

### `updateProfile()` field-level split

```php
// Fields any authenticated user can write to their own profile
$selfFields = [
    'date_of_birth', 'gender', 'marital_status', 'address',
    'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
];

// Fields only church_admin / super_admin can write
// (coordinators manage departments, not church records)
$adminFields = ['membership_date', 'baptism_date', 'salvation_date'];

// If editing someone else's profile — must be church_admin or super_admin
if ($request->user()->id !== $user->id) {
    $this->authorize('update', $user); // UserPolicy::update → church_admin | super_admin
    $allowed = array_merge($selfFields, $adminFields);
} else {
    // Member editing their own profile — self fields only; admin fields silently stripped
    $allowed = $selfFields;
}
```

---

## Routes

```php
// Additions to existing dashboard/members group
Route::get('/',                          [MembersController::class, 'index'])->name('index');
Route::get('/{user}',                    [MembersController::class, 'show']) ->name('show');
Route::put('/{user}/profile',            [MembersController::class, 'updateProfile'])->name('profile.update');
```

---

## New & Modified Files

### Backend

| File | Change |
|------|--------|
| `database/migrations/2026_06_01_000003_create_member_profiles_table.php` | New |
| `app/Models/MemberProfile.php` | New |
| `app/Models/User.php` | Add `hasOne(MemberProfile::class)` |
| `app/Http/Controllers/Dashboard/MembersController.php` | Expand `index()` + `show()`, add `updateProfile()` |
| `app/Http/Controllers/Dashboard/DepartmentController.php` | Add `AuditLog::record()` to `addMember`, `removeMember`, `updateMemberRole` |
| `app/Http/Resources/MemberResource.php` | Add `profile`, `recentAttendances`, `recentTasks`, `timeline` keys |
| `app/Policies/UserPolicy.php` | Confirm `update()` method exists |
| `routes/web.php` | Add `PUT /{user}/profile` route |

### Frontend

| File | Change |
|------|--------|
| `resources/js/Pages/Dashboard/Members/Index.vue` | Add dept filter dropdown + stats strip |
| `resources/js/Pages/Dashboard/Members/Show.vue` | Full refactor to sidebar + tabs |
| `resources/js/Components/Members/MemberProfileForm.vue` | New — CRM field form (handles self vs admin field visibility) |
| `resources/js/Components/Members/MemberTimeline.vue` | New — activity timeline feed |

---

## Scalability Notes

- `member_profiles` can accept new columns without touching `users`.
- The timeline `type` enum is open — new event types can be added by writing to `audit_logs` with a new `action` string and updating the timeline builder.
- The `stats` prop pattern on the index is additive — new metrics can be passed without breaking the existing table.
- All queries are scoped to `church_id` — multi-tenant safe.
