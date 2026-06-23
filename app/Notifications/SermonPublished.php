<?php

namespace App\Notifications;

use App\Models\Sermon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SermonPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Sermon $sermon) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("New sermon: {$this->sermon->title}")
            ->greeting("Hi {$notifiable->name},")
            ->line("A new sermon has been published: **{$this->sermon->title}**.");

        if ($this->sermon->speaker) {
            $mail->line("Speaker: {$this->sermon->speaker}");
        }

        if ($this->sermon->preached_at) {
            $mail->line("Date: {$this->sermon->preached_at->format('D, j M Y')}");
        }

        return $mail->action('Listen Now', url('/sermons'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => AppNotification::TYPE_SERMON_PUBLISHED,
            'title'      => "New sermon: {$this->sermon->title}",
            'body'       => $this->sermon->speaker
                                ? "Preached by {$this->sermon->speaker}"
                                : 'A new sermon is available.',
            'action_url' => '/sermons',
        ];
    }
}
