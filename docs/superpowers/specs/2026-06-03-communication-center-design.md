# Communication Center — Design Spec

> **Status:** Approved — ready for implementation planning
> **Date:** 2026-06-03
> **Scope:** Communication Core (Phase 1). Campaigns (drip sequences) are a separate future sub-project.

---

## 1. Goal

Create a centralized communication hub that lets church leadership send targeted in-app broadcast messages to any subset of their membership — entire church, departments, event attendees, volunteers, or custom saved audiences — with scheduling, delivery tracking, reusable templates, and audit logging. The system is designed from the ground up to support email, SMS, and WhatsApp channels in a future phase without schema changes.

---

## 2. Scope Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Campaigns | Deferred to Phase 2 | Drip sequences are a separate, complex subsystem that depends on this core infrastructure |
| Audience builder | Smart presets only | Covers 90% of real church use cases; full query builder is over-engineering for Phase 1 |
| Variables | Fixed set of 3 | `{{member_name}}` `{{church_name}}` `{{department_name}}` — rendered server-side via str_replace |
| Delivery channel | In-app only (Phase 1) | Uses existing AppNotification infrastructure; schema supports future channels via `channel` column |
| Composer layout | Split panel | Audience picker left, message composer right, live recipient count feedback |
| Dashboard layout | Quick actions + overview | Action-first; surfacing "New Broadcast" reduces friction for the primary use case |
| Announcement link | Option C — delivery engine | Broadcasts are the push layer for Announcements; both modules stay separate but are linked |
| Rich text | Plain textarea + variable chips | In-app notifications render as plain text; Tiptap deferred to Phase 2 (email needs it more) |

---

## 3. Architecture Overview

Communication Center is a new top-level Dashboard module. It is a **managed layer over the existing `AppNotification` infrastructure**.

```
BroadcastController
    └── BroadcastService
            ├── resolveAudience()   → Collection<User>
            ├── send()              → dispatches SendBroadcastJob
            ├── schedule()          → dispatches SendBroadcastJob with delay()
            └── renderBody()        → str_replace variables per recipient

SendBroadcastJob (queued)
    └── for each recipient:
            $user->notify(new AppNotification(TYPE_BROADCAST, ...))
            broadcast_recipients.status = sent | failed
    └── update broadcast counters + status
```

All audience resolution applies `is_active = true` on every branch — deactivated members are never included in any send.

---

## 4. Database Schema

### `broadcasts`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| church_id | FK → churches | tenant scope |
| created_by | FK → users | |
| title | string | Internal admin label |
| subject | string | Notification title shown to recipient |
| body | text | Message body; may contain `{{variables}}` |
| status | enum | `draft` `scheduled` `sending` `sent` `failed` |
| audience_type | enum | `all_members` `role` `department` `event_attendees` `volunteers` `saved_audience` |
| audience_config | json | Parameters for audience resolution (see §6) |
| announcement_id | FK nullable | nullOnDelete; links to triggering announcement |
| template_id | FK nullable | nullOnDelete; records which template was used |
| scheduled_at | datetime null | When to send; null = immediate |
| sent_at | datetime null | When delivery job completed |
| recipient_count | int default 0 | Denormalised; set at send time |
| delivered_count | int default 0 | Incremented by SendBroadcastJob |
| failed_count | int default 0 | Incremented by SendBroadcastJob |
| timestamps | | |
| deleted_at | | soft deletes |

### `broadcast_recipients`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| broadcast_id | FK → broadcasts | cascade delete |
| user_id | FK → users | cascade delete |
| channel | string default 'in_app' | Extensible: `in_app` `email` `sms` |
| status | enum | `pending` `sent` `failed` |
| sent_at | datetime null | |
| failed_at | datetime null | |
| failure_reason | string null | Exception message on failure |
| timestamps | | |

Unique constraint: `(broadcast_id, user_id, channel)` — prevents duplicate deliveries.

### `broadcast_templates`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| church_id | FK → churches | tenant scope |
| created_by | FK → users | |
| name | string | Internal template label |
| subject | string | Pre-filled subject line |
| body | text | Pre-filled body with optional variables |
| category | string null | `event_reminder` `volunteer_reminder` `welcome` `attendance` `newsletter` `general` |
| usage_count | int default 0 | Incremented each time a broadcast is created from this template |
| timestamps | | |
| deleted_at | | soft deletes |

