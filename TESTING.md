# Church SaaS Platform — Testing Guide

**Version:** 2.1 · **Date:** 2026-06-08  
**Base URL:** `http://localhost:8000`  
**Dev server:** `php artisan serve` + `node_modules\.bin\vite`

---

## Seeded Test Credentials

| Role | Email | Password | Notes |
|------|-------|----------|-------|
| Church Admin | `admin@gracechurch.org` | `password` | Full access to all dashboard features |
| Coordinator | *(assign via tinker — see RBAC-003)* | `password` | Dept-scoped create/manage; no Settings/Reports |
| Member | `member@gracechurch.org` | `password` | Read-only + own tasks/attendance/my-schedule |

---

## Test Status Key

| Symbol | Meaning |
|--------|---------|
| ✅ | Pass |
| ❌ | Fail |
| ⚠️ | Needs Review |
| ⬜ | Not Yet Tested |

---

## How to Use This Document

1. Start `php artisan serve` and `node_modules\.bin\vite` (for HMR) or use a built copy.
2. Work through each section top-to-bottom.
3. Fill in **Actual Result** and tick the appropriate **Status** checkbox after each test.
4. Note any bug details in **Actual Result** including the URL, HTTP status code, and error message if applicable.
5. On failure: open a GitHub issue or note in a bug tracker referencing the test ID (e.g. `AUTH-003`).

---

---

# 1. Authentication Tests

---

## AUTH-001 — Valid Login (Admin)

**Purpose:** Confirm that a church admin can log in with correct credentials.

**Preconditions:** Database seeded. Server running.

**Steps:**
1. Navigate to `http://localhost:8000/login`
2. Enter email: `admin@gracechurch.org`
3. Enter password: `password`
4. Click **Sign in**

**Expected Result:**
- Redirected to `http://localhost:8000/dashboard`
- Dashboard page loads with the admin's name in the top-right avatar
- Sidebar shows all admin navigation items (Departments, Members, Settings, Reports, etc.)

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUTH-002 — Valid Login (Member)

**Purpose:** Confirm that a standard member can log in.

**Preconditions:** Database seeded.

**Steps:**
1. Navigate to `/login`
2. Enter email: `member@gracechurch.org`, password: `password`
3. Click **Sign in**

**Expected Result:**
- Redirected to `/dashboard`
- Sidebar shows limited items (no Settings, no Reports, no Members management)
- No "Create" buttons visible for admin-only resources

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUTH-003 — Invalid Credentials

**Purpose:** Confirm login is rejected for wrong credentials.

**Preconditions:** None.

**Steps:**
1. Navigate to `/login`
2. Enter email: `admin@gracechurch.org`, password: `wrongpassword`
3. Click **Sign in**

**Expected Result:**
- Page reloads and displays a validation error: "These credentials do not match our records."
- User remains on `/login`
- No session cookie is set

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUTH-004 — Logout

**Purpose:** Confirm that logout destroys the session.

**Preconditions:** Logged in as admin.

**Steps:**
1. Click the user avatar in the top-right corner of the dashboard
2. Click **Sign out** (or equivalent)
3. After redirect, attempt to navigate to `/dashboard`

**Expected Result:**
- After logout, redirected to `/login`
- Attempting to visit `/dashboard` while unauthenticated redirects back to `/login`

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUTH-005 — Registration — New User

**Purpose:** Confirm a new user can register and is added to the existing church.

**Preconditions:** Not logged in. Grace Community Church exists in DB.

**Steps:**
1. Navigate to `/register`
2. Fill in: Name = `Test Member`, Email = `testmember@example.com`, Password = `TestPass123!`
3. Click **Create account**

**Expected Result:**
- Redirected to `/dashboard`
- User is created with `church_id` matching Grace Community Church
- User is assigned the `member` role
- Dashboard loads with restricted member permissions

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUTH-006 — Registration — Duplicate Email

**Purpose:** Confirm duplicate emails are rejected.

**Preconditions:** User with `admin@gracechurch.org` already exists.

**Steps:**
1. Navigate to `/register`
2. Fill in: Email = `admin@gracechurch.org`, Name = `Duplicate`, Password = `TestPass123!`
3. Click **Create account**

**Expected Result:**
- Validation error: "The email has already been taken."
- User is NOT created
- Stays on `/register`

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUTH-007 — Password Reset Flow

**Purpose:** Confirm the full password reset flow works.

**Preconditions:** A real email driver is configured OR use `MAIL_MAILER=log` and check `storage/logs/laravel.log`.

**Steps:**
1. Navigate to `/forgot-password`
2. Enter `member@gracechurch.org` and click **Send reset link**
3. Open the log file at `storage/logs/laravel.log` and find the reset URL
4. Navigate to the reset URL
5. Enter a new password: `NewPass456!` and confirm it
6. Click **Reset password**
7. Try logging in with `member@gracechurch.org` / `NewPass456!`

**Expected Result:**
- Step 2: Flash message "We have emailed your password reset link."
- Step 4: Reset form loads with the token pre-filled
- Step 6: Redirected to `/login` with a success message
- Step 7: Login succeeds

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUTH-008 — Guest Cannot Access Dashboard

**Purpose:** Confirm unauthenticated access to dashboard routes is blocked.

**Preconditions:** Not logged in (clear cookies or use incognito).

**Steps:**
1. Navigate directly to `http://localhost:8000/dashboard`
2. Navigate directly to `http://localhost:8000/dashboard/members`
3. Navigate directly to `http://localhost:8000/dashboard/settings`

**Expected Result:**
- All three URLs redirect to `/login`
- No dashboard content is visible
- No 500 errors

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUTH-009 — Remember Me / Session Persistence

**Purpose:** Verify "remember me" flag extends session.

**Preconditions:** Login form has a "Remember me" checkbox.

**Steps:**
1. Navigate to `/login`
2. Enter admin credentials, check **Remember me**
3. Log in
4. Close the browser tab entirely (not incognito)
5. Reopen the browser and navigate to `/dashboard`

**Expected Result:**
- Dashboard loads without requiring re-login
- Session cookie has a long expiry (visible in browser DevTools → Application → Cookies)

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUTH-010 — Onboarding Wizard (Guest)

**Purpose:** Verify a new church can complete self-service onboarding.

**Preconditions:** Not logged in.

**Steps:**
1. Navigate to `/onboarding`
2. Step 1: Enter Church Name = `Test Church`, tagline, timezone, country
3. Step 2: Pick a brand colour from the swatches
4. Step 3: Enter admin name = `Founder`, email = `founder@testchurch.org`, password = `Founder123!`
5. Click **Create my workspace**

**Expected Result:**
- Redirected to `/onboarding/welcome` — the celebration screen
- A new church record exists in `churches` table
- The `founder@testchurch.org` user has the `church_admin` role
- 4 starter departments were seeded for the new church

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 2. Dashboard Tests

---

## DASH-001 — Dashboard Homepage Loads

**Purpose:** Confirm the main dashboard renders without errors.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard`

**Expected Result:**
- Page title shows "Dashboard"
- Stats widgets visible (Total Members, Upcoming Events, Pending Tasks, Active Departments)
- Feed columns visible (Upcoming Events, Recent Announcements, My Tasks / Recent Tasks)
- No console errors in browser DevTools

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DASH-002 — Dashboard Stats Accuracy

**Purpose:** Confirm stat counts match the database.

**Preconditions:** Logged in as admin. Run: `php artisan tinker` and check counts manually.

**Steps:**
1. On the dashboard, note the "Total Members" stat
2. Run `App\Models\User::where('church_id', 1)->count()` in tinker
3. Compare

**Expected Result:**
- Dashboard stat matches the actual DB count (or is within ±1 for seeded vs registered users)

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DASH-003 — Sidebar Navigation (Admin)

**Purpose:** Confirm all admin navigation links are present and work.

**Preconditions:** Logged in as admin.

**Steps:**
1. Verify these sidebar links exist and load their pages without errors:
   - **Work group:** Tasks → `/dashboard/tasks`, Scheduling → `/dashboard/scheduling`, Position Library → `/dashboard/scheduling/positions`, My Schedule → `/dashboard/scheduling/my-schedule`, Attendance → `/dashboard/attendance`, Media → `/dashboard/media`
   - **Community group:** Events → `/dashboard/events`, Announcements → `/dashboard/announcements`, Departments → `/dashboard/departments`, Members → `/dashboard/members`, Sermons → `/dashboard/sermons`
   - **Administration group:** Reports → `/dashboard/reports`, Settings → `/dashboard/settings`, Audit Log → `/dashboard/audit`

**Expected Result:**
- All 15 links are present
- Each link navigates to the correct page (no 404/403/500)
- Active link is highlighted in the sidebar
- "Position Library" and "Audit Log" items are only visible to admin/coordinator; "My Schedule" is visible to all authenticated users

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DASH-004 — Sidebar Navigation (Member)

**Purpose:** Confirm members see a restricted sidebar.

**Preconditions:** Logged in as member.

**Steps:**
1. Note which sidebar items are visible

**Expected Result:**
- Visible: Dashboard, Tasks, Scheduling, **My Schedule**, Attendance, Media, Events, Announcements, Departments, Members, Sermons
- NOT visible: Position Library (requires `scheduling.manage`), Reports, Settings, Audit Log
- All visible links load without 403 errors

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DASH-005 — Flash Messages

**Purpose:** Confirm success/error toasts appear after actions.

**Preconditions:** Logged in as admin.

**Steps:**
1. Create a new announcement (any content)
2. Immediately after redirect, look for a green toast notification

**Expected Result:**
- A toast notification appears at the top of the screen with a success message
- Toast auto-dismisses after a few seconds

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DASH-006 — Mobile Responsive Layout

**Purpose:** Verify the dashboard is usable on mobile viewports.

**Preconditions:** Logged in as admin.

**Steps:**
1. In browser DevTools, set viewport to 375×812 (iPhone 14 Pro)
2. Navigate to `/dashboard`
3. Look for a hamburger/menu button
4. Tap it and verify the sidebar drawer opens
5. Tap a sidebar link

**Expected Result:**
- Sidebar collapses on mobile, accessible via a menu button
- Drawer opens and closes correctly
- Navigation works from the mobile drawer

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DASH-007 — Notification Bell (Unread Count)

**Purpose:** Confirm the notification badge shows correct unread count.

**Preconditions:** Logged in as member. Admin assigns a task to the member.

**Steps:**
1. As admin, create a task and assign it to the member user
2. Log in as member (new session or refresh)
3. Look at the notification bell in the header

**Expected Result:**
- Bell shows a red badge with at least 1 unread notification
- Clicking the bell opens a dropdown with the task assignment notification

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DASH-008 — Global Search Opens

**Purpose:** Confirm the global search modal works.

**Preconditions:** Logged in as admin.

**Steps:**
1. Press `Ctrl+K` (Windows) or `Cmd+K` (Mac)
2. Type "grace" in the search box

**Expected Result:**
- A command-palette modal appears
- Typing shows results grouped by category (Members, Departments, etc.)
- Keyboard arrows navigate results
- Pressing Escape closes the modal

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DASH-009 — Recent Activity Widget

**Purpose:** Confirm the Recent Activity widget appears on the dashboard for users with `audit.view`.

**Preconditions:** Logged in as admin. At least one action has been taken (e.g. publish an announcement).

**Steps:**
1. Navigate to `/dashboard`
2. Scroll down below the stats section
3. Look for the "Recent Activity" widget
4. Click **View all →** in the widget header

**Expected Result:**
- Widget renders with up to 8 recent audit events
- Each row shows a colour-coded module dot, action name, actor, and time-ago
- **View all →** navigates to `/dashboard/audit`
- Logged-in member (no `audit.view`) does NOT see the widget at all

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 3. Department Tests

---

## DEPT-001 — Create Department (Admin)

**Purpose:** Confirm an admin can create a new department.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/departments/create`
2. Enter: Name = `Media Ministry`, Description = `Handles church media`, Icon = `🎬`, Visibility = `Public`
3. Click **Create department**

**Expected Result:**
- Redirected to the new department's show page
- Department name "Media Ministry" is visible
- Department appears in the departments list at `/dashboard/departments`

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DEPT-002 — Edit Department

**Purpose:** Confirm department details can be updated.

**Preconditions:** At least one department exists. Logged in as admin.

**Steps:**
1. Navigate to a department's edit page (`/dashboard/departments/{id}/edit`)
2. Change the description to "Updated description — test"
3. Click **Update**

