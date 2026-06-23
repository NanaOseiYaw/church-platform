<?php

namespace App\Policies;

use App\Models\PrayerRequest;
use App\Models\User;

class PrayerRequestPolicy
{
    /** Admins and coordinators can view prayer requests. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('church_admin') || $user->hasRole('coordinator');
    }

    public function update(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->hasRole('church_admin') && $user->church_id === $prayerRequest->church_id;
    }

    public function delete(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->hasRole('church_admin') && $user->church_id === $prayerRequest->church_id;
    }
}