### `broadcast_audiences`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| church_id | FK → churches | tenant scope |
| created_by | FK → users | |
| name | string | Display label |
| description | text null | |
| audience_type | enum | `all_members` `role` `department` `event_attendees` `volunteers` |
| audience_config | json | Same shape as `broadcasts.audience_config` |
| member_count | int default 0 | Cached; refreshed on save and on broadcast resolve |
| timestamps | | |
| deleted_at | | soft deletes |

---

## 5. Models

### `Broadcast`
- `BelongsToChurch` trait (church_id scope)
- Casts: `audience_config` → array, `status` → string, `scheduled_at/sent_at` → datetime
- Relationships: `creator()`, `announcement()`, `template()`, `recipients()` hasMany
- Scopes: `scopeDraft()`, `scopeScheduled()`, `scopeSent()`, `scopeFailed()`
- Accessor: `getStatusLabelAttribute()`

### `BroadcastRecipient`
- Relationships: `broadcast()` belongsTo, `user()` belongsTo
- Cast: `status` → string

### `BroadcastTemplate`
- `BelongsToChurch` trait
- Relationships: `creator()`, `broadcasts()` hasMany

### `BroadcastAudience`
- `BelongsToChurch` trait
- Relationships: `creator()`

---

## 6. Audience Resolution

`BroadcastService::resolveAudience(Broadcast $broadcast): Collection<User>`

All branches filter `is_active = true` and scope to `church_id`.

| `audience_type` | `audience_config` keys | Query |
|---|---|---|
| `all_members` | *(none)* | All active users in church |
| `role` | `role: string` | Users with the given Spatie role |
| `department` | `department_id: int` | Active members of department via pivot |
| `event_attendees` | `event_id: int` | Users with RSVP status `going` or `maybe` |
| `volunteers` | `plan_id: int` | Non-declined `VolunteerAssignment` users for plan |
| `saved_audience` | `audience_id: int` | Load `BroadcastAudience` and re-resolve its `type + config` |

Returns empty collection (never throws) if no recipients found. Controller checks count and returns validation error before dispatching.

---

## 7. Variable Rendering

`BroadcastService::renderBody(string $body, User $recipient, Broadcast $broadcast): string`

```php
$replacements = [
    '{{member_name}}'     => $recipient->name,
    '{{church_name}}'     => $broadcast->church->name,
    '{{department_name}}' => resolvedDeptName($broadcast), // dept name if dept audience, else church name
];
return str_replace(array_keys($replacements), array_values($replacements), $body);
```

`previewBody()` — identical, called from controller with `Auth::user()` as the sample recipient.

---

## 8. Service Layer

### `BroadcastService`

```
resolveAudience(Broadcast): Collection<User>
    — resolves audience_type + config
    — always filters is_active = true
    — returns User collection (may be empty)

send(Broadcast): void
    — validates: status must be draft or failed
    — calls resolveAudience(); throws if count = 0
    — creates broadcast_recipients rows (status=pending, channel=in_app)
    — sets broadcast.status = 'sending', recipient_count
    — dispatches SendBroadcastJob

schedule(Broadcast): void
    — validates: status = draft, scheduled_at is future
    — sets broadcast.status = 'scheduled'
    — dispatches SendBroadcastJob::delay(scheduled_at)

renderBody(string, User, Broadcast): string
    — str_replace for the 3 supported variables

previewBody(string, User, Broadcast): string
    — same as renderBody; used for live preview in composer

broadcastAnnouncement(Announcement, User): Broadcast
    — creates a Broadcast linked via announcement_id
    — subject = announcement->title, body = announcement->body (stripped of HTML if needed)
    — audience_type = all_members (or department if announcement is department-scoped)
    — calls send() immediately
    — returns the created Broadcast
```

### `SendBroadcastJob`