**Expected Result:**
- Redirected back to the department show page
- Updated description is displayed
- Flash success toast appears

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DEPT-003 — Delete Department

**Purpose:** Confirm a department can be deleted.

**Preconditions:** A test department with no critical data exists. Logged in as admin.

**Steps:**
1. Navigate to the test department's show/edit page
2. Click **Delete** and confirm the dialog

**Expected Result:**
- Redirected to `/dashboard/departments`
- Department no longer appears in the list
- No orphaned member records in `department_user` table

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DEPT-004 — Add Member to Department

**Purpose:** Confirm members can be added to a department.

**Preconditions:** Department exists. Member user exists. Logged in as admin.

**Steps:**
1. Navigate to the department show page → **Members** tab
2. Click **Add member**, select `member@gracechurch.org`, role = `Member`
3. Click **Add**

**Expected Result:**
- Member appears in the members list on the department page
- The pivot `department_user` record is created with correct role and joined_at

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DEPT-005 — Change Member Role in Department

**Purpose:** Confirm member roles can be updated within a department.

**Preconditions:** Member is already in a department as `member`.

**Steps:**
1. On the department's Members tab, find the member
2. Change their role to `coordinator` using the role dropdown/button

**Expected Result:**
- Role updates to "Coordinator"
- `department_user.role` column is updated in the DB

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DEPT-006 — Remove Member from Department

**Purpose:** Confirm members can be removed from a department.

**Preconditions:** Member is in a department.

**Steps:**
1. On the department's Members tab
2. Click the remove button next to the member
3. Confirm the action

**Expected Result:**
- Member is removed from the list
- `department_user` pivot record is deleted
- Member's user account still exists

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DEPT-007 — Member Cannot Create Departments

**Purpose:** Confirm members lack `departments.create` permission.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate directly to `/dashboard/departments/create`

**Expected Result:**
- 403 Forbidden page OR redirect to dashboard with error
- No form is accessible

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DEPT-008 — Department Visibility — Private

**Purpose:** Confirm private departments are not accessible by non-members.

**Preconditions:** A "Private" department exists. Member is NOT in it. Logged in as member.

**Steps:**
1. Note the ID of the private department from the admin account
2. Switch to member session and navigate to `/dashboard/departments/{id}`

**Expected Result:**
- 403 Forbidden — member cannot view a private department they don't belong to

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DEPT-009 — Department Files Tab

**Purpose:** Confirm files can be uploaded and viewed on a department's Files tab.

**Preconditions:** Logged in as admin. Department exists.

**Steps:**
1. Navigate to a department's show page → **Files** tab
2. Upload a PDF file (any small PDF)
3. Wait for upload to complete

**Expected Result:**
- File appears in the files list with correct name, size, and upload date
- Download link works
- File is stored in `storage/app/public/`

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## DEPT-010 — Department Notification on Member Added

**Purpose:** Confirm the new member receives a notification when added to a department.

**Preconditions:** Member has an account. Admin adds them to a department.

**Steps:**
1. As admin, add the member to a department (see DEPT-004)
2. Switch to member account
3. Check the notification bell

**Expected Result:**
- Member has a new notification: "You were added to [Department Name]"
- Clicking the notification navigates to the department page (no 403)

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 4. Member Tests

---

## MBR-001 — Members List (Admin)

**Purpose:** Confirm the members list loads and shows all church members.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/members`

**Expected Result:**
- List of members with name, email, avatar/initials, and role badges
- Search box is functional (type a partial name and see filtered results)
- Pagination appears if > 1 page

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## MBR-002 — Member Profile Page (Admin)

**Purpose:** Confirm the admin can view a member's full profile.

**Preconditions:** At least one member exists. Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/members`
2. Click on a member's name or profile card

**Expected Result:**
- Member detail page loads at `/dashboard/members/{id}`
- Shows: name, email, avatar, role, joined date, department memberships
- Attendance history section visible (if attendance records exist)

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## MBR-003 — Member Cannot Access Members List (No members.view)

**Purpose:** Confirm a user without `members.view` cannot access the list.

**Preconditions:** This test requires a user with no `members.view` permission. Since member role DOES have `members.view`, create a custom test user or note as N/A if all roles include it.

**Steps:**
1. Verify in `config/permissions.php` that `member` role includes `members.view`
2. If it does, confirm logged-in member CAN access `/dashboard/members`

**Expected Result:**
- Member CAN view the members list (per config)
- The page loads without 403

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## MBR-004 — Member Profile Own View

**Purpose:** Confirm a member can view their own profile.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/members/{id}` where `{id}` is the member's own user ID
2. Also navigate to `/dashboard/members/{id}` for another user

**Expected Result:**
- Own profile: loads successfully
- Another user's profile: loads (members.view is granted to all)

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## MBR-005 — Member Search

**Purpose:** Verify the members list search filters correctly.

**Preconditions:** Logged in as admin. Multiple members exist.

**Steps:**
1. Navigate to `/dashboard/members`
2. Type "Pastor" (or partial name of a seeded member) in the search box
3. Wait for debounced filter

**Expected Result:**
- List filters to show only members whose name or email contains "Pastor"
- URL updates with `?search=Pastor` query parameter
- Clearing the search restores the full list

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 5. Announcement Tests

---

## ANN-001 — Create Announcement (Draft)

**Purpose:** Confirm admins can create a draft announcement.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/announcements/create`
2. Title = `Test Announcement`, Body = `This is a test.`, Priority = `Medium`
3. Leave **Published at** empty (keep as draft)
4. Click **Create**

**Expected Result:**
- Redirected to the announcement show page
- Status badge shows "Draft"
- Announcement is NOT visible on the public `/announcements` page

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ANN-002 — Publish Announcement

**Purpose:** Confirm an announcement can be published.

**Preconditions:** A draft announcement exists. Logged in as admin.

**Steps:**
1. Navigate to the draft announcement show page
2. Click the **Publish** button

**Expected Result:**
- Status changes to "Published"
- `published_at` timestamp is set to now
- Announcement is visible on the public `/announcements` page (if visibility = public)
- A notification is generated for all church members

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ANN-003 — Pin Announcement

**Purpose:** Confirm announcements can be pinned.

**Preconditions:** A published announcement exists. Logged in as admin.

**Steps:**
1. On the announcement show page, click **Pin**

**Expected Result:**
- Announcement shows "Pinned" badge
- `is_pinned = 1` in the database
- Announcement appears at the top of the list

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ANN-004 — Department-Scoped Announcement

**Purpose:** Confirm a department announcement is only visible to department members.

**Preconditions:** A department exists with at least one member. Logged in as admin.

**Steps:**
1. Create an announcement with visibility = `department_only` and assign it to "Worship Team"
2. Publish it
3. Log in as a member who is NOT in "Worship Team"
4. Navigate to `/dashboard/announcements`

**Expected Result:**
- The announcement is NOT visible to members outside the department
- The member in "Worship Team" CAN see it

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [] Needs Review

---

## ANN-005 — Mark Announcement as Read

**Purpose:** Confirm announcements can be marked read.

**Preconditions:** An unread published announcement exists. Logged in as member.

**Steps:**
1. Navigate to the announcement show page
2. Check the unread announcements count in the header before and after

**Expected Result:**
- The announcement is marked as read (auto-read on visit OR via a "Mark read" button)
- Unread count decreases
- `announcement_reads` pivot record is created in the DB

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ANN-006 — Edit Announcement

**Purpose:** Confirm published announcements can be edited.

**Preconditions:** Published announcement exists. Logged in as admin.

**Steps:**
1. Navigate to the announcement edit page (`/dashboard/announcements/{id}/edit`)
2. Change the title to `Updated Title`
3. Click **Update**

**Expected Result:**
- Show page displays the updated title
- `updated_at` timestamp is refreshed

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ANN-007 — Delete Announcement

**Purpose:** Confirm announcements can be deleted.

**Preconditions:** A test announcement exists. Logged in as admin.

**Steps:**
1. On the announcement show page, click **Delete** and confirm

**Expected Result:**
- Redirected to `/dashboard/announcements`
- Announcement no longer appears in the list
- Soft-deleted record remains in DB with `deleted_at` set

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ANN-008 — Member Cannot Publish

**Purpose:** Confirm members without `announcements.publish` cannot publish.

**Preconditions:** Logged in as member. A draft announcement exists (created by admin or coordinator).

**Steps:**
1. Navigate to the draft announcement show page

**Expected Result:**
- No **Publish** button is visible
- A direct POST to `/dashboard/announcements/{id}/publish` returns 403

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [] Needs Review

---

---

# 6. Event Tests

---

## EVT-001 — Create Event (Admin)

**Purpose:** Confirm an admin can create a public event.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/events/create`
2. Title = `Sunday Service`, Start = next Sunday 10:00 AM, Visibility = `Public`, RSVP = enabled
3. Click **Create event**

**Expected Result:**
- Redirected to the event show page
- Event appears in the list at `/dashboard/events`
- Event appears on the public `/events` page

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## EVT-002 — Edit Event

**Purpose:** Confirm event details can be updated.

**Preconditions:** An event exists. Logged in as admin.

**Steps:**
1. Navigate to the event edit page
2. Change the title to `Sunday Service — Updated`
3. Click **Update**

**Expected Result:**
- Show page reflects the new title
- Public events page also shows the updated title

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## EVT-003 — RSVP to Event (Member)

**Purpose:** Confirm members can RSVP to events.

**Preconditions:** An event with RSVP enabled exists. Logged in as member.

**Steps:**
1. Navigate to the event show page in the dashboard
2. Click **Going**

**Expected Result:**
- RSVP status changes to "Going" (button updates)
- `event_rsvp` record is created with status = `going`
- Going count increments

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [] Needs Review

---

## EVT-004 — Cancel RSVP

**Purpose:** Confirm RSVP can be cancelled.

**Preconditions:** Member has RSVPed to an event.

**Steps:**
1. On the event show page, click **Cancel RSVP** or click the active "Going" button again

**Expected Result:**
- RSVP status reverts to "not going"
- `event_rsvp` record is deleted or status is updated
- Going count decrements

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## EVT-005 — Delete Event

**Purpose:** Confirm events can be deleted.

**Preconditions:** A test event exists. Logged in as admin.

**Steps:**
1. On the event show page, click **Delete** and confirm

**Expected Result:**
- Redirected to `/dashboard/events`
- Event no longer appears in the list
- Event no longer appears on the public events page

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## EVT-006 — Members-Only Event Visibility

**Purpose:** Confirm `members_only` events are hidden from the public site.

**Preconditions:** An event with visibility = `members_only` exists.

**Steps:**
1. Navigate to `/events` (public, not logged in)
2. Note if the members-only event appears

**Expected Result:**
- Members-only event is NOT visible on the public events page
- The event IS visible to logged-in members at `/dashboard/events`

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## EVT-007 — Attach File to Event

**Purpose:** Confirm files can be attached to an event.

**Preconditions:** An event exists. Logged in as admin.

**Steps:**
1. Navigate to the event show page
2. In the Attachments section, upload a PDF
3. Verify the file appears

**Expected Result:**
- File appears in the attachments list with name, size, and upload date
- Download button works
- File is stored under `churches/{church_id}/event/`

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 7. Task Tests

---

## TASK-001 — Create Task (Admin assigns to Member)

**Purpose:** Confirm tasks can be created and assigned.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/tasks/create`
2. Title = `Prepare worship slides`, Priority = `High`, Assign to = `member@gracechurch.org`, Due = tomorrow
3. Click **Create**

**Expected Result:**
- Redirected to the task show page
- Task appears in the list with status `Pending`
- Assigned member receives a notification

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [] Needs Review

---

## TASK-002 — Member Sees Their Assigned Tasks

**Purpose:** Confirm members can view tasks assigned to them.

**Preconditions:** A task is assigned to the member (TASK-001). Logged in as member.

**Steps:**
1. Navigate to `/dashboard/tasks`

**Expected Result:**
- The assigned task "Prepare worship slides" is visible
- Tasks NOT assigned to this member are NOT visible (members only see their own)

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## TASK-003 — Change Task Status

**Purpose:** Confirm task status can be updated.

**Preconditions:** A `pending` task is assigned to member. Logged in as member.

**Steps:**
1. Navigate to the task show page
2. Use the status dropdown/buttons to change status to `in_progress`

**Expected Result:**
- Status badge updates to "In Progress"
- `tasks.status` column updated in DB
- No page reload (AJAX update)

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## TASK-004 — Complete Task

