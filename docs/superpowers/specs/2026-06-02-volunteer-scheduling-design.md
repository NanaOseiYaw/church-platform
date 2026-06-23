# Volunteer Scheduling System — Design Spec (Phase 1)
**Date:** 2026-06-02
**Status:** Approved

---

## Overview

A purpose-built volunteer scheduling system for church services and ministry activities. Inspired by Planning Center's serving-plans UX, but integrated natively into the existing platform. Phase 1 delivers the core scheduling loop: create service plans → assign volunteers → publish → volunteers confirm or decline. Availability tracking, recurring schedules, conflict detection, and reporting are deferred to Phase 2+.

---

## Goals (Phase 1)

- Allow church admins and coordinators to create service plans with departments and serving positions.
- Assign department members to positions on each plan.
- Publish plans, triggering in-app notifications to assigned volunteers.
- Volunteers can confirm or decline their assignments from a "My Schedule" page.
- Leaders see confirmation status on the plan detail page.
- Position library: reusable position definitions per department, set up once and reused across plans.

---

## Out of Scope (Phase 1)

- Volunteer availability (Available / Unavailable / Maybe per date)
- Conflict detection (double-booking, overlapping services)
- Recurring / template-based schedules
- Drag-and-drop assignment UI
- Attendance integration (marking present/late/absent on service day)
- Department-level schedule reports
- Member profile volunteer history
- Email notifications (in-app only for now)
- Calendar / agenda view (list view only for Phase 1)
- Teams (a future grouping layer above departments for very large churches)

---

## Architecture

### Design decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Relation to Events | **Separate `service_plans` table** | Events are public-facing (registration, RSVPs). Service plans are internal operational tools. Different concerns, different schema. Can be linked to an Event in future via optional FK. |
| Position model | **Three-tier: Library → Slots → Assignments** | Positions defined once per department, reused across plans. Slot rows represent open/filled seats. Assignments bind a volunteer to a slot. Enables open-position visibility before anyone is assigned. |
| Volunteer confirmation | **Included in Phase 1** | Confirm/decline is a one-column addition that closes the feedback loop and makes Phase 1 independently useful. |
| Plan detail layout | **Sidebar + Detail Panel** | Department list on the left, selected department's positions and assignments on the right. Linear-style split — clean navigation between departments without losing context. |

---

## Database

### `service_plans`

Top-level plan for a service or ministry activity.

```php
Schema::create('service_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('church_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('title');               // "Sunday Morning Service"
    $table->text('description')->nullable();
    $table->dateTime('scheduled_at');      // when the service happens
    $table->string('location')->nullable();
    $table->string('status')->default('draft'); // draft | published | archived
    $table->text('notes')->nullable();
    $table->dateTime('published_at')->nullable();
    $table->timestamps();

    $table->index(['church_id', 'status']);
    $table->index(['church_id', 'scheduled_at']);
});
```

### `serving_positions`

Reusable position definitions per department. Set up once, used across all plans.

```php
Schema::create('serving_positions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('church_id')->constrained()->cascadeOnDelete();
    $table->foreignId('department_id')->constrained()->cascadeOnDelete();
    $table->string('name');                // "Camera Operator"
    $table->text('description')->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['church_id', 'department_id']);
});
```

### `service_plan_positions`

A specific position slot on a specific plan. One row = one open (or filled) seat.

```php
Schema::create('service_plan_positions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('service_plan_id')->constrained()->cascadeOnDelete();
    $table->foreignId('serving_position_id')->constrained()->cascadeOnDelete();
    $table->text('notes')->nullable();     // per-slot instructions
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index('service_plan_id');
});
```

### `volunteer_assignments`

A volunteer assigned to a plan position slot.

```php
Schema::create('volunteer_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('church_id')->constrained()->cascadeOnDelete();     // denormalized — platform pattern
    $table->foreignId('service_plan_position_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();        // the volunteer
    $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('status')->default('pending');  // pending | confirmed | declined
    $table->text('notes')->nullable();
    $table->dateTime('responded_at')->nullable();
    $table->timestamps();

    $table->unique(['service_plan_position_id', 'user_id']); // one person per slot
    $table->index(['church_id', 'user_id']);
    $table->index(['church_id', 'status']);
});
```

### Relationship map

```
departments
  └──< serving_positions (position library, per dept)
         └──< service_plan_positions (slots added to a plan)
                  │
service_plans ───┘
                  └──< volunteer_assignments (user on a slot)
```

---

## Models

### `ServicePlan`

