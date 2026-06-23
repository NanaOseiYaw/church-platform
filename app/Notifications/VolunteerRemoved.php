<?php

namespace App\Notifications;

use App\Models\VolunteerAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VolunteerRemoved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly VolunteerAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planTitle = $this->assignment->planPosition?->plan?->title ?? 'Upcoming Service';
        $position  = $this->assignment->planPosition?->servingPosition?->name;
        $dateStr   = $this->assignment->planPosition?->plan?->scheduled_at?->format('D, j M Y \a\t g:i A') ?? 'an upcoming date';

        $mail = (new MailMessage)
            ->subject("Assignment removed: {$planTitle}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your assignment for **{$planTitle}** on {$dateStr} has been removed.");

        if ($position) {
            $mail->line("Role was: {$position}");
        }

        return $mail->action('View My Schedule', url('/dashboard/scheduling/my-schedule'));
    }

    public function toArray(object $notifiable): array
    {
        $planPosition = $this->assignment->planPosition;

        if (! $planPosition) {
            return [
                'type'       => 'scheduling.removed',
                'title'      => 'Assignment removed',
                'body'       => 'One of your scheduling assignments has been removed.',
                'action_url' => '/dashboard/scheduling/my-schedule',
            ];
        }

        $plan     = $planPosition->plan;
        $position = $planPosition->servingPosition;
        $dateStr  = $plan?->scheduled_at?->format('D, j M Y \a\t g:i A') ?? 'an upcoming service';

        return [
            'type'       => 'scheduling.removed',
            'title'      => 'Assignment removed: ' . ($plan?->title ?? 'Upcoming Service'),
            'body'       => $position?->name
                                ? "Your slot as {$position->name} on {$dateStr} has been removed."
                                : "Your assignment on {$dateStr} has been removed.",
            'action_url' => '/dashboard/scheduling/my-schedule',
        ];
    }
}
