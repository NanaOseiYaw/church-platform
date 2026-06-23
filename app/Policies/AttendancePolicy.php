<?php

namespace App\Policies;

use App\Models\AttendanceSession;
use App\Models\User;

/**
 * Authorization rules for attendance sessions.
 *
 * Access matrix:
 *   super_admin   — bypassed globally (Gate::before in AppServiceProvider)
 *   church_admin  — full access within their church
 *   coordinator   — manage sessions for their own departments; view all sessions
 *   member        — no dashboard access; history via dedicated member route
 */
class AttendancePolicy
{
    /**
     * Can the user see the attendance index / session list?
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('attendance.view');
    }

    /**
     * Can the user view this specific session's attendance?
     *
     * Church admins see everything.
     * Coordinators see only sessions belonging to departments they are
     * currently a member of — removing a coordinator from a department
     * immediately revokes view access to that department's sessions.
     * Church-wide sessions (department_id IS NULL) are admin-only.
     */
    public function view(User $user, AttendanceSession $session): bool
    {
        if ($user->church_id !== $session->church_id) {
            return false;
        }

        if (! $user->hasPermissionTo('attendance.view')) {
            return false;
        }

        // Church admins bypass the department membership check
        if ($user->isChurchAdmin()) {
            return true;
        }

        // Church-wide sessions have no department; only admins may view them
        if ($session->department_id === null) {
            return false;
        }

        // Coordinator-level: must still belong to the session's department
        return $user->departments()
            ->where('departments.id', $session->department_id)
            ->exists();
    }

    /**
     * Can the user create a new attendance session?
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attendance.manage');
    }

    /**
     * Can the user edit session metadata or mark attendance within it?
     */
    public function update(User $user, AttendanceSession $session): bool
    {
        if ($user->church_id !== $session->church_id) {
            return false;
        }

        if (! $user->hasPermissionTo('attendance.manage')) {
            return false;
        }

        // Coordinators may only update sessions scoped to their own departments.
        if ($user->isCoordinator() && ! $user->isChurchAdmin()) {
            if ($session->department_id === null) {
                return false; // church-wide session — admin-only
            }

            return $user->departments()->where('departments.id', $session->department_id)->exists();
        }

        return true;
    }

    /**
     * Can the user permanently delete this session and all its records?
     */
    public function delete(User $user, AttendanceSession $session): bool
    {
        if ($user->church_id !== $session->church_id) {
            return false;
        }

        return $user->hasPermissionTo('attendance.delete');
    }

    /**
     * Alias used in controller for "mark / bulk-save attendance records".
     * Same rules as update.
     */
    public function markAttendance(User $user, AttendanceSession $session): bool
    {
        return $this->update($user, $session);
    }
}
