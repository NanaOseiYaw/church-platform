<?php

namespace App\Policies;

use App\Models\ServicePlan;
use App\Models\User;

class ServicePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scheduling.view');
    }

    public function view(User $user, ServicePlan $plan): bool
    {
        return $user->can('scheduling.view');
    }

    public function create(User $user): bool
    {
        return $user->can('scheduling.manage');
    }

    public function update(User $user, ServicePlan $plan): bool
    {
        if (! $user->can('scheduling.manage')) return false;
        if ($user->can('church.edit')) return true;

        // Coordinator: allowed if they created the plan OR their dept has positions on it
        if ($plan->created_by === $user->id) return true;

        $deptIds = $plan->planPositions()
            ->join('serving_positions', 'service_plan_positions.serving_position_id', '=', 'serving_positions.id')
            ->pluck('serving_positions.department_id')
            ->unique();

        return $user->departments()->whereIn('departments.id', $deptIds)->exists();
    }

    public function publish(User $user, ServicePlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    public function archive(User $user, ServicePlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    public function delete(User $user, ServicePlan $plan): bool
    {
        return $user->hasRole('church_admin') || $user->hasRole('super_admin');
    }
}
