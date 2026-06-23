<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\TaskResource;
use App\Models\Announcement;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Event;
use App\Models\AuditLog;
use App\Models\Task;
use App\Models\User;
use App\Models\VolunteerAssignment;
use App\Http\Resources\AttendanceSessionResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user     = $request->user();
        $churchId = $this->resolvedChurchId();

        $upcomingEvents = Event::forChurch($churchId)
            ->published()
            ->visibleTo($user)                // hide department_only events from non-members
            ->where('start_at', '>=', now())
            ->orderBy('start_at')
            ->take(5)
            ->get();

        $recentAnnouncements = Announcement::forChurch($churchId)
            ->published()
            ->visibleTo($user)   // scope hides department_only announcements from non-members
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->take(5)
            ->get();

        // Use ->active() so tasks with status='overdue' (set by the scheduler) are
        // included alongside 'pending'/'in_progress' ones. The TaskResource computes
        // is_overdue from due_at, keeping the visual indicator consistent.
        $myTasks = Task::forChurch($churchId)
            ->where('assigned_to', $user->id)
            ->active()
            ->orderByRaw("CASE status WHEN 'overdue' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderBy('due_at')
            ->take(5)
            ->get();

        $stats = $user->hasPermissionTo('reports.view') ? [
            'total_members'      => User::where('church_id', $churchId)->count(),
            'upcoming_events'    => Event::forChurch($churchId)->where('start_at', '>=', now())->count(),
            'pending_tasks'      => Task::forChurch($churchId)->whereIn('status', ['pending', 'in_progress'])->count(),
            'active_departments' => Department::forChurch($churchId)->where('is_active', true)->count(),
        ] : null;

        // Recent attendance sessions for dashboard widget (admins/coordinators only)
        $recentSessions = $user->hasPermissionTo('attendance.view')
            ? AttendanceSession::forChurch($churchId)
                ->where('status', 'completed')
                ->orderByDesc('scheduled_at')
                ->withCount([
                    'attendances',
                    'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
                ])
                ->take(4)
                ->get()
            : collect();

        // Recent activity widget — only for users with audit.view permission
        $recentActivity = [];
        if ($user->can('audit.view')) {
            try {
                $activityQuery = AuditLog::where('church_id', $churchId)
                    ->with('user:id,name,avatar')
                    ->latest()
                    ->limit(8);

                // Coordinators: scope to their departments
                if (! $user->hasRole('church_admin') && ! $user->hasRole('super_admin')) {
                    $deptIds = $user->departments()->pluck('departments.id')->map(fn ($id) => (int) $id)->toArray();
                    if (! empty($deptIds)) {
                        $placeholders = implode(',', $deptIds);
                        $activityQuery->whereRaw("json_extract(metadata, '$.department_id') IN ({$placeholders})");
                    } else {
                        $activityQuery->whereRaw('0 = 1');
                    }
                }

                $recentActivity = $activityQuery->get()->map(fn ($log) => [
                    'id'          => $log->id,
                    'action'      => $log->action,
                    'target_name' => $log->target_name,
                    'created_at'  => $log->created_at?->toISOString(),
                    'actor'       => $log->user
                        ? ['id' => $log->user->id, 'name' => $log->user->name, 'avatar' => $log->user->avatar]
                        : null,
                ]);
            } catch (\Throwable) {
                // Audit table may not exist on first deploy
            }
        }

        // Upcoming assignments for this user — next 30 days, any plan status, max 3
        $myUpcomingAssignments = [];
        try {
            $myUpcomingAssignments = VolunteerAssignment::where('user_id', $user->id)
                ->where('status', '!=', 'declined')
                ->with(['planPosition.plan', 'planPosition.servingPosition'])
                ->whereHas('planPosition.plan', fn ($q) =>
                    $q->where('scheduled_at', '>=', now()->startOfDay())
                      ->where('scheduled_at', '<',  now()->addDays(30))
                )
                ->get()
                ->sortBy('planPosition.plan.scheduled_at')
                ->take(3)
                ->map(fn ($a) => [
                    'id'                     => $a->id,
                    'status'                 => $a->status,
                    'plan_title'             => $a->planPosition->plan->title,
                    'plan_status'            => $a->planPosition->plan->status,
                    'scheduled_at_formatted' => $a->planPosition->plan->scheduled_at?->format('D, j M'),
                    'position'               => $a->planPosition->servingPosition?->name,
                ])
                ->values()
                ->all();
        } catch (\Throwable) {
            // Silently fail if scheduling tables don't exist yet
        }

        // Pass resources directly to Inertia so PropsResolver calls toResponse()->getData(true),
        // which runs resolve() + filter() on each item — properly stripping MissingValue keys
        // (unloaded relations). Calling ->toArray() directly bypasses filter() and leaves
        // MissingValue / null-collection AnonymousResourceCollection objects in the prop array,
        // causing json_encode to crash when it tries to serialize them.
        return Inertia::render('Dashboard/Home', [
            'upcomingEvents'      => EventResource::collection($upcomingEvents),
            'recentAnnouncements' => AnnouncementResource::collection($recentAnnouncements),
            'myTasks'             => TaskResource::collection($myTasks),
            'stats'               => $stats,
            'recentSessions'      => AttendanceSessionResource::collection($recentSessions),
            'recentActivity'      => $recentActivity,
            'myUpcomingAssignments' => $myUpcomingAssignments,
        ]);
    }
}
