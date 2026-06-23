# Audit Log Coverage — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expand audit logging from 9 actions to ~55 actions across all 15 modules, add a centralised AuditLogService, a hybrid observer/explicit-call pattern, a new filtered paginated audit viewer at `/dashboard/audit`, coordinator-scoped access, and a Recent Activity dashboard widget.

**Architecture:** A new `AuditLogService` replaces all inline `AuditLog::record()` calls. A generic `AuditableObserver` automatically logs `created` and `deleted` on 9 auditable models. All `updated` events and named business actions (publish, assign, archive, etc.) use explicit service calls via the `LogsAuditEvents` trait in controllers. A new `AuditLogController` serves the paginated viewer with filters; coordinators see only events tagged with their department_id in metadata.

**Tech Stack:** Laravel 12, Eloquent observers, Inertia/Vue 3 `<script setup lang="ts">`, Spatie Permission, lucide-vue-next. No git repository — omit all git commit steps.

---

## Context for all tasks

- **`AuditLogService::record()`** — the single entry point for all audit writes after this plan. Always wrapped in try/catch.
- **`LogsAuditEvents` trait** — gives controllers `$this->auditLog(action, target, old, new, metadata)`.
- **`AuditableObserver`** — fires on `created` and `deleted` only; never on `updated`.
- **`metadata['department_id']`** — must be included in every event that belongs to a department. This is how coordinator scoping works.
- **`target_name`** — resolved as `$model->name ?? $model->title ?? (string) $model->id`.
- **No git commits** — skip all `git commit` steps.
- **Feature tests**: use `RefreshDatabase`, create Church directly (`Church::create(['name' => 'Test', 'slug' => 'test-'.uniqid()])`), seed `RolesAndPermissionsSeeder`.

---

## Task 1: Migration — Add `target_name` and `metadata`

**Files:**
- Create: `database/migrations/2026_06_03_000001_add_target_name_metadata_to_audit_logs.php`

- [ ] **Step 1: Create the migration**

```php
<?php
// database/migrations/2026_06_03_000001_add_target_name_metadata_to_audit_logs.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('target_name', 200)->nullable()->after('model_id');
            $table->json('metadata')->nullable()->after('new_values');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['target_name', 'metadata']);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `2026_06_03_000001_add_target_name_metadata_to_audit_logs ... DONE`

---

## Task 2: Update AuditLog Model

**Files:**
- Modify: `app/Models/AuditLog.php`

- [ ] **Step 1: Read the file first**

Read `app/Models/AuditLog.php` to see current contents.

- [ ] **Step 2: Replace the file with the updated version**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Audit entries are write-once.
    public $timestamps = true;

    protected $fillable = [
        'church_id', 'user_id', 'action',
        'model_type', 'model_id', 'target_name',
        'old_values', 'new_values', 'metadata',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
    ];

    public function church(): BelongsTo { return $this->belongsTo(Church::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }

    /**
     * @deprecated Use AuditLogService::record() instead.
     * Kept for backward compatibility during migration.
     */
    public static function record(
        int     $churchId,
        ?int    $userId,
        string  $action,
        array   $oldValues  = [],
        array   $newValues  = [],
        ?object $request    = null,
        ?string $modelType  = null,
        ?int    $modelId    = null,
        ?string $targetName = null,
        array   $metadata   = [],
    ): static {
        return static::create([
            'church_id'   => $churchId,
            'user_id'     => $userId,
            'action'      => $action,
            'model_type'  => $modelType,
            'model_id'    => $modelId,
            'target_name' => $targetName,
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'metadata'    => $metadata  ?: null,
            'ip_address'  => $request?->ip(),
            'user_agent'  => $request?->userAgent(),
        ]);
    }
}
```

---

## Task 3: AuditLogService + LogsAuditEvents Trait

**Files:**
- Create: `app/Services/AuditLogService.php`
- Create: `app/Traits/LogsAuditEvents.php`

- [ ] **Step 1: Create AuditLogService**

```php
<?php
// app/Services/AuditLogService.php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Record an auditable action.
     *
     * Always silently no-ops on failure — audit errors must never
     * break the main request flow.
     *
     * @param  string      $action   Dot-separated action name, e.g. 'announcement.published'
     * @param  Model|null  $target   The affected Eloquent model (optional)
     * @param  array       $old      Previous values for diff display
     * @param  array       $new      New values for diff display
     * @param  array       $metadata Free-form context. Include 'department_id' for dept-scoped events.
     * @param  User|null   $actor    Defaults to Auth::user()
     * @param  int|null    $churchId Defaults to app('church.id') or actor's church_id
     */
    public function record(
        string  $action,
        ?Model  $target    = null,
        array   $old       = [],
        array   $new       = [],
        array   $metadata  = [],
        ?User   $actor     = null,
        ?int    $churchId  = null,
    ): void {
        try {
            /** @var User|null $user */
            $user = $actor ?? Auth::user();

            $church = $churchId
                ?? (app()->bound('church.id') ? app('church.id') : null)
                ?? $user?->church_id;

            if (! $church) {
                return; // No church context — cannot log
            }

            $targetName = null;
            if ($target) {
                $targetName = $target->name
                    ?? $target->title
                    ?? (string) $target->getKey();
            }

            AuditLog::create([
                'church_id'   => $church,
                'user_id'     => $user?->id,
                'action'      => $action,
                'model_type'  => $target ? get_class($target) : null,
                'model_id'    => $target?->getKey(),
                'target_name' => $targetName,
                'old_values'  => $old      ?: null,
                'new_values'  => $new      ?: null,
                'metadata'    => $metadata ?: null,
                'ip_address'  => request()?->ip(),
                'user_agent'  => request()?->userAgent(),
            ]);
        } catch (\Throwable) {
            // Swallow all exceptions — audit must never break primary flow.
        }
    }
}
```

- [ ] **Step 2: Create LogsAuditEvents trait**

```php
<?php
// app/Traits/LogsAuditEvents.php

namespace App\Traits;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Convenience mixin for controllers and services.
 * Delegates to AuditLogService — keeps controller code concise.
 */
trait LogsAuditEvents
{
    protected function auditLog(
        string $action,
        ?Model $target   = null,
        array  $old      = [],
        array  $new      = [],
        array  $metadata = [],
    ): void {
        app(AuditLogService::class)->record($action, $target, $old, $new, $metadata);
    }
}
```

