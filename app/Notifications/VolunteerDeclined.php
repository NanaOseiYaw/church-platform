<?php

namespace App\Notifications;

use App\Models\VolunteerAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class VolunteerDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly VolunteerAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $planPosition = $this->assignment->planPosition;

        if (! $planPosition) {
            return [
                'type'       => 'scheduling.declined',
                'title'      => 'Volunteer declined',
                'body'       => 'A volunteer has declined their assignment.',
                'action_url' => '/dashboard/scheduling',
            ];
        }

        $plan        = $planPosition->plan;
        $position    = $planPosition->servingPosition;
        $volunteer   = $this->assignment->volunteer;
        $whoDeclined = $volunteer?->name ?? 'A volunteer';
        $dateStr     = $plan?->scheduled_at?->format('D, j M Y \a\t g:i A') ?? 'an upcoming service';

        return [
            'type'       => 'scheduling.declined',
            'title'      => "{$whoDeclined} declined: " . ($plan?->title ?? 'Upcoming Service'),
            'body'       => $position?->name
                                ? "{$whoDeclined} cannot serve as {$position->name} on {$dateStr}."
                                : "{$whoDeclined} declined their assignment on {$dateStr}.",
            'action_url' => '/dashboard/scheduling/plans/' . ($plan?->id ?? ''),
        ];
    }
}
