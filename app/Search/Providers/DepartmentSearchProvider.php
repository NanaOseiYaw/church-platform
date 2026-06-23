<?php

namespace App\Search\Providers;

use App\Models\Department;
use App\Models\User;
use App\Search\Contracts\SearchProvider;
use App\Search\SearchResult;

class DepartmentSearchProvider implements SearchProvider
{
    public function getType(): string  { return 'department'; }
    public function getLabel(): string { return 'Departments'; }

    public function isAvailable(User $user): bool
    {
        return $user->can('departments.view');
    }

    public function search(string $query, User $user, int $churchId, int $limit): array
    {
        $departments = Department::where('church_id', $churchId)
            ->where(function ($b) use ($query) {
                $b->where('name',        'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->withCount('members')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $departments->map(function (Department $dept) {
            $memberCount = $dept->members_count ?? 0;

            return new SearchResult(
                id:         $dept->id,
                type:       $this->getType(),
                title:      $dept->name,
                subtitle:   $dept->description
                                ? str($dept->description)->limit(80)->toString()
                                : null,
                meta:       $memberCount . ' ' . ($memberCount === 1 ? 'member' : 'members'),
                url:        "/dashboard/departments/{$dept->id}",
                icon:       'building',
                badge:      ! $dept->is_active ? 'Inactive' : null,
                badgeColor: 'neutral',
            );
        })->all();
    }
}
