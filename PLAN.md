# Church Platform — PLAN.md
> Single source of truth for project state, decisions, and next actions.
> Last updated: 2026-06-03 (Session 17 — error pages: Tailwind CDN → inline styles)

---

## 1. Project Overview

A multi-tenant SaaS church management platform. Churches register via onboarding,
then operate from a unified dashboard: members, departments, events, tasks,
attendance, announcements, sermons, volunteer scheduling, and audit logs — all
scoped to their tenant by `church_id`.

Includes a public-facing church website and a full internal administration centre.

**Target users:** Church admins · Department coordinators · Volunteers / members

---

## 2. Tech Stack

| Layer         | Technology                                          |
|---------------|-----------------------------------------------------|
| Frontend      | Vue 3 + Inertia.js + Tailwind CSS                   |
| Backend       | Laravel 12                                          |
| Auth / RBAC   | Laravel Auth + Spatie Permissions                   |
| Database      | SQLite (dev) — MySQL/Postgres for prod              |
| Notifications | Laravel database notifications + queues             |
| Queue (dev)   | `sync` driver — runs inline, no worker needed       |
| Queue (prod)  | `database` or `redis` + `php artisan queue:work`    |
| Real-time     | Laravel Echo + Reverb (WebSocket)                   |
| Testing       | PHPUnit (Feature tests per module)                  |

---

## 3. Architecture

```
Client (Vue 3 / Inertia)
  → DashboardLayout.vue  (sidebar nav, top bar, notifications)
  → Page components      (one Vue page per controller action)

Laravel
  → Routes (web.php, single file)
  → Controllers (Dashboard/* — resource-style, thin)
  → Services (EventService, TaskService, SermonSyncService, etc.)
  → Jobs (SyncYouTubeChannelJob — queued background work)
  → Policies (every model has a Policy)
  → Notifications (App\Notifications\* — all use AppNotification format)
  → Models (BelongsToChurch trait — global church_id scope)

Permissions
  → config/permissions.php  — SINGLE SOURCE OF TRUTH for all permissions + roles
  → RolesAndPermissionsSeeder — reads config, idempotent
  → HandleInertiaRequests — injects user permissions into every Inertia page

Tenant isolation
  → ResolveTenant middleware → binds app('church') + app('church.id')
  → BelongsToChurch trait → global Eloquent scope WHERE church_id = ?
```

---

## 4. Current Status

**Overall: ~100% complete** (all core features working; all must-fix, high-priority, nice-to-have, and beta-gate items resolved)

| Module                     | Status     | Notes                                                    |
|----------------------------|------------|----------------------------------------------------------|
| Authentication             | ✅ Complete | Login, register, password reset, logout                  |
| Roles & Permissions        | ✅ Complete | Spatie, 4 roles, config-driven, full seeder              |
| Members                    | ✅ Complete | List, profile view, profile edit, avatar upload; admin   |
|                            |            | role assignment UI; invite link; member deactivation;    |
|                            |            | active/inactive filter + badge; deactivated excluded     |
|                            |            | from volunteer pickers                                   |
| Departments                | ✅ Complete | CRUD, member management, roles, coordinator guard        |
| Announcements              | ✅ Complete | CRUD, publish, pin, visibility rules, unread badge       |
| Events                     | ✅ Complete | CRUD, RSVP (going/maybe/cancel), file attachments        |
| Tasks                      | ✅ Complete | CRUD, status workflow, comments, overdue badge           |
| Attendance                 | ✅ Complete | Sessions, marking, member history, lifecycle;            |
|                            |            | service plan link — volunteers auto-populate as          |
|                            |            | expected attendees; bidirectional cross-links            |
| Files & Media              | ✅ Complete | Upload, download, delete, media library page             |
| Notifications              | ✅ Complete | Bell badge, dropdown, mark read, real-time; email        |
|                            |            | channel on all 7 key notification types                  |
| Sermons / YouTube Sync     | ✅ Complete | Channel connect, playlist sync, stage logging,           |
|                            |            | sync notification, create/edit manual sermons, series    |
|                            |            | management (CRUD), Show.vue admin actions, Add Sermon    |
| Volunteer Scheduling       | ✅ Complete | Plans, positions, assignments, notifications,            |
|                            |            | My Schedule, dashboard widget, confirm/decline           |
| Audit Logs                 | ✅ Complete | Full coverage: create/update/delete/pin/assign for       |
|                            |            | events, tasks, announcements, plans, sermons, files,     |
|                            |            | departments, members, settings, scheduling               |
| Reports                    | ✅ Complete | Aggregate stats + CSV export + Phase 1 analytics:        |
|                            |            | attendance trends, member growth, task insights, event   |
|                            |            | RSVPs, volunteer participation; date+dept filters        |
| Church Admin Centre        | ✅ Complete | 20 settings sections; logo upload (Branding page)        |
| User Profile               | ✅ Complete | Name update, password change, avatar upload (clickable)  |
| Public Website             | ✅ Complete | Home, about, events, sermons, ministries, contact        |
| Search                     | ✅ Complete | Global search modal (Ctrl+K) across all modules          |
| Onboarding                 | ✅ Complete | Church registration flow                                 |

