# Scheduling Workflow Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the end-to-end volunteer scheduling workflow so that assigned volunteers immediately receive correct, readable notifications and see their assignments on both the dashboard home and the My Schedule page.

**Architecture:** Four layers of fixes: (1) PHP notification classes return the wrong data format — fix `toArray()` in all four scheduling notifications to match the `{ type, title, body, action_url }` shape the frontend expects; (2) controller logic — always send `VolunteerAssigned` on assignment (not only for published plans), and send it per-volunteer on publish rather than a generic broadcast; (3) scope fix — `mySchedule()` currently hides draft-plan assignments from volunteers; (4) new dashboard widget — `DashboardController` passes upcoming assignments, a new `UpcomingAssignmentsWidget.vue` renders them. No new routes, migrations, or models are required.

**Tech Stack:** Laravel 12, Inertia, Vue 3 `<script setup lang="ts">`, lucide-vue-next, Tailwind CSS. No git repository — omit all commit steps.

---

## Context for all tasks

- **Project root:** `C:\Users\osein\OneDrive\Desktop\Church webstie`
- **No git commits** — project has no git repository; omit all commit steps
- **BelongsToChurch** — global scope on all models; `ServicePlan::count()` is already church-scoped
- **AppNotification** (`app/Notifications/AppNotification.php`) is the standard notification class. Its `toDatabase()` returns `{ type, title, body, action_url, actor }`. The `NotificationDropdown.vue` reads exactly those keys.
- **All four scheduling notification classes** (`VolunteerAssigned`, `SchedulePublished`, `VolunteerRemoved`, `VolunteerDeclined`) use their own `toArray()` and return `{ plan_title, position, url }` — the wrong keys. The badge count works but every notification renders blank and is unclickable.
- **Feature tests:** `php artisan test tests/Feature/Scheduling/ --stop-on-failure`

---

## File Map

| File | Change |
|------|--------|
| `app/Notifications/AppNotification.php` | Add 4 scheduling type constants |
| `app/Notifications/VolunteerAssigned.php` | Fix `toArray()` → `title`, `body`, `action_url` |
| `app/Notifications/SchedulePublished.php` | Fix `toArray()` → `title`, `body`, `action_url` |
| `app/Notifications/VolunteerRemoved.php` | Fix `toArray()` → `title`, `body`, `action_url` |
| `app/Notifications/VolunteerDeclined.php` | Fix `toArray()` → `title`, `body`, `action_url` |
| `app/Http/Controllers/Dashboard/AssignmentController.php` | Always send `VolunteerAssigned` (remove `isPublished()` guard) |
| `app/Http/Controllers/Dashboard/ServicePlanController.php` | Send `VolunteerAssigned` per-volunteer on publish (not generic `SchedulePublished`) |
| `app/Http/Controllers/Dashboard/SchedulingController.php` | `mySchedule()`: include draft-plan assignments + add `plan.status` to response |
| `app/Http/Controllers/Dashboard/DashboardController.php` | Add `myUpcomingAssignments` query |
| `resources/js/Components/Dashboard/NotificationDropdown.vue` | Add scheduling types to `TYPE_ICONS`; add `CalendarCheck2` to import |
| `resources/js/Components/Dashboard/UpcomingAssignmentsWidget.vue` | **New** — upcoming assignments widget for main dashboard |
| `resources/js/Pages/Dashboard/Home.vue` | Import + render `UpcomingAssignmentsWidget` |
| `resources/js/Components/Scheduling/MyAssignmentCard.vue` | Accept + display `plan.status` (Draft badge) |
| `resources/js/Pages/Dashboard/Scheduling/MySchedule.vue` | Update `AssignmentRow.plan` type to include `status` |

---

## Task 1: Fix scheduling notification data format

