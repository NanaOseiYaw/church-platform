<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('announcements.view');
    }

    /**
     * A user can view an announcement if:
     *   1. They belong to the same church.
     *   2. Their role satisfies the announcement's 4-level visibility.
     *   3. Admins/editors bypass the visibility check so they can preview drafts.
     *
     * Visibility levels (source of truth: announcements.visibility column):
     *   'public'          → any church member
     *   'members_only'    → any church member
     *   'department_only' → only members of the linked department
     *   'private'         → creator only
     */
    public function view(User $user, Announcement $announcement): bool
    {
        if (! $user->can('announcements.view')) return false;
        if ($user->church_id !== $announcement->church_id) return false;

        // Admins and editors can always view (including drafts and private)
        if ($user->can('announcements.edit')) return true;

        return match ($announcement->visibility) {
            'public', 'members_only' => true,
            'department_only'        => (bool) $announcement->department
                                            ?->members()
                                            ->where('users.id', $user->id)
                                            ->exists(),
            'private'                => $announcement->created_by === $user->id,
            default                  => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->can('announcements.create');
    }

    /**
     * Permission hierarchy:
     *   – announcements.delete  → church_admin level: can edit any announcement
     *   – announcements.edit    → coordinator level: can only edit their own
     *
     * The `delete` permission is scoped to church_admin in the seeder, making it
     * a reliable stand-in for "admin-level edit access" without needing a separate
     * announcements.manage permission.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        if ($user->church_id !== $announcement->church_id) return false;

        // Church admins (delete permission) can edit any announcement
        if ($user->can('announcements.delete')) return true;

        // Coordinators (edit permission) can only edit their own announcements
        return $user->can('announcements.edit')
            && $announcement->created_by === $user->id;
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->can('announcements.delete')
            && $user->church_id === $announcement->church_id;
    }

    /**
     * Same hierarchy as update: church admins can publish any; coordinators only their own.
     */
    public function publish(User $user, Announcement $announcement): bool
    {
        if ($user->church_id !== $announcement->church_id) return false;

        if ($user->can('announcements.delete')) return true;

        return $user->can('announcements.publish')
            && $announcement->created_by === $user->id;
    }

    public function pin(User $user, Announcement $announcement): bool
    {
        return $user->can('announcements.pin')
            && $user->church_id === $announcement->church_id;
    }
}