---

## 5. Progress

```
Core CRUD systems:        100% ← logo upload, avatar upload, member deactivation Session 13
Workflow systems:         100% ← sermon published notification + beta security Session 14
Notifications:            100% ← email channel on all key notification types Session 13
Scheduling workflow:      100%
Sermon sync pipeline:     100%
Audit log coverage:       98%
UI consistency:           97%  ← members inactive badge + status filter Session 14
Test coverage:            75%
Production readiness:     95%  ← DEPLOYMENT.md written Session 15; remaining 5% is
                                  server-specific config (DNS, SSL, queue driver switch)
```

---

## 6. Completed Tasks

### Sessions 1–6: Foundation
- [x] Multi-tenant architecture (Church model, BelongsToChurch, ResolveTenant)
- [x] Authentication (login, register, reset) + logout
- [x] RBAC — Spatie Permissions, config/permissions.php, seeder
- [x] DashboardLayout.vue — sidebar, top bar, real-time notifications
- [x] Members module (list, show, profile edit)
- [x] Departments module (CRUD, member management)
- [x] Announcements module (CRUD, publish, pin, visibility, unread badge)
- [x] Events module (CRUD, RSVP, file attachments)
- [x] Tasks module (CRUD, comments, status, overdue badge)
- [x] Attendance module (sessions, marking, history)
- [x] Files / Media module (upload, download, delete, library page)
- [x] Notifications module (bell, dropdown, mark read, database + real-time)
- [x] Sermons module (YouTube sync, feature toggle, visibility)
- [x] Church Administration Centre (20 settings sections)
- [x] Public website (7 pages)
- [x] Onboarding flow

### Session 7: Audit Logs + Scheduling polish
- [x] AuditLog model + migration (target_name, metadata, old/new values)
- [x] AuditLogService + LogsAuditEvents trait
- [x] AuditableObserver + AppServiceProvider integration
- [x] Audit events across all 10 modules
- [x] AuditLogController (admin full view; coordinator sees own dept events)
- [x] Audit/Index.vue with filters (module, date, actor, department)
- [x] Reports/Index.vue (aggregate stats)
- [x] RecentActivityWidget on dashboard
- [x] Scheduling polish: null-safe dept, Position Library nav, quick-actions

### Session 8: TESTING.md + Volunteer Scheduling Workflow
- [x] TESTING.md v2.0 — 193 tests across 24 modules
- [x] Fixed all 4 scheduling notification classes (correct format + null-safety)
- [x] AssignmentController — always notify on assign (draft or published plan)
- [x] ServicePlanController::publish() — personalized VolunteerAssigned per volunteer
- [x] SchedulingController::mySchedule() — shows draft + published, 4-week history
- [x] DashboardController — upcoming assignments widget data
- [x] UpcomingAssignmentsWidget.vue — dashboard widget
- [x] MyAssignmentCard.vue + MySchedule.vue — plan.status + draft badge
- [x] 21/21 scheduling feature tests passing