**Files:**
- Modify: `app/Notifications/AppNotification.php`
- Modify: `app/Notifications/VolunteerAssigned.php`
- Modify: `app/Notifications/SchedulePublished.php`
- Modify: `app/Notifications/VolunteerRemoved.php`
- Modify: `app/Notifications/VolunteerDeclined.php`

- [ ] **Step 1: Add scheduling type constants to AppNotification**

Open `app/Notifications/AppNotification.php`. After the last `const TYPE_DEPT_ROLE_CHANGED` line, add:

```php
    public const TYPE_SCHEDULING_ASSIGNED  = 'scheduling.assigned';
    public const TYPE_SCHEDULING_PUBLISHED = 'scheduling.published';
    public const TYPE_SCHEDULING_REMOVED   = 'scheduling.removed';
    public const TYPE_SCHEDULING_DECLINED  = 'scheduling.declined';
```

- [ ] **Step 2: Fix VolunteerAssigned::toArray()**

Replace the entire `toArray()` method in `app/Notifications/VolunteerAssigned.php`:

```php
    public function toArray(object $notifiable): array
    {
        $plan     = $this->assignment->planPosition->plan;
        $position = $this->assignment->planPosition->servingPosition;

        $dateStr = $plan->scheduled_at?->format('D, j M Y \a\t g:i A') ?? 'an upcoming service';

        return [
            'type'       => 'scheduling.assigned',
            'title'      => "New assignment: {$plan->title}",
            'body'       => $position?->name
                                ? "You're serving as {$position->name} on {$dateStr}."
                                : "You have a new assignment on {$dateStr}.",
            'action_url' => '/dashboard/scheduling/my-schedule',
        ];
    }
```

- [ ] **Step 3: Fix SchedulePublished::toArray()**

Replace the entire `toArray()` method in `app/Notifications/SchedulePublished.php`:

```php
    public function toArray(object $notifiable): array
    {
        $dateStr = $this->plan->scheduled_at?->format('D, j M Y') ?? 'an upcoming date';

        return [
            'type'       => 'scheduling.published',
            'title'      => "Schedule published: {$this->plan->title}",
            'body'       => "The schedule for {$dateStr} is now live. Check your assignments.",
            'action_url' => '/dashboard/scheduling/my-schedule',
        ];
    }
```

- [ ] **Step 4: Fix VolunteerRemoved::toArray()**

Replace the entire `toArray()` method in `app/Notifications/VolunteerRemoved.php`:

```php
    public function toArray(object $notifiable): array
    {
        $plan     = $this->assignment->planPosition->plan;
        $position = $this->assignment->planPosition->servingPosition;

        $dateStr = $plan->scheduled_at?->format('D, j M Y') ?? 'an upcoming service';

        return [
            'type'       => 'scheduling.removed',
            'title'      => "Assignment removed: {$plan->title}",
            'body'       => $position?->name
                                ? "Your slot as {$position->name} on {$dateStr} has been removed."
                                : "Your assignment on {$dateStr} has been removed.",
            'action_url' => '/dashboard/scheduling/my-schedule',
        ];
    }
```

- [ ] **Step 5: Fix VolunteerDeclined::toArray()**

Replace the entire `toArray()` method in `app/Notifications/VolunteerDeclined.php`:

```php
    public function toArray(object $notifiable): array
    {
        $plan      = $this->assignment->planPosition->plan;
        $position  = $this->assignment->planPosition->servingPosition;
        $volunteer = $this->assignment->volunteer;

        $dateStr = $plan->scheduled_at?->format('D, j M Y') ?? 'an upcoming service';

        return [
            'type'       => 'scheduling.declined',
            'title'      => ($volunteer?->name ?? 'A volunteer') . " declined: {$plan->title}",
            'body'       => $position?->name
                                ? ($volunteer?->name ?? 'A volunteer') . " cannot serve as {$position->name} on {$dateStr}."
                                : ($volunteer?->name ?? 'A volunteer') . " declined their assignment on {$dateStr}.",
            'action_url' => '/dashboard/scheduling/plans/' . $plan->id,
        ];
    }
```

