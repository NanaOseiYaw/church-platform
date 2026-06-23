# Layout & Navigation Overhaul — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the dashboard navigation from an overwhelming 21-item sidebar into a clean, scannable 16-item structure, add a mobile-first bottom tab bar, and eliminate the double-sidebar problem in the Settings section.

**Architecture:** Three targeted edits to two layout files — no page files are touched. Task 1 and Task 2 both modify `DashboardLayout.vue`; execute them in order since Task 2 adds to what Task 1 establishes. Task 3 is fully independent (Settings only). All changes are backward-compatible: no routes, controllers, or page components change.

**Tech Stack:** Vue 3 + TypeScript, Tailwind CSS v4, Lucide Vue Next, Inertia.js `<Link>`. No test runner — verify via `npm run type-check` + browser.

---

> **Context — Plan 2 of 4 in the UX Overhaul:**
> - Plan 1 ✅ Design System Foundation (completed)
> - **Plan 2 (this):** Layout & Navigation
> - Plan 3: Dashboard Redesign
> - Plan 4: Module Pages

---

## File Map

**Modified:**
- `resources/js/Layouts/DashboardLayout.vue` — Tasks 1 & 2: nav cleanup + mobile bottom tab bar
- `resources/js/Layouts/SettingsLayout.vue` — Task 3: remove inner desktop sidebar, expose tab strip on all screen sizes

---

## Current State Reference

Before writing any code, understand what exists:

**DashboardLayout.vue nav items (current — 21 total):**
```
Overview (1):   Dashboard
Ministry (5):   Events, Announcements, Sermons, [Add Sermon], [Sermon Series]
Work (6):       Tasks, Scheduling, [Position Library], My Schedule, Attendance, Media
People (2):     Members, Departments
Communication(4): [Dashboard], Broadcasts, Templates, Audiences
Administration(3): Reports, Settings, Audit Log
```
Items in `[brackets]` are removed in Task 1.

**SettingsLayout.vue layout (current):**
- Mobile: horizontal pill-tab strip (hidden on desktop via `lg:hidden`)
- Desktop: 240px left sidebar with grouped nav items (hidden on mobile via `hidden lg:flex`)
- Problem: DashboardLayout already has a sidebar → users see two sidebars on desktop

---

## Task 1: DashboardLayout — Slim sidebar navigation

**Files:**
- Modify: `resources/js/Layouts/DashboardLayout.vue`

Removes 5 nav items that are sub-actions (not primary destinations) and 4 unused lucide imports. After this task the nav drops from 21 to 16 items with no orphaned imports.

**Items removed and where users access them instead:**
- "Add Sermon" → CTA button on Sermons/Index page (Plan 4)
- "Sermon Series" → tab on Sermons/Index page (Plan 4)
- "Position Library" → link from Scheduling/Dashboard page (already has quick-actions strip)
- "Communication › Dashboard" → redundant since Broadcasts is the natural landing

- [ ] **Step 1: Read the current file to confirm current state**

```
File: resources/js/Layouts/DashboardLayout.vue
Confirm these lines exist before editing:
  Line 22: Layers, BookOpen, PlusCircle,
  Line 23: Radio, Send, FileText,
  Line 88: { label: 'Add Sermon', ... icon: PlusCircle ... }
  Line 89: { label: 'Sermon Series', ... icon: BookOpen ... }
  Line 98: { label: 'Position Library', ... icon: Layers ... }
  Line 114: { label: 'Dashboard', ... icon: Radio ... exact: true },
```

- [ ] **Step 2: Remove the 5 nav items from navGroups**

In the `navGroups` computed, make these four targeted removals:

**Ministry group** — remove lines 88–89 (the two sub-items after Sermons). The Ministry group goes from 5 items to 3:

```ts
{
    label: 'Ministry',
    items: [
        { label: 'Events',        href: '/dashboard/events',        icon: CalendarDays, perm: 'events.view' },
        { label: 'Announcements', href: '/dashboard/announcements', icon: Megaphone,    perm: 'announcements.view',
          badge: () => auth.unreadAnnouncements > 0 ? auth.unreadAnnouncements : null },
        { label: 'Sermons',       href: '/dashboard/sermons',       icon: Mic2,         perm: 'sermons.view' },
    ],
},
```

**Work group** — remove the "Position Library" item. The Work group goes from 6 items to 5:

```ts
{
    label: 'Work',
    items: [
        { label: 'Tasks',      href: '/dashboard/tasks',                    icon: CheckSquare,   perm: 'tasks.view_own',
          badge: () => auth.overdueTasksCount > 0 ? auth.overdueTasksCount : null },
        { label: 'Scheduling', href: '/dashboard/scheduling',               icon: ClipboardList, perm: 'scheduling.manage' },
        { label: 'My Schedule',href: '/dashboard/scheduling/my-schedule',   icon: CalendarDays,  perm: 'scheduling.view', exact: true },
        { label: 'Attendance', href: '/dashboard/attendance',               icon: CalendarCheck2, perm: 'attendance.view' },
        { label: 'Media',      href: '/dashboard/media',                    icon: Upload,        perm: 'media.view' },
    ],
},
```

**Communication group** — remove the "Dashboard" item. The group goes from 4 items to 3:

```ts
{
    label: 'Communication',
    items: [
        { label: 'Broadcasts', href: '/dashboard/communication/broadcasts', icon: Send,     perm: 'communication.view' },
        { label: 'Templates',  href: '/dashboard/communication/templates',  icon: FileText, perm: 'communication.view' },
        { label: 'Audiences',  href: '/dashboard/communication/audiences',  icon: Users,    perm: 'communication.view' },
    ],
},
```

- [ ] **Step 3: Remove unused lucide imports**

Change the lucide import block (lines 17–24) from:

```ts
import {
    LayoutDashboard, CalendarDays, Megaphone, CheckSquare, Mic2,
    Users, Building2, BarChart3, Settings, LogOut,
    Menu, X, ChevronRight, ExternalLink, Upload, ChevronDown,
    Shield, Hash, CalendarCheck2, Search, Command, UserCog, ClipboardList, ShieldCheck,
    Layers, BookOpen, PlusCircle,
    Radio, Send, FileText,
} from 'lucide-vue-next'
```

to (remove `Layers`, `BookOpen`, `PlusCircle`, `Radio` — also remove the now-empty `Shield` and `Hash` if they are not used in the template; check first):

```ts
import {
    LayoutDashboard, CalendarDays, Megaphone, CheckSquare, Mic2,
    Users, Building2, BarChart3, Settings, LogOut,
    Menu, X, ChevronRight, ExternalLink, Upload, ChevronDown,
    CalendarCheck2, Search, Command, UserCog, ClipboardList, ShieldCheck,
    Send, FileText,
} from 'lucide-vue-next'
```

> **Note:** `Shield` and `Hash` were already unused in the original file (imported but never referenced in the template). Remove them too. Confirm by searching the template for `<Shield` and `<Hash` before removing.

- [ ] **Step 4: Run type-check**

```bash
npm run type-check
```

Expected: 0 new errors in DashboardLayout.vue. (Pre-existing errors in other files are fine.)

- [ ] **Step 5: Browser verify**

Start `npm run dev`, open `/dashboard`. Confirm:
- Sidebar shows 16 items total
- "Add Sermon", "Sermon Series", "Position Library", and "Communication › Dashboard" are gone
- All remaining items still link correctly
- No TypeScript import errors in browser console

---

## Task 2: DashboardLayout — Mobile bottom tab bar

**Files:**
- Modify: `resources/js/Layouts/DashboardLayout.vue`

Adds a fixed 5-tab bottom navigation bar on mobile (`lg:hidden`). The "More" tab opens the existing full sidebar, so all nav items remain reachable. The main content area gets bottom padding on mobile so content doesn't hide behind the fixed bar.

**The 5 tabs (left to right):**

| Tab | Icon | Href | Shown when |
|-----|------|------|------------|
| Home | LayoutDashboard | /dashboard | always |
| Events | CalendarDays | /dashboard/events | `auth.can('events.view')` |
| Tasks | CheckSquare | /dashboard/tasks | `auth.can('tasks.view_own')` |
| Members | Users | /dashboard/members | `auth.can('members.view')` |
| More | Menu | — (opens sidebar) | always |

The "More" tab calls `sidebarOpen = true`. The existing hamburger button in the top bar continues to work as a fallback.

- [ ] **Step 1: Add bottom tab bar to the template**

Inside the template, find the closing line of the root shell:

```html
    <!-- Global search modal (mounted once, open from anywhere via useSearchStore) -->
    <GlobalSearchModal />
</template>
```

Add the bottom tab bar **before** `<GlobalSearchModal />`:

```html
    <!-- ── Mobile bottom tab bar ─────────────────────────────────────────────── -->
    <nav class="fixed bottom-0 inset-x-0 z-30 lg:hidden bg-white border-t border-neutral-100 flex items-center justify-around px-1 pb-safe">
        <!-- Home -->
        <Link
            href="/dashboard"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0"
            :class="$page.url === '/dashboard' ? 'text-brand-600' : 'text-neutral-400'"
        >
            <LayoutDashboard class="w-5 h-5 shrink-0" />
            <span class="text-[10px] font-medium leading-none">Home</span>
        </Link>

        <!-- Events -->
        <Link
            v-if="auth.can('events.view')"
            href="/dashboard/events"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0"
            :class="$page.url.startsWith('/dashboard/events') ? 'text-brand-600' : 'text-neutral-400'"
        >
            <CalendarDays class="w-5 h-5 shrink-0" />
            <span class="text-[10px] font-medium leading-none">Events</span>
        </Link>

        <!-- Tasks -->
        <Link
            v-if="auth.can('tasks.view_own')"
            href="/dashboard/tasks"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0 relative"
            :class="$page.url.startsWith('/dashboard/tasks') ? 'text-brand-600' : 'text-neutral-400'"
        >
            <div class="relative">
                <CheckSquare class="w-5 h-5 shrink-0" />
                <span
                    v-if="auth.overdueTasksCount > 0"
                    class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white text-[8px] font-bold rounded-full flex items-center justify-center"
                >
                    {{ auth.overdueTasksCount > 9 ? '9+' : auth.overdueTasksCount }}
                </span>
            </div>
            <span class="text-[10px] font-medium leading-none">Tasks</span>
        </Link>

        <!-- Members -->
        <Link
            v-if="auth.can('members.view')"
            href="/dashboard/members"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0"
            :class="$page.url.startsWith('/dashboard/members') ? 'text-brand-600' : 'text-neutral-400'"
        >
            <Users class="w-5 h-5 shrink-0" />
            <span class="text-[10px] font-medium leading-none">People</span>
        </Link>

        <!-- More (opens full sidebar) -->
        <button
            type="button"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0 text-neutral-400"
            @click="sidebarOpen = true"
        >
            <Menu class="w-5 h-5 shrink-0" />
            <span class="text-[10px] font-medium leading-none">More</span>
        </button>
    </nav>
```

- [ ] **Step 2: Add bottom padding to main content on mobile**

Find the main content element:

```html
<main class="flex-1 overflow-y-auto p-4 lg:p-6">
```

Change to:

```html
<main class="flex-1 overflow-y-auto p-4 pb-20 lg:p-6 lg:pb-6">
```

The `pb-20` (80px) creates clearance for the fixed 64px bottom bar plus breathing room on mobile. `lg:pb-6` resets to normal on desktop where the bar is hidden.

- [ ] **Step 3: Add `usePage` import**

The bottom bar uses `$page.url` to detect the active tab. `usePage` is already available via `@inertiajs/vue3`. Check that `usePage` is imported at the top of the script:

Find:
```ts
import { Head, Link, router } from '@inertiajs/vue3'
```

If `usePage` is not there, add it:
```ts
import { Head, Link, router, usePage } from '@inertiajs/vue3'
```

Then add after the other store setup (e.g., after `const search = useSearchStore()`):
```ts
const page = usePage()
```

> **Note:** If `usePage` is already imported (e.g., from a previous change), skip this step.

Then update the bottom bar to use `page.url` instead of `$page.url` since we're in `<script setup>` context:

Replace all `$page.url` in the bottom bar template with `page.url`.

- [ ] **Step 4: Run type-check**

```bash
npm run type-check
```

Expected: 0 new errors in DashboardLayout.vue.

- [ ] **Step 5: Browser verify**

Resize browser to mobile width (< 1024px). Confirm:
- Bottom tab bar appears with icons + labels
- Active tab is highlighted in brand color (matching current URL)
- "More" button opens the full sidebar slide-over
- Task badge shows red dot when overdue tasks exist
- On desktop (>= 1024px): bar is completely hidden

---

## Task 3: SettingsLayout — Remove inner desktop sidebar

**Files:**
- Modify: `resources/js/Layouts/SettingsLayout.vue`

**Problem:** Settings currently renders a second sidebar inside the DashboardLayout — creating a confusing double-sidebar experience on desktop. The mobile tab strip is already good; it just needs to show on all screen sizes.

**Solution:**
1. Remove `lg:hidden` from the existing tab strip → it shows on all sizes
2. Delete the desktop `<aside>` and the `<div class="hidden lg:block w-px ...">` divider
3. Simplify the outer flex container to a plain `<div>`
4. Style the tab strip as an underline-style nav (more modern than pills)

- [ ] **Step 1: Read the current SettingsLayout.vue**