**Purpose:** Confirm tasks can be marked as completed.

**Preconditions:** A task in `in_progress` state. Logged in as admin or assigned member.

**Steps:**
1. On the task show page, click **Mark as complete** or set status to `completed`

**Expected Result:**
- Status changes to "Completed"
- `completed_at` timestamp is set
- Admin receives a notification that the task was completed

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## TASK-005 — Add Comment to Task

**Purpose:** Confirm comments can be added to tasks.

**Preconditions:** A task exists. Logged in as admin.

**Steps:**
1. Navigate to the task show page
2. Type a comment: "Please coordinate with the AV team"
3. Submit

**Expected Result:**
- Comment appears below the task details
- Comment shows author name and timestamp
- Comment is saved to the `task_comments` table

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## TASK-006 — Delete Comment

**Purpose:** Confirm comments can be deleted by the author or admin.

**Preconditions:** A comment exists on a task. Logged in as admin.

**Steps:**
1. On the task show page, click **Delete** on a comment
2. Confirm the deletion

**Expected Result:**
- Comment is removed from the page
- `task_comments` record is deleted from DB

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## TASK-007 — Admin Sees All Tasks

**Purpose:** Confirm admins can see all tasks, not just their own.

**Preconditions:** Multiple tasks assigned to different users. Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/tasks`

**Expected Result:**
- Tasks assigned to ALL users are visible
- Filter/search options include all department assignments

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## TASK-008 — Overdue Task Display

**Purpose:** Confirm overdue tasks are visually indicated.

**Preconditions:** Create a task with due date = yesterday. Logged in as admin.

**Steps:**
1. Create a task with `due_at` set to yesterday
2. Navigate to the tasks list

**Expected Result:**
- Overdue task shows a red "Overdue" badge or indicator
- `is_overdue` computed field is `true` in the API response

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [] Needs Review

---

---

# 8. Attendance Tests

---

## ATT-001 — Create Attendance Session (Admin)

**Purpose:** Confirm an attendance session can be created.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/attendance/create`
2. Title = `Sunday Service Attendance`, Type = `Service`, Scheduled = today at 10:00 AM
3. Click **Create session**

**Expected Result:**
- Redirected to the session show page
- Session status = `planned`
- Session appears in the attendance list

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ATT-002 — Mark Bulk Attendance

**Purpose:** Confirm attendance can be marked for multiple members.

**Preconditions:** An attendance session exists. At least 2 church members exist. Logged in as admin.

**Steps:**
1. Navigate to the session show page
2. For each member in the table, select status: `present` or `absent`
3. Click **Save attendance**

**Expected Result:**
- Attendance records are saved without page reload (JSON response)
- Attendance counts update: present count, total count
- `attendances` table has records with correct status

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ATT-003 — Session Status Lifecycle

**Purpose:** Confirm sessions move through planned → active → completed.

**Preconditions:** A `planned` session exists. Logged in as admin.

**Steps:**
1. On the session show page, click **Start session** (or change status to `active`)
2. Then click **End session** (or change status to `completed`)

**Expected Result:**
- Status updates correctly at each step
- When `active`: a check-in token may be generated
- When `completed`: session is listed in the completed view

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ATT-004 — Member Attendance History

**Purpose:** Confirm a member's attendance history is accessible.

**Preconditions:** Attendance records exist for a member. Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/attendance/members/{user_id}` for a member with records

**Expected Result:**
- Timeline of sessions they attended
- Stats card: total sessions, present count, rate %
- Paginated history if > 1 page

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ATT-005 — Member Sees Own History Only

**Purpose:** Confirm a member can only view their own attendance history.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/attendance/members/{own_id}` — own history
2. Navigate to `/dashboard/attendance/members/{other_id}` — another member's history

**Expected Result:**
- Own history: loads successfully
- Other member's history: 403 Forbidden

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ATT-006 — Attendance Statistics on Dashboard

**Purpose:** Confirm attendance stats are shown in the dashboard widget or on the attendance index.

**Preconditions:** At least one completed session with attendance records exists. Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/attendance`

**Expected Result:**
- Stats widget shows sessions_this_week, total_records_month, present_count_month, avg_attendance_rate
- Sparkline/bar chart of recent session rates is visible

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## ATT-007 — Delete Attendance Session

**Purpose:** Confirm sessions can be deleted.

**Preconditions:** A completed or cancelled session exists. Logged in as admin.

**Steps:**
1. Navigate to the session show page
2. Click **Delete session** and confirm

**Expected Result:**
- Session and all its attendance records are deleted
- No orphaned `attendances` rows remain

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 9. Media & Files Tests

---

## MED-001 — Upload Image to Announcement

**Purpose:** Confirm images can be uploaded as attachments.

**Preconditions:** An announcement exists. Logged in as admin.

**Steps:**
1. Navigate to the announcement show page
2. In the Attachments panel, click **Upload** and select a `.jpg` or `.png` file (< 2 MB)

**Expected Result:**
- Upload progress bar appears
- File appears in the attachment list after upload
- `files` table record created with correct `mime_type`, `is_image = 1`
- File preview opens when clicking the thumbnail

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## MED-002 — Upload PDF

**Purpose:** Confirm PDFs upload and download correctly.

**Preconditions:** A task or event exists. Logged in as admin.

**Steps:**
1. Navigate to any resource with an AttachmentList panel
2. Upload a `.pdf` file

**Expected Result:**
- PDF file card shows the `FileText` icon (not an image preview)
- Download link downloads the actual file
- `is_pdf = 1` in the `files` table

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## MED-003 — Media Library Page

**Purpose:** Confirm the media library aggregates all church files.

**Preconditions:** Several files have been uploaded (from MED-001, MED-002). Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/media`

**Expected Result:**
- All uploaded files appear in a list sorted by date
- Type filter tabs (All, Images, Audio, Video, Documents) show correct counts
- Clicking a tab filters the list
- Search box filters by file name

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## MED-004 — Delete File

**Purpose:** Confirm files can be deleted from the media library.

**Preconditions:** At least one file exists. Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/media`
2. Hover over a file card
3. Click the trash icon and confirm

**Expected Result:**
- File is removed from the list (optimistic update)
- `files` record is deleted from DB
- Physical file is removed from storage

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## MED-005 — Invalid File Type Rejected

**Purpose:** Confirm dangerous file types are blocked.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to any AttachmentList uploader
2. Attempt to upload a `.php`, `.exe`, or `.sh` file

**Expected Result:**
- Upload is rejected with a validation error
- Error message indicates unsupported file type
- No file is stored on disk

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## MED-006 — Member Cannot Delete Files

**Purpose:** Confirm members without `media.delete` cannot delete files.

**Preconditions:** A file exists. Logged in as member.

**Steps:**
1. Navigate to `/dashboard/media`
2. Check if the delete button is visible
3. Attempt a direct DELETE request to `/dashboard/files/{id}`

**Expected Result:**
- No delete button visible for member
- Direct DELETE request returns 403

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 10. Notification Tests

---

## NOTIF-001 — Task Assignment Notification

**Purpose:** Confirm assigning a task generates a notification.

**Preconditions:** Logged in as admin.

**Steps:**
1. Create a task and assign it to `member@gracechurch.org`
2. Log out, log in as member
3. Check notification bell

**Expected Result:**
- New notification: "You have been assigned: [Task Title]"
- Unread count badge is shown on the bell
- Notification appears in the dropdown

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## NOTIF-002 — Notification Read State

**Purpose:** Confirm clicking a notification marks it as read.

**Preconditions:** Member has at least one unread notification.

**Steps:**
1. Click the notification bell
2. Click on the notification item in the dropdown

**Expected Result:**
- `read_at` is set on the notification record
- Unread count decreases by 1
- Notification is no longer highlighted as unread

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## NOTIF-003 — Mark All Notifications as Read

**Purpose:** Confirm "Mark all read" works.

**Preconditions:** Member has multiple unread notifications.

**Steps:**
1. Open the notifications page at `/dashboard/notifications`
2. Click **Mark all read**

**Expected Result:**
- All notifications have `read_at` set
- Unread count badge disappears
- Page shows all notifications without the unread highlight

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## NOTIF-004 — Notification Action URL

**Purpose:** Confirm notification links navigate to the correct resource.

**Preconditions:** A "task assigned" notification exists for the member.

**Steps:**
1. Click the notification in the dropdown or notifications page
2. Observe where it navigates

**Expected Result:**
- Navigates to the specific task show page (e.g. `/dashboard/tasks/{id}`)
- Task page loads without errors
- No 403 or 404

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## NOTIF-005 — Department Added Notification Link (403 Fix)

**Purpose:** Specifically verify the bug fix: member can follow department notification links.

**Preconditions:** Member has been added to a department → received a `DepartmentMemberAdded` notification.

**Steps:**
1. Log in as the member
2. Open the notification for "You were added to [Department]"
3. Click the notification

**Expected Result:**
- Navigates to `/dashboard/departments/{id}`
- Page loads successfully (no 403)
- `departments.view` permission is active for member role

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## NOTIF-006 — Notification Isolation (Tenant)

**Purpose:** Confirm users only see their own church's notifications.

**Preconditions:** Multiple churches exist (via onboarding second church). Logged in as member of Church A.

**Steps:**
1. Navigate to `/dashboard/notifications`

**Expected Result:**
- Only notifications from Church A are shown
- No notifications from Church B are visible

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 11. Global Search Tests

---

## SRCH-001 — Search Departments

**Purpose:** Confirm departments appear in global search results.

**Preconditions:** Logged in as admin. At least one department exists.

**Steps:**
1. Press `Ctrl+K` to open global search
2. Type the first 3 letters of a department name

**Expected Result:**
- Department appears in the "Departments" result group
- Subtitle shows member count or description
- Clicking navigates to the department page

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRCH-002 — Search Members

**Purpose:** Confirm members appear in search.

**Preconditions:** Logged in as admin.

**Steps:**
1. Open global search
2. Type "Pastor" or part of a member's name

**Expected Result:**
- Member appears in the "Members" result group
- Subtitle shows email
- Result navigates to member profile page

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRCH-003 — Search Announcements

**Purpose:** Confirm announcements appear in search.

**Preconditions:** A published announcement exists. Logged in as admin.

**Steps:**
1. Open global search
2. Type a word from an announcement title

**Expected Result:**
- Announcement appears in the "Announcements" group
- Meta shows published date
- Clicking navigates to the announcement

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRCH-004 — Search Permission Filtering

**Purpose:** Confirm search respects permissions (member can't find admin-only items).

**Preconditions:** Logged in as member.

**Steps:**
1. Open global search
2. Search for something only admins can see (e.g. a private announcement)

**Expected Result:**
- Private/members-only items are filtered based on user permissions
- No items the member can't access appear in results

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRCH-005 — Search Tenant Isolation

**Purpose:** Confirm search only returns results from the user's church.

**Preconditions:** Two churches with different data exist.

**Steps:**
1. Logged in as admin of Church A
2. Search for a department/member that exists only in Church B

**Expected Result:**
- No results from Church B appear
- Only Church A data is returned

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRCH-006 — Empty Search Returns No Results

**Purpose:** Confirm an empty search doesn't crash.

**Preconditions:** Global search modal open.

**Steps:**
1. Open search
2. Type a single space or clear the input completely

**Expected Result:**
- No API call is made (debounce prevents it for very short queries)
- Shows an idle state, not a loading spinner forever
- No console errors

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 12. Sermon & YouTube Tests

---

## SRM-001 — Sermons List Page Loads

**Purpose:** Confirm the sermon archive page loads correctly.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/sermons`

**Expected Result:**
- Page loads without error
- Shows "0 sermons" or lists existing sermons
- "YouTube Channel" button is visible for admins with `sermons.manage_channels`

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRM-002 — YouTube Channel Page Loads

**Purpose:** Confirm the channel connection page renders.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/sermons/channel`

**Expected Result:**
- Stats row (Total sermons, Auto-synced, Featured) shows
- Connect form with channel URL input is visible
- "No channels connected yet" empty state if no connections

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRM-003 — Connect YouTube Channel (requires YOUTUBE_API_KEY)

**Purpose:** Confirm a real YouTube channel can be connected.

**Preconditions:** `YOUTUBE_API_KEY` is set in `.env`. Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/sermons/channel`
2. Enter a real YouTube channel URL (e.g. `https://www.youtube.com/@YourChurch`)
3. Click **Connect & Sync**

