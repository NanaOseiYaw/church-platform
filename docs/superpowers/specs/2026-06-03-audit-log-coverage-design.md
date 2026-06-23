# Audit Log Coverage — Design Spec

**Date:** 2026-06-03
**Status:** Approved

---

## Goal

Expand the existing audit log from 9 logged actions to complete coverage across all 15 platform modules (~55 distinct actions). Add a centralised `AuditLogService`, a hybrid observer/explicit-call implementation pattern, enhanced filters and pagination on the audit viewer, coordinator-scoped access, and a Recent Activity dashboard widget.

---

## Background

The current system logs:
- `auth.login` / `auth.logout` (via `AuthService`)
- `settings.profile.updated`, `settings.branding.updated`, `settings.security.updated`, `settings.advanced.updated` (via `ChurchSettingsController`)
- `member.added`, `member.role_updated`, `member.removed` (via `DepartmentController` — department membership only)

**Standardisation note:** The three existing department-member actions (`member.added`, `member.role_updated`, `member.removed`) will be renamed to `department.member.added`, `department.member.updated`, `department.member.removed` to match the new naming convention. This is a breaking change to the action strings stored in the DB, but since no external system consumes them, the migration plan will update the existing calls in `DepartmentController` to the new names going forward. Historical records with old names will remain as-is.

The audit viewer lives at `GET /dashboard/settings/audit`, is admin-only, shows the last 100 entries with a text search only, and has no filters or pagination.

---

## Architecture

### Implementation Strategy — Hybrid

**Eloquent Observers** fire automatically on `created` and `deleted` for auditable models. This provides zero-miss coverage for creates and deletes even if a developer forgets to add an explicit call.

**Explicit AuditLogService calls** handle all `updated` events and every named business action (publish, assign, archive, RSVP, etc.). This ensures action names are rich and contextual rather than generic.

No duplicates: observers are scoped to `created`/`deleted` only; all updates are explicit.

### AuditLogService

Single class at `app/Services/AuditLogService.php`. Public interface:

```php
AuditLogService::record(
    string $action,
    ?Model $target    = null,
    array  $old       = [],
    array  $new       = [],
    array  $metadata  = [],
    ?User  $actor     = null,   // defaults to Auth::user()
    ?int   $churchId  = null,   // defaults to app('church.id')
)
```

- Always wrapped in `try/catch` — audit failures never break the main request flow.
- Auto-captures `ip_address` and `user_agent` from the current request.
- When `$target` is provided, fills `model_type`, `model_id`, and `target_name` automatically.
- `target_name` is resolved by calling `$target->name ?? $target->title ?? (string) $target->id`.

### AuditableObserver

Generic observer at `app/Observers/AuditableObserver.php`. Any model can register it. Handles only `created` and `deleted` events. Maps model class names to action prefixes via a static map (e.g. `Announcement::class → 'announcement'`).

Registered in `AppServiceProvider::boot()`:

```php
foreach ([
    Announcement::class,
    Event::class,
    Task::class,
    TaskComment::class,
    Sermon::class,
    SermonChannel::class,
    Department::class,
    ServingPosition::class,
    ServicePlan::class,
    AttendanceSession::class,
] as $model) {
    $model::observe(AuditableObserver::class);
}
```

### LogsAuditEvents Trait

Convenience trait `app/Traits/LogsAuditEvents.php` for controllers and services. Provides:

```php
protected function auditLog(
    string $action,
    ?Model $target   = null,
    array  $old      = [],
    array  $new      = [],
    array  $metadata = [],
): void
```

Delegates to `AuditLogService::record()`. Keeps controller code concise.

---

## Schema Changes

One migration adds two columns to `audit_logs`:

| Column | Type | Notes |
|--------|------|-------|
| `target_name` | `string(200)` nullable | Human-readable label for the target |
| `metadata` | `json` nullable | Free-form context (department name, role, etc.) |