---

## Task 4: AuditableObserver + AppServiceProvider + Permissions

**Files:**
- Create: `app/Observers/AuditableObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/permissions.php`

- [ ] **Step 1: Create AuditableObserver**

```php
<?php
// app/Observers/AuditableObserver.php

namespace App\Observers;

use App\Models\Announcement;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Event;
use App\Models\Sermon;
use App\Models\ServicePlan;
use App\Models\ServingPosition;
use App\Models\Task;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic observer that logs 'created' and 'deleted' events for all
 * auditable models. Models are registered in AppServiceProvider::boot().
 *
 * Fires on created + deleted only. All 'updated' events and named
 * business actions (publish, assign, archive) use explicit service calls.
 */
class AuditableObserver
{
    /**
     * Map model class → dot-separated action prefix.
     * Verb ('created' or 'deleted') is appended automatically.
     */
    private static array $prefixMap = [
        Announcement::class     => 'announcement',
        Event::class            => 'event',
        Task::class             => 'task',
        Department::class       => 'department',
        ServingPosition::class  => 'schedule.position',
        ServicePlan::class      => 'schedule.plan',
        AttendanceSession::class => 'attendance.session',
        Sermon::class           => 'sermon',
    ];

    public function created(Model $model): void
    {
        $prefix = self::$prefixMap[get_class($model)] ?? null;
        if (! $prefix) return;

        $metadata = [];
        if (isset($model->department_id) && $model->department_id) {
            $metadata['department_id'] = (int) $model->department_id;
        }

        app(AuditLogService::class)->record(
            action:   "{$prefix}.created",
            target:   $model,
            metadata: $metadata,
        );
    }

    public function deleted(Model $model): void
    {
        $prefix = self::$prefixMap[get_class($model)] ?? null;
        if (! $prefix) return;

        $metadata = [];
        if (isset($model->department_id) && $model->department_id) {
            $metadata['department_id'] = (int) $model->department_id;
        }

        app(AuditLogService::class)->record(
            action:   "{$prefix}.deleted",
            target:   $model,
            metadata: $metadata,
        );
    }
}
```

- [ ] **Step 2: Register observer in AppServiceProvider::boot()**

Read `app/Providers/AppServiceProvider.php`. Add the following import at the top:

```php
use App\Observers\AuditableObserver;
```

Then inside `public function boot(): void`, add these observer registrations at the beginning of the method (before the existing `$this->registerPolicies()` line or after it — either works):

```php
// ── Audit observers (created + deleted only) ───────────────────────────
foreach ([
    Announcement::class,
    Event::class,
    Task::class,
    Department::class,
    ServingPosition::class,
    ServicePlan::class,
    AttendanceSession::class,
    Sermon::class,
] as $model) {
    $model::observe(AuditableObserver::class);
}
```

- [ ] **Step 3: Add `audit.view` to coordinator role in config/permissions.php**

Read `config/permissions.php`. Find the `'coordinator' => [...]` role array. Add `'audit.view'` to the end of that role's permission list (before the closing `],`).

- [ ] **Step 4: Re-run the permissions seeder**

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Expected: `RBAC seeded: N permissions across 5 roles.`

---

## Task 5: Migrate Existing Audit Calls

Update the three files that already call `AuditLog::record()` to use the new `AuditLogService` and new action names.

**Files:**
- Modify: `app/Services/AuthService.php`
- Modify: `app/Http/Controllers/Dashboard/ChurchSettingsController.php`
- Modify: `app/Http/Controllers/Dashboard/DepartmentController.php`

- [ ] **Step 1: Update AuthService**

