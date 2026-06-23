<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Traits\LogsAuditEvents;
use App\Models\ServicePlanPosition;
use App\Models\User;
use App\Models\VolunteerAssignment;
use App\Notifications\VolunteerAssigned;
use App\Notifications\VolunteerDeclined;
use App\Notifications\VolunteerRemoved;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    use LogsAuditEvents;
    /**
     * GET /dashboard/scheduling/assignments/{assignment}
     * Handles stale notification links that deep-linked to this URL before
     * notifications were updated to point at /my-schedule or /plans/{id}.
     * Redirects to the most useful destination for the current user.
     */
    public function show(Request $request, VolunteerAssignment $assignment): RedirectResponse
    {
        $user = $request->user();

        // Volunteer viewing their own assignment → My Schedule
        if ($assignment->user_id === $user->id) {
            return redirect('/dashboard/scheduling/my-schedule');
        }

        // Coordinator/admin — take them to the plan if it still exists
        $planId = $assignment->planPosition?->plan_id;
        if ($planId) {
            return redirect("/dashboard/scheduling/plans/{$planId}");
        }

        // Fallback — scheduling overview
        return redirect('/dashboard/scheduling');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_plan_position_id' => ['required', 'integer', 'exists:service_plan_positions,id'],
            'user_id'                  => ['required', 'integer', 'exists:users,id'],
            'notes'                    => ['nullable', 'string', 'max:500'],
        ]);

        $planPosition = ServicePlanPosition::with('plan', 'servingPosition')->findOrFail($validated['service_plan_position_id']);

        $this->authorize('create', [VolunteerAssignment::class, $planPosition]);

        abort_if($planPosition->plan->isArchived(), 422, 'Cannot assign to an archived plan.');

        // Unique check — give a user-friendly error instead of letting the DB throw
        $alreadyExists = VolunteerAssignment::where('service_plan_position_id', $planPosition->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($alreadyExists) {
            return back()->withErrors(['user_id' => 'This volunteer is already assigned to this slot.']);
        }

        $volunteer  = User::findOrFail($validated['user_id']);
        $assignment = VolunteerAssignment::create([
            'church_id'                => $planPosition->plan->church_id,
            'service_plan_position_id' => $planPosition->id,
            'user_id'                  => $validated['user_id'],
            'assigned_by'              => $request->user()->id,
            'status'                   => 'pending',
            'notes'                    => $validated['notes'] ?? null,
        ]);
        $deptId = $planPosition->servingPosition?->department_id;
        $this->auditLog(
            'schedule.assignment.created',
            $assignment,
            [],
            ['user_id' => $assignment->user_id, 'position' => $planPosition->servingPosition?->name],
            array_filter(['department_id' => $deptId]),
        );

        // Always notify the volunteer immediately on assignment
        $assignment->load('planPosition.plan', 'planPosition.servingPosition');
        $volunteer->notify(new VolunteerAssigned($assignment));

        return back()->with('success', 'Volunteer assigned.');
    }

    public function destroy(Request $request, VolunteerAssignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);

        $assignment->load('planPosition.plan', 'planPosition.servingPosition', 'volunteer');

        $volunteer = $assignment->volunteer;
        $assignment->delete();
        $this->auditLog('schedule.assignment.removed', $assignment);

        $volunteer?->notify(new VolunteerRemoved($assignment));

        return back()->with('success', 'Assignment removed.');
    }

    public function respond(Request $request, VolunteerAssignment $assignment): RedirectResponse
    {
        $this->authorize('respond', $assignment);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'declined'])],
        ]);

        $assignment->update([
            'status'       => $validated['status'],
            'responded_at' => now(),
        ]);
        $action = $validated['status'] === 'confirmed' ? 'schedule.assignment.confirmed' : 'schedule.assignment.declined';
        $deptId = $assignment->planPosition?->servingPosition?->department_id;
        $this->auditLog($action, $assignment, [], ['status' => $validated['status']], array_filter(['department_id' => $deptId]));

        if ($validated['status'] === 'declined') {
            $assignment->load('planPosition.plan', 'planPosition.servingPosition', 'volunteer');

            // Notify plan creator
            $creator = $assignment->planPosition->plan->creator;
            $creator?->notify(new VolunteerDeclined($assignment));

            // Notify dept coordinator (if different from creator)
            $dept = $assignment->planPosition->servingPosition?->department;
            $dept?->load('coordinator');
            if ($dept?->coordinator_id && $dept->coordinator_id !== $creator?->id) {
                $dept->coordinator?->notify(new VolunteerDeclined($assignment));
            }
        }

        return back()->with('success', 'Response saved.');
    }
}