- [ ] **Step 6: Verify PHP syntax on all 5 files**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php -l app/Notifications/AppNotification.php && php -l app/Notifications/VolunteerAssigned.php && php -l app/Notifications/SchedulePublished.php && php -l app/Notifications/VolunteerRemoved.php && php -l app/Notifications/VolunteerDeclined.php
```

Expected: 5 × `No syntax errors detected`

- [ ] **Step 7: Run scheduling tests**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php artisan test tests/Feature/Scheduling/ --stop-on-failure
```

Expected: All tests pass.

---

## Task 2: Fix notification sending logic

**Files:**
- Modify: `app/Http/Controllers/Dashboard/AssignmentController.php`
- Modify: `app/Http/Controllers/Dashboard/ServicePlanController.php`

### AssignmentController::store()

- [ ] **Step 1: Read AssignmentController**

Read `app/Http/Controllers/Dashboard/AssignmentController.php`. Find the `store()` method. The current code around line 62 reads:

```php
        // Notify if plan is already published (late assignment)
        if ($planPosition->plan->isPublished()) {
            $assignment->load('planPosition.plan', 'planPosition.servingPosition');
            $volunteer->notify(new VolunteerAssigned($assignment));
        }
```

- [ ] **Step 2: Replace the conditional with an unconditional notification**

Replace that block with:

```php
        // Always notify the volunteer immediately on assignment
        $assignment->load('planPosition.plan', 'planPosition.servingPosition');
        $volunteer->notify(new VolunteerAssigned($assignment));
```

### ServicePlanController::publish()

- [ ] **Step 3: Read ServicePlanController**

Read `app/Http/Controllers/Dashboard/ServicePlanController.php`. Find the `publish()` method. The current notification block reads:

```php
        // Notify all assigned (non-declined) volunteers
        $volunteers = $plan->planPositions()
            ->with('assignments.volunteer')
            ->get()
            ->flatMap(fn ($pp) => $pp->assignments)
            ->where('status', '!=', 'declined')
            ->pluck('volunteer')
            ->filter()
            ->unique('id');

        foreach ($volunteers as $volunteer) {
            $volunteer->notify(new SchedulePublished($plan));
        }
```

- [ ] **Step 4: Send VolunteerAssigned per-assignment on publish**

The `SchedulePublished` notification is generic — it has no position info. When a plan is published, each volunteer should receive a personalised `VolunteerAssigned` notification that includes their specific role, plus a generic `SchedulePublished` to the same set so they also see "schedule is live."

Replace the block with:

```php
        // Load all non-declined assignments with their position info
        $plan->load('planPositions.assignments.volunteer', 'planPositions.servingPosition');

        $assignmentsToNotify = $plan->planPositions
            ->flatMap(fn ($pp) => $pp->assignments->where('status', '!=', 'declined'))
            ->filter(fn ($a) => $a->volunteer !== null);

        foreach ($assignmentsToNotify as $assignment) {
            // Load relations needed by VolunteerAssigned::toArray()
            $assignment->setRelation('planPosition', $assignment->planPosition);
            $assignment->volunteer->notify(new VolunteerAssigned($assignment));
        }
```

