# Member Directory & CRM Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing Member Directory with a `member_profiles` CRM layer, upgrade the directory index with department filtering and stats, and refactor the member profile page to a sidebar + tabs layout surfacing attendance history, tasks, and a unified activity timeline.

**Architecture:** A new `member_profiles` table holds all church-CRM data as a 1:1 optional extension of `User`; auth/roles are untouched. The controller aggregates attendance, tasks, and audit-log events into a flat `TimelineEntry[]` prop at query time — no stored timeline table. The frontend refactor replaces the existing two-panel Show layout with a persistent sidebar (profile fields always visible) and four tabs (Departments, Attendance, Tasks, Timeline).

**Tech Stack:** Laravel 11, PHP 8.2, Spatie Permissions, Inertia.js v2, Vue 3 `<script setup lang="ts">`, Tailwind CSS 3, Lucide Vue Next.

**Spec:** `docs/superpowers/specs/2026-06-01-member-directory-crm-design.md`

---

## File Map

### New files
| Path | Purpose |
|------|---------|
| `database/migrations/2026_06_01_000003_create_member_profiles_table.php` | Schema for `member_profiles` |
| `app/Models/MemberProfile.php` | Eloquent model + relationships + casts |
| `resources/js/Components/Members/MemberProfileForm.vue` | Reusable CRM profile edit form |
| `resources/js/Components/Members/MemberTimeline.vue` | Activity timeline feed component |

### Modified files
| Path | Change |
|------|--------|
| `app/Models/User.php` | Add `hasOne(MemberProfile::class)` |
| `app/Http/Resources/MemberResource.php` | Add `profile` key |
| `app/Http/Controllers/Dashboard/DepartmentController.php` | Add `AuditLog::record()` to 3 methods |
| `app/Http/Controllers/Dashboard/MembersController.php` | Expand `index()`/`show()`, add `updateProfile()` + `buildTimeline()` |
| `routes/web.php` | Add `PUT /dashboard/members/{user}/profile` |
| `resources/js/Pages/Dashboard/Members/Index.vue` | Dept filter dropdown + stats strip |
| `resources/js/Pages/Dashboard/Members/Show.vue` | Full refactor to sidebar + tabs |

---

## Task 1: Migration + MemberProfile model + User relationship