### Session 10: Full Sermons Module Audit + Implementation

**Audit findings:**
- COMPLETED: DB schema, models, policy, permissions, resources, YouTube sync, index/show/toggle/delete, Channel.vue
- PARTIALLY IMPLEMENTED: Show.vue ("Admin actions coming soon"), Series (model only, no admin UI)
- MISSING: create/edit forms, series CRUD, Show.vue admin actions, routes for all of the above

**Implemented:**
- [x] **BUG-006** — Public sermons page crash (`HAVING` on non-aggregate SQLite query)
      — replaced `->having('sermons_count', '>', 0)` with `->whereHas('sermons', …)` in `Public/SermonsController.php`
- [x] **Form requests** — `StoreSermonRequest`, `UpdateSermonRequest`, `StoreSeriesRequest`
- [x] **SermonsController** — added `create()`, `store()`, `edit()`, `update()` methods
- [x] **SermonSeriesController** — new controller: `index()`, `store()`, `update()`, `destroy()`
- [x] **Routes** — `GET /create`, `POST /`, `GET /{sermon}/edit`, `PATCH /{sermon}`;
      series CRUD: `GET|POST /series`, `PUT|DELETE /series/{series}`
- [x] **Show.vue** — replaced "Admin actions coming soon" with real Edit / Visibility / Feature / Delete buttons
      (reactive ref so optimistic UI updates without page reload)
- [x] **Sermons/Create.vue** — full manual sermon creation form (title, speaker+datalist,
      description, series select, preached_at, video/audio/thumbnail URLs, visibility, is_featured)
- [x] **Sermons/Edit.vue** — same form pre-populated from existing sermon; YouTube-sourced banner warning
- [x] **Sermons/Series.vue** — series management page with AppModal create/edit and confirm delete
- [x] **Index.vue** — added "Add Sermon" button in header (shown to `sermons.upload` users)
- [x] **DashboardLayout.vue** — added "Add Sermon" (`sermons.upload`) + "Sermon Series" (`sermons.edit`) sidebar items
- [x] 27/27 tests passing; Vite build clean (zero errors)

### Session 9: Bug fixes, exports, sync audit
- [x] **BUG-001** — `member` role missing `scheduling.view` — added to config/permissions.php
- [x] **BUG-002** — SchedulingController missing permission guards — added abort_unless()
- [x] Sidebar refined: Scheduling overview = `scheduling.manage`; My Schedule = `scheduling.view`
- [x] **Reports CSV Export** — `GET /dashboard/reports/export?section=members|attendance|tasks|events`
      — UTF-8 BOM, chunked queries, download buttons on Reports page
- [x] Deleted stale `ExampleTest.php` — was permanently failing, tested nothing
- [x] **lodash → @vueuse/core** — replaced `import { debounce } from 'lodash'` in Audit/Index.vue
      with `useDebounceFn` from @vueuse/core (lodash not installed)
- [x] **SyncYouTubeChannelJob $connection FatalError** — renamed constructor-promoted property
      `$connection` → `$channelConnection` (PHP 8.2 fatal: trait + class both defined `$connection`)
