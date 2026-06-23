<?php

namespace App\Policies;

use App\Enums\DepartmentRole;
use App\Enums\DepartmentVisibility;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        if (! $user->can('departments.view')) {
            return false;
        }

        if ($user->church_id !== $department->church_id) {
            return false;
        }

        // PRIVATE and MEMBERS_ONLY workspaces are restricted to current members
        // and church admins. MEMBERS_ONLY is intentionally treated identically
        // to PRIVATE here — the difference is cosmetic (shown vs hidden in the
        // index); access control is the same.
        if (in_array($department->visibility, [
            DepartmentVisibility::PRIVATE,
            DepartmentVisibility::MEMBERS_ONLY,
        ], strict: true)) {
            return $user->can('departments.edit')
                || $department->members()->where('users.id', $user->id)->exists();
        }

        return true; // PUBLIC → any church member with departments.view
    }

    public function create(User $user): bool
    {
        return $user->can('departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('departments.edit')
            && $user->church_id === $department->church_id;
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can('departments.delete')
            && $user->church_id === $department->church_id;
    }

    /**
     * Gate for POST /departments/{dept}/members — adding a new pivot row.
     * Both coordinators and assistant-coordinators may add members.
     * The narrower updateMemberRole gate covers role changes.
     */
    public function manageMembers(User $user, Department $department): bool
    {
        // Tenant boundary — always enforced first.
        if ($user->church_id !== $department->church_id) {
            return false;
        }

        // Church administrators hold departments.edit and can manage any
        // department in the church without needing a pivot entry.
        if ($user->can('departments.edit')) {
            return true;
        }

        // Department-scoped authority: only users who hold a leadership pivot
        // role in THIS specific department may manage its members.
        //
        // This replaces the previous permission-only check, which had two flaws:
        //   1. assistant_coordinator (Spatie) lacked departments.manage_members →
        //      always blocked, even when they were the department coordinator.
        //   2. coordinator (Spatie) had departments.manage_members globally →
        //      could manage every department, violating tenant-safety isolation.
        //
        // Checking the pivot ensures:
        //   • any user (regardless of Spatie role) who is coordinator or
        //     assistant_coordinator of THIS department can manage it, and
        //   • they cannot manage departments they don't lead.
        $pivotRole = $department->members()
            ->where('users.id', $user->id)
            ->first()?->pivot?->role;

        return in_array($pivotRole, [
            DepartmentRole::COORDINATOR->value,
            DepartmentRole::ASSISTANT_COORDINATOR->value,
        ], strict: true);
    }

    /**
     * Gate for PATCH /departments/{dept}/members/{user} — changing a pivot role.
     *
     * Only pivot coordinators and church administrators may change roles.
     * Assistant coordinators can add/remove members (manageMembers) but have
     * no role-assignment authority — they are excluded here intentionally.
     *
     * Fine-grained rules (no self-edit, no promotion to coordinator, no
     * modifying another coordinator, last-coordinator protection) are enforced
     * as business-rule guards inside DepartmentController::updateMemberRole()
     * after this gate passes.
     */
    public function updateMemberRole(User $user, Department $department): bool
    {
        if ($user->church_id !== $department->church_id) {
            return false;
        }

        // Church administrators can update any member's role in any department.
        if ($user->can('departments.edit')) {
            return true;
        }

        // Only department coordinators may update roles.
        // Assistant coordinators and members are excluded.
        $pivotRole = $department->members()
            ->where('users.id', $user->id)
            ->first()?->pivot?->role;

        return $pivotRole === DepartmentRole::COORDINATOR->value;
    }
}
