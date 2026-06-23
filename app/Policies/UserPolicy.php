<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('members.view');
    }

    public function view(User $current, User $target): bool
    {
        return $current->can('members.view')
            && $current->church_id === $target->church_id;
    }

    public function update(User $current, User $target): bool
    {
        // Admins can edit anyone in the same church; users can edit themselves
        return ($current->can('members.edit') && $current->church_id === $target->church_id)
            || $current->id === $target->id;
    }

    public function delete(User $current, User $target): bool
    {
        return $current->can('members.delete')
            && $current->church_id === $target->church_id
            && $current->id !== $target->id; // can't delete yourself
    }
}
