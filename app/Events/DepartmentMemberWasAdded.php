<?php

namespace App\Events;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class DepartmentMemberWasAdded
{
    use Dispatchable;
    public function __construct(
        public readonly Department $department,
        public readonly User       $member,   // the user who was added
        public readonly User       $actor,    // who added them
        public readonly string     $role,     // the role they were given
    ) {}
}