```
File: resources/js/Layouts/SettingsLayout.vue
Confirm:
  - Mobile strip: class starts with "lg:hidden overflow-x-auto..."
  - Desktop aside: class starts with "hidden lg:flex flex-col w-56..."
  - Divider: class "hidden lg:block w-px bg-neutral-100 mx-6..."
  - Outer wrapper: class "flex items-start gap-0"
```

- [ ] **Step 2: Replace the entire template block**

The `<script setup>` section is unchanged. Only replace the `<template>` section with:

```vue
<template>
    <DashboardLayout
        :title="`Administration — ${current?.label ?? 'Settings'}`"
        :breadcrumbs="breadcrumbs"
    >
        <!-- ── Settings tab navigation — shows on all screen sizes ─────────────── -->
        <!-- Underline-style tabs with horizontal scroll for 21 items across 5 groups -->
        <div class="overflow-x-auto scrollbar-none -mx-4 sm:-mx-6 px-4 sm:px-6 mb-6">
            <div class="flex border-b border-neutral-100 w-max min-w-full">
                <template v-for="(group, gi) in groups" :key="group.label">
                    <!-- Group separator (except before first group) -->
                    <div
                        v-if="gi > 0"
                        class="w-px bg-neutral-100 my-2 mx-1 shrink-0"
                    />
                    <Link
                        v-for="item in group.items"
                        :key="item.key"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 -mb-px transition-all duration-150 shrink-0',
                            item.key === section
                                ? 'border-brand-600 text-brand-700'
                                : 'border-transparent text-neutral-500 hover:text-neutral-800 hover:border-neutral-200',
                        ]"
                    >
                        <component :is="item.icon" class="w-3.5 h-3.5 shrink-0" />
                        <span class="hidden sm:inline">{{ item.label }}</span>
                        <span class="sm:hidden">{{ item.label.split(' ')[0] }}</span>
                    </Link>
                </template>
            </div>
        </div>

        <!-- ── Page content ─────────────────────────────────────────────────────── -->
        <div>
            <slot />
        </div>
    </DashboardLayout>
</template>
```

> **What changed vs the old template:**
> - Removed `lg:hidden` — tab strip now shows on desktop too
> - Changed pill style → underline tab style (`border-b-2`, `-mb-px`) — matches the tab nav convention used by Linear, GitHub, Stripe
> - Added group separator dividers (vertical lines between the 5 groups so 21 tabs are scannable)
> - On sm+ screens: full label text. On xs (very small mobile): shows first word of label only to prevent wrapping
> - Removed the `<aside>` sidebar block entirely
> - Removed the `<div class="hidden lg:block w-px...">` divider
> - Outer wrapper simplified from `<div class="flex items-start gap-0">` to `<div>`

- [ ] **Step 3: Run type-check**

```bash
npm run type-check
```

Expected: 0 new errors in SettingsLayout.vue. The script section is unchanged so no type issues should arise.

- [ ] **Step 4: Browser verify**

Open `/dashboard/settings/branding`. Confirm:
- On desktop: horizontal underline tab navigation replaces the old sidebar
- Active tab is underlined in brand color
- Tab groups have thin vertical separators between them
- Horizontal scroll works for 21 items
- On mobile: same tab strip, same behavior
- No second sidebar visible anywhere on any screen size
- Click several settings tabs to confirm active state updates correctly

---

## Self-Review

**Spec coverage:**
- ✅ Navigation declutter: 5 items removed (Add Sermon, Sermon Series, Position Library, Communication Dashboard, plus icon cleanup)
- ✅ Navigation group structure preserved: same 6 groups, just fewer items
- ✅ Mobile navigation improved: bottom tab bar with 5 primary destinations
- ✅ Settings double-sidebar eliminated: inner sidebar removed, tab strip promoted to all sizes
- ✅ No business logic touched: zero route/controller/page changes

**Removed items are still accessible:**
- Add Sermon → `/dashboard/sermons/create` URL works directly (Plan 4 adds it as a page button)
- Sermon Series → `/dashboard/sermons/series` URL works directly (Plan 4 adds it as a tab)
- Position Library → `/dashboard/scheduling/positions` URL works directly (Scheduling Dashboard already has quick-actions)
- Communication Dashboard → `/dashboard/communication` URL works directly

**Placeholder scan:** No TBDs, no vague steps — every step has exact class strings, exact code blocks, exact commands.

**Type consistency:** `page.url`, `auth.can()`, `auth.overdueTasksCount` — all used exactly as defined in the existing DashboardLayout composables.