```php
class ServicePlan extends Model {
    use BelongsToChurch, SoftDeletes;

    protected $fillable = [
        'church_id', 'created_by', 'published_by',
        'title', 'description', 'scheduled_at', 'location',
        'status', 'notes', 'published_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    // Relationships: church, creator, publisher, planPositions (hasMany)
    // Scope: scopeUpcoming, scopePast, scopeDraft, scopePublished, scopeArchived
    // Helper: isDraft(), isPublished(), isArchived()
}
```

### `ServingPosition`

```php
class ServingPosition extends Model {
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'department_id', 'name', 'description',
        'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // Relationships: church, department, planPositions (hasMany)
    // Scope: scopeActive
}
```

### `ServicePlanPosition`

```php
class ServicePlanPosition extends Model {
    protected $fillable = [
        'service_plan_id', 'serving_position_id', 'notes', 'sort_order',
    ];

    // Relationships: plan (belongsTo ServicePlan), position (belongsTo ServingPosition),
    //               assignments (hasMany VolunteerAssignment)
    // Computed: isFilled() → assignments()->where('status','!=','declined')->exists()
}
```

### `VolunteerAssignment`

```php
class VolunteerAssignment extends Model {
    use BelongsToChurch;

    const STATUSES = ['pending', 'confirmed', 'declined'];

    protected $fillable = [
        'church_id', 'service_plan_position_id', 'user_id',
        'assigned_by', 'status', 'notes', 'responded_at',
    ];

    protected $casts = ['responded_at' => 'datetime'];

    // Relationships: church, planPosition, volunteer (belongsTo User), assigner (belongsTo User)
    // Scope: scopePending, scopeConfirmed, scopeDeclined
}
```

---

## Permissions

### New Spatie permissions

| Permission | Holders |
|------------|---------|
| `scheduling.manage` | `super_admin`, `church_admin`, `coordinator` (dept-scoped via policy) |
| `scheduling.view` | `super_admin`, `church_admin`, `coordinator`, `assistant_coordinator` |

### `ServicePlanPolicy`