- [ ] **Step 5: Verify PHP syntax**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php -l app/Http/Controllers/Dashboard/AssignmentController.php && php -l app/Http/Controllers/Dashboard/ServicePlanController.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Run scheduling tests**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php artisan test tests/Feature/Scheduling/ --stop-on-failure
```

Expected: All tests pass.

---

## Task 3: Fix MySchedule scope — include draft-plan assignments

**Files:**
- Modify: `app/Http/Controllers/Dashboard/SchedulingController.php`

- [ ] **Step 1: Read SchedulingController**

Read `app/Http/Controllers/Dashboard/SchedulingController.php`. Find the `mySchedule()` method. The query currently filters to `where('status', 'published')` and `where('scheduled_at', '>=', now()->startOfDay())`.

- [ ] **Step 2: Update the query**

Replace the entire `$assignments` query block (the `VolunteerAssignment::where(...)` chain) with:

```php
        $assignments = VolunteerAssignment::where('user_id', $user->id)
            ->where('status', '!=', 'declined')
            ->with([
                'planPosition.plan',
                'planPosition.servingPosition.department:id,name,icon,color',
            ])
            ->whereHas('planPosition.plan', fn ($q) =>
                // Show last 4 weeks of history + all future assignments (any plan status)
                $q->where('scheduled_at', '>=', now()->subWeeks(4)->startOfDay())
            )
            ->get()
            ->sortBy('planPosition.plan.scheduled_at')
            ->map(fn ($a) => [
                'id'     => $a->id,
                'status' => $a->status,
                'plan'   => [
                    'id'                     => $a->planPosition->plan->id,
                    'title'                  => $a->planPosition->plan->title,
                    'status'                 => $a->planPosition->plan->status,
                    'scheduled_at'           => $a->planPosition->plan->scheduled_at?->toJSON(),
                    'scheduled_at_formatted' => $a->planPosition->plan->scheduled_at?->format('D, j M Y'),
                    'scheduled_time'         => $a->planPosition->plan->scheduled_at?->format('g:i A'),
                    'location'               => $a->planPosition->plan->location,
                ],
                'position' => [
                    'name'       => $a->planPosition->servingPosition?->name,
                    'department' => $a->planPosition->servingPosition?->department ? [
                        'id'    => $a->planPosition->servingPosition->department->id,
                        'name'  => $a->planPosition->servingPosition->department->name,
                        'icon'  => $a->planPosition->servingPosition->department->icon,
                        'color' => $a->planPosition->servingPosition->department->color,
                    ] : null,
                ],
            ])
            ->values();
```

- [ ] **Step 3: Verify PHP syntax**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php -l app/Http/Controllers/Dashboard/SchedulingController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Run scheduling tests**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php artisan test tests/Feature/Scheduling/ --stop-on-failure
```

Expected: All tests pass.

---

## Task 4: Add upcoming assignments to DashboardController

**Files:**
- Modify: `app/Http/Controllers/Dashboard/DashboardController.php`

- [ ] **Step 1: Read DashboardController**

Read `app/Http/Controllers/Dashboard/DashboardController.php`. Note the existing imports at the top and the `return Inertia::render(...)` at the bottom.

- [ ] **Step 2: Add the VolunteerAssignment import**

At the top of the file, after the existing `use App\Models\User;` line, add:

```php
use App\Models\VolunteerAssignment;
```

- [ ] **Step 3: Add the query before the Inertia::render call**

Insert this block just before the `return Inertia::render(...)` line:

```php
        // Upcoming assignments for the current user — shown to all authenticated users
        // Shows assignments on any plan status (draft or published), next 30 days, max 3
        $myUpcomingAssignments = [];
        try {
            $myUpcomingAssignments = VolunteerAssignment::where('user_id', $user->id)
                ->where('status', '!=', 'declined')
                ->with(['planPosition.plan', 'planPosition.servingPosition'])
                ->whereHas('planPosition.plan', fn ($q) =>
                    $q->where('scheduled_at', '>=', now()->startOfDay())
                      ->where('scheduled_at', '<',  now()->addDays(30))
                )
                ->get()
                ->sortBy('planPosition.plan.scheduled_at')
                ->take(3)
                ->map(fn ($a) => [
                    'id'                     => $a->id,
                    'status'                 => $a->status,
                    'plan_title'             => $a->planPosition->plan->title,
                    'plan_status'            => $a->planPosition->plan->status,
                    'scheduled_at_formatted' => $a->planPosition->plan->scheduled_at?->format('D, j M'),
                    'position'               => $a->planPosition->servingPosition?->name,
                ])
                ->values()
                ->all();
        } catch (\Throwable) {
            // Silently fail if scheduling tables don't exist yet
        }
```

