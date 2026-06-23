<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Event;
use App\Models\Sermon;
use App\Models\Task;
use App\Models\User;
use App\Models\VolunteerAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    /**
     * GET /dashboard/reports
     *
     * Aggregate stats + Phase 1 analytics for church leadership.
     * Accepts optional filters: date_range (30d|90d|6m|12m|all) and department_id.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $churchId  = $this->resolvedChurchId();
        $dateRange = $request->get('date_range', '90d');
        $deptId    = $request->get('department_id') ? (int) $request->get('department_id') : null;

        $fromDate = match ($dateRange) {
            '30d'  => now()->subDays(30),
            '6m'   => now()->subMonths(6),
            '12m'  => now()->subMonths(12),
            'all'  => null,
            default => now()->subDays(90),   // '90d'
        };

        // ── Departments for filter dropdown ───────────────────────────────────
        $departments = Department::forChurch($churchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])
            ->toArray();

        $cacheKey = "church.{$churchId}.reports.{$dateRange}.{$deptId}";

        [$stats, $analytics] = cache()->remember($cacheKey, 300, function () use ($churchId, $fromDate, $deptId) {

        // ═══════════════════════════════════════════════════════════════════════
        // TOP-LEVEL STAT CARDS (existing — all-time, no date filter)
        // ═══════════════════════════════════════════════════════════════════════

        $totalMembers      = User::where('church_id', $churchId)->count();
        $verifiedMembers   = User::where('church_id', $churchId)->whereNotNull('email_verified_at')->count();
        $activeDepts       = Department::forChurch($churchId)->where('is_active', true)->count();
        $totalDepts        = Department::forChurch($churchId)->count();

        $totalEvents       = Event::forChurch($churchId)->count();
        $upcomingEvents    = Event::forChurch($churchId)->where('start_at', '>=', now())->count();
        $pastEvents        = $totalEvents - $upcomingEvents;

        $totalTasks        = Task::forChurch($churchId)->count();
        $completedTasks    = Task::forChurch($churchId)->where('status', 'completed')->count();
        $pendingTasks      = Task::forChurch($churchId)->whereIn('status', ['pending', 'in_progress'])->count();
        $taskCompletionRate = $totalTasks > 0
            ? round($completedTasks / $totalTasks * 100, 1)
            : null;

        $totalAnnouncements     = Announcement::forChurch($churchId)->count();
        $publishedAnnouncements = Announcement::forChurch($churchId)->whereNotNull('published_at')->count();

        $totalSermons  = Sermon::forChurch($churchId)->count();
        $publicSermons = Sermon::forChurch($churchId)->where('is_public', true)->count();

        $totalSessions     = AttendanceSession::forChurch($churchId)->count();
        $completedSessions = AttendanceSession::forChurch($churchId)->where('status', 'completed')->count();

        $avgRate = AttendanceSession::forChurch($churchId)
            ->where('status', 'completed')
            ->whereHas('attendances')
            ->withCount([
                'attendances',
                'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
            ])
            ->get()
            ->filter(fn ($s) => $s->attendances_count > 0)
            ->avg(fn ($s) => round($s->present_count / $s->attendances_count * 100, 1));

        $recentSessionRates = AttendanceSession::forChurch($churchId)
            ->where('status', 'completed')
            ->whereHas('attendances')
            ->withCount([
                'attendances',
                'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
            ])
            ->orderByDesc('scheduled_at')
            ->take(5)
            ->get()
            ->map(fn ($s) => [
                'label' => $s->scheduled_at?->format('j M') ?? $s->title,
                'rate'  => $s->attendances_count > 0
                    ? round($s->present_count / $s->attendances_count * 100, 1)
                    : 0,
            ])
            ->values()
            ->toArray();

        $roleBreakdown = User::where('church_id', $churchId)
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', User::class)
            ->select('roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->get()
            ->map(fn ($r) => ['role' => $r->name, 'count' => $r->count])
            ->values()
            ->toArray();

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1 ANALYTICS (date-filtered)
        // ═══════════════════════════════════════════════════════════════════════

        // ── Attendance: monthly trend ─────────────────────────────────────────
        $allCompletedSessions = AttendanceSession::forChurch($churchId)
            ->where('status', 'completed')
            ->when($fromDate, fn ($q) => $q->where('scheduled_at', '>=', $fromDate))
            ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
            ->with('department:id,name')
            ->withCount([
                'attendances',
                'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
            ])
            ->orderBy('scheduled_at')
            ->get();

        $attendanceMonthly = $allCompletedSessions
            ->groupBy(fn ($s) => $s->scheduled_at?->format('Y-m') ?? 'unknown')
            ->map(fn ($group, $key) => [
                'month'    => Carbon::parse($key . '-01')->format('M Y'),
                'sessions' => $group->count(),
                'avg_rate' => (int) round(
                    $group->filter(fn ($s) => $s->attendances_count > 0)
                          ->avg(fn ($s) => ($s->present_count / $s->attendances_count * 100)) ?? 0
                ),
            ])
            ->values()
            ->toArray();

        // ── Attendance: by department ─────────────────────────────────────────
        $attendanceByDept = $allCompletedSessions
            ->groupBy(fn ($s) => $s->department?->name ?? 'General')
            ->map(fn ($group, $name) => [
                'name'     => $name,
                'sessions' => $group->count(),
                'avg_rate' => (int) round(
                    $group->filter(fn ($s) => $s->attendances_count > 0)
                          ->avg(fn ($s) => ($s->present_count / $s->attendances_count * 100)) ?? 0
                ),
            ])
            ->sortByDesc('sessions')
            ->values()
            ->take(8)
            ->toArray();

        // ── Attendance: by type ───────────────────────────────────────────────
        $attendanceByType = $allCompletedSessions
            ->groupBy('type')
            ->map(fn ($group, $type) => [
                'type'     => $type,
                'sessions' => $group->count(),
                'avg_rate' => (int) round(
                    $group->filter(fn ($s) => $s->attendances_count > 0)
                          ->avg(fn ($s) => ($s->present_count / $s->attendances_count * 100)) ?? 0
                ),
            ])
            ->sortByDesc('sessions')
            ->values()
            ->toArray();

        // ── Members: growth trend ─────────────────────────────────────────────
        $memberGrowth = User::where('church_id', $churchId)
            ->when($fromDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->orderBy('created_at')
            ->get(['id', 'created_at'])
            ->groupBy(fn ($u) => $u->created_at?->format('Y-m') ?? 'unknown')
            ->map(fn ($group, $key) => [
                'month' => Carbon::parse($key . '-01')->format('M Y'),
                'count' => $group->count(),
            ])
            ->values()
            ->toArray();

        // ── Members: active in last 90 days (always all-time, not date-filtered)
        $activeMembersCount = Attendance::where('church_id', $churchId)
            ->where('status', 'present')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subDays(90))
            ->distinct()
            ->count('user_id');

        // ── Members: by department ────────────────────────────────────────────
        $membersByDept = Department::forChurch($churchId)
            ->withCount('members')
            ->where('is_active', true)
            ->orderByDesc('members_count')
            ->take(8)
            ->get(['id', 'name'])
            ->map(fn ($d) => ['name' => $d->name, 'count' => $d->members_count])
            ->toArray();

        // ── Tasks: overdue ────────────────────────────────────────────────────
        $overdueTasks = Task::forChurch($churchId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        // ── Tasks: by department ──────────────────────────────────────────────
        $tasksByDept = Task::forChurch($churchId)
            ->when($fromDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
            ->with('department:id,name')
            ->get(['id', 'status', 'department_id'])
            ->groupBy(fn ($t) => $t->department?->name ?? 'Unassigned')
            ->map(fn ($group, $name) => [
                'name'      => $name,
                'total'     => $group->count(),
                'completed' => $group->where('status', 'completed')->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->take(8)
            ->toArray();

        // ── Tasks: by priority ────────────────────────────────────────────────
        $tasksByPriority = Task::forChurch($churchId)
            ->when($fromDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->get(['id', 'status', 'priority'])
            ->groupBy('priority')
            ->map(fn ($group, $priority) => [
                'priority'  => ucfirst($priority ?? 'none'),
                'total'     => $group->count(),
                'completed' => $group->where('status', 'completed')->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->toArray();

        // ── Events: RSVP totals ───────────────────────────────────────────────
        $eventRsvpBase = Event::forChurch($churchId)
            ->where('rsvp_enabled', true)
            ->when($fromDate, fn ($q) => $q->where('start_at', '>=', $fromDate))
            ->withCount(Event::rsvpCountConstraints())
            ->get(['id', 'title', 'start_at']);

        $rsvpTotals = [
            'going' => $eventRsvpBase->sum('going_count'),
            'maybe' => $eventRsvpBase->sum('maybe_count'),
        ];

        $topEvents = $eventRsvpBase
            ->sortByDesc('going_count')
            ->take(5)
            ->map(fn ($e) => [
                'title' => $e->title,
                'date'  => $e->start_at?->format('j M Y'),
                'going' => (int) ($e->going_count ?? 0),
                'maybe' => (int) ($e->maybe_count ?? 0),
            ])
            ->values()
            ->toArray();

        // ── Volunteers: assignment stats ──────────────────────────────────────
        $volunteerCounts = VolunteerAssignment::forChurch($churchId)
            ->when($fromDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->status => (int) $r->cnt]);

        $totalAssignments = $volunteerCounts->sum();
        $confirmedCount   = $volunteerCounts->get('confirmed', 0);
        $declinedCount    = $volunteerCounts->get('declined', 0);
        $pendingCount     = $volunteerCounts->get('pending', 0);

        // ── Volunteers: top participants ──────────────────────────────────────
        $topVolunteerRows = VolunteerAssignment::forChurch($churchId)
            ->where('status', 'confirmed')
            ->when($fromDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->select('user_id', DB::raw('count(*) as assignments'))
            ->groupBy('user_id')
            ->orderByDesc('assignments')
            ->take(5)
            ->get();

        $volunteerNames = User::whereIn('id', $topVolunteerRows->pluck('user_id'))
            ->pluck('name', 'id');

        $topVolunteers = $topVolunteerRows
            ->map(fn ($r) => [
                'name'        => $volunteerNames[$r->user_id] ?? 'Unknown',
                'assignments' => (int) $r->assignments,
            ])
            ->toArray();

        // ── Communication: broadcast stats ───────────────────────────────────────
        $broadcastStats = \App\Models\Broadcast::forChurch($churchId)
            ->when($fromDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->status => (int) $r->cnt]);

        $totalBroadcasts    = $broadcastStats->sum();
        $sentBroadcasts     = $broadcastStats->get('sent', 0);
        $draftBroadcasts    = $broadcastStats->get('draft', 0);
        $failedBroadcasts   = $broadcastStats->get('failed', 0);

        $broadcastDelivery = \App\Models\Broadcast::forChurch($churchId)
            ->where('status', 'sent')
            ->when($fromDate, fn ($q) => $q->where('sent_at', '>=', $fromDate))
            ->select(
                DB::raw('SUM(recipient_count) as total_recipients'),
                DB::raw('SUM(delivered_count) as total_delivered'),
            )
            ->first();

        $overallDeliveryRate = ($broadcastDelivery->total_recipients ?? 0) > 0
            ? round(($broadcastDelivery->total_delivered / $broadcastDelivery->total_recipients) * 100, 1)
            : null;

        // Recent broadcasts (last 5 sent)
        $recentBroadcasts = \App\Models\Broadcast::forChurch($churchId)
            ->where('status', 'sent')
            ->orderByDesc('sent_at')
            ->take(5)
            ->get(['id', 'title', 'recipient_count', 'delivered_count', 'sent_at'])
            ->map(fn ($b) => [
                'title'          => $b->title,
                'sent_at'        => $b->sent_at?->format('j M Y'),
                'recipients'     => $b->recipient_count,
                'delivered'      => $b->delivered_count,
                'delivery_rate'  => $b->recipient_count > 0
                    ? round($b->delivered_count / $b->recipient_count * 100, 1)
                    : 0,
            ])
            ->toArray();

        // ═══════════════════════════════════════════════════════════════════════
        // BUILD CACHED RETURN VALUE
        // ═══════════════════════════════════════════════════════════════════════

        $statsInner = [
            'people' => [
                'total_members'    => $totalMembers,
                'verified_members' => $verifiedMembers,
                'active_depts'     => $activeDepts,
                'total_depts'      => $totalDepts,
                'role_breakdown'   => $roleBreakdown,
            ],
            'events' => [
                'total'    => $totalEvents,
                'upcoming' => $upcomingEvents,
                'past'     => $pastEvents,
            ],
            'tasks' => [
                'total'           => $totalTasks,
                'completed'       => $completedTasks,
                'pending'         => $pendingTasks,
                'completion_rate' => $taskCompletionRate,
            ],
            'content' => [
                'announcements_total'     => $totalAnnouncements,
                'announcements_published' => $publishedAnnouncements,
                'sermons_total'           => $totalSermons,
                'sermons_public'          => $publicSermons,
            ],
            'attendance' => [
                'total_sessions'     => $totalSessions,
                'completed_sessions' => $completedSessions,
                'avg_rate'           => $avgRate ? round((float) $avgRate, 1) : null,
                'recent_rates'       => $recentSessionRates,
            ],
        ];

        $analyticsInner = [
            'attendance' => [
                'monthly_trend' => $attendanceMonthly,
                'by_department' => $attendanceByDept,
                'by_type'       => $attendanceByType,
            ],
            'members' => [
                'monthly_growth'  => $memberGrowth,
                'active_count'    => $activeMembersCount,
                'by_department'   => $membersByDept,
            ],
            'tasks' => [
                'overdue'       => $overdueTasks,
                'by_department' => $tasksByDept,
                'by_priority'   => $tasksByPriority,
            ],
            'events' => [
                'rsvp_totals' => $rsvpTotals,
                'top_events'  => $topEvents,
            ],
            'volunteers' => [
                'total_assignments' => $totalAssignments,
                'confirmed'         => $confirmedCount,
                'declined'          => $declinedCount,
                'pending'           => $pendingCount,
                'top_volunteers'    => $topVolunteers,
            ],
            'communication' => [
                'total_broadcasts'      => $totalBroadcasts,
                'sent'                  => $sentBroadcasts,
                'draft'                 => $draftBroadcasts,
                'failed'                => $failedBroadcasts,
                'overall_delivery_rate' => $overallDeliveryRate,
                'total_recipients'      => (int) ($broadcastDelivery->total_recipients ?? 0),
                'total_delivered'       => (int) ($broadcastDelivery->total_delivered ?? 0),
                'recent_broadcasts'     => $recentBroadcasts,
            ],
        ];

        return [$statsInner, $analyticsInner];

        }); // end cache()->remember()

        // ═══════════════════════════════════════════════════════════════════════
        // RENDER
        // ═══════════════════════════════════════════════════════════════════════

        return Inertia::render('Dashboard/Reports/Index', [
            'exportSections' => ['members', 'attendance', 'tasks', 'events', 'volunteers'],
            'filters'        => ['date_range' => $dateRange, 'department_id' => $deptId],
            'departments'    => $departments,
            'stats'          => $stats,
            'analytics'      => $analytics,
        ]);
    }

    /**
     * GET /dashboard/reports/export?section={members|attendance|tasks|events}
     */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $section  = $request->get('section', 'members');
        $churchId = $this->resolvedChurchId();
        $date     = now()->format('Y-m-d');

        $validSections = ['members', 'attendance', 'tasks', 'events', 'volunteers'];
        abort_unless(in_array($section, $validSections, true), 422, 'Invalid export section.');

        $filename = "church-report-{$section}-{$date}.csv";

        return response()->streamDownload(function () use ($section, $churchId) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            match ($section) {
                'members'    => $this->exportMembers($out, $churchId),
                'attendance' => $this->exportAttendance($out, $churchId),
                'tasks'      => $this->exportTasks($out, $churchId),
                'events'     => $this->exportEvents($out, $churchId),
                'volunteers' => $this->exportVolunteers($out, $churchId),
            };

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── CSV section writers ────────────────────────────────────────────────────

    private function exportMembers($handle, int $churchId): void
    {
        fputcsv($handle, ['Name', 'Email', 'Email Verified', 'Roles', 'Departments', 'Joined']);

        User::where('church_id', $churchId)
            ->with('roles', 'departments:id,name')
            ->orderBy('name')
            ->chunk(200, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->name,
                        $user->email,
                        $user->email_verified_at ? 'Yes' : 'No',
                        $user->getRoleNames()->join(', '),
                        $user->departments->pluck('name')->join(', '),
                        $user->created_at?->format('Y-m-d'),
                    ]);
                }
            });
    }

    private function exportAttendance($handle, int $churchId): void
    {
        fputcsv($handle, ['Session', 'Type', 'Date', 'Status', 'Total Records', 'Present', 'Absent', 'Rate %']);

        AttendanceSession::forChurch($churchId)
            ->withCount([
                'attendances',
                'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
            ])
            ->orderByDesc('scheduled_at')
            ->chunk(200, function ($sessions) use ($handle) {
                foreach ($sessions as $s) {
                    $total   = $s->attendances_count;
                    $present = $s->present_count ?? 0;
                    $absent  = $total - $present;
                    $rate    = $total > 0 ? round($present / $total * 100, 1) : '';

                    fputcsv($handle, [
                        $s->title,
                        $s->type,
                        $s->scheduled_at?->format('Y-m-d'),
                        $s->status,
                        $total,
                        $present,
                        $absent,
                        $rate !== '' ? "{$rate}%" : '',
                    ]);
                }
            });
    }

    private function exportTasks($handle, int $churchId): void
    {
        fputcsv($handle, ['Title', 'Priority', 'Status', 'Assigned To', 'Department', 'Due Date', 'Completed At']);

        Task::forChurch($churchId)
            ->with('assignee:id,name', 'department:id,name')
            ->orderByDesc('created_at')
            ->chunk(200, function ($tasks) use ($handle) {
                foreach ($tasks as $task) {
                    fputcsv($handle, [
                        $task->title,
                        $task->priority,
                        $task->status,
                        $task->assignee?->name ?? '—',
                        $task->department?->name ?? '—',
                        $task->due_at?->format('Y-m-d') ?? '',
                        $task->completed_at?->format('Y-m-d') ?? '',
                    ]);
                }
            });
    }

    private function exportEvents($handle, int $churchId): void
    {
        fputcsv($handle, ['Title', 'Date', 'Location', 'Department', 'RSVP Enabled', 'Going', 'Maybe', 'Capacity']);

        Event::forChurch($churchId)
            ->with('department:id,name')
            ->withCount(Event::rsvpCountConstraints())
            ->orderByDesc('start_at')
            ->chunk(200, function ($events) use ($handle) {
                foreach ($events as $event) {
                    fputcsv($handle, [
                        $event->title,
                        $event->start_at?->format('Y-m-d H:i'),
                        $event->location ?? '',
                        $event->department?->name ?? '—',
                        $event->rsvp_enabled ? 'Yes' : 'No',
                        $event->going_count ?? 0,
                        $event->maybe_count ?? 0,
                        $event->capacity ?? '',
                    ]);
                }
            });
    }

    private function exportVolunteers($handle, int $churchId): void
    {
        fputcsv($handle, ['Volunteer', 'Position', 'Department', 'Service Plan', 'Scheduled Date', 'Status', 'Assigned At']);

        VolunteerAssignment::forChurch($churchId)
            ->with([
                'volunteer:id,name',
                'planPosition.servingPosition:id,name',
                'planPosition.servingPosition.department:id,name',
                'planPosition.plan:id,title,scheduled_at',
            ])
            ->orderByDesc('created_at')
            ->chunk(200, function ($assignments) use ($handle) {
                foreach ($assignments as $a) {
                    fputcsv($handle, [
                        $a->volunteer?->name ?? '—',
                        $a->planPosition?->servingPosition?->name ?? '—',
                        $a->planPosition?->servingPosition?->department?->name ?? '—',
                        $a->planPosition?->plan?->title ?? '—',
                        $a->planPosition?->plan?->scheduled_at?->format('Y-m-d H:i') ?? '',
                        $a->status,
                        $a->created_at?->format('Y-m-d'),
                    ]);
                }
            });
    }
}