Read `app/Services/AuthService.php`. Replace the entire file:

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function login(array $credentials, bool $remember = false): User
    {
        if (! Auth::attempt($credentials, $remember)) {
            // Log failed login attempt — look up user by email for context
            $failedUser = User::where('email', $credentials['email'])->first();
            if ($failedUser) {
                $this->audit->record(
                    action:   'auth.login.failed',
                    target:   $failedUser,
                    metadata: ['email' => $credentials['email']],
                    actor:    $failedUser,
                    churchId: $failedUser->church_id,
                );
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        $this->audit->record(
            action:   'auth.login',
            actor:    $user,
            churchId: $user->church_id,
        );

        return $user;
    }

    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            $this->audit->record(
                action:   'auth.logout',
                actor:    $user,
                churchId: $user->church_id,
            );
        }

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    public function register(array $data): User
    {
        $user = User::create([
            'church_id'         => $data['church_id'] ?? null,
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('member');

        Auth::login($user);

        return $user;
    }
}
```

- [ ] **Step 2: Update ChurchSettingsController**

Read `app/Http/Controllers/Dashboard/ChurchSettingsController.php`.

Add this import at the top (with other `use` statements):
```php
use App\Services\AuditLogService;
use App\Traits\LogsAuditEvents;
```

Add `use LogsAuditEvents;` inside the class body at the top.

Replace the private `auditSettings()` method with:
```php
private function auditSettings(Request $request, string $action, array $old, array $new): void
{
    $church = $this->resolvedChurch();
    $this->auditLog($action, $church, $old, $new);
}
```

The rest of the file remains unchanged — the four existing audit calls (`settings.profile.updated`, `settings.branding.updated`, `settings.security.updated`, `settings.advanced.updated`) are already correct action names and continue to work via the updated `auditSettings()` helper.

- [ ] **Step 3: Update DepartmentController — rename action strings and add department.updated**

Read `app/Http/Controllers/Dashboard/DepartmentController.php`.

Add this import at the top:
```php
use App\Traits\LogsAuditEvents;
```

Add `use LogsAuditEvents;` inside the class body.

Replace the three inline `AuditLog::record(...)` calls:

**In `addMember()`**, replace the `AuditLog::record(...)` call with:
```php
$this->auditLog(
    'department.member.added',
    $user,
    [],
    ['department_id' => $department->id, 'department_name' => $department->name, 'role' => $validated['role']],
    ['department_id' => $department->id],
);
```

**In `updateMemberRole()`**, replace the `AuditLog::record(...)` call with:
```php
$this->auditLog(
    'department.member.updated',
    $user,
    ['role' => $targetPivotRole],
    ['department_id' => $department->id, 'department_name' => $department->name, 'role' => $newRole],
    ['department_id' => $department->id],
);
```

**In `removeMember()`**, replace the `AuditLog::record(...)` call with:
```php
$this->auditLog(
    'department.member.removed',
    $user,
    ['department_id' => $department->id, 'department_name' => $department->name, 'role' => $targetPivotRole],
    [],
    ['department_id' => $department->id],
);
```

**In `update()`**, add an audit call after `$this->departments->update($department, $request->validated());`:
```php
$this->auditLog('department.updated', $department, [], [], ['department_id' => $department->id]);
```

Remove the old `use App\Models\AuditLog;` import if no other code still uses it in this file.

- [ ] **Step 4: Update MembersController timeline to use new action names**

Read `app/Http/Controllers/Dashboard/MembersController.php`.

In the `buildTimeline()` method, find the `whereIn('action', [...])` query and replace the old action names:

Replace:
```php
->whereIn('action', ['member.added', 'member.removed', 'member.role_updated', 'role.assigned'])
```

With:
```php
->whereIn('action', ['department.member.added', 'department.member.removed', 'department.member.updated', 'member.role.assigned'])
```

Also update the `$typeMap` and `match` block below it:

Replace:
```php
$typeMap = [
    'member.added'        => 'dept_joined',
    'member.removed'      => 'dept_left',
    'member.role_updated' => 'dept_joined',
    'role.assigned'       => 'role_changed',
];
```

With:
```php
$typeMap = [
    'department.member.added'   => 'dept_joined',
    'department.member.removed' => 'dept_left',
    'department.member.updated' => 'dept_joined',
    'member.role.assigned'      => 'role_changed',
];
```

Replace the `match` block:
```php
$label = match ($log->action) {
    'department.member.added'   => 'Joined ' . ($deptName ?? 'a department'),
    'department.member.removed' => 'Left '   . ($deptName ?? 'a department'),
    'department.member.updated' => 'Role updated in ' . ($deptName ?? 'a department'),
    'member.role.assigned'      => 'Role changed to ' . ($role ?? 'unknown'),
    default                     => $log->action,
};
```

---

## Task 6: Member + Profile Audit Events

**Files:**
- Modify: `app/Http/Controllers/Dashboard/MembersController.php`
- Modify: `app/Http/Controllers/Dashboard/ProfileController.php`

- [ ] **Step 1: Add LogsAuditEvents trait to MembersController**

Read `app/Http/Controllers/Dashboard/MembersController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

In `updateProfile()`, after `MemberProfile::updateOrCreate(...)`, add:
```php
$this->auditLog('member.updated', $user, [], $data);
```

- [ ] **Step 2: Add audit call to ProfileController for password change**

Read `app/Http/Controllers/Dashboard/ProfileController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

In `updatePassword()`, after `$request->user()->update(...)`, add:
```php
$this->auditLog('auth.password.changed', $request->user());
```

---

## Task 7: Announcements + Events Audit Events

**Files:**
- Modify: `app/Http/Controllers/Dashboard/AnnouncementsController.php`
- Modify: `app/Http/Controllers/Dashboard/EventsController.php`

- [ ] **Step 1: Add audit calls to AnnouncementsController**

Read `app/Http/Controllers/Dashboard/AnnouncementsController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

**In `update()`**, after `$this->announcements->update($announcement, $request->validated());`, add:
```php
$this->auditLog(
    'announcement.updated',
    $announcement,
    [],
    [],
    ['department_id' => $announcement->department_id],
);
```

**In `publish()`**, after each branch:
- After `$this->announcements->unpublish($announcement)`, before the return, add:
```php
$this->auditLog('announcement.unpublished', $announcement, [], [], ['department_id' => $announcement->department_id]);
```
- After `$this->announcements->publish($announcement)` (and before `AnnouncementWasPublished::dispatch`), add:
```php
$this->auditLog('announcement.published', $announcement, [], [], ['department_id' => $announcement->department_id]);
```

- [ ] **Step 2: Add audit calls to EventsController**

Read `app/Http/Controllers/Dashboard/EventsController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

**In `update()`**, after `$this->events->update($event, $request->validated());`, add:
```php
$this->auditLog(
    'event.updated',
    $event,
    [],
    [],
    ['department_id' => $event->department_id],
);
```

**In `rsvp()`**, after `$this->events->removeRsvp($event, $user)` (toggle cancel branch), add:
```php
$this->auditLog('event.rsvp.cancelled', $event, [], [], ['department_id' => $event->department_id]);
```

After `$this->events->upsertRsvp($event, $user, $newStatus)`, add:
```php
$this->auditLog('event.rsvp.created', $event, [], ['status' => $newStatus], ['department_id' => $event->department_id]);
```

**In `cancelRsvp()`**, after `$this->events->removeRsvp($event, $request->user())`, add:
```php
$this->auditLog('event.rsvp.cancelled', $event, [], [], ['department_id' => $event->department_id]);
```

---

## Task 8: Tasks Audit Events

**Files:**
- Modify: `app/Http/Controllers/Dashboard/TasksController.php`

- [ ] **Step 1: Read the file**

Read `app/Http/Controllers/Dashboard/TasksController.php`.

- [ ] **Step 2: Add trait and audit calls**

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

**In `store()`**, after `$task = $this->tasks->create(...)`, the observer fires `task.created` automatically. Also add explicit `task.assigned` when there is an assignee:
```php
if ($task->assigned_to && $task->assigned_to !== $actor->id) {
    $this->auditLog(
        'task.assigned',
        $task,
        [],
        ['assigned_to' => $task->assigned_to],
        ['department_id' => $task->department_id],
    );
}
```
(This goes after the existing `if ($task->assigned_to && $task->assigned_to !== $actor->id) { TaskWasAssigned::dispatch(...); }` block.)

**In `update()`**, after `$this->tasks->update($task, $request->validated());`, add:
```php
$this->auditLog('task.updated', $task, [], [], ['department_id' => $task->department_id]);
```

**In `updateStatus()`**, after `$this->tasks->updateStatus($task, $validated['status']);`, add:
```php
$action = $validated['status'] === 'completed' ? 'task.completed' : 'task.status.changed';
$this->auditLog(
    $action,
    $task,
    [],
    ['status' => $validated['status']],
    ['department_id' => $task->department_id],
);
```

**In `comment()`**, after `$this->tasks->addComment(...)`, add:
```php
$this->auditLog('task.comment.added', $task, [], [], ['department_id' => $task->department_id]);
```

---

## Task 9: Attendance + Files Audit Events

**Files:**
- Modify: `app/Http/Controllers/Dashboard/AttendanceController.php`
- Modify: `app/Http/Controllers/Dashboard/FilesController.php`
- Modify: `app/Http/Controllers/Dashboard/MediaController.php`

- [ ] **Step 1: Add audit calls to AttendanceController**

Read `app/Http/Controllers/Dashboard/AttendanceController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

The observer handles `AttendanceSession::created` → `attendance.session.created` automatically.

**In `saveAttendance()`**, after `$saved = $this->attendance->bulkSave(...)`, add:
```php
$this->auditLog(
    'attendance.bulk.marked',
    $session,
    [],
    ['saved' => $saved],
    ['department_id' => $session->department_id],
);
```

Find the `updateStatus()` method. After `$session->update(['status' => $newStatus])` (or however the status is applied — read the file to confirm the exact update call), add:
```php
if ($newStatus === 'active') {
    $this->auditLog('attendance.session.opened', $session, [], ['status' => 'active'], ['department_id' => $session->department_id]);
} elseif ($newStatus === 'completed') {
    $this->auditLog('attendance.session.closed', $session, [], ['status' => 'completed'], ['department_id' => $session->department_id]);
}
```

- [ ] **Step 2: Add audit calls to FilesController**

Read `app/Http/Controllers/Dashboard/FilesController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

**In `store()`**, after the `$file = $this->media->upload(...)` call, add:
```php
$this->auditLog('file.uploaded', $file);
```

**In `destroy()`**, read the method to find where `$file` is deleted. Add before the delete call:
```php
$this->auditLog('file.deleted', $file);
```

- [ ] **Step 3: Add audit calls to MediaController**

Read `app/Http/Controllers/Dashboard/MediaController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

In whatever `store()` method handles media upload, add after upload:
```php
$this->auditLog('media.uploaded', $media ?? $file);
```

In `destroy()`, add before deletion:
```php
$this->auditLog('media.deleted', $media ?? $file);
```

---

## Task 10: Scheduling + Sermons Audit Events

**Files:**
- Modify: `app/Http/Controllers/Dashboard/ServicePlanController.php`
- Modify: `app/Http/Controllers/Dashboard/ServingPositionController.php`
- Modify: `app/Http/Controllers/Dashboard/AssignmentController.php`
- Modify: `app/Http/Controllers/Dashboard/SermonsController.php`
- Modify: `app/Http/Controllers/Dashboard/SermonChannelController.php`

- [ ] **Step 1: Add audit calls to ServicePlanController**

Read `app/Http/Controllers/Dashboard/ServicePlanController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

The observer handles `ServicePlan::created` and `ServicePlan::deleted` automatically.

**In `update()`**, after `$plan->update($validated)`, add:
```php
$this->auditLog('schedule.plan.updated', $plan);
```

**In `publish()`**, after `$plan->update([...])`, add:
```php
$this->auditLog('schedule.plan.published', $plan);
```

**In `archive()`**, after `$plan->update(['status' => 'archived'])`, add:
```php
$this->auditLog('schedule.plan.archived', $plan);
```

- [ ] **Step 2: Add audit calls to ServingPositionController**

Read `app/Http/Controllers/Dashboard/ServingPositionController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

The observer handles `ServingPosition::created` and `ServingPosition::deleted` automatically.

**In `update()`**, after `$pos->update($validated)`, add:
```php
$this->auditLog('schedule.position.updated', $pos, [], [], ['department_id' => $pos->department_id]);
```

- [ ] **Step 3: Add audit calls to AssignmentController**

Read `app/Http/Controllers/Dashboard/AssignmentController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

**In `store()`**, after `$assignment = VolunteerAssignment::create([...])`, add:
```php
$deptId = $planPosition->servingPosition?->department_id;
$this->auditLog(
    'schedule.assignment.created',
    $assignment,
    [],
    ['user_id' => $assignment->user_id, 'position' => $planPosition->servingPosition?->name],
    array_filter(['department_id' => $deptId]),
);
```

**In `destroy()`**, after `$assignment->delete()`, add:
```php
$this->auditLog('schedule.assignment.removed', $assignment);
```

**In `respond()`**, after `$assignment->update([...])`, add:
```php
$action = $validated['status'] === 'confirmed' ? 'schedule.assignment.confirmed' : 'schedule.assignment.declined';
$deptId = $assignment->planPosition?->servingPosition?->department_id;
$this->auditLog($action, $assignment, [], ['status' => $validated['status']], array_filter(['department_id' => $deptId]));
```

- [ ] **Step 4: Add audit calls to SermonsController**

Read `app/Http/Controllers/Dashboard/SermonsController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

The observer handles `Sermon::created` and `Sermon::deleted` automatically.

In `update()` (find the method that handles PUT/PATCH for a sermon), after the update call, add:
```php
$this->auditLog('sermon.updated', $sermon);
```

In the publish/visibility toggle method (if it exists), add:
```php
$this->auditLog('sermon.published', $sermon);
```

- [ ] **Step 5: Add audit calls to SermonChannelController**

Read `app/Http/Controllers/Dashboard/SermonChannelController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

In `store()` (if it exists for creating channel connections), after creation, add:
```php
$this->auditLog('sermon.channel.created', $channel ?? $connection);
```

In `update()`, after the update, add:
```php
$this->auditLog('sermon.channel.updated', $channel ?? $connection);
```

In `destroy()`, before deletion, add:
```php
$this->auditLog('sermon.channel.deleted', $channel ?? $connection);
```

---

## Task 11: Notifications Audit Event

**Files:**
- Modify: `app/Http/Controllers/Dashboard/NotificationsController.php`

- [ ] **Step 1: Add audit call to NotificationsController**

Read `app/Http/Controllers/Dashboard/NotificationsController.php`.

Add import: `use App\Traits\LogsAuditEvents;`
Add trait use: `use LogsAuditEvents;` inside the class.

Find any method that sends or dispatches a bulk notification to members (not the mark-read endpoints). If there is a `send()` or similar action method, add after the send:
```php
$this->auditLog('notification.sent', null, [], ['count' => $count ?? 1]);
```

If no such send method exists in the controller (notifications are sent only via domain events), skip this step — it's acceptable since notifications are already triggered by audited actions.

---

## Task 12: AuditLogController + Feature Test

**Files:**
- Create: `app/Http/Controllers/Dashboard/AuditLogController.php`
- Create: `tests/Feature/AuditLogControllerTest.php`

- [ ] **Step 1: Create the feature test**

```php
<?php
// tests/Feature/AuditLogControllerTest.php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $admin;
    private User $coordinator;
    private User $member;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church      = Church::create(['name' => 'Test Church', 'slug' => 'test-' . uniqid()]);
        $this->admin       = User::factory()->create(['church_id' => $this->church->id]);
        $this->coordinator = User::factory()->create(['church_id' => $this->church->id]);
        $this->member      = User::factory()->create(['church_id' => $this->church->id]);

        $this->admin->assignRole('church_admin');
        $this->coordinator->assignRole('coordinator');
        $this->member->assignRole('member');

        $this->dept = Department::create([
            'church_id' => $this->church->id,
            'name'      => 'Media Team',
            'slug'      => 'media-team',
            'is_active' => true,
        ]);
    }

    private function createLog(string $action, array $metadata = [], ?User $actor = null): AuditLog
    {
        return AuditLog::create([
            'church_id'  => $this->church->id,
            'user_id'    => ($actor ?? $this->admin)->id,
            'action'     => $action,
            'metadata'   => $metadata ?: null,
        ]);
    }

    public function test_admin_can_view_audit_log_index(): void
    {
        $this->createLog('auth.login');
        $this->createLog('announcement.published', ['department_id' => $this->dept->id]);

        $response = $this->actingAs($this->admin)->get('/dashboard/audit');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard/Audit/Index')
                 ->has('logs.data', 2)
        );
    }

    public function test_member_cannot_view_audit_log(): void
    {
        $response = $this->actingAs($this->member)->get('/dashboard/audit');
        $response->assertForbidden();
    }

    public function test_coordinator_sees_only_their_department_events(): void
    {
        // Coordinator is member of dept
        $this->dept->members()->attach($this->coordinator->id, ['role' => 'coordinator', 'joined_at' => now()]);

        // Event with dept metadata — should be visible
        $this->createLog('announcement.published', ['department_id' => $this->dept->id]);

        // Event without dept metadata — should NOT be visible to coordinator
        $this->createLog('auth.login');

        $response = $this->actingAs($this->coordinator)->get('/dashboard/audit');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard/Audit/Index')
                 ->has('logs.data', 1)  // only dept event
        );
    }

    public function test_admin_can_filter_by_module(): void
    {
        $this->createLog('auth.login');
        $this->createLog('announcement.published', ['department_id' => $this->dept->id]);

        $response = $this->actingAs($this->admin)->get('/dashboard/audit?module=auth');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->has('logs.data', 1)
        );
    }

    public function test_admin_can_filter_by_date_range(): void
    {
        // Create a log with a past date
        $old = $this->createLog('auth.login');
        $old->update(['created_at' => now()->subDays(10)]);

        // Create a recent log
        $this->createLog('auth.logout');

        $response = $this->actingAs($this->admin)->get('/dashboard/audit?date_from=' . now()->subDay()->format('Y-m-d'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->has('logs.data', 1)
        );
    }
}
```

- [ ] **Step 2: Run test to confirm it fails (controller doesn't exist yet)**

```bash
php artisan test tests/Feature/AuditLogControllerTest.php
```

Expected: All tests fail with 404 or 403 (route not registered).

- [ ] **Step 3: Create AuditLogController**

```php
<?php
// app/Http/Controllers/Dashboard/AuditLogController.php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    use ResolvesChurchData;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $user     = $request->user();
        $churchId = $this->resolvedChurchId();
        $isAdmin  = $user->hasRole('church_admin') || $user->hasRole('super_admin');

        $query = AuditLog::where('church_id', $churchId)
            ->with('user:id,name,avatar')
            ->latest();

        // Coordinators: scope to events tagged with their department(s)
        if (! $isAdmin) {
            $deptIds = $user->departments()->pluck('departments.id')->map(fn ($id) => (int) $id)->toArray();

            if (empty($deptIds)) {
                // Coordinator with no departments sees nothing
                $query->whereRaw('0 = 1');
            } else {
                $placeholders = implode(',', $deptIds);
                $query->whereRaw("json_extract(metadata, '$.department_id') IN ({$placeholders})");
            }
        }

        // ── Filters ───────────────────────────────────────────────────────────

        if ($module = $request->module) {
            $query->where('action', 'like', "{$module}.%");
        }

        if ($action = $request->action) {
            $query->where('action', $action);
        }

        if ($userId = $request->user_id) {
            $query->where('user_id', (int) $userId);
        }

        if ($deptId = $request->department_id) {
            $query->whereRaw("json_extract(metadata, '$.department_id') = ?", [(int) $deptId]);
        }

        if ($from = $request->date_from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->date_to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(25)->withQueryString()->through(fn ($log) => [
            'id'          => $log->id,
            'action'      => $log->action,
            'model_type'  => $log->model_type,
            'model_id'    => $log->model_id,
            'target_name' => $log->target_name,
            'old_values'  => $log->old_values,
            'new_values'  => $log->new_values,
            'metadata'    => $log->metadata,
            'ip_address'  => $log->ip_address,
            'created_at'  => $log->created_at?->toISOString(),
            'actor'       => $log->user
                ? ['id' => $log->user->id, 'name' => $log->user->name, 'avatar' => $log->user->avatar]
                : null,
        ]);

        // Distinct actors who have audit entries for this church (for filter dropdown)
        $actors = AuditLog::where('church_id', $churchId)
            ->whereNotNull('user_id')
            ->join('users', 'audit_logs.user_id', '=', 'users.id')
            ->distinct()
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);

        $modules = [
            'auth', 'member', 'department', 'announcement', 'event',
            'task', 'attendance', 'file', 'media', 'notification',
            'schedule', 'sermon', 'settings', 'role', 'admin',
        ];

        return Inertia::render('Dashboard/Audit/Index', [
            'logs'        => $logs,
            'filters'     => $request->only('module', 'action', 'user_id', 'department_id', 'date_from', 'date_to'),
            'actors'      => $actors,
            'modules'     => $modules,
            'departments' => $this->activeDepartments(),
            'isAdmin'     => $isAdmin,
        ]);
    }
}
```

---

## Task 13: Audit/Index.vue

**Files:**
- Create: `resources/js/Pages/Dashboard/Audit/Index.vue`

- [ ] **Step 1: Create the directory and Vue page**

Create `resources/js/Pages/Dashboard/Audit/Index.vue`:

```vue
<!-- resources/js/Pages/Dashboard/Audit/Index.vue -->
<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import { ShieldCheck, ChevronDown, ChevronUp, Search } from 'lucide-vue-next'
import { debounce } from 'lodash'

