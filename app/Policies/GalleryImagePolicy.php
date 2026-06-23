<?php

namespace App\Policies;

use App\Models\GalleryImage;
use App\Models\User;

class GalleryImagePolicy
{
    /** Reuses media.view — coordinators and admins can see the gallery admin. */
    public function viewAny(User $user): bool
    {
        return $user->can('media.view');
    }

    public function view(User $user, GalleryImage $image): bool
    {
        return $user->can('media.view') && $user->church_id === $image->church_id;
    }

    public function create(User $user): bool
    {
        return $user->can('media.upload');
    }

    public function update(User $user, GalleryImage $image): bool
    {
        return $user->can('media.upload') && $user->church_id === $image->church_id;
    }

    public function delete(User $user, GalleryImage $image): bool
    {
        return $user->can('media.delete') && $user->church_id === $image->church_id;
    }
}
