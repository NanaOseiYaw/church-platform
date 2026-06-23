<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;

class ChurchPolicy
{
    public function update(User $user, mixed $church = null): bool
    {
        return $user->hasAnyRole(['super_admin', 'church_admin']);
    }
}
