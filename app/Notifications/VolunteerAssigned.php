<?php

namespace App\Notifications;

use App\Models\VolunteerAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VolunteerAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly VolunteerAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data       = $this->toArray($notifiable);
        $planTitle  = $this->assignment->planPosition?->plan?->title ?? 'Upcoming Service';
        $position   = $this->assignment->planPosition?->servingPosition?->name;
        $dateStr    = $this->assignment->planPosition?->plan?->scheduled_at?->format('D, j M Y \a\t g:i A') ?? 'an upcoming date';

        $mail = (new MailMessage)
            ->subject("New volunteer assignment: {$planTitle}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You have been assigned to **{$planTitle}** on {$dateStr}.");

        if ($position) {
            $mail->line("Role: **{$position}**");
        }

        return $mail->action('View My Schedule', url('/dashboard/scheduling/my-schedule'));
    }

    public function toArray(object $notifiable): array
    {
        $planPosition = $this->assignment->planPosition;

        if (! $planPosition) {
            return [
                'type'       => 'scheduling.assigned',
                'title'      => 'New assignment',
                'body'       => 'You have been assigned to an upcoming service.',
                'action_url' => '/dashboard/scheduling/my-schedule',
            ];
        }

        $plan     = $planPosition->plan;
        $position = $planPosition->servingPosition;
        $dateStr  = $plan?->scheduled_at?->format('D, j M Y \a\t g:i A') ?? 'an upcoming service';

        return [
            'type'       => 'scheduling.assigned',
            'title'      => 'New assignment: ' . ($plan?->title ?? 'Upcoming Service'),
            'body'       => $position?->name
                                ? "You're serving as {$position->name} on {$dateStr}."
                                : "You have a new assignment on {$dateStr}.",
            'action_url' => '/dashboard/scheduling/my-schedule',
        ];
    }
}