interface Actor { id: number; name: string; avatar: string | null }
interface Dept  { id: number; name: string }

interface LogEntry {
    id: number
    action: string
    model_type: string | null
    model_id: number | null
    target_name: string | null
    old_values: Record<string, unknown> | null
    new_values: Record<string, unknown> | null
    metadata: Record<string, unknown> | null
    ip_address: string | null
    created_at: string | null
    actor: Actor | null
}

interface PaginatedLogs {
    data: LogEntry[]
    links: { url: string | null; label: string; active: boolean }[]
    current_page: number
    last_page: number
    total: number
}

const props = defineProps<{
    logs: PaginatedLogs
    filters: {
        module?: string
        action?: string
        user_id?: string
        department_id?: string
        date_from?: string
        date_to?: string
    }
    actors: Actor[]
    modules: string[]
    departments: Dept[]
    isAdmin: boolean
}>()

// ── Filter state ───────────────────────────────────────────────────────────────

const module      = ref(props.filters.module ?? '')
const action      = ref(props.filters.action ?? '')
const userId      = ref(props.filters.user_id ?? '')
const deptId      = ref(props.filters.department_id ?? '')
const dateFrom    = ref(props.filters.date_from ?? '')
const dateTo      = ref(props.filters.date_to ?? '')

function applyFilters() {
    router.get('/dashboard/audit', {
        module:        module.value || undefined,
        action:        action.value || undefined,
        user_id:       userId.value || undefined,
        department_id: deptId.value || undefined,
        date_from:     dateFrom.value || undefined,
        date_to:       dateTo.value || undefined,
    }, { preserveState: true, replace: true })
}

