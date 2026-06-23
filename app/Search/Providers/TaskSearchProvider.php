<?php

namespace App\Search\Providers;

use App\Models\Task;
use App\Models\User;
use App\Search\Contracts\SearchProvider;
use App\Search\SearchResult;

class TaskSearchProvider implements SearchProvider
{
    public function getType(): string  { return 'task'; }
    public function getLabel(): string { return 'Tasks'; }

    public function isAvailable(User $user): bool
    {
        return $user->can('tasks.view_own');
    }

    public function search(string $query, User $user, int $churchId, int $limit): array
    {
        $builder = Task::where('church_id', $churchId)
            ->where(function ($b) use ($query) {
                $b->where('title',       'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });

        // Members with only view_own see only tasks assigned to/by them
        if (! $user->can('tasks.view_all')) {
            $builder->where(function ($b) use ($user) {
                $b->where('assigned_to', $user->id)
                  ->orWhere('assigned_by', $user->id);
            });
        }

        $results = $builder
            ->with('assignee:id,name')
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        return $results->map(function (Task $task) {
            $priorityColors = [
                'urgent' => 'rose',
                'high'   => 'orange',
                'medium' => 'amber',
                'low'    => 'neutral',
            ];

            $subtitle = $task->assignee?->name
                ? 'Assigned to ' . $task->assignee->name
                : null;

            $isOverdue = $task->due_at
                && $task->due_at->isPast()
                && ! in_array($task->status, ['completed', 'cancelled']);

            return new SearchResult(
                id:         $task->id,
                type:       $this->getType(),
                title:      $task->title,
                subtitle:   $subtitle,
                meta:       $task->due_at?->format('M j, Y'),
                url:        "/dashboard/tasks/{$task->id}",
                icon:       'check-square',
                badge:      $isOverdue ? 'Overdue' : ucfirst($task->priority),
                badgeColor: $isOverdue ? 'rose' : ($priorityColors[$task->priority] ?? 'neutral'),
            );
        })->all();
    }
}
