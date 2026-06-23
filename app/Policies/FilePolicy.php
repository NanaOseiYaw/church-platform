<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;

class FilePolicy
{
    /**
     * Any user with the media.view permission may browse the media library.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('media.view');
    }

    /**
     * Public files are viewable by anyone; private files require same-church membership.
     *
     * Files directly attached to a department (fileable_type = 'department') additionally
     * require the user to be a current member of that department, or a church admin.
     * This ensures that removing a user from a department immediately revokes their
     * ability to download files uploaded to that department's workspace.
     *
     * Files attached to other entities (events, announcements, tasks) rely on those
     * entities' own policies to control access — no extra check here.
     */
    public function view(User $user, File $file): bool
    {
        if ($file->is_public) {
            return true;
        }

        if ($user->church_id !== $file->church_id) {
            return false;
        }

        // Department-scoped files: verify current membership
        if ($file->fileable_type === 'department' && $file->fileable_id !== null) {
            return $user->can('departments.edit')
                || $user->departments()
                    ->where('departments.id', $file->fileable_id)
                    ->exists();
        }

        return true;
    }

    /**
     * Any authenticated church member may upload files.
     */
    public function create(User $user): bool
    {
        return $user->church_id !== null;
    }

    /**
     * The uploader or a church admin may delete a file.
     */
    public function delete(User $user, File $file): bool
    {
        if ($user->church_id !== $file->church_id) {
            return false;
        }

        // uploaded_by is nullable (set to NULL when the uploader is deleted),
        // so guard against a null match that would otherwise allow any user
        // with ID matching NULL (there are none, but be explicit).
        return ($file->uploaded_by !== null && $user->id === $file->uploaded_by)
            || $user->hasPermissionTo('media.delete');
    }
}