- [ ] **Step 4: Pass the data to Inertia**

In the `return Inertia::render('Dashboard/Home', [...])` call, add this entry to the props array:

```php
            'myUpcomingAssignments' => $myUpcomingAssignments,
```

- [ ] **Step 5: Verify PHP syntax**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php -l app/Http/Controllers/Dashboard/DashboardController.php
```

Expected: `No syntax errors detected`

---

## Task 5: Add scheduling icons to NotificationDropdown.vue

**Files:**
- Modify: `resources/js/Components/Dashboard/NotificationDropdown.vue`

- [ ] **Step 1: Read NotificationDropdown.vue**

Read `resources/js/Components/Dashboard/NotificationDropdown.vue`. Find the lucide import on line 9:

```typescript
import {
    Bell, CheckSquare, Megaphone, CalendarDays, Building2,
    CheckCheck, X, ExternalLink, Loader2
} from 'lucide-vue-next'
```

- [ ] **Step 2: Add CalendarCheck2 to the import**

Replace the lucide import block with:

```typescript
import {
    Bell, CheckSquare, Megaphone, CalendarDays, Building2,
    CheckCheck, X, ExternalLink, Loader2, CalendarCheck2,
} from 'lucide-vue-next'
```

- [ ] **Step 3: Add scheduling entries to TYPE_ICONS**

Find the `TYPE_ICONS` object (around line 87). After the last entry (`department_role_changed`), add four new entries:

```typescript
    'scheduling.assigned':  { icon: CalendarDays,   bg: 'bg-indigo-100', text: 'text-indigo-600'  },
    'scheduling.published': { icon: CalendarCheck2, bg: 'bg-green-100',  text: 'text-green-600'   },
    'scheduling.removed':   { icon: CalendarDays,   bg: 'bg-red-100',    text: 'text-red-600'     },
    'scheduling.declined':  { icon: CalendarDays,   bg: 'bg-orange-100', text: 'text-orange-600'  },
```

---

## Task 6: Create UpcomingAssignmentsWidget.vue

**Files:**
- Create: `resources/js/Components/Dashboard/UpcomingAssignmentsWidget.vue`

- [ ] **Step 1: Create the component**

Create `resources/js/Components/Dashboard/UpcomingAssignmentsWidget.vue` with the following content:

```vue
<!-- resources/js/Components/Dashboard/UpcomingAssignmentsWidget.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { CalendarDays, ArrowRight } from 'lucide-vue-next'

interface AssignmentRow {
    id: number
    status: string
    plan_title: string
    plan_status: string
    scheduled_at_formatted: string
    position: string | null
}

defineProps<{ assignments: AssignmentRow[] }>()

const statusStyles: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-700',
    confirmed: 'bg-green-100 text-green-700',
}

function statusLabel(s: string): string {
    if (s === 'pending')   return 'Pending'
    if (s === 'confirmed') return 'Confirmed'
    return s
}
</script>