```
implements ShouldQueue
$tries = 3
$maxExceptions = 1

__construct(public int $broadcastId) {}

handle():
    $broadcast = Broadcast::with('church')->findOrFail($broadcastId)
    guard: if status not in [sending, scheduled] → return early

    BroadcastRecipient::where('broadcast_id', $broadcastId)
        ->where('status', 'pending')
        ->with('user')
        ->chunkById(50, function($chunk) use ($broadcast) {
            foreach ($chunk as $recipient) {
                try {
                    rendered = renderBody(broadcast->body, recipient->user, broadcast)
                    recipient->user->notify(new AppNotification(
                        TYPE_BROADCAST,
                        title:      broadcast->subject,
                        body:       rendered,
                        actionUrl:  '/dashboard/communication'
                    ))
                    recipient->update([status: sent, sent_at: now()])
                    $delivered++
                } catch (Exception $e) {
                    recipient->update([status: failed, failed_at: now(), failure_reason: $e->message])
                    $failed++
                }
            }
        })

    broadcast->update([
        delivered_count: $delivered,
        failed_count:    $failed,
        status:          $failed === $broadcast->recipient_count ? 'failed' : 'sent',
        sent_at:         now()
    ])
```

---

## 9. Controllers

| Controller | Route prefix | Key methods |
|---|---|---|
| `CommunicationController` | `/dashboard/communication` | `index()` — dashboard stats |
| `BroadcastController` | `/dashboard/communication/broadcasts` | `index`, `create`, `store`, `show`, `update`, `destroy`, `send` (POST), `preview` (POST) |
| `BroadcastTemplateController` | `/dashboard/communication/templates` | `index`, `store`, `update`, `destroy` |
| `BroadcastAudienceController` | `/dashboard/communication/audiences` | `index`, `store`, `update`, `destroy`, `resolveCount` (GET `/resolve-count?audience_type=X&audience_config[key]=value` — returns `{count: int}` for live composer preview) |

All controllers use `abort_unless($request->user()->can('communication.view'))` as the base guard. `send_dept` constraint is enforced in `BroadcastController::store()` by checking department ownership when `audience_type = department`.

---

## 10. Permissions

Add to `config/permissions.php`:

```php
'communication' => [
    'communication.view',       // See Communication Center dashboard and broadcast list
    'communication.send',       // Create and send broadcasts to any audience
    'communication.send_dept',  // Create and send to own department only
    'communication.manage',     // Create/edit/delete templates and saved audiences
    'communication.delete',     // Delete broadcasts
],
```

**Role assignments:**

| Role | Permissions granted |
|---|---|
| `super_admin` | `*` |
| `church_admin` | All `communication.*` |
| `coordinator` | `communication.view`, `communication.send_dept`, `communication.manage` |
| `assistant_coordinator` | `communication.view` |
| `member` | *(none — receive only)* |

**Department enforcement for `send_dept`:**
`BroadcastController::store()` checks: when `audience_type = department`, the `department_id` in `audience_config` must match a department where `Auth::user()` holds the `coordinator` pivot role. Returns 403 otherwise.

---

## 11. Announcement Integration

`AnnouncementsController::store()` and `::publish()` accept an optional `broadcast_to_members` boolean.

When `true`:
1. After the announcement is created/published, call `BroadcastService::broadcastAnnouncement($announcement, $request->user())`
2. The service creates a linked `Broadcast` (announcement_id set) with `subject = $announcement->title` and `body = strip_tags($announcement->body)` — announcement bodies are HTML rich text; the broadcast body must be plain text for in-app notification delivery
3. For department-scoped announcements: `audience_type = department`, `audience_config = {department_id: $announcement->department_id}`
4. For church-wide: `audience_type = all_members`
5. Send fires immediately via `SendBroadcastJob`

The broadcast appears in Communication Center logs with a link back to the announcement.

---

## 12. Notification Type

Add to `AppNotification`:

```php
public const TYPE_BROADCAST = 'broadcast';
```

Add to `NotificationDropdown.vue` `TYPE_ICONS`:
```js
'broadcast': { icon: Radio, classes: 'text-indigo-600 bg-indigo-50' }
```

---

## 13. UI Pages

### `Communication/Dashboard.vue`
- Quick actions bar: **[+ New Broadcast]** [Dept Message] [Event Attendees] [Volunteers]
- Stats row: sent this month · delivery rate · scheduled count
- Upcoming scheduled broadcasts (next 3)
- Recent broadcasts table (last 10): title · audience badge · recipient count · status badge · sent date

### `Communication/Broadcasts/Index.vue`
- Status tabs: All · Draft · Scheduled · Sent · Failed
- Table: title · audience · recipients · status badge · sent date · actions
- Empty state per tab with contextual CTA

