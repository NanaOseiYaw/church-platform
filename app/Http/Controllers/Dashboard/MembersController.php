<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\MemberResource;
use App\Http\Resources\TaskResource;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\MemberProfile;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MembersController extends Controller
{
    use LogsAuditEvents;
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
            ->when($request->status, function ($q, $s) {
                if ($s === 'active')   $q->where('is_active', true);
                if ($s === 'inactive') $q->where('is_active', false);
            })
            ->orderBy('name');

        $members = $query->paginate(20)->withQueryString();

        // ── Stats (unfiltered totals for the church) ───────────────────────────
        $base = User::where('church_id', $churchId);

        $stats = [
            'total'        => (clone $base)->count(),
            'active'       => (clone $base)->where('is_active', true)->count(),
            'inactive'     => (clone $base)->where('is_active', false)->count(),
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
            'filters'     => $request->only('search', 'role', 'dept_id', 'status'),
            'stats'       => $stats,
            'departments' => $departments,
            'newMember'   => session('newMember'),
        ]);
    }

    /** POST /dashboard/members — admin-creates a member with a temporary password */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('church_admin'), 403);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')],
            'role'  => ['required', 'string', 'in:member,assistant_coordinator,coordinator,church_admin'],
        ]);

        // Alphanumeric (no symbols) so it's easy for the admin to relay verbally.
        $tempPassword = Str::password(10, symbols: false);

        $user = User::create([
            'church_id' => $this->resolvedChurchId(),
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            // The User model's 'hashed' cast hashes this on save.
            'password'  => $tempPassword,
            'is_active' => true,
        ]);

        // email_verified_at is guarded (not in $fillable) — set it explicitly so
        // admin-created accounts are pre-verified (no email delivery is wired up).
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole($validated['role']);
        $this->auditLog('member.created', $user, [], ['role' => $validated['role']]);

        // Flash the one-time credentials so the admin can relay them to the member.
        return redirect()
            ->route('dashboard.members.index')
            ->with('newMember', [
                'name'          => $user->name,
                'email'         => $user->email,
                'temp_password' => $tempPassword,
            ]);
    }

    /** GET /dashboard/members/{user} */
    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        $churchId = $this->resolvedChurchId();
        abort_unless($user->church_id === $churchId, 403);

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
            'canEditAdminFields'  => $actor->can('update', $user) && $actor->id !== $user->id,
            // Admin can change roles for any member except themselves
            'canAssignRole'       => $actor->hasRole('church_admin') && $actor->id !== $user->id,
            // Admin can deactivate/reactivate any member except themselves
            'canToggleActive'     => $actor->hasRole('church_admin') && $actor->id !== $user->id,
        ]);
    }

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

        $this->auditLog('member.updated', $user, [], $data);

        return back()->with('success', 'Profile updated.');
    }

    /** PATCH /dashboard/members/{user}/role */
    public function assignRole(\Illuminate\Http\Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        // Only admins can change church-wide roles
        abort_unless($request->user()->hasRole('church_admin'), 403);
        abort_unless($user->church_id === $this->resolvedChurchId(), 403);
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own role.');

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:member,coordinator,church_admin'],
        ]);

        $old     = $user->roles->pluck('name')->first() ?? 'member';
        $newRole = $validated['role'];
        $user->syncRoles([$newRole]);

        $this->auditLog('member.role.assigned', $user, ['role' => $old], ['role' => $newRole]);

        // Notify the member their role has changed
        $roleLabel = str_replace('_', ' ', ucfirst($newRole));
        $user->notify(new AppNotification(
            AppNotification::TYPE_ROLE_CHANGED,
            'Your role has been updated',
            "You have been assigned the role: {$roleLabel}.",
            '/dashboard',
        ));

        return back()->with('success', 'Role updated.');
    }

    /** PATCH /dashboard/members/{user}/activate */
    public function toggleActive(\Illuminate\Http\Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()->hasRole('church_admin'), 403);
        abort_unless($user->church_id === $this->resolvedChurchId(), 403);
        abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate your own account.');

        $newState = ! (bool) ($user->is_active ?? true);
        $user->update(['is_active' => $newState]);

        $action  = $newState ? 'member.reactivated' : 'member.deactivated';
        $message = $newState ? "{$user->name} has been reactivated." : "{$user->name} has been deactivated.";
        $this->auditLog($action, $user);

        return back()->with('success', $message);
    }

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
                    'meta'      => ucfirst($task->priority ?? '') . ' priority',
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
            ->whereIn('action', ['department.member.added', 'department.member.removed', 'department.member.updated', 'member.role.assigned'])
            ->latest()
            ->limit(30)
            ->get();

        $typeMap = [
            'department.member.added'   => 'dept_joined',
            'department.member.removed' => 'dept_left',
            'department.member.updated' => 'dept_joined',
            'member.role.assigned'      => 'role_changed',
        ];

        foreach ($logs as $log) {
            if (! $log->created_at) {
                continue;
            }
            $newVals  = $log->new_values ?? [];
            $deptName = $newVals['department_name'] ?? null;
            $role     = $newVals['role'] ?? null;

            $label = match ($log->action) {
                'department.member.added'   => 'Joined ' . ($deptName ?? 'a department'),
                'department.member.removed' => 'Left '   . ($deptName ?? 'a department'),
                'department.member.updated' => 'Role updated in ' . ($deptName ?? 'a department'),
                'member.role.assigned'      => 'Role changed to ' . ($role ?? 'unknown'),
                default                     => $log->action,
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
}