**Expected Result:**
- Success flash: "✓ [Channel Name] connected. Sync in progress."
- A `ChannelConnection` record is created in the DB
- A `SyncYouTubeChannelJob` is dispatched to the queue

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [] Needs Review

---

## SRM-004 — Invalid Channel Input

**Purpose:** Confirm that an invalid channel input shows an error.

**Preconditions:** `YOUTUBE_API_KEY` is set. Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/sermons/channel`
2. Enter a nonsense value: `not_a_real_channel_xyz123456`
3. Click **Connect & Sync**

**Expected Result:**
- Error shown: "[youtube] Channel not found for input: not_a_real_channel_xyz123456"
- No channel connection is created

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRM-005 — No API Key Configured

**Purpose:** Confirm a helpful error is shown when YOUTUBE_API_KEY is missing.

**Preconditions:** `YOUTUBE_API_KEY` is empty in `.env`. Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/sermons/channel`
2. Enter any channel URL
3. Click **Connect & Sync**

**Expected Result:**
- Error: "[youtube] API key is not configured. Set YOUTUBE_API_KEY in .env"
- No crash / 500 error

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRM-006 — Feature a Sermon

**Purpose:** Confirm admins can feature and unfeature sermons.

**Preconditions:** At least one sermon exists (manually created or synced). Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/sermons`
2. On a sermon card, click the ☆ (star) icon

**Expected Result:**
- Star fills with amber colour
- `is_featured = 1` in DB
- Flash: "Sermon featured."
- On the public `/sermons` page, this sermon appears as the featured hero

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRM-007 — Cycle Sermon Visibility

**Purpose:** Confirm the visibility badge cycles correctly.

**Preconditions:** A sermon exists. Logged in as admin with `sermons.edit`.

**Steps:**
1. On a sermon card in the grid, note the current visibility badge
2. Click the badge (e.g. "Public")
3. Observe the change, click again

**Expected Result:**
- Cycles: Public → Members only → Unlisted → Public
- Badge text and colour update immediately (optimistic update)
- `visibility` column updates in DB

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRM-008 — Public Sermons Page

**Purpose:** Confirm the public sermons page renders correctly.

**Preconditions:** At least one public sermon exists in the DB.

**Steps:**
1. Navigate to `http://localhost:8000/sermons` (no login)

**Expected Result:**
- Dark hero header loads
- Featured sermon hero card visible (if a sermon has `is_featured = 1`)
- Recent sermons grid visible
- Series filter tabs visible
- No errors for unauthenticated access

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRM-009 — Sermon Watch Page

**Purpose:** Confirm the individual sermon watch page renders.

**Preconditions:** At least one public sermon with a slug exists.

**Steps:**
1. Navigate to `http://localhost:8000/sermons/{slug}` for an existing sermon

**Expected Result:**
- Page loads with sermon title, player area, description, related sermons
- If sermon has `embed_url` (YouTube), iframe embed is displayed
- If audio-only, audio player shows
- No login required

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SRM-010 — Member Cannot Manage Channels

**Purpose:** Confirm members cannot access the channel management page.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/sermons/channel`

**Expected Result:**
- 403 Forbidden page
- No channel connection form visible

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 13. Volunteer Scheduling Tests

---

## SCHED-001 — Scheduling Dashboard Loads

**Purpose:** Confirm the scheduling hub page renders without errors.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/scheduling`

**Expected Result:**
- Page loads with stats strip (Upcoming Plans, Active Positions, Assigned Volunteers, Unfilled Slots)
- Quick-actions strip visible for admin/coordinator users ("Service Plans" and "Position Library" cards)
- Upcoming published plans section visible below
- No console errors

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-002 — Quick-Actions Strip Hidden from Members

**Purpose:** Confirm the quick-actions strip is only shown to users with `scheduling.manage`.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/scheduling`

**Expected Result:**
- Page loads without error
- The "Service Plans" and "Position Library" action cards are NOT visible
- Upcoming plans are still visible (if any are published)

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-003 — Service Plans List with Tabs

**Purpose:** Confirm the plans list loads and tabs filter correctly.

**Preconditions:** Logged in as admin. At least one plan in each status (draft, published, archived) exists.

**Steps:**
1. Navigate to `/dashboard/scheduling/plans`
2. Default tab is "Upcoming" — verify only published upcoming plans show
3. Click the "Draft" tab — verify only drafts show
4. Click the "Past" tab — verify only published past plans show
5. Click the "Archived" tab — verify only archived plans show

**Expected Result:**
- Each tab shows only plans matching that status
- Pagination appears if > 20 plans
- Plan cards show title, date, location, filled/total positions, status badge
- "Create Plan" button visible for admin/coordinator

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-004 — Create Service Plan

**Purpose:** Confirm an admin can create a new service plan.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/scheduling/plans/create`
2. Title = `Sunday Morning Service`, Scheduled = next Sunday 10:00 AM, Location = `Main Sanctuary`
3. Click **Create plan**

**Expected Result:**
- Redirected to the new plan's show page at `/dashboard/scheduling/plans/{id}`
- Plan status badge shows "Draft"
- Title, date, and location are visible
- "Add Position", "Edit", and "Publish" buttons visible

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-005 — Edit Service Plan

**Purpose:** Confirm a plan's details can be updated.

**Preconditions:** A draft plan exists. Logged in as admin.

**Steps:**
1. On the plan show page, click **Edit**
2. Change the title to `Sunday Morning Service — Updated`
3. Click **Update**

**Expected Result:**
- Plan show page reflects the updated title
- Flash success toast appears
- Status remains "Draft"

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-006 — Publish Service Plan + Volunteer Notifications

**Purpose:** Confirm publishing a plan changes its status and notifies assigned volunteers.

**Preconditions:** A draft plan exists with at least one assigned volunteer. Logged in as admin.

**Steps:**
1. On the plan show page, click **Publish**
2. Confirm the action
3. Switch to the assigned volunteer's session

**Expected Result:**
- Plan status badge changes to "Published"
- `published_at` is set in the DB
- Assigned volunteer receives a `SchedulePublished` notification in their bell
- Notification says "You've been scheduled for: [Plan Title]"
- Plan appears in the "Upcoming" tab of the plans list

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-007 — Archive Service Plan

**Purpose:** Confirm a published plan can be archived.

**Preconditions:** A published plan exists. Logged in as admin.

**Steps:**
1. On the plan show page, click **Archive**

**Expected Result:**
- Status badge changes to "Archived"
- Plan disappears from "Upcoming" tab
- Plan appears in "Archived" tab
- Cannot be edited or have positions added

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-008 — Delete Draft Plan

**Purpose:** Confirm draft plans can be deleted.

**Preconditions:** A draft plan exists. Logged in as admin.

**Steps:**
1. On the draft plan show page, click **Delete** and confirm

**Expected Result:**
- Redirected to `/dashboard/scheduling/plans`
- Plan no longer appears in any tab
- All related `service_plan_positions` and `volunteer_assignments` deleted

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-009 — Position Library Nav Item Accessible

**Purpose:** Confirm the Position Library nav item exists and loads the correct page.

**Preconditions:** Logged in as admin.

**Steps:**
1. Look for "Position Library" in the sidebar under the Work group
2. Click it

**Expected Result:**
- Link is present in the sidebar (visible to admin and coordinator)
- Navigates to `/dashboard/scheduling/positions`
- Position Library page loads with a list of positions (or empty state)
- "Add Position" button visible

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-010 — Create Serving Position

**Purpose:** Confirm a serving position can be created in the library.

**Preconditions:** At least one department exists. Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/scheduling/positions`
2. Click **Add Position**
3. Fill in: Name = `Camera Operator`, Department = `Media Team`, Description = `Handles camera for livestream`
4. Click **Create**

**Expected Result:**
- Position appears in the library grouped under "Media Team"
- `serving_positions` DB record created with correct `church_id`
- Flash success toast

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-011 — Edit Serving Position

**Purpose:** Confirm a serving position can be edited.

**Preconditions:** A serving position exists. Logged in as admin.

**Steps:**
1. On the Position Library, click the edit icon on a position
2. Change the name to `Camera Operator — Updated`
3. Save

**Expected Result:**
- Position name updates in the library
- `schedule.position.updated` audit event recorded
- Flash success toast

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-012 — Delete Serving Position

**Purpose:** Confirm a serving position can be deleted.

**Preconditions:** A serving position with no active assignments exists. Logged in as admin.

**Steps:**
1. On the Position Library, click the delete icon and confirm

**Expected Result:**
- Position removed from the library
- `serving_positions` record soft-deleted
- No orphaned `service_plan_positions` records

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-013 — Add Position to Plan (Normal Flow)

**Purpose:** Confirm a position from the library can be added to a plan.

**Preconditions:** A draft plan exists. At least one position exists in the library. Logged in as admin.

**Steps:**
1. Navigate to the plan show page
2. Click **+ Add Position**
3. The modal opens — select "Camera Operator" from the dropdown
4. Click **Add to Plan**

**Expected Result:**
- Position card appears in the plan's position list
- Dropdown groups positions by department
- `service_plan_positions` record created
- Flash success toast

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-014 — Add Position Modal — Empty Library State

**Purpose:** Confirm the modal shows a helpful link when no positions exist.

**Preconditions:** Position library is empty (no positions created yet). A plan exists. Logged in as admin.

**Steps:**
1. Navigate to the plan show page
2. Click **+ Add Position**

**Expected Result:**
- Modal opens but shows "No positions in the library yet." message
- A link "Create positions first →" links to `/dashboard/scheduling/positions`
- No empty dropdown or broken UI

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-015 — Remove Position from Plan

**Purpose:** Confirm a position can be removed from a plan (and assigned volunteers notified).

**Preconditions:** A plan has a position with an assigned volunteer. Logged in as admin.

**Steps:**
1. On the plan show page, click the remove icon on a position card
2. Confirm the removal

**Expected Result:**
- Position card removed from the plan
- If a volunteer was assigned and not declined, they receive a `VolunteerRemoved` notification
- `service_plan_positions` record deleted
- Cascading `volunteer_assignments` also deleted

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-016 — Assign Volunteer to Position

**Purpose:** Confirm volunteers can be assigned to plan positions.

**Preconditions:** A plan has a position with no assignment. At least one member exists. Logged in as admin.

**Steps:**
1. On the plan show page, on an empty position card click **Assign**
2. Select a member from the dropdown
3. Click **Assign**

**Expected Result:**
- Volunteer's name appears on the position card with a "Pending" badge
- `volunteer_assignments` record created with `status = pending`
- Flash success toast
- `schedule.assignment.created` audit event recorded

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-017 — Volunteer Confirms Assignment

**Purpose:** Confirm a volunteer can confirm their assignment.

**Preconditions:** A volunteer has a pending assignment. Logged in as that volunteer.

**Steps:**
1. Navigate to `/dashboard/scheduling/my-schedule`
2. Find the pending assignment
3. Click **Confirm**

**Expected Result:**
- Assignment badge changes to "Confirmed" (green)
- `volunteer_assignments.status = confirmed` in DB
- `schedule.assignment.confirmed` audit event recorded

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-018 — Volunteer Declines Assignment

**Purpose:** Confirm a volunteer can decline their assignment.

**Preconditions:** A volunteer has a pending assignment. Logged in as that volunteer.

**Steps:**
1. Navigate to `/dashboard/scheduling/my-schedule`
2. Find the pending assignment
3. Click **Decline**

**Expected Result:**
- Assignment badge changes to "Declined" (red)
- `volunteer_assignments.status = declined` in DB
- Position slot shows as unfilled on the plan
- `schedule.assignment.declined` audit event recorded

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-019 — Remove Volunteer Assignment (Admin)

**Purpose:** Confirm admins can remove a volunteer from a position slot.

**Preconditions:** A volunteer assignment exists. Logged in as admin.

**Steps:**
1. On the plan show page, click the × icon on a volunteer's assignment card
2. Confirm

**Expected Result:**
- Volunteer removed from the position
- Assignment slot shows as empty again
- `volunteer_assignments` record deleted

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-020 — My Schedule Page (Volunteer View)

**Purpose:** Confirm the My Schedule page shows a volunteer's upcoming assignments.

**Preconditions:** A volunteer has at least one assignment (any status). Logged in as that volunteer.

**Steps:**
1. Click "My Schedule" in the sidebar (Work group)
2. Navigate to `/dashboard/scheduling/my-schedule`

**Expected Result:**
- Page loads at `/dashboard/scheduling/my-schedule`
- Shows the volunteer's assignments grouped by upcoming / past
- Each assignment shows plan title, date, position name, status badge
- Confirm/Decline buttons visible for `pending` assignments

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-021 — Member Cannot Create Positions

**Purpose:** Confirm regular members lack `scheduling.manage` and cannot create positions.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/scheduling/positions` — confirm it returns 403
2. Attempt to POST to `/dashboard/scheduling/positions`

