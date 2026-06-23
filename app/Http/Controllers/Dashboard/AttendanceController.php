<?php

namespace App\Http\Controllers\Dashboard;

use App\Events\Broadcast\AttendanceSessionUpdated;
use App\Http\Controllers\Controller;
use App\Traits\LogsAuditEvents;
use App\Http\Requests\Attendance\BulkAttendanceRequest;
use App\Http\Requests\Attendance\StoreSessionRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\AttendanceSessionResource;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Event;
use App\Models\ServicePlan;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    use LogsAuditEvents;

    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    // ── Session list ───────────────────────────────────────────────────────────

    /**
     * GET /dashboard/attendance
     * Paginated list of sessions with inline stats + filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $churchId = $request->user()->church_id;
        $user     = $request->user();

        $query = AttendanceSession::forChurch($churchId)
            ->with(['department:id,name,icon,color', 'event:id,title'])
            ->withCount([
                'attendances',
                'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
                'attendances as absent_count'  => fn ($q) => $q->where('status', 'absent'),
                'attendances as late_count'    => fn ($q) => $q->where('status', 'late'),
                'attendances as excused_count' => fn ($q) => $q->where('status', 'excused'),
            ])
            ->orderByDesc('scheduled_at');

        // Non-admin coordinators see only sessions for departments they currently belong to.
        // This ensures removing a coordinator from a department immediately revokes
        // visibility of that department's attendance records from the index.
        // Church-wide sessions (department_id IS NULL) are admin-only.
        if ($user->isCoordinator() && ! $user->isChurchAdmin()) {
            $myDeptIds = $user->departments()->pluck('departments.id');
            $query->whereIn('department_id', $myDeptIds);
        }

        // ── Filters ─────────────────────────────────────────────────────────
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($deptId = $request->query('department_id')) {
            $query->where('department_id', $deptId);
        }

        if ($from = $request->query('from')) {
            $query->where('scheduled_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('scheduled_at', '<=', $to);
        }

        $sessions = $query->paginate(20)->withQueryString();

        // ── Stats for the top widgets ────────────────────────────────────────
        $stats = $user->hasPermissionTo('attendance.view')
            ? $this->attendance->getDashboardStats($churchId)
            : null;

        // ── Dropdown data for filter UI ───────────────────────────────────────
        $departments = Department::forChurch($churchId)
            ->where('is_active', true)
            ->select('id', 'name', 'icon', 'color')
            ->orderBy('name')
            ->get();

        return Inertia::render('Dashboard/Attendance/Index', [
            'sessions'    => AttendanceSessionResource::collection($sessions),
            'stats'       => $stats,
            'departments' => $departments,
            'filters'     => $request->only(['type', 'status', 'department_id', 'from', 'to']),
        ]);
    }

    // ── Create session ─────────────────────────────────────────────────────────

    /**
     * GET /dashboard/attendance/create
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', AttendanceSession::class);

        $churchId = $request->user()->church_id;

        $departments = Department::forChurch($churchId)
            ->where('is_active', true)
            ->select('id', 'name', 'icon', 'color')
            ->orderBy('name')
            ->get();

        $upcomingEvents = Event::forChurch($churchId)
            ->upcoming()
            ->notCancelled()
            ->select('id', 'title', 'start_at')
            ->orderBy('start_at')
            ->take(20)
            ->get()
            ->map(fn ($e) => [
                'id'    => $e->id,
                'title' => $e->title,
                'start_at_formatted' => $e->start_at?->format('D, M j, Y g:i A'),
            ]);

        // Service plans available to link — published plans without an existing session
        $servicePlans = ServicePlan::forChurch($churchId)
            ->whereIn('status', ['draft', 'published'])
            ->whereDoesntHave('attendanceSession')
            ->orderBy('scheduled_at')
            ->select('id', 'title', 'scheduled_at', 'status')
            ->take(30)
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'title'        => $p->title,
                'status'       => $p->status,
                'scheduled_at' => $p->scheduled_at?->toJSON(),
                'scheduled_at_formatted' => $p->scheduled_at?->format('D, j M Y'),
            ]);

        return Inertia::render('Dashboard/Attendance/Create', [
            'departments'    => $departments,
            'upcomingEvents' => $upcomingEvents,
            'servicePlans'   => $servicePlans,
            'prefill'        => $request->only(['event_id', 'department_id', 'service_plan_id']),
        ]);
    }

    /**
     * POST /dashboard/attendance
     */
    public function store(StoreSessionRequest $request): RedirectResponse
    {
        $session = AttendanceSession::create([
            ...$request->validated(),
            'church_id'  => $request->user()->church_id,
            'created_by' => $request->user()->id,
            'status'     => 'planned',
        ]);

        return redirect()->route('dashboard.attendance.show', $session)
            ->with('success', 'Attendance session created.');
    }

    // ── Show / mark attendance ─────────────────────────────────────────────────

    /**
     * GET /dashboard/attendance/{session}
     * Shows the session detail and the interactive attendance table.
     */
    public function show(Request $request, AttendanceSession $session): Response
    {
        $this->authorize('view', $session);

        $session->load(['department:id,name,icon,color', 'event:id,title,start_at', 'creator:id,name,avatar', 'servicePlan:id,title,status,scheduled_at'])
                ->loadCount([
                    'attendances',
                    'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
                    'attendances as absent_count'  => fn ($q) => $q->where('status', 'absent'),
                    'attendances as late_count'    => fn ($q) => $q->where('status', 'late'),
                    'attendances as excused_count' => fn ($q) => $q->where('status', 'excused'),
                ]);

        // Build merged attendee + status list
        $attendees = $this->attendance->buildAttendeeList($session);

        return Inertia::render('Dashboard/Attendance/Show', [
            'session'    => AttendanceSessionResource::make($session)->toArray($request),
            'attendees'  => $attendees,
            'canManage'  => $request->user()->can('update', $session),
        ]);
    }

    // ── Save attendance ────────────────────────────────────────────────────────

    /**
     * POST /dashboard/attendance/{session}/save
     * Bulk upsert all attendance records for a session.
     */
    public function saveAttendance(BulkAttendanceRequest $request, AttendanceSession $session): JsonResponse
    {
        $this->authorize('markAttendance', $session);

        $saved = $this->attendance->bulkSave(
            $session,
            $request->validated('attendances'),
            $request->user()->id,
        );

        // Reload updated stats
        $session->loadCount([
            'attendances',
            'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
            'attendances as absent_count'  => fn ($q) => $q->where('status', 'absent'),
            'attendances as late_count'    => fn ($q) => $q->where('status', 'late'),
            'attendances as excused_count' => fn ($q) => $q->where('status', 'excused'),
        ]);

        $this->auditLog(
            'attendance.bulk.marked',
            $session,
            [],
            ['saved' => $saved],
            ['department_id' => $session->department_id],
        );

        // Broadcast live stats update to all users watching this session
        try {
            AttendanceSessionUpdated::dispatch(
                sessionId:        $session->id,
                churchId:         (int) $session->church_id,
                attendancesCount: (int) $session->attendances_count,
                presentCount:     (int) $session->present_count,
                absentCount:      (int) $session->absent_count,
                lateCount:        (int) $session->late_count,
                excusedCount:     (int) $session->excused_count,
                attendanceRate:   (int) $session->attendance_rate,
            );
        } catch (\Illuminate\Broadcasting\BroadcastException) {
            // WebSocket server unavailable — real-time push skipped, no data loss
        }

        return response()->json([
            'message'          => "Saved {$saved} attendance records.",
            'attendances_count'=> $session->attendances_count,
            'present_count'    => $session->present_count,
            'absent_count'     => $session->absent_count,
            'late_count'       => $session->late_count,
            'excused_count'    => $session->excused_count,
            'attendance_rate'  => $session->attendance_rate,
        ]);
    }

    // ── Session lifecycle ──────────────────────────────────────────────────────

    /**
     * PATCH /dashboard/attendance/{session}/status
     * Transition: planned → active → completed | cancelled
     */
    public function updateStatus(Request $request, AttendanceSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $request->validate([
            'status' => ['required', 'in:active,completed,cancelled'],
        ]);

        $newStatus = $request->input('status');

        if ($newStatus === 'active') {
            $session = $this->attendance->activateSession($session);
        } elseif ($newStatus === 'completed') {
            $session = $this->attendance->completeSession($session);
        } else {
            $session->update(['status' => 'cancelled']);
            $session->refresh();
        }

        if ($newStatus === 'active') {
            $this->auditLog('attendance.session.opened', $session, [], ['status' => 'active'], ['department_id' => $session->department_id]);
        } elseif ($newStatus === 'completed') {
            $this->auditLog('attendance.session.closed', $session, [], ['status' => 'completed'], ['department_id' => $session->department_id]);
        }

        return response()->json([
            'status'           => $session->status,
            'check_in_enabled' => $session->check_in_enabled,
            'check_in_token'   => $session->check_in_token,
        ]);
    }

    // ── Delete session ─────────────────────────────────────────────────────────

    /**
     * DELETE /dashboard/attendance/{session}
     */
    public function destroy(AttendanceSession $session): RedirectResponse
    {
        $this->authorize('delete', $session);

        $session->delete(); // cascades to attendances via FK

        return redirect()->route('dashboard.attendance.index')
            ->with('success', 'Session deleted.');
    }

    // ── Member attendance history ──────────────────────────────────────────────

    /**
     * GET /dashboard/attendance/members/{user}
     * Member's personal attendance timeline and stats.
     */
    public function memberHistory(Request $request, User $user): Response
    {
        // Members may only view their own history; admins/coordinators see all
        if ($request->user()->id !== $user->id) {
            $this->authorize('viewAny', AttendanceSession::class);
        }

        // Tenant guard
        abort_if($user->church_id !== $request->user()->church_id, 403);

        $stats = $this->attendance->getMemberStats($user);

        $history = Attendance::where('user_id', $user->id)
            ->where('church_id', $user->church_id)
            ->with([
                'session:id,title,type,scheduled_at,status,department_id,event_id',
                'session.department:id,name,icon,color',
            ])
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('Dashboard/Attendance/Member', [
            'member'  => [
                'id'     => $user->id,
                'name'   => $user->name,
                'avatar' => $user->avatar,
                'email'  => $user->email,
                'roles'  => $user->getRoleNames(),
            ],
            'stats'   => $stats,
            'history' => AttendanceResource::collection($history),
        ]);
    }
}