### `Communication/Broadcasts/Create.vue`
Split panel layout:
- **Left:** Audience type cards (All Members / Department / Event Attendees / Volunteers / Saved Audience). Selecting a type reveals a sub-picker (department select, event select, etc.). Live "Sending to N people" count at bottom (calls `BroadcastAudienceController::count()` on change).
- **Right:** Subject input · body textarea · variable chip buttons (clickable, insert at cursor) · preview toggle · [Save Draft] [Schedule] [Send to N →]
- Schedule modal: datetime-local picker, sets `scheduled_at` and calls `schedule()` endpoint

### `Communication/Broadcasts/Show.vue`
- Header: title, subject, audience badge, status, sent at
- Stats strip: sent / delivered / failed
- Recipient table: avatar + name · channel badge · status badge · sent at · failure reason
- Table filterable by status (All / Sent / Failed)
- Link back to source announcement if `announcement_id` is set

### `Communication/Templates/Index.vue`
- Template cards: name · category badge · usage count · last used
- Create/edit in AppModal (name, category, subject, body with variable chips)
- "Use template" button → navigates to Create with fields pre-filled

### `Communication/Audiences/Index.vue`
- Audience rows: name · type badge · cached member count · last used
- Create/edit in AppModal (name, description, type picker + sub-picker, resolved count preview)

---

## 14. Sidebar Navigation

New entry in `DashboardLayout.vue` sidebar under a **"Communication"** group:

```
Communication  (Radio icon, permission: communication.view)
├── Dashboard    /dashboard/communication
├── Broadcasts   /dashboard/communication/broadcasts
├── Templates    /dashboard/communication/templates
└── Audiences    /dashboard/communication/audiences
```

---

## 15. Audit Log Events

All fired via `AuditLogService::record()`:

| Event | Trigger |
|---|---|
| `communication.broadcast_created` | `BroadcastController::store()` |
| `communication.broadcast_sent` | `SendBroadcastJob::handle()` on completion |
| `communication.broadcast_scheduled` | `BroadcastController` schedule action |
| `communication.broadcast_deleted` | `BroadcastController::destroy()` |
| `communication.template_created` | `BroadcastTemplateController::store()` |
| `communication.template_updated` | `BroadcastTemplateController::update()` |
| `communication.audience_created` | `BroadcastAudienceController::store()` |

---

## 16. Analytics (Communication Dashboard)

Computed in `CommunicationController::index()`:

```
sent_this_month:     broadcasts.where(sent_at >= start of month).count()
delivery_rate:       sum(delivered_count) / sum(recipient_count) * 100
scheduled_count:     broadcasts.where(status=scheduled).count()
recent_broadcasts:   last 10 sent/scheduled/draft, eager-load creator
upcoming:            broadcasts.where(status=scheduled).orderBy(scheduled_at).limit(3)
```

---

## 17. Testing (TESTING.md additions)

| ID | Test | Coverage |
|---|---|---|
| COM-001 | Create broadcast — draft saved correctly | BroadcastController::store |
| COM-002 | Send broadcast — job dispatched, recipients created, notifications sent | SendBroadcastJob |
| COM-003 | Department broadcast — coordinator scoped to own dept; 403 on other dept | Permission enforcement |
| COM-004 | Event attendees broadcast — only going/maybe RSVPs included | resolveAudience |
| COM-005 | Volunteer broadcast — only non-declined assignments included | resolveAudience |
| COM-006 | Scheduled broadcast — status=scheduled, job delayed | BroadcastService::schedule |
| COM-007 | Template usage — creates broadcast with pre-filled fields, increments usage_count | BroadcastTemplateController |
| COM-008 | Saved audience — resolves correctly via saved_audience type | BroadcastAudienceController |
| COM-009 | Delivery log — failed recipient recorded with failure_reason | SendBroadcastJob failure path |
| COM-010 | Permission gates — member cannot create; assistant can view only | All controllers |
| COM-011 | Announcement broadcast — broadcast_to_members creates linked broadcast | broadcastAnnouncement() |
| COM-012 | Variable rendering — {{member_name}} replaced correctly per recipient | renderBody() |

---

## 18. Out of Scope (Phase 1)

- Campaigns (drip sequences) — separate sub-project
- Email / SMS / WhatsApp channels — Phase 2; schema is ready (`channel` column)
- Rich text editor (Tiptap) — deferred to Phase 2 when email needs HTML
- Read receipts / open tracking — requires email pixel tracking or push acknowledgement
- Custom audience query builder — smart presets cover Phase 1 needs
- Member-facing inbox UI — in-app notifications use existing bell dropdown