**Expected Result:**
- Sidebar does NOT show "Position Library" for member
- Direct navigation to `/dashboard/scheduling/positions` returns 403 Forbidden
- POST request returns 403

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SCHED-022 — Member Cannot Publish Plans

**Purpose:** Confirm members cannot publish service plans.

**Preconditions:** A draft plan exists. Logged in as member.

**Steps:**
1. Navigate to the plan show page
2. Check for a Publish button
3. Attempt PATCH to `/dashboard/scheduling/plans/{id}/publish`

**Expected Result:**
- No "Publish" button visible
- Direct PATCH request returns 403

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 14. Church Administration Centre Tests

---

## SET-001 — Administration Centre Hub Loads

**Purpose:** Confirm admins can access the Administration Centre hub and it redirects to overview.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/settings`

**Expected Result:**
- Redirects to `/dashboard/settings/overview` (the hub landing page)
- Left-hand sidebar shows all 19 sections: Overview, Church Profile, Branding & Theme, Social Media, Public Website, Service Times, Livestream, Donations, Communication, Membership, Departments, Notifications, Roles & Permissions, Media Library, Security, SEO & Analytics, Integrations, Audit Logs, Advanced
- Current church data is pre-populated

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-002 — Update Church Name

**Purpose:** Confirm church name can be updated.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/settings`
2. Change the Church Name to `Grace Community Church — Updated`
3. Click **Save settings**

**Expected Result:**
- Flash success toast
- Sidebar and any branding elements reflect the new name
- Public website header shows the updated name
- After page refresh, name persists

**Actual Result:** _______________________________________________

**Status:** - [✅] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-003 — Upload Logo

**Purpose:** Confirm a church logo can be uploaded.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/settings`
2. Click the logo upload area and select a PNG logo (< 2 MB)
3. Click **Save settings**

**Expected Result:**
- Logo preview updates
- `churches.logo` field is set to the storage path
- Logo appears in the public site header and dashboard sidebar

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-004 — Update Primary Colour

**Purpose:** Confirm the brand colour can be changed.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/settings`
2. Change the Primary Color to `#E53E3E` (red)
3. Save

**Expected Result:**
- Saved successfully
- CSS custom property `--color-brand` (or equivalent) updates
- Buttons and accent elements reflect the new colour after page refresh

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-005 — Member Cannot Access Settings

**Purpose:** Confirm members are blocked from church settings.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/settings`

**Expected Result:**
- 403 Forbidden — member does not have `church.edit` permission
- No settings form visible

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-006 — Settings Persist After Refresh

**Purpose:** Confirm saved settings are not lost on reload.

**Preconditions:** Admin updated church name in SET-002.

**Steps:**
1. Hard-refresh the dashboard (`Ctrl+Shift+R`)
2. Re-open `/dashboard/settings`

**Expected Result:**
- Updated name "Grace Community Church — Updated" is still shown
- No revert to old values

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-007 — Branding & Theme Section

**Purpose:** Confirm the Branding section loads and can save a new colour.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/settings/branding`
2. Change the primary colour to `#7C3AED`
3. Click **Save**

**Expected Result:**
- Flash success toast appears
- `churches.primary_color` is updated in the DB
- After reload, the saved colour is shown pre-filled

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-008 — Social Media Section

**Purpose:** Confirm social links can be saved.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/settings/social`
2. Enter `https://facebook.com/gracechurch` in the Facebook field
3. Click **Save**

**Expected Result:**
- Success toast
- Value persists on reload
- Public homepage footer social icons reflect the saved link

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-009 — Service Times Section

**Purpose:** Confirm service times can be added and saved.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/settings/services`
2. Add a service time: Sunday, 10:00 AM, "Main Worship Service"
3. Save

**Expected Result:**
- Service time appears in the list
- Saved data visible on reload
- Public homepage services section renders the new entry

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-010 — Roles & Permissions View (Read-Only)

**Purpose:** Confirm the Roles section renders the permission matrix without errors.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/settings/roles`

**Expected Result:**
- Page loads showing `super_admin`, `church_admin`, `coordinator`, `member` roles
- Each role's permissions are listed
- No edit controls (read-only view)
- No 500 errors

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-011 — All 19 Settings Sections Load Without Errors

**Purpose:** Smoke-test all Administration Centre section routes.

**Preconditions:** Logged in as admin.

**Steps:**
1. Visit each of these URLs in sequence and verify HTTP 200 + no console errors:
   - `/dashboard/settings/overview`
   - `/dashboard/settings/profile`
   - `/dashboard/settings/branding`
   - `/dashboard/settings/social`
   - `/dashboard/settings/website`
   - `/dashboard/settings/services`
   - `/dashboard/settings/livestream`
   - `/dashboard/settings/donations`
   - `/dashboard/settings/communication`
   - `/dashboard/settings/membership`
   - `/dashboard/settings/depts`
   - `/dashboard/settings/notifications`
   - `/dashboard/settings/roles`
   - `/dashboard/settings/media`
   - `/dashboard/settings/security`
   - `/dashboard/settings/seo`
   - `/dashboard/settings/integrations`
   - `/dashboard/settings/audit`
   - `/dashboard/settings/advanced`

**Expected Result:**
- All 19 pages return HTTP 200
- No 500 errors on any section
- Each section renders with a heading and content area

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SET-012 — Communication Settings (Email Reply-To)

**Purpose:** Confirm communication settings can be saved.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/settings/communication`
2. Set reply-to email to `pastor@gracechurch.org`
3. Click **Save**

**Expected Result:**
- Success toast
- Value persists on page reload

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 14. Reports Tests

---

## RPT-001 — Reports Page Loads

**Purpose:** Confirm the admin reports page renders.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/reports`

**Expected Result:**
- Page loads with People, Events, Tasks, Content, and Attendance sections
- Each section shows numeric stats
- Attendance section shows a mini bar chart of recent session rates (if sessions exist)

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## RPT-002 — Member Cannot Access Reports

**Purpose:** Confirm members are blocked from reports.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/reports`

**Expected Result:**
- 403 Forbidden — member does not have `reports.view`
- No stats data is visible

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 16. User Profile Tests

---

## PROF-001 — Profile Page Loads

**Purpose:** Confirm every authenticated user can access their profile page.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/profile`

**Expected Result:**
- Page loads with the user's current name, email, avatar/initials, and role badge(s)
- Two forms visible: "Update Display Name" and "Change Password"
- No 403 or 404

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PROF-002 — Update Display Name

**Purpose:** Confirm a user can change their display name.

**Preconditions:** Logged in as any authenticated user.

**Steps:**
1. Navigate to `/dashboard/profile`
2. Change the name field to `Test Member Updated`
3. Click **Save profile**

**Expected Result:**
- Flash success toast: "Profile updated."
- Name updates in the top-right avatar area
- Dashboard sidebar reflects the new name
- `users.name` updated in DB

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PROF-003 — Change Password (Correct Current Password)

**Purpose:** Confirm a user can change their password with the correct current password.

**Preconditions:** Logged in as member with known password `password`.

**Steps:**
1. Navigate to `/dashboard/profile`
2. In the Change Password form, enter:
   - Current password: `password`
   - New password: `NewSecure123!`
   - Confirm: `NewSecure123!`
3. Click **Change password**

**Expected Result:**
- Flash success toast: "Password changed successfully."
- User can log in with `NewSecure123!` after next login
- `auth.password.changed` audit event recorded
- Old password `password` is rejected on next login attempt

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PROF-004 — Change Password (Wrong Current Password Rejected)

**Purpose:** Confirm wrong current password is rejected.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/profile`
2. In the Change Password form, enter:
   - Current password: `wrongpassword`
   - New password: `NewSecure123!`
   - Confirm: `NewSecure123!`
3. Click **Change password**

**Expected Result:**
- Validation error: "The current_password field is invalid."
- Password is NOT changed
- No audit event recorded

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 17. Audit Log Tests

---

## AUDIT-001 — Audit Log Page Loads (Admin)

**Purpose:** Confirm admins can access the full audit log viewer.

**Preconditions:** Logged in as admin. At least one audit event exists (e.g. from logging in).

**Steps:**
1. Navigate to `/dashboard/audit` (or click "Audit Log" in the sidebar)

**Expected Result:**
- Page loads with a paginated table of audit events
- Each row shows: timestamp, action, target name, actor name, module colour badge
- Filter controls visible: Module, Action, Actor, Department, Date From, Date To
- 25 events per page (pagination controls visible if > 25)
- "Audit Log" sidebar item is highlighted

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUDIT-002 — Member Cannot Access Audit Log

**Purpose:** Confirm members without `audit.view` get 403.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/audit`
2. Check sidebar for "Audit Log" item

**Expected Result:**
- HTTP 403 Forbidden
- "Audit Log" item is NOT visible in the sidebar for member role

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUDIT-003 — Coordinator Sees Only Their Department Events

**Purpose:** Confirm coordinator's audit view is scoped to their department(s).

**Preconditions:** Coordinator is a member of "Media Team" department. Some audit events exist tagged with `department_id` for Media Team; others are untagged (e.g. `auth.login`).

**Steps:**
1. Log in as coordinator
2. Navigate to `/dashboard/audit`

**Expected Result:**
- Only events with `metadata.department_id` matching coordinator's department(s) are shown
- Global events like `auth.login` (no department_id) are NOT visible to coordinator
- If coordinator belongs to no departments, they see zero events

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUDIT-004 — Filter by Module

**Purpose:** Confirm the module filter narrows results to one module's events.

**Preconditions:** Logged in as admin. Audit events from multiple modules exist.

**Steps:**
1. Navigate to `/dashboard/audit`
2. Select "auth" from the Module dropdown
3. Apply the filter (or observe auto-apply on change)

**Expected Result:**
- Only events whose action starts with `auth.` are shown (e.g. `auth.login`, `auth.logout`, `auth.password.changed`)
- URL updates with `?module=auth`
- Clearing the filter restores all events

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUDIT-005 — Filter by Actor

**Purpose:** Confirm the actor filter shows only events from a specific user.

**Preconditions:** Logged in as admin. Multiple users have generated audit events.

**Steps:**
1. Navigate to `/dashboard/audit`
2. Select the admin user from the Actor dropdown
3. Apply

**Expected Result:**
- Only events where `user_id` matches the selected actor are shown
- URL updates with `?user_id={id}`

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUDIT-006 — Filter by Date Range

**Purpose:** Confirm date-range filters restrict events to the selected window.

**Preconditions:** Logged in as admin. Events exist from both today and more than 7 days ago.

**Steps:**
1. Navigate to `/dashboard/audit`
2. Set Date From = today minus 2 days, Date To = today
3. Apply

**Expected Result:**
- Only events from within that date window are shown
- Older events are excluded
- URL updates with `?date_from=...&date_to=...`

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUDIT-007 — Expand Row to See old/new Values

**Purpose:** Confirm clicking an audit row expands it to show change detail.