Existing columns are unchanged: `church_id`, `user_id`, `action`, `model_type`, `model_id`, `old_values`, `new_values`, `ip_address`, `user_agent`.

---

## Action Naming Convention

Dot-separated, lowercase. First segment = **module prefix** (used as filter key).

```
{module}.{entity}.{verb}
```

Examples: `announcement.published`, `schedule.assignment.confirmed`, `auth.login.failed`.

---

## Complete Action Coverage

### Authentication
| Action | Trigger |
|--------|---------|
| `auth.login` ✓ | `AuthService::login()` |
| `auth.logout` ✓ | `AuthService::logout()` |
| `auth.login.failed` | `AuthService::login()` on failure |
| `auth.password.changed` | `ProfileController::updatePassword()` |

### Members
| Action | Trigger |
|--------|---------|
| `member.created` | Observer on `User::created` |
| `member.updated` | `MembersController::update()` |
| `member.deleted` | Observer on `User::deleted` |
| `member.role.assigned` | `MembersController::assignRole()` or `RolesController` |
| `member.role.removed` | `MembersController::removeRole()` |

### Departments
| Action | Trigger |
|--------|---------|
| `department.created` | Observer on `Department::created` |
| `department.updated` | `DepartmentController::update()` |
| `department.deleted` | Observer on `Department::deleted` |
| `department.member.added` ✓ | `DepartmentController::addMember()` |
| `department.member.updated` ✓ | `DepartmentController::updateMemberRole()` |
| `department.member.removed` ✓ | `DepartmentController::removeMember()` |
| `department.coordinator.assigned` | `DepartmentController::update()` when `coordinator_id` changes |

### Announcements
| Action | Trigger |
|--------|---------|
| `announcement.created` | Observer on `Announcement::created` |
| `announcement.updated` | `AnnouncementsController::update()` |
| `announcement.published` | `AnnouncementsController::publish()` |
| `announcement.deleted` | Observer on `Announcement::deleted` |

### Events
| Action | Trigger |
|--------|---------|
| `event.created` | Observer on `Event::created` |
| `event.updated` | `EventsController::update()` |
| `event.deleted` | Observer on `Event::deleted` |
| `event.rsvp.created` | `EventsController::rsvp()` |
| `event.rsvp.cancelled` | `EventsController::cancelRsvp()` |

### Tasks
| Action | Trigger |
|--------|---------|
| `task.created` | Observer on `Task::created` |
| `task.updated` | `TasksController::update()` |
| `task.assigned` | `TasksController::store()` / `update()` when `assigned_to` set |
| `task.status.changed` | `TasksController::update()` when `status` changes |
| `task.completed` | `TasksController::update()` when status → completed |
| `task.deleted` | Observer on `Task::deleted` |
| `task.comment.added` | Observer on `TaskComment::created` |

### Attendance
| Action | Trigger |
|--------|---------|
| `attendance.session.opened` | `AttendanceController::store()` |
| `attendance.session.closed` | `AttendanceController::close()` |
| `attendance.record.marked` | `AttendanceController::mark()` |
| `attendance.bulk.marked` | `AttendanceController::bulkMark()` |

### Files & Media
| Action | Trigger |
|--------|---------|
| `file.uploaded` | `FilesController::store()` |
| `file.deleted` | `FilesController::destroy()` |
| `media.uploaded` | `MediaController::store()` |
| `media.deleted` | `MediaController::destroy()` |

### Notifications
| Action | Trigger |
|--------|---------|
| `notification.sent` | `NotificationsController::send()` |

