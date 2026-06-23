<?php

namespace App\Events;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class DepartmentMemberRoleWasChanged
{
    use Dispatchable;
    public function __construct(
        public readonly Department $department,
        public readonly User       $member,   // the user whose role changed
        public readonly User       $actor,    // who made the change
        public readonly string     $newRole,
    ) {}
}