**Preconditions:** An audit event with `old_values` and `new_values` exists (e.g. from editing a department or announcement). Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/audit`
2. Find a row with an "updated" action
3. Click the row or the expand chevron

**Expected Result:**
- Row expands inline showing a JSON or key-value display of `old_values`, `new_values`, and `metadata`
- Collapsing the row hides the detail again
- No page navigation or reload

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUDIT-008 — Recent Activity Widget on Dashboard

**Purpose:** Confirm the dashboard shows the Recent Activity widget for users with `audit.view`.

**Preconditions:** Logged in as admin. Audit events exist.

**Steps:**
1. Navigate to `/dashboard`
2. Scroll below the stats section

**Expected Result:**
- "Recent Activity" widget renders with up to 8 events
- Each row shows module colour dot, action string, actor name, time-ago
- "View all →" link navigates to `/dashboard/audit`
- Member (no `audit.view`) does NOT see the widget

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUDIT-009 — Audit Log Nav Item Gated by Permission

**Purpose:** Confirm the "Audit Log" sidebar nav item is hidden for members.

**Preconditions:** Two sessions open: one admin, one member.

**Steps:**
1. Admin session: verify "Audit Log" appears in the Administration sidebar group
2. Member session: verify "Audit Log" does NOT appear in the sidebar

**Expected Result:**
- Admin: "Audit Log" nav item visible, links to `/dashboard/audit`
- Member: no "Audit Log" item anywhere in the sidebar

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## AUDIT-010 — Key Actions Are Logged

**Purpose:** Confirm critical user actions produce audit records.

**Preconditions:** Logged in as admin.

**Steps:**
Perform each action and verify a corresponding audit event appears in `/dashboard/audit`:

| Action | Expected audit event |
|--------|---------------------|
| Log in | `auth.login` |
| Log out | `auth.logout` |
| Change password | `auth.password.changed` |
| Publish an announcement | `announcement.published` |
| Create a task and assign it | `task.assigned` |
| Mark a task completed | `task.completed` |
| Add a member to a department | `department.member.added` |
| Publish a service plan | `schedule.plan.published` |
| Assign a volunteer | `schedule.assignment.created` |
| Upload a file | `file.uploaded` |
| Feature a sermon | `sermon.published` |

**Expected Result:**
- Each action produces exactly one audit event with the correct `action` string
- `user_id`, `church_id`, and `target_name` are populated on each record
- No audit failure causes the primary action to fail (audit errors are silent)

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 15. Public Website Tests

---

## PUB-001 — Homepage Loads

**Purpose:** Confirm the public homepage renders.

**Preconditions:** Server running. Not logged in.

**Steps:**
1. Navigate to `http://localhost:8000/`

**Expected Result:**
- Hero section with church name and tagline
- Upcoming events section (pulls from DB)
- Services section, About section, CTA section
- No login required, no 500 errors

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-002 — About Page

**Purpose:** Confirm /about loads.

**Steps:**
1. Navigate to `/about`

**Expected Result:**
- Page renders with church story, team, values, stats
- No errors

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-003 — Events Page

**Purpose:** Confirm public events page shows only public events.

**Steps:**
1. Navigate to `/events`

**Expected Result:**
- Only events with `visibility = public` are shown
- Events display date, time, title, location
- Responsive grid layout
- Private/members-only events are hidden

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-004 — Announcements Page

**Purpose:** Confirm public announcements page renders.

**Steps:**
1. Navigate to `/announcements`

**Expected Result:**
- Only published, public announcements are shown
- Each shows title, excerpt, date
- No errors

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-005 — Sermons Page (Live Data)

**Purpose:** Confirm the public sermons page renders real data (not hardcoded).

**Preconditions:** At least one public sermon exists in DB with `is_public = 1`.

**Steps:**
1. Navigate to `/sermons`

**Expected Result:**
- Sermons from the database are displayed (not the old hardcoded list)
- Featured sermon hero shows if `is_featured = 1`
- Series filter tabs are populated
- Thumbnails load (YouTube CDN or placeholder)

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-006 — Sermon Watch Page — Valid Slug

**Purpose:** Confirm `/sermons/{slug}` loads for a valid sermon.

**Preconditions:** A sermon with slug exists.

**Steps:**
1. Navigate to `/sermons/{valid-slug}`

**Expected Result:**
- Watch page loads with player, title, description
- Related sermons sidebar shows if data exists
- No login required

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-007 — Sermon Watch Page — Invalid Slug

**Purpose:** Confirm 404 for non-existent sermon slugs.

**Steps:**
1. Navigate to `/sermons/this-sermon-does-not-exist`

**Expected Result:**
- 404 Not Found page
- No 500 internal server error

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-008 — Contact Form Submission

**Purpose:** Confirm the contact form submits successfully.

**Steps:**
1. Navigate to `/contact`
2. Fill in name, email, message
3. Click **Send message**

**Expected Result:**
- Success flash message
- Form resets after submission
- (If mail is configured) an email is sent to the church address

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-009 — Donations Page

**Purpose:** Confirm the giving/donations page loads.

**Steps:**
1. Navigate to `/give`

**Expected Result:**
- Page renders with donation funds and giving options
- No errors

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-010 — Ministries / Departments Page

**Purpose:** Confirm the public ministries page shows visible departments.

**Steps:**
1. Navigate to `/ministries`

**Expected Result:**
- Departments with `visibility = public` are shown
- Private departments are NOT shown
- Department icons and descriptions render

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-011 — Livestream Page

**Purpose:** Confirm the livestream page loads.

**Steps:**
1. Navigate to `/live`

**Expected Result:**
- Page renders without errors
- Livestream placeholder or embed is shown

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## PUB-012 — Public Pages Mobile Responsiveness

**Purpose:** Confirm all public pages are readable on mobile.

**Steps:**
1. In DevTools, set viewport to 390×844 (iPhone 14)
2. Check: `/`, `/events`, `/sermons`, `/announcements`, `/about`, `/contact`

**Expected Result:**
- No horizontal overflow/scrollbar on any page
- Navigation collapses to hamburger menu
- Text is readable, images scale correctly
- All CTAs are tappable (min 44×44 touch target)

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 16. Multi-Tenant Tests

---

## TNT-001 — Data Scoping (Departments)

**Purpose:** Confirm departments are scoped to the correct church.

**Preconditions:** Two churches exist (Church A seeded, Church B created via onboarding). Each has its own departments.

**Steps:**
1. Log in as admin of Church A
2. Navigate to `/dashboard/departments`
3. Note the departments listed

**Expected Result:**
- Only Church A's departments are shown
- Church B's departments do NOT appear
- Count matches only Church A's records

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## TNT-002 — Direct URL Cross-Tenant Access Prevention

**Purpose:** Confirm Church B's admin cannot access Church A's department via direct URL.

**Preconditions:** Church A has a department with ID = 1. Church B admin knows this ID.

**Steps:**
1. Log in as Church B admin
2. Navigate to `/dashboard/departments/1`

**Expected Result:**
- 403 Forbidden OR 404 Not Found
- Church B admin cannot view Church A's department content

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## TNT-003 — Cross-Tenant Task Access Prevention

**Purpose:** Confirm tasks are scoped per tenant.

**Preconditions:** Two churches. Task ID 1 belongs to Church A.

**Steps:**
1. Log in as Church B admin
2. Navigate to `/dashboard/tasks/1`

**Expected Result:**
- 403 Forbidden or 404 Not Found
- Task content from Church A is NOT displayed

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## TNT-004 — Cross-Tenant Member Access Prevention

**Purpose:** Confirm members from Church A can't be viewed by Church B.

**Preconditions:** Two churches with separate members.

**Steps:**
1. Log in as Church B admin
2. Navigate to `/dashboard/members` — verify only Church B members appear
3. Navigate to `/dashboard/members/{churchA_user_id}` directly

**Expected Result:**
- Step 2: Only Church B members in the list
- Step 3: 403 or 404

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## TNT-005 — Cross-Tenant Search Isolation

**Purpose:** Confirm global search never returns results from another church.

**Preconditions:** Two churches. Church B admin knows a unique name from Church A.

**Steps:**
1. Log in as Church B admin
2. Open global search
3. Search for a unique term from Church A (e.g. a department name unique to Church A)

**Expected Result:**
- No results from Church A appear
- Search is bounded by the resolved church context

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## TNT-006 — Cross-Tenant Notification Isolation

**Purpose:** Confirm notifications are not leaked between churches.

**Preconditions:** Two churches. Church A admin assigns a task to a Church A member.

**Steps:**
1. Log in as Church B member
2. Check notifications

**Expected Result:**
- No Church A notifications appear for Church B users
- `notifications` table records are user-scoped, not church-scoped, but since the action was on Church A's task, it only notifies Church A's users

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## TNT-007 — Tenant File Isolation

**Purpose:** Confirm files uploaded in Church A cannot be accessed by Church B.

**Preconditions:** Church A has a file at `churches/1/event/abc.pdf`.

**Steps:**
1. Log in as Church B admin
2. Navigate to `/dashboard/files/{churchA_file_id}/download`

**Expected Result:**
- 403 Forbidden — file does not belong to Church B's tenant

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 17. RBAC & Permission Tests

---

## RBAC-001 — Permission Matrix: church_admin

**Purpose:** Verify church_admin has all expected permissions.

**Preconditions:** Logged in as church_admin.

**Steps:**
1. Confirm these actions are available (visible + functional):
   - Create/Edit/Delete: Departments, Events, Announcements, Tasks, Sermons
   - View + manage: Members, Attendance, Media
   - Access: Settings, Reports
   - Connect YouTube channel

**Expected Result:**
- All listed actions are accessible without 403 errors
- `users.permissions` for this user includes all 42 permissions

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## RBAC-002 — Permission Matrix: member

**Purpose:** Verify member has only allowed permissions.

**Preconditions:** Logged in as member.

**Steps:**
1. Confirm these actions ARE available:
   - View departments, events, announcements, sermons, members, media
   - RSVP to events
   - View own tasks
2. Confirm these actions ARE BLOCKED (403 or hidden):
   - Create/Edit departments, events, announcements
   - Access church settings
   - Access reports
   - Connect YouTube channel
   - Delete anything (except own task comments)

**Expected Result:**
- All allowed actions work
- All blocked actions return 403 or are hidden from the UI

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## RBAC-003 — Coordinator Role Permissions

**Purpose:** Verify coordinator permissions are correctly scoped.

**Preconditions:** Assign `member@gracechurch.org` the coordinator role via tinker:
```
$user = App\Models\User::find(2); $user->syncRoles(['coordinator']);
```
Then log in as that user.

**Steps:**
1. Verify coordinator CAN: Create events, Create announcements, Manage dept members, Manage attendance sessions, Upload files
2. Verify coordinator CANNOT: Delete events, Delete sermons, Access church settings, Access reports, Connect YouTube channels

**Expected Result:**
- All items in Step 1 are accessible
- All items in Step 2 return 403 or are hidden

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## RBAC-004 — Re-seeding Permissions is Idempotent

**Purpose:** Confirm running the seeder multiple times doesn't break anything.

**Steps:**
1. Run `php artisan db:seed --class=RolesAndPermissionsSeeder` twice
2. Log in as admin and verify all permissions still work

**Expected Result:**
- No duplicate permission rows created
- All roles have the same permissions as before
- Dashboard fully functional

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## RBAC-005 — Super Admin Bypass

**Purpose:** Confirm super_admin bypasses all policy checks via Gate::before.

**Preconditions:** Create a super_admin user via tinker:
```php
$u = App\Models\User::find(1); $u->syncRoles(['super_admin']);
```

**Steps:**
1. Log in as the super_admin
2. Access any page that normally requires specific permissions

**Expected Result:**
- All pages accessible with no 403 errors
- super_admin can view/edit/delete across all modules

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## RBAC-006 — Coordinator Scheduling Permissions

**Purpose:** Verify coordinator can manage scheduling but cannot publish or delete plans.

**Preconditions:** Assign coordinator role to a test user:
```php
$user = App\Models\User::find(2); $user->syncRoles(['coordinator']);
```
Log in as that coordinator.

**Steps:**
1. Verify coordinator CAN:
   - View `/dashboard/scheduling` and all sub-pages
   - View Position Library at `/dashboard/scheduling/positions`
   - Create serving positions (POST to positions)
   - Create and edit service plans
   - Add/remove positions on a plan
   - Assign volunteers to plan positions
   - View audit log at `/dashboard/audit` (scoped to their department)
2. Verify coordinator CANNOT:
   - Publish a service plan (no `scheduling.publish` permission → button hidden, direct PATCH returns 403)
   - Archive a plan
   - Access `/dashboard/settings` (403)
   - Access `/dashboard/reports` (403)

**Expected Result:**
- All Step 1 actions succeed without 403
- All Step 2 actions return 403 or are absent from the UI

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 18. Security Tests

---

## SEC-001 — Direct Dashboard Access Without Login

**Purpose:** Verify all dashboard routes redirect unauthenticated users.

**Preconditions:** Not logged in.

**Steps:**
1. In a fresh incognito window, visit each of these URLs:
   - `/dashboard`
   - `/dashboard/members`
   - `/dashboard/settings`
   - `/dashboard/reports`

**Expected Result:**
- All redirect to `/login`
- No data is leaked in the response body

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SEC-002 — Permission Escalation via URL Manipulation

**Purpose:** Verify a member cannot access admin-only pages by guessing URLs.

**Preconditions:** Logged in as member.