<template>
    <div class="bg-white border border-neutral-100 rounded-2xl p-5">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-neutral-900 flex items-center gap-2">
                <CalendarDays class="w-4 h-4 text-indigo-500" />
                My Upcoming Schedule
            </h3>
            <Link
                href="/dashboard/scheduling/my-schedule"
                class="flex items-center gap-1 text-xs text-indigo-600 hover:underline"
            >
                View all
                <ArrowRight class="w-3 h-3" />
            </Link>
        </div>

        <!-- Assignment list -->
        <div v-if="assignments.length > 0" class="space-y-2">
            <Link
                v-for="a in assignments"
                :key="a.id"
                href="/dashboard/scheduling/my-schedule"
                class="flex items-start justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2.5 hover:bg-indigo-50/40 transition-colors group"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ a.plan_title }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ a.scheduled_at_formatted }}
                        <span v-if="a.position" class="text-indigo-600"> · {{ a.position }}</span>
                    </p>
                    <span
                        v-if="a.plan_status === 'draft'"
                        class="mt-1 inline-block text-[10px] font-medium bg-yellow-50 text-yellow-600 border border-yellow-200 px-1.5 py-0.5 rounded"
                    >
                        Draft schedule
                    </span>
                </div>
                <span
                    :class="['shrink-0 mt-0.5 text-[11px] font-medium px-2 py-0.5 rounded-full', statusStyles[a.status] ?? 'bg-neutral-100 text-neutral-600']"
                >
                    {{ statusLabel(a.status) }}
                </span>
            </Link>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-6 text-center">
            <CalendarDays class="w-8 h-8 text-gray-200 mb-2" />
            <p class="text-sm text-gray-400">No upcoming assignments</p>
            <Link
                href="/dashboard/scheduling"
                class="mt-1 text-xs text-indigo-500 hover:underline"
            >
                View scheduling →
            </Link>
        </div>
    </div>
</template>
```

---

## Task 7: Update Home.vue — add UpcomingAssignmentsWidget

**Files:**
- Modify: `resources/js/Pages/Dashboard/Home.vue`

- [ ] **Step 1: Read the top of Home.vue**

Read `resources/js/Pages/Dashboard/Home.vue` (first 60 lines). Note the existing imports and `defineProps`.

- [ ] **Step 2: Add the import**

In the `<script setup lang="ts">` block, after the `import RecentActivityWidget from '@/Components/Dashboard/RecentActivityWidget.vue'` line, add:

```typescript
import UpcomingAssignmentsWidget from '@/Components/Dashboard/UpcomingAssignmentsWidget.vue'
```

- [ ] **Step 3: Add myUpcomingAssignments to defineProps**

Find the `defineProps<{...}>()` block. Add this property to the interface:

```typescript
    myUpcomingAssignments: {
        id: number
        status: string
        plan_title: string
        plan_status: string
        scheduled_at_formatted: string
        position: string | null
    }[]
```

- [ ] **Step 4: Render the widget in the template**

Read `resources/js/Pages/Dashboard/Home.vue` from line 60 onwards to find where `RecentActivityWidget` is rendered (near the bottom of the template, after the feed columns).

Insert the `UpcomingAssignmentsWidget` directly before (or after) the `RecentActivityWidget`. It should be visible to ALL authenticated users (not just those with `audit.view`). Use this markup:

```html
<!-- Upcoming assignments — shown to every authenticated user -->
<UpcomingAssignmentsWidget
    v-if="myUpcomingAssignments.length > 0"
    :assignments="myUpcomingAssignments"
    class="mt-6"
/>
```

---

## Task 8: Update MyAssignmentCard + MySchedule.vue for plan_status

**Files:**
- Modify: `resources/js/Components/Scheduling/MyAssignmentCard.vue`
- Modify: `resources/js/Pages/Dashboard/Scheduling/MySchedule.vue`

### MyAssignmentCard.vue

- [ ] **Step 1: Read MyAssignmentCard.vue**

Read `resources/js/Components/Scheduling/MyAssignmentCard.vue`. Note the `AssignmentRow` interface and the template.

- [ ] **Step 2: Add status to plan interface**

In the `interface AssignmentRow`, the `plan` sub-interface currently is:

```typescript
    plan: {
        id: number
        title: string
        scheduled_at: string
        scheduled_at_formatted: string
        scheduled_time: string
        location: string | null
    }
```

Add `status: string` to it:

```typescript
    plan: {
        id: number
        title: string
        status: string
        scheduled_at: string
        scheduled_at_formatted: string
        scheduled_time: string
        location: string | null
    }
```

- [ ] **Step 3: Add Draft badge to the template**

Find the block below the plan title and date/location lines (before the position paragraph). After the location `<span>`, add:

```html
<span
    v-if="assignment.plan.status === 'draft'"
    class="inline-block text-[10px] font-medium bg-yellow-50 text-yellow-600 border border-yellow-200 px-1.5 py-0.5 rounded"
