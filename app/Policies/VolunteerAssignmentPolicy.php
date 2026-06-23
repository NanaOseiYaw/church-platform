<?php

namespace App\Policies;

use App\Models\ServicePlanPosition;
use App\Models\VolunteerAssignment;
use App\Models\User;

class VolunteerAssignmentPolicy
{
    public function create(User $user, ServicePlanPosition $planPosition): bool
    {
        if (! $user->can('scheduling.manage')) return false;
        if ($user->can('church.edit')) return true;

        $deptId = $planPosition->servingPosition?->department_id;

        return $deptId && $user->departments()
            ->where('departments.id', $deptId)
            ->exists();
    }

    public function delete(User $user, VolunteerAssignment $assignment): bool
    {
        if (! $user->can('scheduling.manage')) return false;
        if ($user->can('church.edit')) return true;

        $deptId = $assignment->planPosition?->servingPosition?->department_id;

        return $deptId && $user->departments()
            ->where('departments.id', $deptId)
            ->exists();
    }

    public function respond(User $user, VolunteerAssignment $assignment): bool
    {
        return $assignment->user_id === $user->id;
    }
}
