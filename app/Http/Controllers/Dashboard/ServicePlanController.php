<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServicePlanResource;
use App\Models\ServicePlan;
use App\Models\ServicePlanPosition;
use App\Models\ServingPosition;
use App\Notifications\VolunteerAssigned;
use App\Notifications\VolunteerRemoved;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicePlanController extends Controller
{
    use ResolvesChurchData, LogsAuditEvents;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServicePlan::class);

        $tab = $request->input('tab', 'upcoming');

        $query = ServicePlan::with('creator:id,name,avatar')
            ->withCount('planPositions as total_positions')
            ->withCount(['planPositions as filled_positions' => fn ($q) =>
                $q->whereHas('assignments', fn ($a) => $a->where('status', '!=', 'declined'))
            ])
            ->orderByDesc('scheduled_at');

        if ($tab === 'draft')         $query->draft();
        elseif ($tab === 'archived')  $query->archived();
        elseif ($tab === 'upcoming')  $query->published()->upcoming();
        elseif ($tab === 'past')      $query->published()->past();

        $plans = $query->paginate(20)->through(fn ($p) =>
            ServicePlanResource::make($p)->toArray($request)
        );

        return Inertia::render('Dashboard/Scheduling/Plans/Index', [
            'plans'   => $plans,
            'filters' => $request->only('tab'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ServicePlan::class);

        return Inertia::render('Dashboard/Scheduling/Plans/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ServicePlan::class);

        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date'],
            'location'     => ['nullable', 'string', 'max:200'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $plan = ServicePlan::create([
            ...$validated,
            'church_id'  => $this->resolvedChurchId(),
            'created_by' => $request->user()->id,
            'status'     => 'draft',
        ]);

        $this->auditLog('schedule.plan.created', $plan);

        return redirect()
            ->route('dashboard.scheduling.plans.show', $plan)
            ->with('success', "\"{$plan->title}\" created.");
    }

    public function show(Request $request, ServicePlan $plan): Response
    {
        $this->authorize('view', $plan);

        $plan->load([
            'creator:id,name,avatar',
            'planPositions.servingPosition.department:id,name,icon,color',
            'planPositions.assignments.volunteer:id,name,avatar',
            'attendanceSession:id,title,status,scheduled_at',
        ]);

        $availablePositions = ServingPosition::with('department:id,name,icon,color')
            ->active()
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->get();

        $user = $request->user();

        $attendanceSession = $plan->attendanceSession
            ? [
                'id'     => $plan->attendanceSession->id,
                'title'  => $plan->attendanceSession->title,
                'status' => $plan->attendanceSession->status,
            ]
            : null;

        return Inertia::render('Dashboard/Scheduling/Plans/Show', [
            'plan'               => ServicePlanResource::make($plan),
            'members'            => $this->churchMembers(),
            'availablePositions' => $availablePositions->map(fn ($p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'department' => $p->department
                    ? ['id' => $p->department->id, 'name' => $p->department->name]
                    : null,
            ])->filter(fn ($p) => $p['department'] !== null)->values(),
            'canManage'         => $user->can('update', $plan),
            'canPublish'        => $user->can('publish', $plan),
            'canDelete'         => $user->can('delete', $plan),
            'attendanceSession' => $attendanceSession,
        ]);
    }

    public function update(Request $request, ServicePlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date'],
            'location'     => ['nullable', 'string', 'max:200'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $plan->update($validated);
        $this->auditLog('schedule.plan.updated', $plan);

        return back()->with('success', 'Plan updated.');
    }

    public function destroy(ServicePlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $this->auditLog('schedule.plan.deleted', $plan);
        $plan->delete();

        return redirect()
            ->route('dashboard.scheduling.plans.index')
            ->with('success', "\"{$plan->title}\" deleted.");
    }

    public function publish(Request $request, ServicePlan $plan): RedirectResponse
    {
        $this->authorize('publish', $plan);

        abort_if(! $plan->isDraft(), 422, 'Only draft plans can be published.');

        $plan->update([
            'status'       => 'published',
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);
        $this->auditLog('schedule.plan.published', $plan);

        // Notify each assigned (non-declined) volunteer with their specific position info
        $plan->load('planPositions.assignments.volunteer', 'planPositions.servingPosition');

        $assignmentsToNotify = $plan->planPositions
            ->flatMap(fn ($pp) => $pp->assignments->where('status', '!=', 'declined'))
            ->filter(fn ($a) => $a->volunteer !== null);

        foreach ($assignmentsToNotify as $assignment) {
            $assignment->volunteer->notify(new VolunteerAssigned($assignment));
        }

        return back()->with('success', 'Plan published.');
    }

    public function archive(ServicePlan $plan): RedirectResponse
    {
        $this->authorize('archive', $plan);

        abort_if($plan->isArchived(), 422, 'Plan is already archived.');

        $plan->update(['status' => 'archived']);
        $this->auditLog('schedule.plan.archived', $plan);

        return back()->with('success', 'Plan archived.');
    }

    public function addPosition(Request $request, ServicePlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        abort_if($plan->isArchived(), 422, 'Cannot modify an archived plan.');

        $validated = $request->validate([
            'serving_position_id' => ['required', 'integer', 'exists:serving_positions,id'],
            'notes'               => ['nullable', 'string', 'max:500'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        // Ensure the serving position belongs to this church
        $servingPosition = ServingPosition::findOrFail($validated['serving_position_id']);
        abort_if($servingPosition->church_id !== $this->resolvedChurchId(), 403);

        ServicePlanPosition::create([
            'service_plan_id'     => $plan->id,
            'serving_position_id' => $validated['serving_position_id'],
            'notes'               => $validated['notes'] ?? null,
            'sort_order'          => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Position added to plan.');
    }

    public function removePosition(Request $request, ServicePlan $plan, ServicePlanPosition $pp): RedirectResponse
    {
        $this->authorize('update', $plan);

        abort_if($plan->isArchived(), 422, 'Cannot modify an archived plan.');
        abort_if($pp->service_plan_id !== $plan->id, 404);

        // Notify any assigned volunteers that their slot is removed
        $pp->load('assignments.volunteer', 'servingPosition', 'plan');
        foreach ($pp->assignments()->where('status', '!=', 'declined')->with('volunteer')->get() as $a) {
            $a->volunteer?->notify(new VolunteerRemoved($a));
        }

        $pp->delete();

        return back()->with('success', 'Position removed from plan.');
    }
}