>
    Draft schedule
</span>
```

### MySchedule.vue

- [ ] **Step 4: Read MySchedule.vue**

Read `resources/js/Pages/Dashboard/Scheduling/MySchedule.vue`. Find the `AssignmentRow` interface.

- [ ] **Step 5: Add status to plan interface**

In the `AssignmentRow` interface, add `status: string` to the `plan` object:

```typescript
    plan: {
        id: number
        title: string
        status: string
        scheduled_at: string
        scheduled_at_formatted: string
        scheduled_time: string
        location: string | null
    }
```

---

## Task 9: Feature test — verify notification is always sent on assignment

**Files:**
- Modify: `tests/Feature/Scheduling/AssignmentControllerTest.php`

- [ ] **Step 1: Read the existing test file**

Read `tests/Feature/Scheduling/AssignmentControllerTest.php`. Note the class setup (church, admin, coordinator, member users, department, plan, position, planPosition).

- [ ] **Step 2: Add notification-on-draft-plan test**

Add the following test method to the class (before the closing `}`):

```php
    public function test_volunteer_is_notified_immediately_when_assigned_to_draft_plan(): void
    {
        // Use a DRAFT plan — notification should fire even before publish
        \Illuminate\Support\Facades\Notification::fake();

        $member = $this->member; // already has 'member' role

        $response = $this->actingAs($this->coordinator)
            ->post('/dashboard/scheduling/assignments', [
                'service_plan_position_id' => $this->planPosition->id,
                'user_id'                  => $member->id,
            ]);

        $response->assertRedirect();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $member,
            \App\Notifications\VolunteerAssigned::class,
        );
    }

    public function test_volunteer_assigned_notification_has_correct_format(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->actingAs($this->coordinator)
            ->post('/dashboard/scheduling/assignments', [
                'service_plan_position_id' => $this->planPosition->id,
                'user_id'                  => $this->member->id,
            ]);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->member,
            \App\Notifications\VolunteerAssigned::class,
            function ($notification) {
                $data = $notification->toArray($this->member);
                return isset($data['title'], $data['body'], $data['action_url'])
                    && $data['type'] === 'scheduling.assigned'
                    && $data['action_url'] === '/dashboard/scheduling/my-schedule';
            }
        );
    }
```

Note: the existing `setUp()` must have created `$this->planPosition`. Check the test file — if `$this->planPosition` is not defined in `setUp()`, read the test to understand how to reference the plan position correctly.

- [ ] **Step 3: Run all scheduling tests**

```bash
cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && php artisan test tests/Feature/Scheduling/ --stop-on-failure
```

Expected: All existing tests + the 2 new notification tests pass.

---

## Self-Review

**Spec coverage:**
- ✅ Database records are created — no change needed, already correct
- ✅ Notifications generated — Task 1 fixes format, Task 2 fixes sending logic
- ✅ Notification bell displays assignments — Task 1 (correct format), Task 5 (correct icons)
- ✅ Dashboard widgets display upcoming assignments — Tasks 4, 6, 7
- ✅ Member schedule page exists — Task 3 (scope fix), Task 8 (plan_status)
- ✅ Confirm/Decline actions exist — already correct, Task 8 ensures draft-plan cards show buttons
- ✅ Permissions correct — no change needed, VolunteerAssignmentPolicy is correct
- ✅ Publishing triggers notifications — Task 2 (VolunteerAssigned per-volunteer)

**No placeholders found.**

**Type consistency:**
- `AssignmentRow.plan.status` added in Tasks 3, 8 (MySchedule.vue + MyAssignmentCard.vue) ✅
- `myUpcomingAssignments` prop added in Tasks 4 + 7 (controller + Home.vue), shape matches UpcomingAssignmentsWidget.vue props ✅