**Files:**
- Create: `database/migrations/2026_06_01_000003_create_member_profiles_table.php`
- Create: `app/Models/MemberProfile.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1.1 — Create the migration**

Create `database/migrations/2026_06_01_000003_create_member_profiles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();

            // Personal — self-editable
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();         // male|female|other|prefer_not_to_say
            $table->string('marital_status')->nullable(); // single|married|widowed|divorced
            $table->text('address')->nullable();

            // Emergency contact — self-editable
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // Church journey — admin-only
            $table->date('membership_date')->nullable();
            $table->date('baptism_date')->nullable();
            $table->date('salvation_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
```

- [ ] **Step 1.2 — Run the migration**

```bash
php artisan migrate
```

Expected output: `2026_06_01_000003_create_member_profiles_table ............. 200ms DONE`

- [ ] **Step 1.3 — Create the MemberProfile model**

Create `app/Models/MemberProfile.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProfile extends Model
{
    protected $fillable = [
        'user_id',
        'church_id',
        'date_of_birth',
        'gender',
        'marital_status',
        'address',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'membership_date',
        'baptism_date',
        'salvation_date',
    ];

    protected $casts = [
        'date_of_birth'   => 'date',
        'membership_date' => 'date',
        'baptism_date'    => 'date',
        'salvation_date'  => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
```

- [ ] **Step 1.4 — Add `hasOne` to User**

Open `app/Models/User.php`. Add the import at the top (after `use Illuminate\Database\Eloquent\Relations\HasMany;`):

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Add the relationship method after the `attendances()` method:

```php
public function profile(): HasOne
{
    return $this->hasOne(MemberProfile::class);
}
```

- [ ] **Step 1.5 — Verify model wiring in tinker**

```bash
php artisan tinker
```

```php
$u = \App\Models\User::first();
$u->profile; // null (no profile yet — correct)
\App\Models\MemberProfile::create([
    'user_id'   => $u->id,
    'church_id' => $u->church_id,
    'gender'    => 'male',
]);
$u->fresh()->profile->gender; // "male"
\App\Models\MemberProfile::where('user_id', $u->id)->delete();
exit
```

---

## Task 2: MemberResource — add `profile` key

**Files:**
- Modify: `app/Http/Resources/MemberResource.php`

- [ ] **Step 2.1 — Add the `profile` key to MemberResource**

Open `app/Http/Resources/MemberResource.php`. Inside `toArray()`, after the `'departments'` block and before the closing `];`, add:

```php
            // ── CRM profile (eager-loaded) ──────────────────────────────────
            'profile' => $this->whenLoaded('profile', fn () =>
                $this->profile ? [
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
                ] : null
            ),
```

- [ ] **Step 2.2 — Spot-check in tinker**

```bash
php artisan tinker
```

```php
$u = \App\Models\User::with('profile')->first();
$r = \App\Http\Resources\MemberResource::make($u)->toArray(request());
array_keys($r); // should include 'profile'
exit
```

---

## Task 3: DepartmentController — audit log writes

**Files:**
- Modify: `app/Http/Controllers/Dashboard/DepartmentController.php`

- [ ] **Step 3.1 — Add AuditLog import**

Open `app/Http/Controllers/Dashboard/DepartmentController.php`. Add after the existing `use App\Models\User;` line:

```php
use App\Models\AuditLog;
```

- [ ] **Step 3.2 — Write audit entry in `addMember()`**

In `addMember()`, after the `DepartmentMemberWasAdded::dispatch(...)` line and before `return back()...`, add:

```php
        AuditLog::record(
            churchId:  $department->church_id,
            userId:    $request->user()->id,
            action:    'member.added',
            newValues: [
                'department_id'   => $department->id,
                'department_name' => $department->name,
                'role'            => $validated['role'],
            ],
            request:   $request,
            modelType: User::class,
            modelId:   $user->id,
        );
```

- [ ] **Step 3.3 — Write audit entry in `updateMemberRole()`**

In `updateMemberRole()`, after `$this->departments->updateMemberRole($department, $user, $newRole);` and after `DepartmentMemberRoleWasChanged::dispatch(...)`, add:

```php
        AuditLog::record(
            churchId:  $department->church_id,
            userId:    $actor->id,
            action:    'member.role_updated',
            oldValues: ['role' => $targetPivotRole],
            newValues: [
                'department_id'   => $department->id,
                'department_name' => $department->name,
                'role'            => $newRole,
            ],
            request:   $request,
            modelType: User::class,
            modelId:   $user->id,
        );
```

- [ ] **Step 3.4 — Write audit entry in `removeMember()`**

Open `removeMember()`. Read it fully first to find where it ends (after the last guard block, it calls `$this->departments->removeMember(...)` and `return back()...`). Add after the `$this->departments->removeMember(...)` call:

```php
        AuditLog::record(
            churchId:  $department->church_id,
            userId:    auth()->id(),
            action:    'member.removed',
            oldValues: [
                'department_id'   => $department->id,
                'department_name' => $department->name,
                'role'            => $targetPivotRole,
            ],
            request:   request(),
            modelType: User::class,
            modelId:   $user->id,
        );
```

- [ ] **Step 3.5 — Verify no syntax errors**

```bash
php artisan route:list --path=dashboard/departments 2>&1 | head -5
```

Expected: routes listed without errors.

---

## Task 4: Route + `updateProfile()` method

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Dashboard/MembersController.php`

- [ ] **Step 4.1 — Add the route**

Open `routes/web.php`. Find the members route group:

```php
    Route::prefix('dashboard/members')->name('dashboard.members.')->group(function () {
        Route::get('/',       [MembersController::class, 'index'])->name('index');
        Route::get('/{user}', [MembersController::class, 'show']) ->name('show');
    });
```

Replace it with:

```php
    Route::prefix('dashboard/members')->name('dashboard.members.')->group(function () {
        Route::get('/',                [MembersController::class, 'index'])         ->name('index');
        Route::get('/{user}',          [MembersController::class, 'show'])          ->name('show');
        Route::put('/{user}/profile',  [MembersController::class, 'updateProfile']) ->name('profile.update');
    });
```

- [ ] **Step 4.2 — Add `updateProfile()` to MembersController**

Open `app/Http/Controllers/Dashboard/MembersController.php`. Add these imports at the top (if not already present):

```php
use App\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
```

Add the `updateProfile()` method after the `show()` method:

```php
    /** PUT /dashboard/members/{user}/profile */
    public function updateProfile(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        abort_unless($user->church_id === $this->resolvedChurchId(), 403);

        // Self-editable by anyone (themselves or an admin)
        $selfFields = [
            'date_of_birth', 'gender', 'marital_status', 'address',
            'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
        ];

        // Admin-only fields (church_admin or super_admin)
        $adminFields = ['membership_date', 'baptism_date', 'salvation_date'];

        if ($request->user()->id !== $user->id) {
            // Editing someone else's profile — must pass the policy
            $this->authorize('update', $user); // UserPolicy::update → members.edit
            $allowed = array_merge($selfFields, $adminFields);
        } else {
            // Editing own profile — self fields only
            $allowed = $selfFields;
        }

        $validated = $request->validate(array_filter([
            'date_of_birth'                  => in_array('date_of_birth', $allowed)                  ? ['nullable', 'date', 'before:today'] : null,
            'gender'                         => in_array('gender', $allowed)                         ? ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'] : null,
            'marital_status'                 => in_array('marital_status', $allowed)                 ? ['nullable', 'string', 'in:single,married,widowed,divorced'] : null,
            'address'                        => in_array('address', $allowed)                        ? ['nullable', 'string', 'max:500'] : null,
            'emergency_contact_name'         => in_array('emergency_contact_name', $allowed)         ? ['nullable', 'string', 'max:150'] : null,
            'emergency_contact_relationship' => in_array('emergency_contact_relationship', $allowed) ? ['nullable', 'string', 'max:100'] : null,
            'emergency_contact_phone'        => in_array('emergency_contact_phone', $allowed)        ? ['nullable', 'string', 'max:30'] : null,
            'membership_date'                => in_array('membership_date', $allowed)                ? ['nullable', 'date'] : null,
            'baptism_date'                   => in_array('baptism_date', $allowed)                   ? ['nullable', 'date'] : null,
            'salvation_date'                 => in_array('salvation_date', $allowed)                 ? ['nullable', 'date'] : null,
        ]));

        // Only save the fields that were both allowed and present in the request
        $data = array_intersect_key($validated, array_flip($allowed));

        MemberProfile::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($data, ['church_id' => $user->church_id]),
        );

        return back()->with('success', 'Profile updated.');
    }
```

- [ ] **Step 4.3 — Confirm route exists**

```bash
php artisan route:list --name=dashboard.members.profile.update
```

Expected: one row showing `PUT dashboard/members/{user}/profile`.

---

## Task 5: `MembersController::index()` — dept filter + stats

**Files:**
- Modify: `app/Http/Controllers/Dashboard/MembersController.php`

- [ ] **Step 5.1 — Add Department import**

Add after the existing `use App\Models\User;` import:

```php
use App\Models\Department;
```

- [ ] **Step 5.2 — Replace `index()` with the upgraded version**

Replace the entire `index()` method:

```php
    /** GET /dashboard/members */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $churchId = $this->resolvedChurchId();

        // ── Base query ─────────────────────────────────────────────────────────
        $query = User::where('church_id', $churchId)
            ->with('roles')
            ->withCount('departments')
            ->when($request->search, function ($q, $s) {
                $q->where(function ($q2) use ($s) {
                    $q2->where('name', 'like', "%{$s}%")
                       ->orWhere('email', 'like', "%{$s}%");
                });
            })
            ->when($request->role, function ($q, $r) {
                $q->whereHas('roles', fn ($q2) => $q2->where('name', $r));
            })
            ->when($request->dept_id, function ($q, $id) {
                $q->whereHas('departments', fn ($q2) => $q2->where('departments.id', $id));
            })
            ->orderBy('name');

        $members = $query->paginate(20)->withQueryString();

        // ── Stats (unfiltered totals for the church) ───────────────────────────
        $base = User::where('church_id', $churchId);

        $stats = [
            'total'        => (clone $base)->count(),
            'admins'       => (clone $base)->whereHas('roles', fn ($q) => $q->where('name', 'church_admin'))->count(),
            'coordinators' => (clone $base)->whereHas('roles', fn ($q) => $q->where('name', 'coordinator'))->count(),
            'members'      => (clone $base)->whereHas('roles', fn ($q) => $q->where('name', 'member'))->count(),
        ];

        // ── Departments dropdown ───────────────────────────────────────────────
        $departments = Department::where('church_id', $churchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color']);

        return Inertia::render('Dashboard/Members/Index', [
            'members'     => $members->through(fn ($u) => MemberResource::make($u)->toArray($request)),
            'filters'     => $request->only('search', 'role', 'dept_id'),
            'stats'       => $stats,
            'departments' => $departments,
        ]);
    }
```

- [ ] **Step 5.3 — Quick smoke test**

```bash
php artisan tinker
```

```php
// Simulate a request hit
$response = app()->handle(\Illuminate\Http\Request::create('/dashboard/members', 'GET'));
$response->getStatusCode(); // expect 302 (redirect to login) — proves route resolves
exit
```

---

## Task 6: `MembersController::show()` + `buildTimeline()`

**Files:**
- Modify: `app/Http/Controllers/Dashboard/MembersController.php`

- [ ] **Step 6.1 — Add missing imports**

Add these at the top of `MembersController.php` (after existing imports):

```php
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Task;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\TaskResource;
use Carbon\Carbon;
```

- [ ] **Step 6.2 — Replace `show()` with the expanded version**

Replace the entire `show()` method:

```php
    /** GET /dashboard/members/{user} */
    public function show(User $user): Response
    {
        $this->authorize('view', $user);
        abort_unless($user->church_id === $this->resolvedChurchId(), 403);

        $churchId = $this->resolvedChurchId();

        // ── Eager-load relations ───────────────────────────────────────────────
        $user->load([
            'roles',
            'departments',
            'departments.coordinator:id,name',
            'profile',
        ]);
        $user->departments->loadCount('members');

        // ── Attendance history ─────────────────────────────────────────────────
        $recentAttendances = Attendance::where('user_id', $user->id)
            ->where('church_id', $churchId)
            ->with('session:id,title,type,scheduled_at,status')
            ->latest('checked_in_at')
            ->limit(20)
            ->get();

        // ── Assigned tasks ─────────────────────────────────────────────────────
        $recentTasks = Task::where('assigned_to', $user->id)
            ->where('church_id', $churchId)
            ->latest('updated_at')
            ->limit(20)
            ->get();

        // ── Timeline ───────────────────────────────────────────────────────────
        $timeline = $this->buildTimeline($user, $churchId, $recentAttendances, $recentTasks);

        $actor = request()->user();

        return Inertia::render('Dashboard/Members/Show', [
            'member'              => MemberResource::make($user)->toArray(request()),
            'recentAttendances'   => $recentAttendances->map(fn ($a) => AttendanceResource::make($a)->toArray(request())),
            'recentTasks'         => $recentTasks->map(fn ($t) => TaskResource::make($t)->toArray(request())),
            'timeline'            => $timeline,
            'canEdit'             => $actor->can('update', $user),
            // Admin fields (baptism/salvation/membership dates) only editable
            // when an admin is viewing someone else's profile, not their own.
            'canEditAdminFields'  => $actor->can('update', $user) && $actor->id !== $user->id,
        ]);
    }
```

- [ ] **Step 6.3 — Add the private `buildTimeline()` helper**

Add this private method at the bottom of the `MembersController` class (before the closing `}`):

```php
    /**
     * Aggregate attendance, task, and audit-log events for a member into a
     * flat timeline array sorted newest-first, capped at 40 entries.
     */
    private function buildTimeline(
        User       $user,
        int        $churchId,
        \Illuminate\Support\Collection $attendances,
        \Illuminate\Support\Collection $tasks,
    ): array {
        $entries = [];

        // ── Attendance entries ─────────────────────────────────────────────────
        foreach ($attendances as $att) {
            $ts = $att->checked_in_at ?? $att->created_at;
            if (! $ts) {
                continue;
            }
            $entries[] = [
                'type'      => 'attendance',
                'label'     => 'Attended ' . ($att->session?->title ?? 'a session'),
                'meta'      => $att->session ? ucfirst($att->session->type) : null,
                'timestamp' => $ts->toISOString(),
                'formatted' => $ts->diffForHumans(),
            ];
        }

        // ── Task entries ───────────────────────────────────────────────────────
        foreach ($tasks as $task) {
            if ($task->created_at) {
                $entries[] = [
                    'type'      => 'task_assigned',
                    'label'     => 'Assigned: ' . $task->title,
                    'meta'      => ucfirst($task->priority) . ' priority',
                    'timestamp' => $task->created_at->toISOString(),
                    'formatted' => $task->created_at->diffForHumans(),
                ];
            }
            if ($task->completed_at) {
                $entries[] = [
                    'type'      => 'task_completed',
                    'label'     => 'Completed: ' . $task->title,
                    'meta'      => null,
                    'timestamp' => $task->completed_at->toISOString(),
                    'formatted' => $task->completed_at->diffForHumans(),
                ];
            }
        }

        // ── Audit log entries ──────────────────────────────────────────────────
        $logs = AuditLog::where('church_id', $churchId)
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->whereIn('action', ['member.added', 'member.removed', 'member.role_updated', 'role.assigned'])
            ->latest()
            ->limit(30)
            ->get();

        $typeMap = [
            'member.added'        => 'dept_joined',
            'member.removed'      => 'dept_left',
            'member.role_updated' => 'dept_joined',
            'role.assigned'       => 'role_changed',
        ];

        foreach ($logs as $log) {
            if (! $log->created_at) {
                continue;
            }
            $newVals  = $log->new_values ?? [];
            $deptName = $newVals['department_name'] ?? null;
            $role     = $newVals['role'] ?? null;

            $label = match ($log->action) {
                'member.added'        => 'Joined ' . ($deptName ?? 'a department'),
                'member.removed'      => 'Left '   . ($deptName ?? 'a department'),
                'member.role_updated' => 'Role updated in ' . ($deptName ?? 'a department'),
                'role.assigned'       => 'Role changed to ' . ($role ?? 'unknown'),
                default               => $log->action,
            };

            $entries[] = [
                'type'      => $typeMap[$log->action] ?? 'role_changed',
                'label'     => $label,
                'meta'      => $role,
                'timestamp' => $log->created_at->toISOString(),
                'formatted' => $log->created_at->diffForHumans(),
            ];
        }

        // ── Sort descending by ISO timestamp string, cap at 40 ─────────────────
        usort($entries, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return array_slice($entries, 0, 40);
    }
```

- [ ] **Step 6.4 — Verify controller parses correctly**

```bash
php artisan route:list --name=dashboard.members.show
```

Expected: one row showing `GET dashboard/members/{user}`.

---

## Task 7: `Members/Index.vue` — dept filter + stats strip

**Files:**
- Modify: `resources/js/Pages/Dashboard/Members/Index.vue`

- [ ] **Step 7.1 — Replace the entire Index.vue**

Replace `resources/js/Pages/Dashboard/Members/Index.vue` with:

```vue
<script setup lang="ts">
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import { Users, Search, ChevronRight } from 'lucide-vue-next'

interface Role { name: string }

interface Member {
    id: number
    name: string
    email: string
    avatar: string | null
    departments_count: number
    roles: Role[]
}

interface PaginatedMembers {
    data: Member[]
    current_page: number
    last_page: number
    total: number
    per_page: number
    links: { url: string | null; label: string; active: boolean }[]
}

interface Dept {
    id: number
    name: string
    icon: string | null
    color: string | null
}

interface Stats {
    total: number
    admins: number
    coordinators: number
    members: number
}

const props = defineProps<{
    members: PaginatedMembers
    filters: { search?: string; role?: string; dept_id?: string }
    stats: Stats
    departments: Dept[]
}>()

const search  = ref(props.filters.search   ?? '')
const role    = ref(props.filters.role     ?? '')
const deptId  = ref(props.filters.dept_id  ?? '')

function pushFilters() {
    router.get('/dashboard/members', {
        search:  search.value,
        role:    role.value,
        dept_id: deptId.value,
    }, { preserveState: true, replace: true })
}

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(pushFilters, 350)
})
watch(role,   pushFilters)
watch(deptId, pushFilters)

const ROLE_OPTIONS = [
    { value: '',                      label: 'All roles' },
    { value: 'super_admin',           label: 'Super Admin' },
    { value: 'church_admin',          label: 'Admin' },
    { value: 'coordinator',           label: 'Coordinator' },
    { value: 'assistant_coordinator', label: 'Asst. Coordinator' },
    { value: 'member',                label: 'Member' },
]

function primaryRole(member: Member): string {
    return (member.roles[0]?.name ?? 'member').replace(/_/g, ' ')
}

const roleColor: Record<string, string> = {
    super_admin:           'bg-rose-50 text-rose-700',
    church_admin:          'bg-violet-50 text-violet-700',
    coordinator:           'bg-blue-50 text-blue-700',
    assistant_coordinator: 'bg-sky-50 text-sky-700',
    member:                'bg-neutral-100 text-neutral-600',
}

function getRoleColor(member: Member): string {
    const r = member.roles[0]?.name ?? 'member'
    return roleColor[r] ?? roleColor['member']
}
</script>

<template>
    <DashboardLayout
        title="Members"
        :breadcrumbs="[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Members' }]"
    >
        <PageHeader title="Members" description="Everyone in your church community." />

        <!-- Stats strip -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-5 text-sm text-neutral-500">
            <span><span class="font-semibold text-neutral-800">{{ stats.total }}</span> total</span>
            <span class="text-neutral-300">·</span>
            <span><span class="font-semibold text-neutral-800">{{ stats.admins }}</span> admins</span>
            <span class="text-neutral-300">·</span>
            <span><span class="font-semibold text-neutral-800">{{ stats.coordinators }}</span> coordinators</span>
            <span class="text-neutral-300">·</span>
            <span><span class="font-semibold text-neutral-800">{{ stats.members }}</span> members</span>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <!-- Search -->
            <div class="relative flex-1 max-w-xs">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email…"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition"
                />
            </div>

            <!-- Role filter -->
            <select
                v-model="role"
                class="px-3 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 bg-white transition"
            >
                <option v-for="opt in ROLE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>

            <!-- Department filter -->
            <select
                v-model="deptId"
                class="px-3 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 bg-white transition"
            >
                <option value="">All departments</option>
                <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.icon ? d.icon + ' ' : '' }}{{ d.name }}</option>
            </select>
        </div>

        <!-- Empty -->
        <EmptyState
            v-if="members.data.length === 0"
            :icon="Users"
            title="No members found"
            description="Try adjusting your search or filters."
        />

        <!-- Table -->
        <div v-else class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-50">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Member</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 hidden sm:table-cell">Role</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 hidden md:table-cell">Depts</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-50">
                    <tr
                        v-for="member in members.data"
                        :key="member.id"
                        class="hover:bg-neutral-50/50 transition-colors"
                    >
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <AppAvatar :name="member.name" :src="member.avatar" size="sm" />
                                <div class="min-w-0">
                                    <p class="font-medium text-neutral-900 truncate">{{ member.name }}</p>
                                    <p class="text-xs text-neutral-400 truncate">{{ member.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 hidden sm:table-cell">
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize', getRoleColor(member)]">
                                {{ primaryRole(member) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-neutral-500">
                            {{ member.departments_count }}
                        </td>
                        <td class="px-3 py-3">
                            <Link
                                :href="`/dashboard/members/${member.id}`"
                                class="p-1.5 rounded-lg hover:bg-neutral-100 text-neutral-400 hover:text-neutral-600 transition-colors flex"
                            >
                                <ChevronRight class="w-4 h-4" />
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppPagination
            :links="members.links"
            :current-page="members.current_page"
            :last-page="members.last_page"
            :total="members.total"
            :per-page="members.per_page"
        />
    </DashboardLayout>
</template>
```

- [ ] **Step 7.2 — Build and check for errors**

```bash
npm run build 2>&1 | tail -10
```

Expected: `✓ built in X.XXs` with no TypeScript errors.

---

## Task 8: `MemberProfileForm.vue` — new component

**Files:**
- Create: `resources/js/Components/Members/MemberProfileForm.vue`

- [ ] **Step 8.1 — Create the Members components directory and the form component**

```bash
mkdir -p "resources/js/Components/Members"
```

Create `resources/js/Components/Members/MemberProfileForm.vue`:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppInput from '@/Components/UI/AppInput.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface ProfileData {
    date_of_birth: string | null
    gender: string | null
    marital_status: string | null
    address: string | null
    emergency_contact_name: string | null
    emergency_contact_relationship: string | null
    emergency_contact_phone: string | null
    membership_date: string | null
    baptism_date: string | null
    salvation_date: string | null
}

const props = defineProps<{
    memberId: number
    profile: ProfileData | null
    canEditAdminFields: boolean
}>()

const emit = defineEmits<{ cancel: [] }>()

const form = useForm({
    date_of_birth:                  props.profile?.date_of_birth                  ?? '',
    gender:                         props.profile?.gender                         ?? '',
    marital_status:                 props.profile?.marital_status                 ?? '',
    address:                        props.profile?.address                        ?? '',
    emergency_contact_name:         props.profile?.emergency_contact_name         ?? '',
    emergency_contact_relationship: props.profile?.emergency_contact_relationship ?? '',
    emergency_contact_phone:        props.profile?.emergency_contact_phone        ?? '',
    membership_date:                props.profile?.membership_date                ?? '',
    baptism_date:                   props.profile?.baptism_date                   ?? '',
    salvation_date:                 props.profile?.salvation_date                 ?? '',
})

function submit() {
    form.put(`/dashboard/members/${props.memberId}/profile`, {
        onSuccess: () => emit('cancel'),
    })
}

const genderOptions = [
    { value: '',                 label: '— select —' },
    { value: 'male',             label: 'Male' },
    { value: 'female',           label: 'Female' },
    { value: 'other',            label: 'Other' },
    { value: 'prefer_not_to_say', label: 'Prefer not to say' },
]

const maritalOptions = [
    { value: '',        label: '— select —' },
    { value: 'single',  label: 'Single' },
    { value: 'married', label: 'Married' },
    { value: 'widowed', label: 'Widowed' },
    { value: 'divorced', label: 'Divorced' },
]

const inputCls = 'w-full px-3 py-2 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 transition-colors'
</script>

<template>
    <form @submit.prevent="submit" class="space-y-5">

        <!-- Personal details -->
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 mb-3">Personal</p>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Date of birth</label>
                    <input type="date" v-model="form.date_of_birth" :class="inputCls" />
                    <p v-if="form.errors.date_of_birth" class="mt-0.5 text-xs text-rose-500">{{ form.errors.date_of_birth }}</p>
                </div>
                <AppSelect label="Gender" v-model="form.gender" :options="genderOptions" :error="form.errors.gender" />
                <AppSelect label="Marital status" v-model="form.marital_status" :options="maritalOptions" :error="form.errors.marital_status" />
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Address</label>
                    <textarea
                        v-model="form.address"
                        rows="2"
                        placeholder="123 Church Street, City…"
                        :class="[inputCls, 'resize-none']"
                    />
                    <p v-if="form.errors.address" class="mt-0.5 text-xs text-rose-500">{{ form.errors.address }}</p>
                </div>
            </div>
        </div>

        <!-- Emergency contact -->
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 mb-3">Emergency contact</p>
            <div class="space-y-3">
                <AppInput label="Name" v-model="form.emergency_contact_name" :error="form.errors.emergency_contact_name" placeholder="Full name" />
                <AppInput label="Relationship" v-model="form.emergency_contact_relationship" :error="form.errors.emergency_contact_relationship" placeholder="e.g. Spouse, Parent" />
                <AppInput label="Phone" v-model="form.emergency_contact_phone" :error="form.errors.emergency_contact_phone" placeholder="+1 555 000 0000" />
            </div>
        </div>

        <!-- Church journey — admin only -->
        <div v-if="canEditAdminFields">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 mb-3">Church journey</p>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Membership date</label>
                    <input type="date" v-model="form.membership_date" :class="inputCls" />
                    <p v-if="form.errors.membership_date" class="mt-0.5 text-xs text-rose-500">{{ form.errors.membership_date }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Baptism date</label>
                    <input type="date" v-model="form.baptism_date" :class="inputCls" />
                    <p v-if="form.errors.baptism_date" class="mt-0.5 text-xs text-rose-500">{{ form.errors.baptism_date }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Salvation date</label>
                    <input type="date" v-model="form.salvation_date" :class="inputCls" />
                    <p v-if="form.errors.salvation_date" class="mt-0.5 text-xs text-rose-500">{{ form.errors.salvation_date }}</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 pt-1">
            <AppButton type="submit" size="sm" :loading="form.processing">Save profile</AppButton>
            <button
                type="button"
                @click="$emit('cancel')"
                class="text-sm text-neutral-500 hover:text-neutral-700 transition-colors"
            >Cancel</button>
        </div>

    </form>
</template>
```

- [ ] **Step 8.2 — Build and check for TypeScript errors**

```bash
npm run build 2>&1 | tail -10
```

Expected: `✓ built in X.XXs` — no errors.

---

## Task 9: `MemberTimeline.vue` — new component

**Files:**
- Create: `resources/js/Components/Members/MemberTimeline.vue`

- [ ] **Step 9.1 — Create the timeline component**

Create `resources/js/Components/Members/MemberTimeline.vue`:

```vue
<script setup lang="ts">
import { CheckCircle2, ClipboardList, Users, UserMinus, UserPlus, ShieldCheck } from 'lucide-vue-next'

type TimelineType = 'attendance' | 'task_assigned' | 'task_completed' | 'dept_joined' | 'dept_left' | 'role_changed'

interface TimelineEntry {
    type: TimelineType
    label: string
    meta: string | null
    timestamp: string
    formatted: string
}

defineProps<{ entries: TimelineEntry[] }>()

const typeConfig: Record<TimelineType, { icon: unknown; dot: string; text: string }> = {
    attendance:     { icon: CheckCircle2,  dot: 'bg-emerald-500', text: 'text-emerald-600' },
    task_assigned:  { icon: ClipboardList, dot: 'bg-blue-500',    text: 'text-blue-600' },
    task_completed: { icon: CheckCircle2,  dot: 'bg-indigo-500',  text: 'text-indigo-600' },
    dept_joined:    { icon: UserPlus,      dot: 'bg-violet-500',  text: 'text-violet-600' },
    dept_left:      { icon: UserMinus,     dot: 'bg-neutral-400', text: 'text-neutral-500' },
    role_changed:   { icon: ShieldCheck,   dot: 'bg-amber-500',   text: 'text-amber-600' },
}

function cfg(type: TimelineType) {
    return typeConfig[type] ?? typeConfig.attendance
}
</script>

<template>
    <div v-if="entries.length === 0" class="py-12 text-center">
        <Users class="w-7 h-7 text-neutral-200 mx-auto mb-2" />
        <p class="text-sm text-neutral-400">No activity recorded yet.</p>
    </div>

    <ol v-else class="relative pl-5 space-y-0">
        <li
            v-for="(entry, i) in entries"
            :key="i"
            class="relative pb-5 last:pb-0"
        >
            <!-- Vertical line -->
            <div
                v-if="i < entries.length - 1"
                class="absolute left-[-8px] top-3 bottom-0 w-px bg-neutral-100"
            />

            <!-- Dot -->
            <span :class="['absolute left-[-11px] top-[7px] w-2.5 h-2.5 rounded-full ring-2 ring-white', cfg(entry.type).dot]" />

            <!-- Content -->
            <div class="ml-2">
                <p class="text-sm text-neutral-800 leading-snug">{{ entry.label }}</p>
                <p v-if="entry.meta" class="text-xs text-neutral-400 mt-0.5">{{ entry.meta }}</p>
                <p class="text-xs text-neutral-300 mt-0.5">{{ entry.formatted }}</p>
            </div>
        </li>
    </ol>
</template>
```

- [ ] **Step 9.2 — Build and check for TypeScript errors**

```bash
npm run build 2>&1 | tail -10
```

Expected: `✓ built in X.XXs` — no errors.

---

## Task 10: `Members/Show.vue` — full refactor

**Files:**
- Modify: `resources/js/Pages/Dashboard/Members/Show.vue`

- [ ] **Step 10.1 — Replace the entire Show.vue**

Replace `resources/js/Pages/Dashboard/Members/Show.vue` with:

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import MemberProfileForm from '@/Components/Members/MemberProfileForm.vue'
import MemberTimeline from '@/Components/Members/MemberTimeline.vue'
import {
    ArrowLeft, Mail, Phone, CalendarDays, Crown,
    Building2, MapPin, Heart, User, AlertCircle,
    CheckCircle2, Clock, Pencil,
} from 'lucide-vue-next'

// ── Types ──────────────────────────────────────────────────────────────────────

interface Role { name: string }

interface Department {
    id: number
    name: string
    icon: string | null
    color: string | null
    is_active: boolean
    members_count: number
    coordinator: { id: number; name: string } | null
    pivot?: { role: string; joined_at: string | null; joined_at_formatted: string | null }
}

interface ProfileData {
    date_of_birth: string | null
    gender: string | null
    marital_status: string | null
    address: string | null
    emergency_contact_name: string | null
    emergency_contact_relationship: string | null
    emergency_contact_phone: string | null
    membership_date: string | null
    baptism_date: string | null
    salvation_date: string | null
}

interface Member {
    id: number
    name: string
    email: string
    avatar: string | null
    phone: string | null
    timezone: string | null
    created_at: string | null
    created_at_formatted: string
    roles: Role[]
    departments: Department[]
    profile: ProfileData | null
}

interface AttendanceEntry {
    id: string
    status: string
    checked_in_at: string | null
    created_at_formatted: string
    session: {
        id: number
        title: string
        type: string
        type_label: string
        scheduled_at: string | null
        scheduled_at_formatted: string
        scheduled_date_short: string
        status: string
    } | null
}

interface TaskEntry {
    id: number
    title: string
    priority: string
    status: string
    due_at: string | null
    due_at_short: string | null
    is_overdue: boolean
    completed_at: string | null
}

interface TimelineEntry {
    type: 'attendance' | 'task_assigned' | 'task_completed' | 'dept_joined' | 'dept_left' | 'role_changed'
    label: string
    meta: string | null
    timestamp: string
    formatted: string
}

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    member: Member
    recentAttendances: AttendanceEntry[]
    recentTasks: TaskEntry[]
    timeline: TimelineEntry[]
    canEdit: boolean
    canEditAdminFields: boolean
}>()

// ── State ──────────────────────────────────────────────────────────────────────

const activeTab = ref<'departments' | 'attendance' | 'tasks' | 'timeline'>('departments')
const editingProfile = ref(false)

// ── Helpers ────────────────────────────────────────────────────────────────────

function primaryRole(member: Member): string {
    return member.roles[0]?.name.replace(/_/g, ' ') ?? 'member'
}

const roleColorMap: Record<string, string> = {
    super_admin:  'bg-violet-100 text-violet-800',
    church_admin: 'bg-indigo-100 text-indigo-700',
    coordinator:  'bg-blue-100 text-blue-700',
    member:       'bg-neutral-100 text-neutral-600',
}

function roleColor(name: string): string {
    return roleColorMap[name] ?? roleColorMap['member']
}

const statusColor: Record<string, string> = {
    present: 'bg-emerald-50 text-emerald-700',
    late:    'bg-amber-50 text-amber-700',
    absent:  'bg-rose-50 text-rose-700',
    excused: 'bg-neutral-100 text-neutral-500',
}

const priorityColor: Record<string, string> = {
    urgent: 'bg-rose-50 text-rose-700',
    high:   'bg-orange-50 text-orange-700',
    medium: 'bg-amber-50 text-amber-700',
    low:    'bg-neutral-100 text-neutral-500',
}

const tabs: { key: 'departments' | 'attendance' | 'tasks' | 'timeline'; label: string; count?: number }[] = [
    { key: 'departments', label: 'Departments', count: props.member.departments.length },
    { key: 'attendance',  label: 'Attendance',  count: props.recentAttendances.length  },
    { key: 'tasks',       label: 'Tasks',       count: props.recentTasks.length        },
    { key: 'timeline',    label: 'Timeline',    count: props.timeline.length           },
]
</script>

<template>
    <DashboardLayout
        :title="member.name"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Members',   href: '/dashboard/members' },
            { label: member.name },
        ]"
    >
        <PageHeader :title="member.name" description="Member profile and activity.">
            <template #actions>
                <AppButton href="/dashboard/members" variant="outline" size="sm">
                    <ArrowLeft class="w-4 h-4" /> Back
                </AppButton>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            <!-- ── SIDEBAR ──────────────────────────────────────────────────── -->
            <div class="lg:col-span-1 space-y-4">

                <!-- Profile card -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5 space-y-4">

                    <!-- Avatar + name + role -->
                    <div class="flex flex-col items-center text-center gap-2">
                        <AppAvatar :name="member.name" :src="member.avatar" size="lg" />
                        <div>
                            <p class="text-base font-semibold text-neutral-900">{{ member.name }}</p>
                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1.5 capitalize', roleColor(member.roles[0]?.name ?? 'member')]">
                                {{ primaryRole(member) }}
                            </span>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="space-y-2.5 pt-3 border-t border-neutral-50">
                        <div class="flex items-center gap-2.5 text-sm text-neutral-600">
                            <Mail class="w-4 h-4 text-neutral-400 shrink-0" />
                            <a :href="`mailto:${member.email}`" class="truncate hover:text-brand-600 transition-colors">{{ member.email }}</a>
                        </div>
                        <div v-if="member.phone" class="flex items-center gap-2.5 text-sm text-neutral-600">
                            <Phone class="w-4 h-4 text-neutral-400 shrink-0" />
                            <span>{{ member.phone }}</span>
                        </div>
                        <div class="flex items-center gap-2.5 text-sm text-neutral-500">
                            <CalendarDays class="w-4 h-4 text-neutral-400 shrink-0" />
                            <span>Joined {{ member.created_at_formatted }}</span>
                        </div>
                    </div>

                    <!-- CRM profile fields (read-only) -->
                    <template v-if="member.profile && !editingProfile">
                        <div class="pt-3 border-t border-neutral-50 space-y-2">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400">Profile</p>

                            <div v-if="member.profile.date_of_birth" class="flex items-start gap-2 text-sm text-neutral-600">
                                <User class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
                                <span>{{ new Date(member.profile.date_of_birth).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
                            </div>
                            <div v-if="member.profile.marital_status" class="flex items-start gap-2 text-sm text-neutral-600 capitalize">
                                <Heart class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
                                <span>{{ member.profile.marital_status }}</span>
                            </div>
                            <div v-if="member.profile.address" class="flex items-start gap-2 text-sm text-neutral-600">
                                <MapPin class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
                                <span class="whitespace-pre-line">{{ member.profile.address }}</span>
                            </div>

                            <template v-if="member.profile.membership_date || member.profile.baptism_date || member.profile.salvation_date">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 pt-2">Church journey</p>
                                <div v-if="member.profile.membership_date" class="text-xs text-neutral-500">
                                    Member since {{ member.profile.membership_date }}
                                </div>
                                <div v-if="member.profile.baptism_date" class="text-xs text-neutral-500">
                                    Baptised {{ member.profile.baptism_date }}
                                </div>
                                <div v-if="member.profile.salvation_date" class="text-xs text-neutral-500">
                                    Saved {{ member.profile.salvation_date }}
                                </div>
                            </template>

                            <template v-if="member.profile.emergency_contact_name">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 pt-2">Emergency</p>
                                <div class="flex items-start gap-2 text-sm text-neutral-600">
                                    <AlertCircle class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
                                    <div>
                                        <p>{{ member.profile.emergency_contact_name }}</p>
                                        <p class="text-xs text-neutral-400">{{ member.profile.emergency_contact_relationship }}</p>
                                        <p class="text-xs text-neutral-400">{{ member.profile.emergency_contact_phone }}</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Empty profile placeholder -->
                    <div v-else-if="!member.profile && !editingProfile" class="pt-3 border-t border-neutral-50">
                        <p class="text-xs text-neutral-400 text-center py-2">No profile details yet.</p>
                    </div>

                    <!-- Edit button -->
                    <div v-if="canEdit && !editingProfile" class="pt-1">
                        <button
                            type="button"
                            @click="editingProfile = true"
                            class="w-full flex items-center justify-center gap-1.5 text-xs font-medium text-neutral-500 hover:text-neutral-700 border border-neutral-200 rounded-lg py-2 hover:bg-neutral-50 transition-colors"
                        >
                            <Pencil class="w-3.5 h-3.5" /> Edit profile
                        </button>
                    </div>

                    <!-- Inline edit form -->
                    <div v-if="editingProfile" class="pt-3 border-t border-neutral-50">
                        <MemberProfileForm
                            :member-id="member.id"
                            :profile="member.profile"
                            :can-edit-admin-fields="canEditAdminFields"
                            @cancel="editingProfile = false"
                        />
                    </div>
                </div>

                <!-- All roles (when more than one) -->
                <div v-if="member.roles.length > 1" class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 mb-2">All roles</p>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="r in member.roles"
                            :key="r.name"
                            :class="['px-2 py-0.5 rounded-full text-xs font-medium capitalize', roleColor(r.name)]"
                        >
                            {{ r.name.replace(/_/g, ' ') }}
                        </span>
                    </div>
                </div>

            </div>

            <!-- ── MAIN (tabs) ───────────────────────────────────────────────── -->
            <div class="lg:col-span-2">
                <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">

                    <!-- Tab bar -->
                    <div class="flex border-b border-neutral-100 overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            @click="activeTab = tab.key"
                            :class="[
                                'flex-shrink-0 flex items-center gap-1.5 px-5 py-3.5 text-sm font-medium transition-colors border-b-2',
                                activeTab === tab.key
                                    ? 'border-brand-500 text-brand-600'
                                    : 'border-transparent text-neutral-500 hover:text-neutral-700',
                            ]"
                        >
                            {{ tab.label }}
                            <span
                                v-if="tab.count !== undefined"
                                :class="['text-[10px] px-1.5 py-0.5 rounded-full font-medium', activeTab === tab.key ? 'bg-brand-50 text-brand-600' : 'bg-neutral-100 text-neutral-500']"
                            >{{ tab.count }}</span>
                        </button>
                    </div>

                    <!-- Departments tab -->
                    <div v-if="activeTab === 'departments'" class="divide-y divide-neutral-50">
                        <div v-if="member.departments.length === 0" class="px-5 py-10 text-center">
                            <Building2 class="w-8 h-8 text-neutral-300 mx-auto mb-2" />
                            <p class="text-sm text-neutral-500">Not assigned to any departments.</p>
                        </div>
                        <div
                            v-for="dept in member.departments"
                            :key="dept.id"
                            class="flex items-center gap-4 px-5 py-3.5"
                        >
                            <div
                                class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-base shrink-0"
                                :style="{ background: dept.color ?? '#6366f1' }"
                            >
                                {{ dept.icon ?? '🏛' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-neutral-900 truncate">{{ dept.name }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-neutral-400 capitalize">{{ dept.pivot?.role ?? 'member' }}</span>
                                    <span v-if="dept.coordinator?.id === member.id" class="inline-flex items-center gap-0.5 text-[10px] text-amber-600">
                                        <Crown class="w-3 h-3" /> Coordinator
                                    </span>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xs text-neutral-500">{{ dept.members_count }} members</p>
                                <Link
                                    :href="`/dashboard/departments/${dept.id}`"
                                    class="text-xs text-brand-600 hover:text-brand-800 font-medium transition-colors"
                                >View →</Link>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance tab -->
                    <div v-else-if="activeTab === 'attendance'" class="divide-y divide-neutral-50">
                        <div v-if="recentAttendances.length === 0" class="px-5 py-10 text-center">
                            <CheckCircle2 class="w-8 h-8 text-neutral-300 mx-auto mb-2" />
                            <p class="text-sm text-neutral-500">No attendance records found.</p>
                        </div>
                        <div
                            v-for="att in recentAttendances"
                            :key="att.id"
                            class="flex items-center justify-between px-5 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-neutral-800 truncate">{{ att.session?.title ?? 'Session' }}</p>
                                <p class="text-xs text-neutral-400 mt-0.5">{{ att.session?.scheduled_date_short ?? att.created_at_formatted }}</p>
                            </div>
                            <span :class="['ml-3 shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize', statusColor[att.status] ?? 'bg-neutral-100 text-neutral-500']">
                                {{ att.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Tasks tab -->
                    <div v-else-if="activeTab === 'tasks'" class="divide-y divide-neutral-50">
                        <div v-if="recentTasks.length === 0" class="px-5 py-10 text-center">
                            <Clock class="w-8 h-8 text-neutral-300 mx-auto mb-2" />
                            <p class="text-sm text-neutral-500">No tasks assigned.</p>
                        </div>
                        <div
                            v-for="task in recentTasks"
                            :key="task.id"
                            class="flex items-center gap-3 px-5 py-3"
                        >
                            <div class="flex-1 min-w-0">
                                <p :class="['text-sm font-medium truncate', task.status === 'completed' ? 'line-through text-neutral-400' : 'text-neutral-800']">
                                    {{ task.title }}
                                </p>
                                <p v-if="task.due_at_short" class="text-xs mt-0.5" :class="task.is_overdue ? 'text-rose-500' : 'text-neutral-400'">
                                    {{ task.is_overdue ? 'Overdue · ' : 'Due ' }}{{ task.due_at_short }}
                                </p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize', priorityColor[task.priority] ?? 'bg-neutral-100 text-neutral-500']">
                                    {{ task.priority }}
                                </span>
                                <span class="text-xs text-neutral-400 capitalize">{{ task.status.replace(/_/g, ' ') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline tab -->
                    <div v-else-if="activeTab === 'timeline'" class="p-5">
                        <MemberTimeline :entries="timeline" />
                    </div>

                </div>
            </div>

        </div>
    </DashboardLayout>
</template>
```

- [ ] **Step 10.2 — Build and check for TypeScript errors**

```bash
npm run build 2>&1 | tail -15
```

Expected: `✓ built in X.XXs` — no TypeScript or Vite errors.

- [ ] **Step 10.3 — Smoke test the member profile page**

Open a browser and navigate to `/dashboard/members/{id}` (replace `{id}` with a real user ID from your database). Confirm:
- Left sidebar renders with avatar, name, role badge, email, joined date.
- "Edit profile" button appears if logged in as admin.
- Tab bar shows Departments, Attendance, Tasks, Timeline.
- Departments tab shows the existing department cards (or the empty state).
- Switching tabs changes the content area.
- Click "Edit profile" — the inline form appears with personal, emergency, and (if admin) church journey fields.
- Click "Cancel" — form collapses back.

- [ ] **Step 10.4 — Smoke test the directory index**

Navigate to `/dashboard/members`. Confirm:
- Stats strip shows total, admins, coordinators, members counts.
- Department dropdown appears (populated with active departments).
- Selecting a department filters the table.
- Search and role filter still work.

---

## Self-Review Notes

- All `buildTimeline()` type guard checks (`if (! $ts) continue`) protect against null timestamps.
- `canEditAdminFields` in `MemberProfileForm` is passed as `canEdit && member.id !== auth.user?.id` — this means admins editing their *own* profile do not see admin fields (they would need another admin to set their baptism date), which is the correct strict interpretation of "admin-only fields".
- `AttendanceResource` already serializes `session.scheduled_date_short` and `session.title` — no changes needed there.
- `TaskResource` already serializes `due_at_short` and `is_overdue` — no changes needed there.
- The `dept_id` filter is passed as a string from the URL (`String(d.id)` in the template option value) and the controller's `when($request->dept_id, ...)` receives it as a string — Laravel's `when()` treats a non-empty string as truthy, and the `whereHas` query does an implicit cast. This is correct.