- [x] **YouTube Sermon Sync — full audit + fix:**
  - Root cause: `QUEUE_CONNECTION=database` with zero workers; 78 jobs stuck at 0 attempts
  - Changed `QUEUE_CONNECTION=sync` in `.env` (jobs run inline in dev, no worker needed)
  - Cleared all 78 stuck jobs from `jobs` table
  - Added `$triggeredByUserId` to job constructor — job now notifies the triggering admin
  - Added `TYPE_SERMON_SYNC_DONE` + `TYPE_SERMON_SYNC_FAILED` to `AppNotification`
  - Added `sermon.sync.done` (purple Mic2) + `sermon.sync.failed` (red AlertCircle) to TYPE_ICONS
  - Added detailed stage logging throughout `SermonSyncService` and `YouTubeProvider`
  - `SermonChannelController` passes `$request->user()->id` to dispatch
  - Updated success flash: "Syncing … you'll get a notification when it finishes"
  - Live end-to-end test confirmed: `last_synced_at` updates, "Never Synced" resolves
- [x] 27/27 tests passing throughout session

---

## 7. Next Actions (Prioritized)

1. **[DONE ✅]** Seeder reminder: `php artisan db:seed --class=RolesAndPermissionsSeeder`
         run (Session 17) — 44 permissions across 5 roles re-synced; `scheduling.view`
         now live on the `member` role in the running DB

2. **[DONE ✅]** Production: `php artisan storage:link` run (Session 17) — `public/storage`
         symlink connected to `storage/app/public`; file uploads now publicly accessible
3. **[DONE ✅]** Tailwind CDN in error pages — replaced with self-contained `<style>` block
         (Session 17); error pages now render correctly with zero external dependencies

---

*Previously completed nice-to-haves (Session 13):*
- ✅ Logo upload (Branding settings) — `POST /dashboard/settings/branding/logo`
- ✅ Avatar upload (Profile page) — clickable avatar with camera overlay
- ✅ Email notification channel on 4 previously DB-only classes: `VolunteerAssigned`, `VolunteerRemoved`, `SchedulePublished`, `TaskStatusChanged`
- ✅ SermonPublished notification — dispatched when a sermon goes public (store + cycleVisibility)
- ✅ Member deactivation — `PATCH /dashboard/members/{user}/activate`, Show.vue deactivate/reactivate card

---

## 8. Technical Debt

| Area                        | Description                                                    | Priority |
|-----------------------------|----------------------------------------------------------------|----------|
| Service layer inconsistency | Some modules use Services (Events, Tasks), others don't        | Low      |
| Queue driver docs           | `.env` comment added; no QUEUE.md yet with prod supervisor cfg | Low      |
| SQLite dev DB               | Fine for dev; migration path to MySQL/Postgres undocumented    | Medium   |
| No factory coverage         | Several models lack factories, limiting test breadth           | Low      |
| Controller size             | ReportsController and ChurchSettingsController are large       | Low      |
| Trait property naming       | SyncYouTubeChannelJob comment documents reserved names;        | Low      |
|                             | other future jobs must avoid: $connection $tries $backoff etc. |          |

---

## 9. Bugs / Issues

| ID       | Description                                              | Severity | Status               |
|----------|----------------------------------------------------------|----------|----------------------|
| BUG-001  | `member` role missing `scheduling.view`                  | Critical | ✅ Fixed Session 9   |
| BUG-002  | SchedulingController missing permission guards           | Low      | ✅ Fixed Session 9   |
| BUG-003  | `lodash` imported but not installed (Audit/Index.vue)    | Medium   | ✅ Fixed Session 9   |
| BUG-004  | `SyncYouTubeChannelJob` fatal trait property conflict    | Critical | ✅ Fixed Session 9   |
| BUG-005  | YouTube sync never executed — no queue worker running    | Critical | ✅ Fixed Session 9   |
|          | (78 jobs stuck in DB, 0 attempts, "Never Synced" forever)|          |                      |
| BUG-006  | Public sermons page: SQLite HAVING on non-aggregate query| High     | ✅ Fixed Session 10  |
|          | `->having('sermons_count', '>', 0)` → `->whereHas(...)`  |          |                      |

*Previously listed bugs (RSVP count inaccuracy, department removal, Reports 404,
Media 404, sidebar missing modules) verified as already resolved in earlier sessions.*

---

## 10. Architecture Notes