**Steps:**
1. Navigate to `/dashboard/settings`
2. Navigate to `/dashboard/reports`
3. Navigate to `/dashboard/sermons/channel`

**Expected Result:**
- All three return 403 Forbidden
- No admin UI is rendered

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SEC-003 — Route Model Binding — Correct Church Scope

**Purpose:** Confirm route model binding respects the BelongsToChurch global scope.

**Preconditions:** Two churches. Task ID 1 belongs to Church A.

**Steps:**
1. Log in as Church B admin (assume task 1 doesn't exist for Church B)
2. Navigate to `/dashboard/tasks/1`

**Expected Result:**
- 404 Not Found (BelongsToChurch scope filters the query, making it appear non-existent)
- No data from Church A is exposed

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SEC-004 — CSRF Protection

**Purpose:** Confirm POST/PUT/DELETE requests without CSRF tokens are rejected.

**Steps:**
1. Open browser DevTools → Network tab
2. Find a POST request (e.g. creating a task)
3. Copy the request, remove the `_token` / `X-XSRF-TOKEN` header
4. Replay via `curl` or fetch

**Expected Result:**
- 419 Page Expired (CSRF mismatch)
- No action is performed

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SEC-005 — File Download Authorization

**Purpose:** Confirm private files require authentication to download.

**Preconditions:** A file exists uploaded with `is_public = false`.

**Steps:**
1. Note the file ID
2. Log out
3. Navigate to `/dashboard/files/{id}/download`

**Expected Result:**
- Redirected to `/login` (not logged in)
- Even with a direct storage URL, the file is stored in the `local` disk (non-public) for private files

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SEC-006 — SQL Injection Prevention

**Purpose:** Verify search inputs are parameterised.

**Steps:**
1. Open global search or the members search
2. Type: `'; DROP TABLE users; --`

**Expected Result:**
- No database error
- 0 search results returned safely
- `users` table remains intact after the query

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SEC-007 — XSS Prevention in User Content

**Purpose:** Confirm user-generated content is escaped/sanitised.

**Steps:**
1. Create an announcement with body: `<script>alert('XSS')</script>`
2. View the announcement on the show page

**Expected Result:**
- Script tag is NOT executed
- The raw text `<script>alert('XSS')</script>` is displayed escaped, OR the sanitiser strips the tag
- No alert popup appears

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SEC-008 — Mass Assignment Protection

**Purpose:** Confirm the Sermon/Task/etc. models reject unexpected fields.

**Steps:**
1. Make a POST request to `/dashboard/tasks` with an extra field:
   ```json
   { "title": "Test", "church_id": 999, "status": "completed", "_is_admin": true }
   ```
2. Check the created record in the DB

**Expected Result:**
- `church_id` is set to the authenticated user's church_id, NOT 999
- `_is_admin` field is ignored entirely
- `status` may be overridden by the controller to `pending`

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 19. Real-Time Infrastructure Tests

---

## RT-001 — WebSocket Server Starts

**Purpose:** Confirm Laravel Reverb starts and accepts connections.

**Preconditions:** `.env` has `BROADCAST_CONNECTION=reverb` and `REVERB_*` keys set.

**Steps:**
1. Run `php artisan reverb:start`
2. Look for "Starting Reverb server on 0.0.0.0:8080..." or similar

**Expected Result:**
- Server starts without errors
- Connection at `ws://localhost:8080` is accepted

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## RT-002 — Real-Time Notification Delivery

**Purpose:** Confirm notifications arrive without page refresh.

**Preconditions:** Reverb is running. Both admin and member are logged in on separate tabs/browsers.

**Steps:**
1. Member tab: notification bell shows "0"
2. Admin tab: assign a task to the member
3. Member tab: watch the bell badge without refreshing

**Expected Result:**
- Bell badge increments to "1" within 2-3 seconds
- No page reload required

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## RT-003 — Real-Time Notification Dropdown Prepend

**Purpose:** Confirm new notifications appear in the dropdown without reload.

**Preconditions:** Reverb running. Member is on the notifications page.

**Steps:**
1. Member opens the notification dropdown in the header
2. Admin sends an announcement targeted at all members
3. Member watches the dropdown

**Expected Result:**
- New notification appears at the top of the dropdown
- "Announcement published" notification is visible without refresh

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## RT-004 — Real-Time Attendance Updates

**Purpose:** Confirm live attendance stats update across tabs.

**Preconditions:** Reverb running. Active attendance session. Two admin tabs open on the session show page.

**Steps:**
1. Admin Tab A: mark 3 members as "present", click Save
2. Admin Tab B: watch the stats (present count, rate) without refreshing

**Expected Result:**
- Stats in Tab B update to reflect the new present count
- `AttendanceSessionUpdated` broadcast is received and processed

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

# 20. Error Handling Tests

---

## ERR-001 — 404 for Non-Existent Resource

**Purpose:** Confirm accessing a deleted/non-existent resource returns 404.

**Steps:**
1. Navigate to `/dashboard/tasks/99999` (non-existent ID)

**Expected Result:**
- 404 Not Found page rendered
- No 500 Internal Server Error
- Inertia renders a styled 404 page

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## ERR-002 — 403 for Unauthorised Action

**Purpose:** Confirm forbidden access returns a proper 403 page.

**Steps:**
1. Log in as member
2. Navigate to `/dashboard/settings`

**Expected Result:**
- 403 Forbidden page
- Not a 500 or a blank page

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## ERR-003 — Validation Errors Display

**Purpose:** Confirm form validation errors are shown inline.

**Preconditions:** Logged in as admin.

**Steps:**
1. Navigate to `/dashboard/events/create`
2. Leave **Title** empty and submit

**Expected Result:**
- Validation error "The title field is required." appears next to the Title field
- Form does NOT submit
- Other fields retain their entered values

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## ERR-004 — Soft-Deleted Resource Not Accessible

**Purpose:** Confirm accessing a soft-deleted record returns 404 (not 500).

**Steps:**
1. Create and then delete a task (which soft-deletes it)
2. Note the task's ID
3. Navigate to `/dashboard/tasks/{deleted_id}`

**Expected Result:**
- 404 Not Found (global scope with `withTrashed` not applied in show route)
- No server error

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## ERR-005 — Invalid File Download

**Purpose:** Confirm downloading a non-existent file returns 404.

**Steps:**
1. Navigate to `/dashboard/files/99999/download`

**Expected Result:**
- 404 Not Found
- No 500 error

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## ERR-006 — Expired Password Reset Token

**Purpose:** Confirm expired reset tokens are rejected.

**Preconditions:** A password reset token that is > 60 minutes old.

**Steps:**
1. Navigate to `/reset-password/{old_expired_token}`
2. Submit a new password

**Expected Result:**
- Error: "This password reset token is invalid."
- Password is NOT changed

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

---

# 24. Regression Tests

> These tests guard against previously identified and fixed bugs. Run them after any change to the affected modules.

---

## REG-001 — Position Library Reachable via Sidebar Nav

**Regression for:** Missing nav link — `/dashboard/scheduling/positions` had no sidebar entry, making the entire position workflow unreachable.

**Steps:**
1. Log in as admin
2. Look for "Position Library" in the Work group of the sidebar
3. Click it

**Expected Result:**
- "Position Library" item IS present (was absent before fix)
- Navigates to `/dashboard/scheduling/positions` (HTTP 200)
- Page renders with position list or empty state

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## REG-002 — My Schedule Reachable via Sidebar Nav

**Regression for:** Missing nav link — `/dashboard/scheduling/my-schedule` had no sidebar entry.

**Steps:**
1. Log in as any authenticated user (member or admin)
2. Look for "My Schedule" in the Work group of the sidebar
3. Click it

**Expected Result:**
- "My Schedule" item IS present (was absent before fix)
- Navigates to `/dashboard/scheduling/my-schedule` (HTTP 200)
- Page renders with the volunteer's assignment list (or empty state)

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## REG-003 — Plan Detail Page Loads When Position Has No Department

**Regression for:** `ServicePlanController::show()` crashed with `Call to member function id() on null` when a `ServingPosition`'s department was soft-deleted.

**Preconditions:** A service plan exists that contains a position whose department has been soft-deleted (or set to null). Logged in as admin.

**Steps:**
1. In tinker, soft-delete the department of a serving position that is on a plan:
   ```php
   App\Models\Department::find($deptId)->delete();
   ```
2. Navigate to `/dashboard/scheduling/plans/{id}`

**Expected Result:**
- Page loads without a 500 error
- The position with the null department is silently excluded from the "Add Position" dropdown
- Other positions on the plan still display correctly

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## REG-004 — Empty Position Library Shows Contextual Link in Add Modal

**Regression for:** When no positions existed, the "Add Position" modal showed an empty `<select>` with no explanation, leaving admins confused.

**Preconditions:** The position library is empty (delete all serving positions if needed). A draft plan exists. Logged in as admin.

**Steps:**
1. Navigate to a draft plan's show page
2. Click **+ Add Position**

**Expected Result:**
- Modal opens showing: "No positions in the library yet."
- A link "Create positions first →" links to `/dashboard/scheduling/positions`
- No empty dropdown rendered
- Close button available

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## REG-005 — Coordinator Audit Scope Uses Department Metadata

**Regression for:** Coordinator audit scoping originally used a raw `json_extract()` query; needed to work on both SQLite (tests) and MySQL (production).

**Preconditions:** Coordinator is a member of "Media Team." Two audit events exist: one tagged `metadata.department_id = <media_team_id>`, one with no metadata (e.g. `auth.login`). Logged in as coordinator.

**Steps:**
1. Navigate to `/dashboard/audit`

**Expected Result:**
- Exactly 1 event visible (the department-tagged one)
- `auth.login` event (no department) is NOT visible
- No SQL error on either SQLite or MySQL

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## REG-006 — Audit Date-Range Filter Works

**Regression for:** `AuditLog.created_at` was not in `$fillable`, causing `update(['created_at' => ...])` to be silently ignored in tests — date filter appeared to not work.

**Preconditions:** Logged in as admin. Two audit events exist: one from 10 days ago, one from today.

**Steps:**
1. Navigate to `/dashboard/audit`
2. Set Date From = yesterday's date
3. Apply filter

**Expected Result:**
- Only today's event is shown (the 10-day-old one is excluded)
- No empty results when today does have events

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## REG-007 — Login Failure Is Logged

**Regression for:** `auth.login.failed` audit event was added to `AuthService` to capture failed login attempts.

**Steps:**
1. Navigate to `/login`
2. Enter a valid email with wrong password and click **Sign in**
3. Log in successfully as admin
4. Navigate to `/dashboard/audit` and filter by module = "auth"

**Expected Result:**
- An `auth.login.failed` event appears with `metadata.email` set to the attempted email
- The failed attempt is recorded before the successful login entry
- No crash — audit failure never blocks the login response

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## REG-008 — Announcement Unpublish Is Audited

**Regression for:** `announcement.unpublished` action was added to handle toggling published state back to draft.

**Preconditions:** A published announcement exists. Logged in as admin.

**Steps:**
1. Navigate to the published announcement show page
2. Click **Unpublish** (or equivalent toggle)
3. Navigate to `/dashboard/audit`

**Expected Result:**
- `announcement.unpublished` event appears in the audit log
- Event has the announcement as `target_name`
- Announcement status reverts to Draft

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

---

# 25. Super Admin Panel Tests

> **Precondition for all SA tests:** A user with the `super_admin` role exists (no `church_id`). Assign via tinker:
> ```php
> $u = App\Models\User::create(['name' => 'Platform Admin', 'email' => 'superadmin@platform.com', 'password' => bcrypt('password'), 'church_id' => null]);
> $u->assignRole('super_admin');
> ```

---

## SA-001 — Super Admin Panel Accessible to Super Admin

**Purpose:** Confirm the `/super-admin` area loads for a user with the `super_admin` role.

**Preconditions:** Logged in as super admin.

**Steps:**
1. Navigate to `http://localhost:8000/super-admin`

**Expected Result:**
- HTTP 200 — page loads
- Uses the Platform Admin shell (slate-900 header, "Platform Admin" brand, no church branding)
- "All Churches" heading with total church count
- "Create Church" button top-right
- Table shows all registered churches

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SA-002 — Regular User Blocked from Super Admin Panel

**Purpose:** Confirm non-super-admin users cannot access `/super-admin`.

**Preconditions:** Logged in as regular church admin or member.

**Steps:**
1. Navigate to `http://localhost:8000/super-admin`

**Expected Result:**
- 403 Forbidden — user does not have `super_admin` role
- No church list data visible

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SA-003 — Church List Shows All Churches with User Count

**Purpose:** Confirm the church table shows all tenants with correct member counts.

**Preconditions:** Logged in as super admin. At least 2 churches exist in the database.

**Steps:**
1. Navigate to `/super-admin`
2. Count the rows in the table
3. Compare with `select count(*) from churches` in DB

**Expected Result:**
- All churches appear (no tenant scoping — super admin sees everything)
- "Members" column shows the correct `users_count` for each church
- Plan, Status (Active/Suspended), and Created columns visible
- Pagination appears if > 20 churches

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SA-004 — Create Church Form Loads

**Purpose:** Confirm the church creation form renders with required props.

**Preconditions:** Logged in as super admin.

**Steps:**
1. Navigate to `/super-admin/churches/create` (or click **Create Church** button)

**Expected Result:**
- Page loads with "Create Church" heading
- "Church Details" section: Church Name (required), Tagline (optional), Timezone (select), Denomination (select)
- "Admin Account" section: Full Name (required), Email (required), Password (required)
- Create Church + Cancel buttons visible
- Uses Platform Admin shell layout (not DashboardLayout)

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SA-005 — Create Church (Happy Path)

**Purpose:** Confirm a super admin can provision a new church workspace.

**Preconditions:** Logged in as super admin.

**Steps:**
1. Navigate to `/super-admin/churches/create`
2. Fill in: Church Name = `Hope Community Church`, Admin Name = `Pastor Hope`, Admin Email = `hope@hopecc.org`, Password = `password123`, Timezone = `UTC`
3. Click **Create Church**

**Expected Result:**
- Redirected to `/super-admin` with a success flash toast
- "Hope Community Church" appears in the church list
- `pastor@hopecc.org` user exists in `users` table with correct `church_id`
- Super admin remains logged in (not replaced by the new church admin session)

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SA-006 — Suspend a Church

**Purpose:** Confirm the Suspend action flips `is_active` to false.

**Preconditions:** An active church exists. Logged in as super admin.

**Steps:**
1. Navigate to `/super-admin`
2. Find an active church (Status badge = "Active")
3. Click the **Suspend** button on that row

**Expected Result:**
- Page refreshes (or instant update) — Status badge changes to "Suspended" (rose colour)
- Button changes to **Reactivate**
- `churches.is_active = 0` in the database
- Success toast: "Church suspended successfully."

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SA-007 — Reactivate a Church

**Purpose:** Confirm the Reactivate action flips `is_active` back to true.

**Preconditions:** A suspended church exists (from SA-006 or seeded). Logged in as super admin.

**Steps:**
1. Navigate to `/super-admin`
2. Find a suspended church (Status badge = "Suspended")
3. Click the **Reactivate** button on that row

**Expected Result:**
- Status badge changes to "Active" (emerald colour)
- Button changes back to **Suspend**
- `churches.is_active = 1` in the database
- Success toast: "Church reactivated successfully."

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SA-008 — Impersonate a Church (Start)

**Purpose:** Confirm clicking "Switch to" enters impersonation mode and shows the amber banner.

**Preconditions:** At least one church with members exists. Logged in as super admin.

**Steps:**
1. Navigate to `/super-admin`
2. Click the **Switch to** button on any church row
3. Observe the redirect destination and banner

**Expected Result:**
- Redirected to `/dashboard`
- An amber banner appears at the very top of the page: "⚠ Viewing as **[Church Name]**"
- A **Stop Impersonating** link is visible in the banner
- Dashboard content reflects the impersonated church's data (members, announcements, etc.)
- `session('super_admin_impersonating')` is set to the church's ID

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SA-009 — Impersonate a Church (Stop)

**Purpose:** Confirm clicking "Stop Impersonating" exits impersonation mode.

**Preconditions:** Super admin is currently impersonating a church (amber banner visible).

**Steps:**
1. Click **Stop Impersonating** in the amber banner

**Expected Result:**
- Redirected to `/super-admin`
- Amber banner is gone
- `session('super_admin_impersonating')` is cleared (null)
- Church list loads normally without any impersonation context

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

## SA-010 — Super Admin Not Logged Out When Creating Church

**Purpose:** Confirm the `$shouldLogin: false` flag prevents session replacement.

**Preconditions:** Logged in as super admin.

**Steps:**
1. Create a new church via `/super-admin/churches/create` (SA-005)
2. After the redirect to `/super-admin`, note the logged-in user in the top-right of the Platform Admin header

**Expected Result:**
- Still logged in as the super admin (not the newly created church admin)
- `auth()->id()` matches the super admin's user ID, not the new church admin
- No unexpected session termination or redirect to `/login`

**Actual Result:** _______________________________________________

**Status:** - [ ] Pass  - [ ] Fail  - [ ] Needs Review

---

---

---

# Final Acceptance Checklist

For each system, mark the overall status after completing all associated tests.

---

## System Readiness

| # | System | Status | Notes |
|---|--------|--------|-------|
| 1 | **Authentication** (AUTH-001 → AUTH-010) | ⬜ Not Tested | |
| 2 | **Dashboard** (DASH-001 → DASH-009) | ⬜ Not Tested | |
| 3 | **Departments** (DEPT-001 → DEPT-010) | ⬜ Not Tested | |
| 4 | **Members** (MBR-001 → MBR-005) | ⬜ Not Tested | |
| 5 | **Announcements** (ANN-001 → ANN-008) | ⬜ Not Tested | |
| 6 | **Events** (EVT-001 → EVT-007) | ⬜ Not Tested | |
| 7 | **Tasks** (TASK-001 → TASK-008) | ⬜ Not Tested | |
| 8 | **Attendance** (ATT-001 → ATT-007) | ⬜ Not Tested | |
| 9 | **Media & Files** (MED-001 → MED-006) | ⬜ Not Tested | |
| 10 | **Notifications** (NOTIF-001 → NOTIF-006) | ⬜ Not Tested | |
| 11 | **Global Search** (SRCH-001 → SRCH-006) | ⬜ Not Tested | |
| 12 | **Sermons / YouTube** (SRM-001 → SRM-010) | ⬜ Not Tested | |
| 13 | **Volunteer Scheduling** (SCHED-001 → SCHED-022) | ⬜ Not Tested | *19 automated feature tests pass* |
| 14 | **Church Administration Centre** (SET-001 → SET-012) | ⬜ Not Tested | |
| 15 | **Reports** (RPT-001 → RPT-002) | ⬜ Not Tested | |
| 16 | **User Profile** (PROF-001 → PROF-004) | ⬜ Not Tested | |
| 17 | **Audit Log** (AUDIT-001 → AUDIT-010) | ⬜ Not Tested | *5 automated feature tests pass* |
| 18 | **Public Website** (PUB-001 → PUB-012) | ⬜ Not Tested | |
| 19 | **Multi-Tenant Isolation** (TNT-001 → TNT-007) | ⬜ Not Tested | |
| 20 | **RBAC & Permissions** (RBAC-001 → RBAC-006) | ⬜ Not Tested | |
| 21 | **Security** (SEC-001 → SEC-008) | ⬜ Not Tested | |
| 22 | **Real-Time Features** (RT-001 → RT-004) | ⬜ Not Tested | |
| 23 | **Error Handling** (ERR-001 → ERR-006) | ⬜ Not Tested | |
| 24 | **Regression Tests** (REG-001 → REG-008) | ⬜ Not Tested | *Guards fixed bugs* |
| 25 | **Super Admin Panel** (SA-001 → SA-010) | ⬜ Not Tested | *11 automated feature tests pass* |

---

## Known Deferred / Untestable Items

> These features exist in the architecture but require external services or are not yet fully built.

| Feature | Reason Deferred |
|---------|----------------|
| Email verification | Requires email driver configuration |
| Real-time WebSocket (RT-002 → RT-004) | Requires `php artisan reverb:start` and properly configured `REVERB_*` env vars |
| YouTube sync (SRM-003) | Requires a valid `YOUTUBE_API_KEY` in `.env` |
| Password reset email (AUTH-007) | Requires `MAIL_MAILER` configuration or `log` driver + `storage/logs/laravel.log` inspection |
| QR self-check-in | Check-in token generated by attendance session; scan/landing page not yet built |
| Sermon upload/create form | Dashboard is read-only; YouTube channel sync is the primary ingestion path |
| SaaS billing | Not yet implemented |
| Donations section in settings (SET-001) | `/dashboard/settings/donations` is a read-only info page; no form to save yet |

---

## Bug Tracker Reference

> Use this table to track bugs found during testing.

| Test ID | Description | Severity | Status | Fixed In |
|---------|-------------|----------|--------|---------|
| | | | | |
| | | | | |
| | | | | |

**Severity legend:** `P1` = Blocking · `P2` = High · `P3` = Medium · `P4` = Low

---

## Test Run Summary

| Metric | Count |
|--------|-------|
| Total Manual Tests | 203 |
| Automated Feature Tests (CI) | 35 *(19 scheduling + 5 audit log + 11 super admin)* |
| Passed | |
| Failed | |
| Needs Review | |
| Not Tested | |
| **Pass Rate** | **— %** |

---

## ✅ Ready for Production?

> Complete all 193 manual tests and fix all P1/P2 bugs before checking these off.

**Module sign-off:**
- [ ] All AUTH tests passed (AUTH-001 → AUTH-010)
- [ ] All DASH tests passed (DASH-001 → DASH-009)
- [ ] All DEPT tests passed (DEPT-001 → DEPT-010)
- [ ] All MBR tests passed (MBR-001 → MBR-005)
- [ ] All ANN tests passed (ANN-001 → ANN-008)
- [ ] All EVT tests passed (EVT-001 → EVT-007)
- [ ] All TASK tests passed (TASK-001 → TASK-008)
- [ ] All ATT tests passed (ATT-001 → ATT-007)
- [ ] All MED tests passed (MED-001 → MED-006)
- [ ] All NOTIF tests passed (NOTIF-001 → NOTIF-006)
- [ ] All SRCH tests passed (SRCH-001 → SRCH-006)
- [ ] All SRM tests passed (SRM-001 → SRM-010)
- [ ] All SCHED tests passed (SCHED-001 → SCHED-022)
- [ ] All SET tests passed (SET-001 → SET-012)
- [ ] All RPT tests passed (RPT-001 → RPT-002)
- [ ] All PROF tests passed (PROF-001 → PROF-004)
- [ ] All AUDIT tests passed (AUDIT-001 → AUDIT-010)
- [ ] All PUB tests passed (PUB-001 → PUB-012)
- [ ] All TNT tests passed (TNT-001 → TNT-007)
- [ ] All RBAC tests passed (RBAC-001 → RBAC-006)
- [ ] All SEC tests passed (SEC-001 → SEC-008)
- [ ] All RT tests passed (RT-001 → RT-004)
- [ ] All ERR tests passed (ERR-001 → ERR-006)
- [ ] All REG regression tests passed (REG-001 → REG-008)
- [ ] All SA super admin panel tests passed (SA-001 → SA-010)

**Bug gate:**
- [ ] Zero P1 (blocking) bugs open
- [ ] Zero P2 (high) bugs open

**CI gate:**
- [ ] `php artisan test` passes with zero failures (35 automated tests)
- [ ] `npm run build` passes with zero errors or type errors

**Infrastructure gate:**
- [ ] `.env` production values confirmed: `APP_KEY`, `APP_URL`, `DB_*`, `MAIL_*`, `REVERB_*`, `YOUTUBE_API_KEY`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache` run successfully
- [ ] Storage symlink created: `php artisan storage:link`
- [ ] Queue worker running: `php artisan queue:work --daemon`
- [ ] Scheduler configured in production cron: `* * * * * php /path/to/artisan schedule:run`
- [ ] `php artisan reverb:start` running (for real-time features)

---

*Testing document for Grace Community Church SaaS Platform — v2.1*  
*Originally generated: 2026-05-29 · Last updated: 2026-06-08*  
*Covers: Authentication, Dashboard, Departments, Members, Announcements, Events, Tasks, Attendance, Media & Files, Notifications, Global Search, Sermons/YouTube, **Volunteer Scheduling**, Church Administration Centre, Reports, **User Profile**, **Audit Log**, Public Website, Multi-Tenant, RBAC, Security, Real-Time, Error Handling, **Regression**, **Super Admin Panel***
