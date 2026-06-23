<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the attendance session channel after a bulk-save.
 * All users watching the same session's Show page see live stat updates.
 *
 * Channel: private-attendance.{sessionId}
 */
class AttendanceSessionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $sessionId,
        public readonly int    $churchId,
        public readonly int    $attendancesCount,
        public readonly int    $presentCount,
        public readonly int    $absentCount,
        public readonly int    $lateCount,
        public readonly int    $excusedCount,
        public readonly int    $attendanceRate,
    ) {}

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("attendance.{$this->sessionId}")];
    }

    public function broadcastAs(): string
    {
        return 'attendance.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id'        => $this->sessionId,
            'attendances_count' => $this->attendancesCount,
            'present_count'     => $this->presentCount,
            'absent_count'      => $this->absentCount,
            'late_count'        => $this->lateCount,
            'excused_count'     => $this->excusedCount,
            'attendance_rate'   => $this->attendanceRate,
        ];
    }
}
