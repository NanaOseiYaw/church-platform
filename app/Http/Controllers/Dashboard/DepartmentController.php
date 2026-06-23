<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DepartmentRole;
use App\Events\DepartmentMemberRoleWasChanged;
use App\Events\DepartmentMemberWasAdded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Traits\LogsAuditEvents;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    use LogsAuditEvents;

    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    // ── Resource actions ───────────────────────────────────────────────────────

    /** GET /dashboard/departments */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $churchId = $this->resolvedChurchId();

        return Inertia::render('Dashboard/Departments/Index', [
            'departments' => $this->departments->paginate($churchId, $request->search),
            'filters'     => $request->only('search'),
        ]);
    }

    /** GET /dashboard/departments/create */
    public function create(): Response
    {
        $this->authorize('create', Department::class);

        return Inertia::render('Dashboard/Departments/Create', [
            'staff'             => $this->departments->staffForChurch($this->resolvedChurchId()),
            'visibilityOptions' => \App\Enums\DepartmentVisibility::options(),
        ]);
    }

    /** POST /dashboard/departments */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $dept = $this->departments->create(
            $request->validated(),
            $this->resolvedChurchId(),
            $request->user()->id,
        );

        return redirect()
            ->route('dashboard.departments.show', $dept)
            ->with('success', "\u{201c}{$dept->name}\u{201d} workspace created.");
    }

    /** GET /dashboard/departments/{department} */
    public function show(Request $request, Department $department): Response
    {
        $this->authorize('view', $department);

        $churchId = $this->resolvedChurchId();

        // Load coordinator, members, and files for the department workspace
        $department->load([
            'coordinator:id,name,avatar',
            'files.uploader:id,name,avatar',
        ]);

        // Members ordered: coordinator → assistant_coordinator → member
        // CASE expression is used intentionally — FIELD() is MySQL-only and
        // would crash on SQLite (tests) and PostgreSQL (production alternatives).
        $members = $department->members()
            ->select('users.id', 'users.name', 'users.avatar', 'users.email')
            ->withPivot('role', 'joined_at')
            ->orderByRaw(DepartmentRole::toOrderSql('department_user.role'))
            ->get()
            ->map(fn ($m) => [
                'id'     => $m->id,
                'name'   => $m->name,
                'avatar' => $m->avatar,
                'email'  => $m->email,
                'pivot'  => [
                    'role'                => $m->pivot->role,
                    'joined_at'           => $m->pivot->joined_at,
                    'joined_at_formatted' => $m->pivot->joined_at
                        ? Carbon::parse($m->pivot->joined_at)->format('j M Y')
                        : null,
                ],
            ])
            ->values();

        // Recent workspace content (preview for Overview tab)
        $recentAnnouncements = $department->announcements()
            ->latest('published_at')
            ->limit(4)
            ->get(['id', 'title', 'published_at'])
            ->map(fn ($a) => [
                'id'                     => $a->id,
                'title'                  => $a->title,
                'published_at'           => $a->published_at?->toJSON(),
                'published_at_formatted' => $a->published_at?->format('j M Y') ?? '—',
            ]);

        $upcomingEvents = $department->events()
            ->where('start_at', '>=', now())
            ->orderBy('start_at')
            ->limit(4)
            ->get(['id', 'title', 'start_at', 'location'])
            ->map(fn ($e) => [
                'id'               => $e->id,
                'title'            => $e->title,
                'start_at'         => $e->start_at?->toJSON(),
                'start_at_formatted' => $e->start_at?->format('D, j M Y') ?? '—',
                'location'         => $e->location,
            ]);

        $recentTasks = $department->tasks()
            ->active()                          // pending + in_progress + overdue (scheduler-stamped)
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'status', 'priority', 'due_at'])
            ->map(fn ($t) => [
                'id'              => $t->id,
                'title'           => $t->title,
                'status'          => $t->status,
                'priority'        => $t->priority,
                'due_at'          => $t->due_at?->toJSON(),
                'due_at_formatted' => $t->due_at?->format('j M Y'),
            ]);

        $user             = $request->user();
        $canManageMembers = $user->can('manageMembers', $department);

        // Extract the current user's pivot role from the already-loaded collection
        // to avoid a second DB hit. Returns null for admins who aren't department members.
        $memberEntry          = $members->firstWhere('id', $user->id);
        $currentUserPivotRole = $memberEntry ? $memberEntry['pivot']['role'] : null;

        // Role updates are limited to pivot coordinators and church admins.
        // Assistant coordinators may add/remove members but not change roles.
        $canUpdateRoles = $user->can('departments.edit')
            || $currentUserPivotRole === DepartmentRole::COORDINATOR->value;

        return Inertia::render('Dashboard/Departments/Show', [
            'department'          => $department,
            'members'             => $members,
            'recentAnnouncements' => $recentAnnouncements,
            'upcomingEvents'      => $upcomingEvents,
            'recentTasks'         => $recentTasks,

            // Only populate the add-member dropdown for users who can actually use it.
            // Sending all church members to read-only visitors leaks member data.
            'availableMembers' => $canManageMembers
                ? $this->departments->availableMembers($department, $churchId)
                : collect(),

            'roleOptions' => array_map(
                fn (DepartmentRole $r) => ['value' => $r->value, 'label' => $r->label()],
                DepartmentRole::cases(),
            ),

            // Pass the collection directly so Inertia's PropsResolver runs
            // toResponse()->resolve()->filter() — consistent with the rest of the app.
            'files'            => \App\Http\Resources\FileResource::collection($department->files),

            'canEdit'              => $user->can('update', $department),
            'canManageMembers'    => $canManageMembers,
            'canUpdateRoles'      => $canUpdateRoles,
            'currentUserPivotRole'=> $currentUserPivotRole,
            'currentUserId'       => $user->id,
            'canUpload'           => $user->can('create', \App\Models\File::class),
        ]);
    }

    /** GET /dashboard/departments/{department}/edit */
    public function edit(Department $department): Response
    {
        $this->authorize('update', $department);

        $department->load('coordinator:id,name,avatar');

        return Inertia::render('Dashboard/Departments/Edit', [
            'department'        => $department,
            'staff'             => $this->departments->staffForChurch($this->resolvedChurchId()),
            'visibilityOptions' => \App\Enums\DepartmentVisibility::options(),
        ]);
    }

    /** PUT /dashboard/departments/{department} */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->departments->update($department, $request->validated());
        $this->auditLog('department.updated', $department, [], [], ['department_id' => $department->id]);

        return redirect()
            ->route('dashboard.departments.show', $department)
            ->with('success', 'Workspace settings saved.');
    }

    /** DELETE /dashboard/departments/{department} */
    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        $name = $department->name;
        $this->departments->delete($department);

        return redirect()
            ->route('dashboard.departments.index')
            ->with('success', "\u{201c}{$name}\u{201d} deleted.");
    }

    // ── Member management ──────────────────────────────────────────────────────

    /** POST /dashboard/departments/{department}/members */
    public function addMember(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('manageMembers', $department);

        $validated = $request->validate([
            // Scope to the department's church to prevent cross-church member assignment.
            'user_id' => ['required', 'integer', "exists:users,id,church_id,{$department->church_id}"],
            'role'    => ['required', DepartmentRole::validationRule()],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $this->departments->addMember($department, $user, $validated['role']);

        DepartmentMemberWasAdded::dispatch($department, $user, $request->user(), $validated['role']);

        $this->auditLog(
            'department.member.added',
            $user,
            [],
            ['department_id' => $department->id, 'department_name' => $department->name, 'role' => $validated['role']],
            ['department_id' => $department->id],
        );

        return back()->with('success', "{$user->name} added to {$department->name}.");
    }

    /** PATCH /dashboard/departments/{department}/members/{user} */
    public function updateMemberRole(Request $request, Department $department, User $user): RedirectResponse
    {
        // Only pivot coordinators and church admins may change roles.
        // Assistant coordinators are excluded by the updateMemberRole policy.
        $this->authorize('updateMemberRole', $department);

        $validated = $request->validate([
            'role' => ['required', DepartmentRole::validationRule()],
        ]);

        $newRole = $validated['role'];
        $actor   = $request->user();
        $isAdmin = $actor->can('departments.edit');

        // Resolve the target member's current pivot role.
        $targetPivotRole = $department->members()
            ->where('users.id', $user->id)
            ->first()?->pivot?->role;

        if ($targetPivotRole === null) {
            throw ValidationException::withMessages([
                'role' => ['This user is not a member of this department.'],
            ]);
        }

        // ── Non-admin governance guards ────────────────────────────────────────

        if (! $isAdmin) {
            // Guard 1: self-modification — coordinators cannot change their own role.
            if ($actor->id === $user->id) {
                throw ValidationException::withMessages([
                    'role' => ['You cannot change your own department role.'],
                ]);
            }

            // Guard 2: coordinators cannot modify another coordinator's role.
            if ($targetPivotRole === DepartmentRole::COORDINATOR->value) {
                throw ValidationException::withMessages([
                    'role' => ["Only administrators can change a Coordinator\u{2019}s role."],
                ]);
            }

            // Guard 3: coordinators cannot promote anyone to coordinator.
            if ($newRole === DepartmentRole::COORDINATOR->value) {
                throw ValidationException::withMessages([
                    'role' => ['Only administrators can promote members to Coordinator.'],
                ]);
            }
        }

        // ── Last-coordinator protection (applies to all actors, including admins) ──

        if ($targetPivotRole === DepartmentRole::COORDINATOR->value
            && $newRole       !== DepartmentRole::COORDINATOR->value
        ) {
            $coordinatorCount = $department->members()
                ->wherePivot('role', DepartmentRole::COORDINATOR->value)
                ->count();

            if ($coordinatorCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => ['This department must have at least one coordinator assigned.'],
                ]);
            }
        }

        $this->departments->updateMemberRole($department, $user, $newRole);

        DepartmentMemberRoleWasChanged::dispatch($department, $user, $actor, $newRole);

        $this->auditLog(
            'department.member.updated',
            $user,
            ['role' => $targetPivotRole],
            ['department_id' => $department->id, 'department_name' => $department->name, 'role' => $newRole],
            ['department_id' => $department->id],
        );

        return back()->with('success', "{$user->name}\u{2019}s role updated.");
    }

    /** DELETE /dashboard/departments/{department}/members/{user} */
    public function removeMember(Department $department, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $department);

        $actor   = auth()->user();
        $isAdmin = $actor->can('departments.edit');

        // Resolve the target member's current pivot role.
        $targetPivotRole = $department->members()
            ->where('users.id', $user->id)
            ->first()?->pivot?->role;

        // ── Non-admin governance guards ────────────────────────────────────────

        if (! $isAdmin) {
            // Guard 1: no self-removal — department leaders cannot eject themselves.
            if ($actor->id === $user->id) {
                throw ValidationException::withMessages([
                    'member' => ['You cannot remove yourself from the department.'],
                ]);
            }

            $actorPivotRole = $department->members()
                ->where('users.id', $actor->id)
                ->first()?->pivot?->role;

            // Guard 2: coordinators cannot remove other coordinators.
            if ($actorPivotRole === DepartmentRole::COORDINATOR->value
                && $targetPivotRole === DepartmentRole::COORDINATOR->value
            ) {
                throw ValidationException::withMessages([
                    'member' => ['Coordinators cannot remove other coordinators.'],
                ]);
            }

            // Guard 3: assistant coordinators can only remove regular members.
            if ($actorPivotRole === DepartmentRole::ASSISTANT_COORDINATOR->value
                && $targetPivotRole !== DepartmentRole::MEMBER->value
            ) {
                throw ValidationException::withMessages([
                    'member' => ['You do not have permission to remove department leadership.'],
                ]);
            }
        }

        // ── Last-coordinator protection (applies to all actors, including admins) ──

        if ($targetPivotRole === DepartmentRole::COORDINATOR->value) {
            $coordinatorCount = $department->members()
                ->wherePivot('role', DepartmentRole::COORDINATOR->value)
                ->count();

            if ($coordinatorCount <= 1) {
                throw ValidationException::withMessages([
                    'member' => ['This department must have at least one coordinator assigned.'],
                ]);
            }
        }

        $this->departments->removeMember($department, $user);

        $this->auditLog(
            'department.member.removed',
            $user,
            ['department_id' => $department->id, 'department_name' => $department->name, 'role' => $targetPivotRole],
            [],
            ['department_id' => $department->id],
        );

        return back()->with('success', "{$user->name} removed from {$department->name}.");
    }
}