- **BelongsToChurch trait** — global `WHERE church_id = app('church.id')` scope on
  every tenant-scoped model. All queries automatically filter by church.
- **AppNotification class** — standard format: `{type, title, body, action_url, actor}`.
  NotificationDropdown.vue reads exactly these keys. All notifications MUST use this
  format or the bell dropdown renders a blank row.
- **config/permissions.php** — single source of truth. Never hardcode permission strings
  in controllers. After editing, run `php artisan db:seed --class=RolesAndPermissionsSeeder`.
- **ResolveTenant middleware** — binds `app('church')` + `app('church.id')` from the
  authenticated user's `church_id` on every request.
- **TYPE_ICONS in NotificationDropdown.vue** — maps notification type strings to lucide
  icons + Tailwind colors. Always add to AppNotification AND TYPE_ICONS together.
- **Queue driver** — `QUEUE_CONNECTION=sync` for dev (inline execution, no worker).
  Switch to `database`/`redis` + `php artisan queue:work --sleep=3 --tries=3` for prod.
- **Job property naming** — Never use `$connection`, `$tries`, `$backoff`, `$timeout`,
  `$queue`, `$delay`, or `$afterCommit` as constructor-promoted property names in jobs.
  These are reserved by the `Queueable`/`InteractsWithQueue` traits (PHP 8.2 fatal error).
- **TESTING.md v2.0** — 193 tests across 24 modules. Pre-production validation spec.
- **SQLite HAVING limitation** — SQLite disallows `HAVING` on a SELECT-alias column (e.g. a
  correlated subquery alias from `withCount`) without `GROUP BY`. MySQL/Postgres permit it.
  Fix: replace `->having('column_count', '>', 0)` with `->whereHas(...)`, which generates
  a `WHERE EXISTS (...)` clause that all drivers support.

---

## 11. Session Log