### Volunteer Scheduling
| Action | Trigger |
|--------|---------|
| `schedule.plan.created` | Observer on `ServicePlan::created` |
| `schedule.plan.updated` | `ServicePlanController::update()` |
| `schedule.plan.published` | `ServicePlanController::publish()` |
| `schedule.plan.archived` | `ServicePlanController::archive()` |
| `schedule.plan.deleted` | Observer on `ServicePlan::deleted` |
| `schedule.position.created` | Observer on `ServingPosition::created` |
| `schedule.position.updated` | `ServingPositionController::update()` |
| `schedule.position.deleted` | Observer on `ServingPosition::deleted` |
| `schedule.assignment.created` | `AssignmentController::store()` |
| `schedule.assignment.removed` | `AssignmentController::destroy()` |
| `schedule.assignment.confirmed` | `AssignmentController::respond()` → confirmed |
| `schedule.assignment.declined` | `AssignmentController::respond()` → declined |

### Sermons
| Action | Trigger |
|--------|---------|
| `sermon.created` | Observer on `Sermon::created` |
| `sermon.updated` | `SermonsController::update()` |
| `sermon.published` | `SermonsController::publish()` |
| `sermon.deleted` | Observer on `Sermon::deleted` |
| `sermon.channel.created` | Observer on `SermonChannel::created` |
| `sermon.channel.updated` | `SermonChannelController::update()` |
| `sermon.channel.deleted` | Observer on `SermonChannel::deleted` |

### Church Settings
| Action | Trigger |
|--------|---------|
| `settings.profile.updated` ✓ | `ChurchSettingsController::updateProfile()` |
| `settings.branding.updated` ✓ | `ChurchSettingsController::updateBranding()` |
| `settings.security.updated` ✓ | `ChurchSettingsController::updateSecurity()` |
| `settings.advanced.updated` ✓ | `ChurchSettingsController::updateAdvanced()` |

### Roles & Permissions
| Action | Trigger |
|--------|---------|
| `member.role.assigned` | Any church-level role assignment (via `MembersController` or admin UI) |
| `member.role.removed` | Any church-level role removal |

Note: `RolesAndPermissionsSeeder` is an automated deployment artifact, not a user action — it is not audited.

### Administration Center
| Action | Trigger |
|--------|---------|
| `admin.church.created` | Admin creates new church |
| `admin.church.updated` | Admin updates church settings |
| `admin.church.suspended` | Admin suspends a church |

---

## Permissions

New permission: `audit.view` added to `config/permissions.php`.

| Role | Access |
|------|--------|
| `super_admin` | Full access (all churches) |
| `church_admin` | Full access (own church) |
| `coordinator` | Scoped to own departments |
| `assistant_coordinator` | No access |
| `member` | No access |

---

## Audit Log Viewer

**Route:** `GET /dashboard/audit` (new dedicated route, distinct from Settings)
**Controller:** `AuditLogController@index`
**Vue page:** `resources/js/Pages/Dashboard/Audit/Index.vue`

Access:
- `church_admin` / `super_admin` — see all events for their church, all filters available
- `coordinator` — department filter pre-set to their departments, locked; all other filters available within that scope

**Filter parameters (query string):**
- `module` — action prefix (e.g. `announcement`, `schedule`, `auth`)
- `action` — full action string
- `user_id` — actor filter
- `department_id` — scoped to events where `metadata.department_id` matches
- `date_from` / `date_to` — inclusive date range

**Response shape (Inertia):**
```
logs: paginated (25/page) collection of AuditLog entries
filters: current filter values echoed back
actors: list of users who have audit entries (for actor dropdown)
modules: list of distinct module prefixes (for module dropdown)
```

**Table columns:** timestamp, actor (avatar + name), action badge (colour-coded by module), target name + type, changes (expandable diff), IP address.

**Expandable row:** shows `old_values`, `new_values`, `metadata` as formatted JSON diff.

**Coordinator scoping:** When a coordinator accesses the page, the controller filters entries where `metadata->>'department_id'` is in the coordinator's department IDs. To make this work, every explicit audit call for a department-scoped event **must** include `department_id` in `metadata`. The implementation plan will enforce this — each controller call in the coverage plan that involves a department-owned resource must pass `metadata: ['department_id' => $dept->id]`. Observer-created entries for models that have a `department_id` column will have it added automatically by the observer. Coordinator cannot remove or change the department filter.

