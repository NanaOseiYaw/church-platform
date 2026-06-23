<?php

namespace App\Services;

use App\Enums\DepartmentRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DepartmentService
{
    /**
     * Paginated list for the dashboard index.
     * Accepts ?int so callers can pass $user->church_id (nullable FK) safely.
     */
    public function paginate(?int $churchId, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return Department::forChurch($churchId)
            ->with(['coordinator:id,name,avatar'])
            ->withCount(['members', 'announcements', 'events', 'tasks'])
            ->when($search, fn ($q, $s) => $q->where(function ($q2) use ($s) {
                $q2->where('name', 'like', "%{$s}%")
                   ->orWhere('description', 'like', "%{$s}%");
            }))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Full collection (used by public pages / dropdowns). */
    public function forChurch(?int $churchId): Collection
    {
        return Department::forChurch($churchId)
            ->with(['coordinator:id,name,avatar'])
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): Department
    {
        return Department::with(['coordinator:id,name,avatar', 'members:id,name,avatar,email'])->findOrFail($id);
    }

    public function create(array $data, int $churchId, int $creatorId): Department
    {
        return Department::create(array_merge($data, [
            'church_id'  => $churchId,
            'created_by' => $creatorId,
        ]));
    }

    public function update(Department $department, array $data): Department
    {
        $department->update($data);
        return $department->refresh();
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }

    public function addMember(Department $department, User $user, string $role = 'member'): void
    {
        $department->members()->syncWithoutDetaching([
            $user->id => ['role' => $role, 'joined_at' => now()],
        ]);
    }

    public function updateMemberRole(Department $department, User $user, string $role): void
    {
        $department->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    public function removeMember(Department $department, User $user): void
    {
        $department->members()->detach($user->id);
    }

    /**
     * All church members not yet assigned to the given department.
     */
    public function availableMembers(Department $department, ?int $churchId): Collection
    {
        if ($churchId === null) {
            return collect();
        }

        $existing = $department->members()->pluck('users.id');

        return User::where('church_id', $churchId)
            ->whereNotIn('id', $existing)
            ->orderBy('name')
            ->get(['id', 'name', 'avatar']);
    }

    /**
     * List of church staff eligible as coordinators.
     */
    public function staffForChurch(?int $churchId): Collection
    {
        if ($churchId === null) {
            return collect();
        }

        return User::where('church_id', $churchId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['church_admin', 'coordinator']))
            ->orderBy('name')
            ->get(['id', 'name', 'avatar']);
    }
}
