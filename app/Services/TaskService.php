<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TaskService
{
    // ── Priority sort order ────────────────────────────────────────────────────

    private const PRIORITY_ORDER = "CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END";
    private const STATUS_ORDER   = "CASE status WHEN 'overdue' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'pending' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END";

    // ── Query / listing ────────────────────────────────────────────────────────

    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Task::forChurch($user->church_id)
            ->visibleTo($user)
            ->with(['assignee:id,name,avatar', 'assigner:id,name', 'department:id,name,color,icon']);

        // Tab filter
        $tab = $filters['tab'] ?? 'all';
        match ($tab) {
            'mine'        => $query->where('assigned_to', $user->id),
            'assigned_by' => $query->where('assigned_by', $user->id),
            'overdue'     => $query->overdue(),
            'completed'   => $query->completed(),
            'cancelled'   => $query->cancelled(),
            default       => $query->active(),
        };

        // Free-text search
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        // Department filter
        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        // Priority filter
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return $query
            ->orderByRaw(self::STATUS_ORDER)
            ->orderByRaw(self::PRIORITY_ORDER)
            ->orderBy('due_at')
            ->paginate(20)
            ->withQueryString();
    }

    // ── CRUD ───────────────────────────────────────────────────────────────────

    public function create(array $data, User $creator): Task
    {
        return Task::create([
            'church_id'     => $creator->church_id,
            'department_id' => $data['department_id'] ?? null,
            'assigned_to'   => $data['assigned_to']   ?? null,
            'assigned_by'   => $creator->id,
            'title'         => $data['title'],
            'description'   => $data['description']   ?? null,
            'priority'      => $data['priority']       ?? 'medium',
            'status'        => 'pending',
            'due_at'        => ! empty($data['due_at']) ? $data['due_at'] : null,
        ]);
    }

    public function update(Task $task, array $data): Task
    {
        $task->update([
            'department_id' => $data['department_id'] ?? null,
            'assigned_to'   => $data['assigned_to']   ?? null,
            'title'         => $data['title'],
            'description'   => $data['description']   ?? null,
            'priority'      => $data['priority']       ?? $task->priority,
            'due_at'        => ! empty($data['due_at']) ? $data['due_at'] : null,
        ]);

        return $task->fresh(['assignee', 'assigner', 'department']);
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    // ── Status ─────────────────────────────────────────────────────────────────

    public function updateStatus(Task $task, string $status): Task
    {
        $task->update([
            'status'       => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        return $task->fresh();
    }

    // ── Comments ───────────────────────────────────────────────────────────────

    public function addComment(Task $task, User $author, string $body): TaskComment
    {
        return $task->comments()->create([
            'user_id' => $author->id,
            'body'    => $body,
        ]);
    }

    public function deleteComment(TaskComment $comment): void
    {
        $comment->delete();
    }

    // ── Backward-compat helpers ────────────────────────────────────────────────

    /**
     * Dashboard widget: returns the 5 most urgent active tasks for a user.
     */
    public function forUser(User $user): Collection
    {
        return Task::forChurch($user->church_id)
            ->visibleTo($user)
            ->active()
            ->with(['assignee:id,name,avatar', 'department:id,name,color,icon'])
            ->orderByRaw(self::STATUS_ORDER)
            ->orderByRaw(self::PRIORITY_ORDER)
            ->orderBy('due_at')
            ->limit(5)
            ->get();
    }

    /**
     * Count tasks assigned to the user that are visually overdue (due_at in the
     * past, not completed or cancelled). Mirrors the is_overdue field in
     * TaskResource and the updated scopeOverdue() so all three surfaces agree.
     *
     * Used by HandleInertiaRequests to share the sidebar badge count on every
     * Inertia response without a separate page-level prop.
     */
    public function overdueCountForUser(User $user): int
    {
        if (! $user->church_id) {
            return 0;
        }

        return Task::forChurch($user->church_id)
            ->where('assigned_to', $user->id)
            ->overdue()
            ->count();
    }

    /**
     * Scheduled command: mark pending tasks whose due_at has passed as overdue.
     */
    public function markOverdue(): int
    {
        return Task::whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->update(['status' => 'overdue']);
    }
}
