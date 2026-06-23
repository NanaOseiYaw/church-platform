<?php

namespace App\Listeners\Broadcast;

use App\Events\Broadcast\TaskActivityOccurred;
use App\Events\TaskWasAssigned;
use App\Events\TaskWasCompleted;
use App\Events\TaskWasUpdated;
use Illuminate\Events\Dispatcher;

/**
 * Listens to the existing domain events for tasks and fires lightweight
 * WebSocket broadcasts so connected clients get instant UI updates.
 *
 * Registered as a subscriber in AppServiceProvider alongside the existing
 * TaskNotificationSubscriber (they run independently).
 */
class BroadcastTaskSubscriber
{
    public function handleTaskAssigned(TaskWasAssigned $event): void
    {
        $this->broadcast($event->task, $event->actor->name, 'assigned');
    }

    public function handleTaskUpdated(TaskWasUpdated $event): void
    {
        $this->broadcast($event->task, $event->actor->name, 'updated');
    }

    public function handleTaskCompleted(TaskWasCompleted $event): void
    {
        $this->broadcast($event->task, $event->actor->name, 'completed');
    }

    private function broadcast(\App\Models\Task $task, string $actorName, string $action): void
    {
        if (! $task->church_id) {
            return;
        }

        try {
            TaskActivityOccurred::dispatch(
                taskId:     $task->id,
                churchId:   $task->church_id,
                taskTitle:  $task->title,
                action:     $action,
                actorName:  $actorName,
                newStatus:  $task->status,
                assignedTo: $task->assigned_to,
            );
        } catch (\Illuminate\Broadcasting\BroadcastException) {
            // WebSocket server unavailable — real-time push skipped, no data loss
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            TaskWasAssigned::class  => 'handleTaskAssigned',
            TaskWasUpdated::class   => 'handleTaskUpdated',
            TaskWasCompleted::class => 'handleTaskCompleted',
        ];
    }
}
