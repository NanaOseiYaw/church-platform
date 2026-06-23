<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared tenant-scoped queries for Dashboard controllers.
 *
 * This trait must only be used in controllers that extend the base Controller,
 * which provides the resolvedChurchId() method it relies on.
 */
trait ResolvesChurchData
{
    /**
     * Active departments for the current church tenant.
     *
     * Used for department pickers on create/edit forms and filter sidebars.
     * Returns only the columns needed by frontend selects/badges.
     */
    protected function activeDepartments(): Collection
    {
        return Department::forChurch($this->resolvedChurchId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color']);
    }

    /**
     * Active members of the current church tenant.
     *
     * Used for assignee/member picker selects. Deactivated members are excluded
     * so they cannot be assigned to volunteer slots, tasks, or other actions
     * while their account is inactive.
     */
    protected function churchMembers(): Collection
    {
        return User::where('church_id', $this->resolvedChurchId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'avatar']);
    }
}
