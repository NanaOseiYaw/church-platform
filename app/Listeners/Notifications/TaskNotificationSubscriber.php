<?php

namespace App\Listeners\Notifications;

use App\Events\TaskWasAssigned;
use App\Events\TaskWasCompleted;
use App\Events\TaskWasUpdated;
use App\Notifications\AppNotification;
use Illuminate\Events\Dispatcher;

class TaskNotificationSubscriber
{
    // ── Task assigned ──────────────────────────────────────────────────────────

    /**
     * Notify the assignee that a task has been assigned to them.
     * Skip if the actor is the same user (self-assignment).
     */
    public function handleTaskAssigned(TaskWasAssigned $event): void
    {
        $task  = $event->task->loadMissing('assignee');
        $actor = $event->actor;

        if (! $task->assigned_to || $task->assigned_to === $actor->id) {
            return;
        }

        $assignee = $task->assignee;
        if (! $assignee) {
            return;
        }

        $assignee->notify(new AppNotification(
            notifType: AppNotification::TYPE_TASK_ASSIGNED,
            title:     'New task assigned',
            body:      "{$actor->name} assigned you \u{201c}{$task->title}\u{201d}",
            actionUrl: "/dashboard/tasks/{$task->id}",
            actor:     ['name' => $actor->name, 'avatar' => $actor->avatar],
        ));
    }

    // ── Task updated ───────────────────────────────────────────────────────────

    /**
     * Notify the other party when a task is edited.
     *
     * Rules:
     *   – If the actor is the assigner, notify the assignee.
     *   – If the actor is the assignee, notify the assigner.
     *   – If actor is someone else entirely, notify both assigned_to and assigned_by.
     *   – Never self-notify.
     */
    public function handleTaskUpdated(TaskWasUpdated $event): void
    {
        $task  = $event->task->loadMissing(['assignee', 'assigner']);
        $actor = $event->actor;

        $recipients = collect();

        if ($task->assigned_to && $task->assigned_to !== $actor->id) {
            $recipients->push($task->assignee);
        }

        if ($task->assigned_by && $task->assigned_by !== $actor->id
            && $task->assigned_by !== $task->assigned_to) {
            $recipients->push($task->assigner);
        }

        $notification = new AppNotification(
            notifType: AppNotification::TYPE_TASK_UPDATED,
            title:     'Task updated',
            body:      "{$actor->name} updated \u{201c}{$task->title}\u{201d}",
            actionUrl: "/dashboard/tasks/{$task->id}",
            actor:     ['name' => $actor->name, 'avatar' => $actor->avatar],
        );

        $recipients->filter()->unique('id')->each(
            fn ($user) => $user->notify($notification)
        );
    }

    // ── Task completed ─────────────────────────────────────────────────────────

    /**
     * Notify the assigner when a task is marked complete.
     * Skip if the assigner completed their own task.
     */
    public function handleTaskCompleted(TaskWasCompleted $event): void
    {
        $task  = $event->task->loadMissing('assigner');
        $actor = $event->actor;

        if (! $task->assigned_by || $task->assigned_by === $actor->id) {
            return;
        }

        $assigner = $task->assigner;
        if (! $assigner) {
            return;
        }

        $assigner->notify(new AppNotification(
            notifType: AppNotification::TYPE_TASK_COMPLETED,
            title:     'Task completed',
            body:      "{$actor->name} completed \u{201c}{$task->title}\u{201d}",
            actionUrl: "/dashboard/tasks/{$task->id}",
            actor:     ['name' => $actor->name, 'avatar' => $actor->avatar],
        ));
    }

    // ── Subscriber map ─────────────────────────────────────────────────────────

    public function subscribe(Dispatcher $events): array
    {
        return [
            TaskWasAssigned::class  => 'handleTaskAssigned',
            TaskWasUpdated::class   => 'handleTaskUpdated',
            TaskWasCompleted::class => 'handleTaskCompleted',
        ];
    }
}
