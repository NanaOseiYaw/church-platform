<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        // tasks.view_all (coordinator) logically includes tasks.view_own.
        // Accept either so coordinators aren't locked out of the Tasks section.
        return $user->can('tasks.view_own') || $user->can('tasks.view_all');
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->can('tasks.view_own')) return false;
        if ($user->church_id !== $task->church_id) return false;

        // Admins / coordinators see all; members see only their own
        return $user->can('tasks.view_all')
            || $task->assigned_to === $user->id
            || $task->assigned_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->church_id !== $task->church_id) return false;

        // Admins/editors can edit any task; assigner or assignee can update their own
        return $user->can('tasks.edit')
            || $task->assigned_to === $user->id
            || $task->assigned_by === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can('tasks.delete')
            && $user->church_id === $task->church_id;
    }

    public function updateStatus(User $user, Task $task): bool
    {
        if ($user->church_id !== $task->church_id) return false;

        // Assignee can mark their own task; editors can update any
        return $user->can('tasks.edit')
            || $task->assigned_to === $user->id;
    }

    public function comment(User $user, Task $task): bool
    {
        // Anyone who can view the task may comment on it
        return $this->view($user, $task);
    }

    public function deleteComment(User $user, TaskComment $comment): bool
    {
        // Author can delete their own comment; admins can delete any
        return $user->id === $comment->user_id
            || $user->can('tasks.delete');
    }
}
