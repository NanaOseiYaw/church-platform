<?php

namespace App\Events;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class TaskWasUpdated
{
    use Dispatchable;
    public function __construct(
        public readonly Task $task,
        public readonly User $actor,
    ) {}
}
