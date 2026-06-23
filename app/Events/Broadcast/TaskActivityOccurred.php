<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the church channel whenever a task is assigned,
 * updated, or completed.  Connected clients update their task lists
 * without a full page reload.
 *
 * Channel: private-church.{churchId}
 */
class TaskActivityOccurred implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $action  'assigned' | 'updated' | 'completed'
     */
    public function __construct(
        public readonly int    $taskId,
        public readonly int    $churchId,
        public readonly string $taskTitle,
        public readonly string $action,
        public readonly string $actorName,
        public readonly string $newStatus,
        public readonly ?int   $assignedTo,
    ) {}

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("church.{$this->churchId}")];
    }

    public function broadcastAs(): string
    {
        return 'task.activity';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id'    => $this->taskId,
            'title'      => $this->taskTitle,
            'action'     => $this->action,
            'actor'      => $this->actorName,
            'new_status' => $this->newStatus,
            'assigned_to'=> $this->assignedTo,
        ];
    }
}