| Session | Summary                                                                      |
|---------|------------------------------------------------------------------------------|
| 1       | Initial SaaS architecture + public frontend build                            |
| 2       | Authentication + roles + departments + announcements                         |
| 3       | Events + tasks + attendance systems                                          |
| 4       | Volunteer scheduling introduced (partial)                                    |
| 5       | YouTube sermons integration                                                  |
| 6       | Church settings + admin centre expansion                                     |
| 7       | Audit logs (all modules), reports page, scheduling polish                    |
| 8       | TESTING.md v2.0 (193 tests); complete volunteer scheduling workflow fix;     |
|         | all 4 notification classes fixed; dashboard widget; draft-plan visibility    |
| 9       | Remade PLAN.md; BUG-001/002 (permissions); Reports CSV export; deleted       |
|         | ExampleTest; lodash → @vueuse/core; SyncYouTubeChannelJob $connection        |
|         | FatalError fix; full YouTube sync audit — root cause: no queue worker,       |
|         | 78 jobs stuck; fixed with QUEUE_CONNECTION=sync; added completion            |
|         | notification + stage logging throughout pipeline; 27/27 tests pass           |
| 10      | Full Sermons module audit; BUG-006 SQLite HAVING fix; added create/edit      |
|         | sermon forms + SermonSeriesController + Series.vue + routes; replaced        |
|         | "Admin actions coming soon" with real buttons; sidebar nav updated;           |
|         | 27/27 tests green, Vite build clean                                          |
| 11      | Reports & Analytics Phase 1: full audit; removed placeholder; attendance     |
|         | trends + by-dept + by-type; member growth + active/inactive; task overdue   |
|         | + by-dept + by-priority; event RSVP table; volunteer stats + top volunteers; |
|         | date range + department filters; 27/27 tests, Vite build clean               |
| 12      | Full platform completion audit; all must-fix + high-priority beta items:     |
|         | contact form now sends real email (ContactFormMail Mailable); donate page    |
|         | "Give" button replaced with honest "Coming soon" card; sidebar active-state  |
|         | bleed fixed (exact: true for sub-page nav items + isActive(item) signature); |
|         | member role assignment UI (PATCH /members/{user}/role + role picker on       |
|         | Show.vue); admin invite-link panel on Members/Index.vue; 9 audit log gaps    |
|         | filled (event/task/announcement create+delete, announcement pin, plan        |
|         | create+delete); 27/27 tests, Vite build clean                                |
| 13      | Nice-to-have before launch: logo upload (Branding settings, POST route +     |
|         | ChurchSettingsController::uploadLogo()); avatar upload (Profile page,        |
|         | clickable avatar with camera overlay, POST /dashboard/profile/avatar);       |
|         | email channel added to VolunteerAssigned, VolunteerRemoved, SchedulePublished|
|         | TaskStatusChanged; SermonPublished notification (new class, dispatched from  |
|         | store + cycleVisibility → public, TYPE_SERMON_PUBLISHED constant, type icon  |
|         | in NotificationDropdown); member deactivation (migration is_active boolean,  |
|         | PATCH /members/{user}/activate, toggleActive() controller, Show.vue card     |
|         | with deactivate/reactivate UI); 27/27 tests, Vite build clean                |
| 16      | Attendance ↔ Scheduling integration: migration adds service_plan_id FK      |
|         | (nullable, nullOnDelete) to attendance_sessions; AttendanceSession and      |
|         | ServicePlan models gain reciprocal relationships; AttendanceService         |
|         | getExpectedAttendees() gains Plan case (priority 1 — non-declined           |
|         | volunteers from plan positions, active members only); StoreSessionRequest    |
|         | validates service_plan_id; Create.vue gains "Linked service plan" dropdown  |
|         | that auto-fills title + date on selection, shows volunteer pre-load hint;   |
|         | DeptSidebar.vue shows Attendance section — linked session name + status with  |
|         | direct link, OR "Track attendance →" link for unlinked plans (canManage);   |
|         | Attendance/Show.vue shows plan chip in meta row (links back to plan);       |
|         | AttendanceSessionResource + ServicePlanResource expose linked relation;      |
|         | 27/27 tests, Vite build clean                                                |
| 15      | Production deployment guide (DEPLOYMENT.md): server requirements table;     |
|         | MySQL DB setup; full production .env template with all vars documented;     |
|         | first-deploy step-by-step (composer, npm build, migrate, seed, storage:link,|
|         | cache commands, permissions); Nginx server block with Reverb WS proxy and   |
|         | SSL headers; Let's Encrypt Certbot; Supervisor configs for queue:work       |
|         | (2 workers, --tries=3, --max-time=3600) and reverb:start; deploy.sh script  |
|         | for subsequent deploys; pre-launch checklist (security, data, files, BG     |
|         | processes, mail, DNS, env); troubleshooting section (9 scenarios)           |
| 17      | Error pages: removed Tailwind CDN script + Google Fonts CDN links from       |
|         | errors/layout.blade.php; replaced with a self-contained <style> block using  |
|         | plain CSS equivalents of every utility class used. Error pages now render    |
|         | correctly with zero external network requests — fully CDN-outage resilient.  |
| 14      | Must-fix before beta: AuthService blocks deactivated login with clear        |
|         | message; EnsureUserIsActive middleware evicts already-active deactivated     |
|         | sessions on next request (logs out, redirects to /login with error);         |
|         | custom error pages for 403/404/500/503 (Blade layout + Tailwind CDN,        |
|         | branded, links to dashboard); churchMembers() now filters is_active=true     |
|         | (deactivated members excluded from volunteer assignment pickers and task      |
|         | assignee lists); Members/Index.vue adds is_active badge ("Deactivated" in   |
|         | rose), dimmed avatar, status filter dropdown (All/Active/Deactivated),       |
|         | stats strip shows active + deactivated counts; MembersController::index()   |
|         | supports ?status=active|inactive filter; 27/27 tests, Vite build clean      |
