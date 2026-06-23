<?php

namespace App\Policies;

use App\Models\Sermon;
use App\Models\User;

class SermonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sermons.view');
    }

    public function view(User $user, Sermon $sermon): bool
    {
        return $user->can('sermons.view')
            && $user->church_id === $sermon->church_id;
    }

    public function create(User $user): bool
    {
        // The seeder uses 'sermons.upload' as the upload/create permission
        return $user->can('sermons.upload');
    }

    public function update(User $user, Sermon $sermon): bool
    {
        return $user->can('sermons.edit')
            && $user->church_id === $sermon->church_id;
    }

    public function delete(User $user, Sermon $sermon): bool
    {
        return $user->can('sermons.delete')
            && $user->church_id === $sermon->church_id;
    }
}
