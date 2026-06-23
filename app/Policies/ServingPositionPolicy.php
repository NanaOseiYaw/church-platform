<?php

namespace App\Policies;

use App\Models\ServingPosition;
use App\Models\User;

class ServingPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scheduling.view');
    }

    public function view(User $user, ServingPosition $position): bool
    {
        return $user->can('scheduling.view');
    }

    public function create(User $user): bool
    {
        return $user->can('scheduling.manage');
    }

    public function update(User $user, ServingPosition $position): bool
    {
        if (! $user->can('scheduling.manage')) return false;
        if ($user->can('church.edit')) return true;

        return $user->departments()
            ->where('departments.id', $position->department_id)
            ->exists();
    }

    public function delete(User $user, ServingPosition $position): bool
    {
        return $this->update($user, $position);
    }
}