| Method | Rule |
|--------|------|
| `viewAny` | `scheduling.view` permission |
| `view` | `scheduling.view` permission |
| `create` | `scheduling.manage` permission |
| `update` | `scheduling.manage` AND (admin OR plan includes user's department) |
| `publish` | same as `update` |
| `delete` | `church_admin` or `super_admin` only |

### `ServingPositionPolicy`

| Method | Rule |
|--------|------|
| `viewAny` / `view` | `scheduling.view` |
| `create` / `update` / `delete` | `scheduling.manage` AND position's dept is user's dept (for coordinator) |

### `VolunteerAssignmentPolicy`

| Method | Rule |
|--------|------|
| `create` (assign) | `scheduling.manage` AND position's dept is user's dept (for coordinator) |
| `delete` (remove) | same as create |
| `respond` (confirm/decline) | assignment's `user_id === auth()->id()` — every member |

---

## Schedule Workflow

```
DRAFT
  │  Admin creates plan: title, date, time, location, notes
  │  Admin adds departments + positions from library to the plan
  │  Admin assigns volunteers to positions
  │  Volunteers do NOT see the plan (not in My Schedule yet)
  │  No notifications sent in draft
  ▼
PUBLISHED  ←── PATCH /scheduling/plans/{plan}/publish
  │  `published_at` and `published_by` stamped
  │  All assigned volunteers notified (in-app via NotificationService)
  │  Plan appears on each volunteer's My Schedule page
  │  Volunteers can confirm or decline
  │  Further assignments after publish: immediately notify new volunteer
  ▼
ARCHIVED   ←── PATCH /scheduling/plans/{plan}/archive
  │  Plan locked — no edits, no new assignments
  │  Stays visible for historical record
```

---

## Assignment Lifecycle

```
[slot created, no assignment — shown as "Open" with dashed border]
  ▼
PENDING  ←── admin assigns volunteer (POST /scheduling/assignments)
  │  Notification: "You've been assigned as {position} for {plan}"
  │  Slot shows volunteer avatar + "Pending" badge
  ▼
CONFIRMED  ←── volunteer responds confirmed
  │  `responded_at` stamped; slot shows green "Confirmed" badge
  OR
DECLINED   ←── volunteer responds declined
  │  `responded_at` stamped; slot reverts to "Open" with red "Declined" indicator
  │  Notification to plan creator + coordinator: "{name} declined {position}"
  │  Admin can assign a different volunteer
```

---

## Notification Triggers

All notifications use the existing `NotificationService`. Data sent as the `data` JSON column on the `notifications` table.

| Trigger | Recipient | Message |
|---------|-----------|---------|
| Volunteer assigned | The volunteer | "You've been assigned as {position} for {plan title} on {date}" |
| Volunteer removed | Removed volunteer | "Your assignment for {plan title} on {date} has been removed" |
| Plan published | All assigned volunteers | "The schedule for {plan title} on {date} has been published" |
| Volunteer declined | Plan creator + dept coordinator | "{name} declined {position} for {plan title} — slot is now open" |

Notification `type` values (new):
- `scheduling.assigned`
- `scheduling.removed`
- `scheduling.published`
- `scheduling.declined`

---

## Routes

```php
Route::prefix('dashboard/scheduling')->name('dashboard.scheduling.')->group(function () {

    // Dashboard
    Route::get('/', [SchedulingController::class, 'dashboard'])->name('dashboard');

    // Service Plans
    Route::prefix('plans')->name('plans.')->group(function () {
        Route::get('/',           [ServicePlanController::class, 'index'])   ->name('index');
        Route::get('/create',     [ServicePlanController::class, 'create'])  ->name('create');
        Route::post('/',          [ServicePlanController::class, 'store'])   ->name('store');
        Route::get('/{plan}',     [ServicePlanController::class, 'show'])    ->name('show');
        Route::get('/{plan}/edit',[ServicePlanController::class, 'edit'])    ->name('edit');
        Route::put('/{plan}',     [ServicePlanController::class, 'update'])  ->name('update');
        Route::delete('/{plan}',  [ServicePlanController::class, 'destroy']) ->name('destroy');
        Route::patch('/{plan}/publish', [ServicePlanController::class, 'publish']) ->name('publish');
        Route::patch('/{plan}/archive', [ServicePlanController::class, 'archive']) ->name('archive');

        // Plan positions (slots on a specific plan)
        Route::post('/{plan}/positions',        [ServicePlanController::class, 'addPosition'])    ->name('positions.add');
        Route::delete('/{plan}/positions/{pp}', [ServicePlanController::class, 'removePosition']) ->name('positions.remove');
    });

    // Serving Position Library
    Route::prefix('positions')->name('positions.')->group(function () {
        Route::get('/',         [ServingPositionController::class, 'index'])   ->name('index');
        Route::post('/',        [ServingPositionController::class, 'store'])   ->name('store');
        Route::put('/{pos}',    [ServingPositionController::class, 'update'])  ->name('update');
        Route::delete('/{pos}', [ServingPositionController::class, 'destroy']) ->name('destroy');
    });

    // Volunteer Assignments
    Route::prefix('assignments')->name('assignments.')->group(function () {
        Route::post('/',                        [AssignmentController::class, 'store'])   ->name('store');
        Route::delete('/{assignment}',          [AssignmentController::class, 'destroy']) ->name('destroy');
        Route::patch('/{assignment}/respond',   [AssignmentController::class, 'respond']) ->name('respond');
    });

    // My Schedule (member's own view)
    Route::get('/my-schedule', [SchedulingController::class, 'mySchedule'])->name('my-schedule');
});
```

---

## UI Structure

### Pages

| Page | Path | Access |
|------|------|--------|
| `Scheduling/Dashboard.vue` | `/dashboard/scheduling` | all authenticated |
| `Scheduling/Plans/Index.vue` | `/dashboard/scheduling/plans` | `scheduling.view` |
| `Scheduling/Plans/Create.vue` | `/dashboard/scheduling/plans/create` | `scheduling.manage` |
| `Scheduling/Plans/Show.vue` | `/dashboard/scheduling/plans/{plan}` | `scheduling.view` |
| `Scheduling/Positions/Index.vue` | `/dashboard/scheduling/positions` | `scheduling.manage` |
| `Scheduling/MySchedule.vue` | `/dashboard/scheduling/my-schedule` | all authenticated |

### Components

| Component | Purpose |
|-----------|---------|
| `Scheduling/PlanCard.vue` | Plan summary card — title, date, dept count, fill rate, status badge |
| `Scheduling/DeptSidebar.vue` | Left nav of departments on Plan Show; each dept shows filled/total count |
| `Scheduling/PositionPanel.vue` | Right panel of position slots for selected department |
| `Scheduling/AssignDrawer.vue` | Slide-in member picker for assigning a volunteer to a slot |
| `Scheduling/AssignmentCard.vue` | One assignment row: avatar, name, confirmation badge, remove button |
| `Scheduling/MyAssignmentCard.vue` | Assignment card on My Schedule with confirm/decline buttons |
| `Scheduling/StatusBadge.vue` | Reusable badge for plan status (draft/published/archived) and assignment status (pending/confirmed/declined) |

### Plan Show layout (sidebar + panel)

```
┌─────────────────────┬──────────────────────────────────────────┐
│  LEFT SIDEBAR       │  RIGHT PANEL (selected department)        │
│                     │                                          │
│  Sunday Service     │  🎥 Media Team                           │
│  Jun 8 • 9:00 AM   │  ─────────────────────────────           │
│  📍 Main Hall       │  ● Camera Operator       [Kwame A.] ✓   │
│  [PUBLISHED]        │  ● Audio Engineer        [Esi M.]   ?   │
│  ───────────        │  ○ Livestream Operator   [Assign →]      │
│  ■ Media Team  3/4  │                                          │
│  ■ Worship     1/3  │  + Add position from library             │
│  ■ Ushers      2/2  │                                          │
│  + Add dept         │                                          │
│  ───────────        │                                          │
│  [Publish]          │                                          │
│  [Archive]          │                                          │
└─────────────────────┴──────────────────────────────────────────┘
```

---

## New Files

### Backend

| File | Type |
|------|------|
| `database/migrations/2026_06_02_000001_create_service_plans_table.php` | New |
| `database/migrations/2026_06_02_000002_create_serving_positions_table.php` | New |
| `database/migrations/2026_06_02_000003_create_service_plan_positions_table.php` | New |
| `database/migrations/2026_06_02_000004_create_volunteer_assignments_table.php` | New |
| `app/Models/ServicePlan.php` | New |
| `app/Models/ServingPosition.php` | New |
| `app/Models/ServicePlanPosition.php` | New |
| `app/Models/VolunteerAssignment.php` | New |
| `app/Http/Controllers/Dashboard/SchedulingController.php` | New |
| `app/Http/Controllers/Dashboard/ServicePlanController.php` | New |
| `app/Http/Controllers/Dashboard/ServingPositionController.php` | New |
| `app/Http/Controllers/Dashboard/AssignmentController.php` | New |
| `app/Policies/ServicePlanPolicy.php` | New |
| `app/Policies/ServingPositionPolicy.php` | New |
| `app/Policies/VolunteerAssignmentPolicy.php` | New |
| `app/Http/Resources/ServicePlanResource.php` | New |
| `app/Http/Resources/ServingPositionResource.php` | New |
| `app/Http/Resources/ServicePlanPositionResource.php` | New |
| `app/Http/Resources/VolunteerAssignmentResource.php` | New |

### Frontend

| File | Type |
|------|------|
| `resources/js/Pages/Dashboard/Scheduling/Dashboard.vue` | New |
| `resources/js/Pages/Dashboard/Scheduling/Plans/Index.vue` | New |
| `resources/js/Pages/Dashboard/Scheduling/Plans/Create.vue` | New |
| `resources/js/Pages/Dashboard/Scheduling/Plans/Show.vue` | New |
| `resources/js/Pages/Dashboard/Scheduling/Positions/Index.vue` | New |
| `resources/js/Pages/Dashboard/Scheduling/MySchedule.vue` | New |
| `resources/js/Components/Scheduling/PlanCard.vue` | New |
| `resources/js/Components/Scheduling/DeptSidebar.vue` | New |
| `resources/js/Components/Scheduling/PositionPanel.vue` | New |
| `resources/js/Components/Scheduling/AssignDrawer.vue` | New |
| `resources/js/Components/Scheduling/AssignmentCard.vue` | New |
| `resources/js/Components/Scheduling/MyAssignmentCard.vue` | New |
| `resources/js/Components/Scheduling/StatusBadge.vue` | New |

### Modified

| File | Change |
|------|--------|
| `routes/web.php` | Add scheduling route group |
| `app/Providers/AuthServiceProvider.php` | Register 3 new policies |
| `database/seeders/RolesAndPermissionsSeeder.php` | Add `scheduling.manage` + `scheduling.view` permissions |

---

## Scalability Notes

- `service_plans` has no recurrence columns — Phase 2 adds `is_recurring` + `recurrence_rule` (RRULE string) as a non-breaking migration.
- `volunteer_assignments.status` is a plain string — new statuses (e.g. `maybe`) are added in Phase 2 without schema changes.
- Notification types are prefixed `scheduling.*` — new triggers added by adding new notification calls, no schema changes.
- Position library is per-department — when Teams are added in Phase 2, positions can optionally belong to a team instead.
- `service_plan_positions` has a `notes` field and `sort_order` — ready for drag-and-drop reordering without schema changes.