---

## Dashboard Widget — Recent Activity

**Component:** `resources/js/Components/Dashboard/RecentActivityWidget.vue`
**Data source:** New method `DashboardController::recentActivity()` returning last 8 scoped audit events, passed as Inertia prop.

Visible to: users with `audit.view` permission only.

Each row shows:
- Colour-coded module dot
- Human-readable description (formatted from action + target_name)
- Actor name
- Time-ago string (`3 minutes ago`)

"View all →" links to `/dashboard/audit`.

---

## Sidebar Navigation

Add to `DashboardLayout.vue` under the **Administration** group:
```
{ label: 'Audit Log', href: '/dashboard/audit', icon: ShieldCheck, perm: 'audit.view' }
```

---

## Files Created / Modified

**New files:**
- `database/migrations/2026_06_03_000001_add_target_name_metadata_to_audit_logs.php`
- `app/Services/AuditLogService.php`
- `app/Observers/AuditableObserver.php`
- `app/Traits/LogsAuditEvents.php`
- `app/Http/Controllers/Dashboard/AuditLogController.php`
- `resources/js/Pages/Dashboard/Audit/Index.vue`
- `resources/js/Components/Dashboard/RecentActivityWidget.vue`

**Modified files:**
- `app/Models/AuditLog.php` — add `target_name`, `metadata` to fillable/casts
- `app/Providers/AppServiceProvider.php` — register observers
- `config/permissions.php` — add `audit.view`
- `database/seeders/RolesAndPermissionsSeeder.php` — no change needed (seeder reads config)
- `app/Services/AuthService.php` — add `auth.login.failed`; standardise via new service
- `app/Http/Controllers/Dashboard/ChurchSettingsController.php` — swap inline `auditSettings()` for `AuditLogService`
- `app/Http/Controllers/Dashboard/DepartmentController.php` — standardise existing calls + add `department.coordinator.assigned` + `department.updated`
- `app/Http/Controllers/Dashboard/MembersController.php` — add member CRUD + role events
- `app/Http/Controllers/Dashboard/AnnouncementsController.php` — add updated + published events
- `app/Http/Controllers/Dashboard/EventsController.php` — add updated + RSVP events
- `app/Http/Controllers/Dashboard/TasksController.php` — add updated + assigned + status events
- `app/Http/Controllers/Dashboard/AttendanceController.php` — add all attendance events
- `app/Http/Controllers/Dashboard/FilesController.php` — add upload/delete events
- `app/Http/Controllers/Dashboard/MediaController.php` — add upload/delete events
- `app/Http/Controllers/Dashboard/NotificationsController.php` — add sent event
- `app/Http/Controllers/Dashboard/ServicePlanController.php` — add updated/published/archived events
- `app/Http/Controllers/Dashboard/ServingPositionController.php` — add updated event
- `app/Http/Controllers/Dashboard/AssignmentController.php` — add assignment events
- `app/Http/Controllers/Dashboard/SermonsController.php` — add updated/published events
- `app/Http/Controllers/Dashboard/SermonChannelController.php` — add updated event
- `app/Http/Controllers/Dashboard/ProfileController.php` — add `auth.password.changed`
- `app/Http/Controllers/Dashboard/DashboardController.php` — add `recentActivity()` prop
- `resources/js/Pages/Dashboard/Settings/AuditLogs.vue` — redirect or deprecate in favour of new page
- `resources/js/Layouts/DashboardLayout.vue` — add Audit Log nav item
- `routes/web.php` — add `GET /dashboard/audit` route

---

## Testing

Feature tests cover:
- `AuditLogController` — index with filters, coordinator scoping (cannot see other departments), admin sees all
- `AuditLogService` — records correctly, fails silently on DB error
- `AuditableObserver` — fires on created/deleted, does not fire on updated
- Spot-check: `AnnouncementsController` logs `announcement.published`; `AssignmentController` logs `schedule.assignment.confirmed`