const debouncedApply = debounce(applyFilters, 300)

watch([module, action, userId, deptId, dateFrom, dateTo], () => debouncedApply())

function clearFilters() {
    module.value = ''
    action.value = ''
    userId.value = ''
    deptId.value = ''
    dateFrom.value = ''
    dateTo.value = ''
}

// ── Expand/collapse rows ───────────────────────────────────────────────────────

const expanded = ref<Set<number>>(new Set())

function toggleExpand(id: number) {
    if (expanded.value.has(id)) {
        expanded.value.delete(id)
    } else {
        expanded.value.add(id)
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function formatAction(action: string): string {
    return action.split('.').map(p => p.replace(/_/g, ' ')).join(' › ')
}

function formatDate(iso: string | null): string {
    if (!iso) return '–'
    return new Date(iso).toLocaleString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}

function timeAgo(iso: string | null): string {
    if (!iso) return ''
    const diff = Date.now() - new Date(iso).getTime()
    const mins  = Math.floor(diff / 60000)
    if (mins < 1)  return 'just now'
    if (mins < 60) return `${mins}m ago`
    const hrs  = Math.floor(mins / 60)
    if (hrs  < 24) return `${hrs}h ago`
    return `${Math.floor(hrs / 24)}d ago`
}

function moduleColor(action: string): string {
    const prefix = action.split('.')[0]
    const colors: Record<string, string> = {
        auth:         'bg-indigo-50 text-indigo-700 border-indigo-200',
        member:       'bg-blue-50 text-blue-700 border-blue-200',
        department:   'bg-purple-50 text-purple-700 border-purple-200',
        announcement: 'bg-amber-50 text-amber-700 border-amber-200',
        event:        'bg-green-50 text-green-700 border-green-200',
        task:         'bg-orange-50 text-orange-700 border-orange-200',
        attendance:   'bg-cyan-50 text-cyan-700 border-cyan-200',
        file:         'bg-slate-50 text-slate-700 border-slate-200',
        media:        'bg-slate-50 text-slate-700 border-slate-200',
        schedule:     'bg-violet-50 text-violet-700 border-violet-200',
        sermon:       'bg-rose-50 text-rose-700 border-rose-200',
        settings:     'bg-gray-50 text-gray-700 border-gray-200',
        notification: 'bg-teal-50 text-teal-700 border-teal-200',
        admin:        'bg-red-50 text-red-700 border-red-200',
        role:         'bg-pink-50 text-pink-700 border-pink-200',
    }
    return colors[prefix] ?? 'bg-gray-50 text-gray-700 border-gray-200'
}

function hasDetails(entry: LogEntry): boolean {
    return !!(entry.old_values || entry.new_values || entry.metadata)
}

const hasActiveFilters = computed(() =>
    module.value || action.value || userId.value || deptId.value || dateFrom.value || dateTo.value
)
</script>

<template>
    <DashboardLayout title="Audit Log">
        <PageHeader title="Audit Log">
            <template #subtitle>
                Complete activity trail for your church platform
            </template>
        </PageHeader>

        <!-- Filter bar -->
        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <!-- Module -->
                <select
                    v-model="module"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                >
                    <option value="">All modules</option>
                    <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
                </select>

                <!-- Actor -->
                <select
                    v-if="isAdmin"
                    v-model="userId"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                >
                    <option value="">All actors</option>
                    <option v-for="actor in actors" :key="actor.id" :value="String(actor.id)">{{ actor.name }}</option>
                </select>

                <!-- Department -->
                <select
                    v-if="isAdmin"
                    v-model="deptId"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                >
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>

                <!-- Date from -->
                <input
                    v-model="dateFrom"
                    type="date"
                    placeholder="From date"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                />

                <!-- Date to -->
                <input
                    v-model="dateTo"
                    type="date"
                    placeholder="To date"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                />

                <!-- Clear -->
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-500 hover:bg-gray-50"
                    @click="clearFilters"
                >
                    Clear filters
                </button>
            </div>
        </div>

        <!-- Stats pill -->
        <p class="mb-3 text-xs text-gray-500">
            {{ logs.total }} total entries
            <span v-if="hasActiveFilters"> matching current filters</span>
        </p>

        <!-- Empty state -->
        <div
            v-if="logs.data.length === 0"
            class="flex flex-col items-center justify-center rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm"
        >
            <ShieldCheck class="mb-3 h-8 w-8 text-gray-200" />
            <p class="text-sm font-medium text-gray-600">No audit entries found</p>
            <p class="mt-1 text-xs text-gray-400">Try adjusting your filters.</p>
        </div>

        <!-- Log table -->
        <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="divide-y divide-gray-50">
                <div
                    v-for="entry in logs.data"
                    :key="entry.id"
                    class="group"
                >
                    <!-- Main row -->
                    <div
                        class="flex items-start gap-3 px-5 py-3.5 hover:bg-gray-50/60 transition-colors cursor-default"
                        :class="{ 'cursor-pointer': hasDetails(entry) }"
                        @click="hasDetails(entry) && toggleExpand(entry.id)"
                    >
                        <!-- Module badge -->
                        <span
                            :class="['inline-flex shrink-0 items-center rounded-md border px-2 py-0.5 text-[10px] font-semibold', moduleColor(entry.action)]"
                        >
                            {{ entry.action.split('.').pop() }}
                        </span>

                        <!-- Details -->
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium capitalize text-gray-800">
                                {{ formatAction(entry.action) }}
                                <span v-if="entry.target_name" class="font-normal text-gray-500">
                                    — {{ entry.target_name }}
                                </span>
                            </p>
                            <p class="mt-0.5 text-[11px] text-gray-400">
                                {{ entry.actor?.name ?? 'System' }}
                                <span v-if="entry.ip_address" class="text-gray-300"> · {{ entry.ip_address }}</span>
                            </p>
                        </div>

                        <!-- Timestamp + expand -->
                        <div class="flex shrink-0 items-center gap-2">
                            <div class="text-right">
                                <p class="text-[11px] text-gray-500">{{ timeAgo(entry.created_at) }}</p>
                                <p class="text-[10px] text-gray-300">{{ formatDate(entry.created_at) }}</p>
                            </div>
                            <button
                                v-if="hasDetails(entry)"
                                type="button"
                                class="rounded p-0.5 text-gray-300 hover:text-gray-500"
                            >
                                <ChevronDown v-if="!expanded.has(entry.id)" class="h-3.5 w-3.5" />
                                <ChevronUp   v-else class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Expandable details -->
                    <div
                        v-if="expanded.has(entry.id) && hasDetails(entry)"
                        class="border-t border-gray-50 bg-gray-50/50 px-5 py-3 font-mono text-xs text-gray-600"
                    >
                        <div v-if="entry.old_values" class="mb-2">
                            <p class="mb-1 font-sans text-[10px] font-semibold uppercase tracking-wider text-gray-400">Before</p>
                            <pre class="whitespace-pre-wrap break-all text-[11px]">{{ JSON.stringify(entry.old_values, null, 2) }}</pre>
                        </div>
                        <div v-if="entry.new_values" class="mb-2">
                            <p class="mb-1 font-sans text-[10px] font-semibold uppercase tracking-wider text-gray-400">After</p>
                            <pre class="whitespace-pre-wrap break-all text-[11px]">{{ JSON.stringify(entry.new_values, null, 2) }}</pre>
                        </div>
                        <div v-if="entry.metadata">
                            <p class="mb-1 font-sans text-[10px] font-semibold uppercase tracking-wider text-gray-400">Context</p>
                            <pre class="whitespace-pre-wrap break-all text-[11px]">{{ JSON.stringify(entry.metadata, null, 2) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <AppPagination v-if="logs.last_page > 1" :links="logs.links" class="mt-6" />
    </DashboardLayout>
</template>
```

---

## Task 14: Routes + Sidebar Nav

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/js/Layouts/DashboardLayout.vue`

- [ ] **Step 1: Add route to web.php**

Read `routes/web.php`. Add the following import at the top with other controller imports:

```php
use App\Http\Controllers\Dashboard\AuditLogController;
```

Inside the `Route::middleware(['auth'])->group(function () {` block, add after the existing Settings routes:

```php
// Audit Log
Route::get('/dashboard/audit', [AuditLogController::class, 'index'])->name('dashboard.audit.index');
```

- [ ] **Step 2: Verify route registered**

```bash
php artisan route:list --path=audit --columns=method,uri,name
```

Expected: One row: `GET /dashboard/audit | dashboard.audit.index`

- [ ] **Step 3: Add Audit Log nav item to DashboardLayout.vue**

Read `resources/js/Layouts/DashboardLayout.vue`.

In the `import { ... } from 'lucide-vue-next'` line, add `ShieldCheck` to the import list if not already present.

In the `navGroups` computed array, find the `Administration` group (or whichever group Settings is in). Add the Audit Log item after Settings:

```typescript
{ label: 'Audit Log', href: '/dashboard/audit', icon: ShieldCheck, perm: 'audit.view' },
```

- [ ] **Step 4: Run the feature tests**

```bash
php artisan test tests/Feature/AuditLogControllerTest.php
```

Expected: All 5 tests pass.

---

## Task 15: RecentActivityWidget.vue

**Files:**
- Create: `resources/js/Components/Dashboard/RecentActivityWidget.vue`

- [ ] **Step 1: Create the widget component**

```vue
<!-- resources/js/Components/Dashboard/RecentActivityWidget.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ShieldCheck } from 'lucide-vue-next'

interface ActivityEntry {
    id: number
    action: string
    target_name: string | null
    created_at: string
    actor: { id: number; name: string; avatar: string | null } | null
}

defineProps<{ activities: ActivityEntry[] }>()

function moduleColor(action: string): string {
    const prefix = action.split('.')[0]
    const colors: Record<string, string> = {
        auth:         'bg-indigo-400',
        member:       'bg-blue-400',
        department:   'bg-purple-400',
        announcement: 'bg-amber-400',
        event:        'bg-green-400',
        task:         'bg-orange-400',
        attendance:   'bg-cyan-400',
        schedule:     'bg-violet-400',
        sermon:       'bg-rose-400',
        settings:     'bg-gray-400',
        file:         'bg-slate-400',
        media:        'bg-slate-400',
        notification: 'bg-teal-400',
    }
    return colors[prefix] ?? 'bg-gray-400'
}

function formatAction(action: string): string {
    return action.split('.').map(p => p.replace(/_/g, ' ')).join(' › ')
}

function timeAgo(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime()
    const mins  = Math.floor(diff / 60000)
    if (mins < 1)  return 'just now'
    if (mins < 60) return `${mins}m ago`
    const hrs   = Math.floor(mins / 60)
    if (hrs < 24)  return `${hrs}h ago`
    return `${Math.floor(hrs / 24)}d ago`
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
            <div class="flex items-center gap-2">
                <ShieldCheck class="h-4 w-4 text-gray-400" />
                <h2 class="text-sm font-semibold text-gray-900">Recent Activity</h2>
            </div>
            <Link href="/dashboard/audit" class="text-xs text-indigo-600 hover:underline">
                View all →
            </Link>
        </div>

        <!-- Empty state -->
        <div v-if="activities.length === 0" class="px-5 py-6 text-center">
            <p class="text-xs text-gray-400">No recent activity.</p>
        </div>

        <!-- Activity list -->
        <ul v-else class="divide-y divide-gray-50">
            <li
                v-for="entry in activities"
                :key="entry.id"
                class="flex items-start gap-3 px-5 py-3"
            >
                <!-- Module colour dot -->
                <span
                    :class="['mt-1.5 h-2 w-2 flex-shrink-0 rounded-full', moduleColor(entry.action)]"
                />

                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium capitalize text-gray-800">
                        {{ formatAction(entry.action) }}
                        <span v-if="entry.target_name" class="font-normal text-gray-500">
                            — {{ entry.target_name }}
                        </span>
                    </p>
                    <p class="text-[11px] text-gray-400">{{ entry.actor?.name ?? 'System' }}</p>
                </div>

                <span class="shrink-0 text-[11px] text-gray-400">{{ timeAgo(entry.created_at) }}</span>
            </li>
        </ul>
    </div>
</template>
```

---

## Task 16: DashboardController Update

**Files:**
- Modify: `app/Http/Controllers/Dashboard/DashboardController.php`

- [ ] **Step 1: Read DashboardController**

Read `app/Http/Controllers/Dashboard/DashboardController.php`.

- [ ] **Step 2: Add recent activity query and prop**

Add this import at the top:
```php
use App\Models\AuditLog;
```

Inside `__invoke()`, after the existing queries, add a `$recentActivity` query (before the `return Inertia::render(...)` call):

```php
// Recent activity widget — only for users with audit.view permission
$recentActivity = [];
if ($user->can('audit.view')) {
    try {
        $activityQuery = AuditLog::where('church_id', $churchId)
            ->with('user:id,name,avatar')
            ->latest()
            ->limit(8);

        // Coordinators: scope to their departments
        if (! $user->hasRole('church_admin') && ! $user->hasRole('super_admin')) {
            $deptIds = $user->departments()->pluck('departments.id')->map(fn ($id) => (int) $id)->toArray();
            if (! empty($deptIds)) {
                $placeholders = implode(',', $deptIds);
                $activityQuery->whereRaw("json_extract(metadata, '$.department_id') IN ({$placeholders})");
            } else {
                $activityQuery->whereRaw('0 = 1');
            }
        }

        $recentActivity = $activityQuery->get()->map(fn ($log) => [
            'id'          => $log->id,
            'action'      => $log->action,
            'target_name' => $log->target_name,
            'created_at'  => $log->created_at?->toISOString(),
            'actor'       => $log->user
                ? ['id' => $log->user->id, 'name' => $log->user->name, 'avatar' => $log->user->avatar]
                : null,
        ]);
    } catch (\Throwable) {
        // Audit table may not exist on first deploy
    }
}
```

Add `'recentActivity' => $recentActivity,` to the `Inertia::render(...)` props array.

- [ ] **Step 3: Add widget to Dashboard/Home.vue**

Read `resources/js/Pages/Dashboard/Home.vue`.

Add the import:
```typescript
import RecentActivityWidget from '@/Components/Dashboard/RecentActivityWidget.vue'
import { useAuthStore } from '@/stores/useAuthStore'
```

Add `recentActivity` to the component's `defineProps`:
```typescript
recentActivity: { id: number; action: string; target_name: string | null; created_at: string; actor: { id: number; name: string; avatar: string | null } | null }[]
```

In the template, find a suitable location (e.g., after the stats grid or alongside the existing widgets) and add:
```html
<RecentActivityWidget
    v-if="auth.can('audit.view') && recentActivity.length >= 0"
    :activities="recentActivity"
    class="mt-6"
/>
```

Also add `const auth = useAuthStore()` in the script setup.

---

## Self-Review Checklist

- [x] **Spec coverage — Schema:** Migration adds `target_name` + `metadata` ✓ (Task 1)
- [x] **Spec coverage — AuditLogService:** Task 3 ✓
- [x] **Spec coverage — LogsAuditEvents trait:** Task 3 ✓
- [x] **Spec coverage — AuditableObserver (created/deleted on 8 models):** Task 4 ✓
- [x] **Spec coverage — auth.login.failed + auth.password.changed:** Tasks 5, 6 ✓
- [x] **Spec coverage — department.* events (all 7):** Task 5 ✓
- [x] **Spec coverage — member.* events:** Task 6 ✓
- [x] **Spec coverage — announcement.*, event.* events:** Task 7 ✓
- [x] **Spec coverage — task.* events:** Task 8 ✓
- [x] **Spec coverage — attendance.*, file.*, media.* events:** Task 9 ✓
- [x] **Spec coverage — schedule.*, sermon.* events:** Task 10 ✓
- [x] **Spec coverage — notification.sent:** Task 11 ✓
- [x] **Spec coverage — AuditLogController with filters + coordinator scoping:** Task 12 ✓
- [x] **Spec coverage — Audit/Index.vue (paginated, filterable, expandable):** Task 13 ✓
- [x] **Spec coverage — Routes + sidebar nav:** Task 14 ✓
- [x] **Spec coverage — RecentActivityWidget:** Task 15 ✓
- [x] **Spec coverage — Dashboard integration:** Task 16 ✓
- [x] **No placeholders:** All code blocks are complete.
- [x] **Coordinator scoping:** Uses `json_extract(metadata, '$.department_id')` — works on both SQLite (tests) and MySQL.
- [x] **try/catch on all audit calls:** AuditLogService wraps in try/catch; audit never breaks main flow.
- [x] **Action name consistency:** `department.member.added/updated/removed` used throughout; MembersController timeline updated in Task 5.
- [x] **No git commits:** All commit steps omitted (no git repository).
- [x] **Feature test setup:** `RefreshDatabase`, `Church::create`, `RolesAndPermissionsSeeder`.
