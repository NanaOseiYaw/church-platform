<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\BroadcastService;
use App\Traits\LogsAuditEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LogsAuditEvents;

    public int $tries        = 3;
    public int $maxExceptions = 3;

    public function __construct(public int $broadcastId) {}

    public function handle(BroadcastService $service): void
    {
        $broadcast = Broadcast::with('church')->find($this->broadcastId);

        if (! $broadcast) {
            return;
        }

        // Guard: only process broadcasts in sending or scheduled state
        if (! in_array($broadcast->status, ['sending', 'scheduled'])) {
            return;
        }

        // Transition scheduled → sending
        if ($broadcast->status === 'scheduled') {
            $broadcast->update(['status' => 'sending']);
        }

        $delivered = 0;
        $failed    = 0;

        $broadcast->recipients()
            ->where('status', 'pending')
            ->with('user')
            ->chunkById(50, function ($recipients) use ($service, $broadcast, &$delivered, &$failed) {
                foreach ($recipients as $recipient) {
                    /** @var BroadcastRecipient $recipient */
                    $user = $recipient->user;

                    if (! $user) {
                        $recipient->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'User not found']);
                        $failed++;
                        continue;
                    }

                    try {
                        $rendered = $service->renderBody($broadcast->body, $user, $broadcast);

                        $user->notify(new AppNotification(
                            AppNotification::TYPE_BROADCAST,
                            $broadcast->subject,
                            $rendered,
                            '/dashboard/communication/broadcasts/' . $broadcast->id,
                        ));

                        $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                        $delivered++;
                    } catch (\Throwable $e) {
                        $recipient->update([
                            'status'         => 'failed',
                            'failed_at'      => now(),
                            'failure_reason' => substr($e->getMessage(), 0, 250),
                        ]);
                        $failed++;
                    }
                }
            });

        $total  = $broadcast->recipient_count;
        $status = ($delivered === 0) ? 'failed' : 'sent';

        $update = [
            'delivered_count' => $delivered,
            'failed_count'    => $failed,
            'status'          => $status,
        ];
        if ($status === 'sent') {
            $update['sent_at'] = now();
        }

        $broadcast->update($update);

        $this->auditLog('communication.broadcast_sent', $broadcast, [], [], [
            'recipient_count' => $total,
            'delivered_count' => $delivered,
            'failed_count'    => $failed,
        ]);

        // Notify the sender so they know delivery is complete
        $sender = $broadcast->created_by ? User::find($broadcast->created_by) : null;
        if ($sender) {
            $label = $status === 'sent'
                ? "Delivered to {$delivered} of {$total} recipient" . ($total !== 1 ? 's' : '')
                : 'Delivery failed — no recipients reached';

            $sender->notify(new AppNotification(
                AppNotification::TYPE_BROADCAST_SENT,
                "Broadcast complete: {$broadcast->title}",
                $label,
                '/dashboard/communication/broadcasts/' . $broadcast->id,
            ));
        }
    }
}
