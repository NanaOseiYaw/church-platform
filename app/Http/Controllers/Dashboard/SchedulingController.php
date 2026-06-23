<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServicePlanResource;
use App\Models\ServicePlan;
use App\Models\VolunteerAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchedulingController extends Controller
{
    use ResolvesChurchData;

    public function dashboard(Request $request): Response
    {
        abort_unless($request->user()->can('scheduling.manage'), 403);

        $churchId = $this->resolvedChurchId();

        $stats = [
            'total'     => ServicePlan::forChurch($churchId)->count(),
            'published' => ServicePlan::forChurch($churchId)->published()->count(),
            'draft'     => ServicePlan::forChurch($churchId)->draft()->count(),
            'archived'  => ServicePlan::forChurch($churchId)->archived()->count(),
        ];

        $upcoming = ServicePlan::forChurch($churchId)->with('creator:id,name,avatar')
            ->withCount('planPositions as total_positions')
            ->withCount(['planPositions as filled_positions' => fn ($q) =>
                $q->whereHas('assignments', fn ($a) => $a->where('status', '!=', 'declined'))
            ])
            ->published()
            ->upcoming()
            ->limit(6)
            ->get()
            ->map(fn ($p) => ServicePlanResource::make($p)->toArray($request));

        return Inertia::render('Dashboard/Scheduling/Dashboard', [
            'stats'    => $stats,
            'upcoming' => $upcoming,
        ]);
    }

    public function mySchedule(Request $request): Response
    {
        abort_unless($request->user()->can('scheduling.view'), 403);

        $user = $request->user();

        $assignments = VolunteerAssignment::where('user_id', $user->id)
            ->where('church_id', $this->resolvedChurchId())
            ->where('status', '!=', 'declined')
            ->with([
                'planPosition.plan',
                'planPosition.servingPosition.department:id,name,icon,color',
            ])
            ->whereHas('planPosition.plan', fn ($q) =>
                // Show last 4 weeks of history + all future (any plan status)
                $q->where('scheduled_at', '>=', now()->subWeeks(4)->startOfDay())
            )
            ->get()
            ->sortBy('planPosition.plan.scheduled_at')
            ->map(fn ($a) => [
                'id'     => $a->id,
                'status' => $a->status,
                'plan'   => [
                    'id'                     => $a->planPosition->plan->id,
                    'title'                  => $a->planPosition->plan->title,
                    'status'                 => $a->planPosition->plan->status,
                    'scheduled_at'           => $a->planPosition->plan->scheduled_at?->toJSON(),
                    'scheduled_at_formatted' => $a->planPosition->plan->scheduled_at?->format('D, j M Y'),
                    'scheduled_time'         => $a->planPosition->plan->scheduled_at?->format('g:i A'),
                    'location'               => $a->planPosition->plan->location,
                ],
                'position' => [
                    'name'       => $a->planPosition->servingPosition?->name,
                    'department' => $a->planPosition->servingPosition?->department ? [
                        'id'    => $a->planPosition->servingPosition->department->id,
                        'name'  => $a->planPosition->servingPosition->department->name,
                        'icon'  => $a->planPosition->servingPosition->department->icon,
                        'color' => $a->planPosition->servingPosition->department->color,
                    ] : null,
                ],
            ])
            ->values();

        return Inertia::render('Dashboard/Scheduling/MySchedule', [
            'assignments' => $assignments,
        ]);
    }
}
