<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the church channel when an announcement is published.
 * Connected users see a live feed update and unread badge tick.
 *
 * Channel: private-church.{churchId}
 */
class AnnouncementWentLive implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $announcementId,
        public readonly int    $churchId,
        public readonly string $title,
        public readonly bool   $isPinned,
        public readonly string $publishedAt,
    ) {}

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("church.{$this->churchId}")];
    }

    public function broadcastAs(): string
    {
        return 'announcement.published';
    }

    public function broadcastWith(): array
    {
        return [
            'announcement_id' => $this->announcementId,
            'title'           => $this->title,
            'is_pinned'       => $this->isPinned,
            'published_at'    => $this->publishedAt,
        ];
    }
}
