<?php

namespace App\Jobs;

use App\Models\ChannelConnection;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Sermons\SermonSyncService;
use App\Services\Sermons\Exceptions\ProviderException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that syncs all videos from a single YouTube channel connection.
 *
 * Dispatched:
 *   - Immediately after a church connects a new channel (SermonChannelController::store)
 *   - On-demand from the dashboard (SermonChannelController::sync)
 *   - By the scheduler: once per hour for every connection where next_sync_at <= now()
 *
 * Failure behaviour:
 *   - ProviderException (bad API key, quota exceeded) → logged, in-app error sent, fails gracefully
 *   - Other exceptions → rethrown → queue handles retry with back-off
 *
 * ⚠ PROPERTY NAMING: Do NOT use $connection, $tries, $backoff, $timeout,
 *   $queue, $delay, or $afterCommit as constructor-promoted property names —
 *   they are already claimed by the Queueable / InteractsWithQueue traits and
 *   PHP 8.1+ will throw a fatal "incompatible definition" error at class load.
 */
class SyncYouTubeChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum time (seconds) the job may run before being killed. */
    public int $timeout = 120;

    /**
     * @param  ChannelConnection  $channelConnection  The channel to sync.
     * @param  int|null           $triggeredByUserId  Auth user ID, or null for scheduled/system syncs.
     *                                                 Used to send a completion notification to the right admin.
     */
    public function __construct(
        private readonly ChannelConnection $channelConnection,
        private readonly ?int $triggeredByUserId = null,
    ) {}

    /**
     * Maximum number of automatic retries.
     * Defined as a method to avoid the Queueable $tries property conflict.
     */
    public function tries(): int
    {
        return 3;
    }

    /**
     * Back-off delays (seconds) between successive retry attempts.
     * Defined as a method to avoid the Queueable $backoff property conflict.
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(SermonSyncService $syncService): void
    {
        $context = "connection #{$this->channelConnection->id} ({$this->channelConnection->channel_title})";

        Log::info("[SermonSync] ▶ START — {$context}");

        // ── Reload to get fresh DB state ───────────────────────────────────────
        $connection = ChannelConnection::find($this->channelConnection->id);

        if (! $connection) {
            Log::warning("[SermonSync] SKIPPED — connection #{$this->channelConnection->id} no longer exists.");
            return;
        }

        if (! $connection->is_active) {
            Log::info("[SermonSync] SKIPPED — {$context} is inactive.");
            return;
        }

        if (! $connection->uploads_playlist_id) {
            Log::error("[SermonSync] ABORT — {$context} has no uploads_playlist_id. Re-connect the channel to fix.");
            $this->notifyUser(
                success:  false,
                title:    "Sync failed: {$connection->channel_title}",
                body:     'Channel is missing a playlist ID. Disconnect and re-connect to fix.',
                actionUrl: '/dashboard/sermons/channel',
            );
            return;
        }

        // ── Run the sync ───────────────────────────────────────────────────────
        try {
            Log::info("[SermonSync] Fetching videos from YouTube API for {$context} …");

            $result = $syncService->syncChannel($connection);

            Log::info(sprintf(
                '[SermonSync] ✓ COMPLETE — %s: %d created, %d updated, %d skipped of %d total.',
                $context,
                $result['created'],
                $result['updated'],
                $result['skipped'],
                $result['total'],
            ));

            // Notify the user who clicked Sync (if applicable)
            $this->notifyUser(
                success:  true,
                title:    "Sync complete: {$connection->channel_title}",
                body:     "{$result['total']} videos processed — {$result['created']} new, {$result['updated']} updated.",
                actionUrl: '/dashboard/sermons',
            );

        } catch (ProviderException $e) {
            // API / quota / configuration errors: log and fail gracefully (no infinite retry)
            Log::error("[SermonSync] ✗ PROVIDER ERROR — {$context}: " . $e->getMessage());

            $this->notifyUser(
                success:  false,
                title:    "Sync failed: {$connection->channel_title}",
                body:     $e->getMessage(),
                actionUrl: '/dashboard/sermons/channel',
            );

            $this->fail($e);
        }
        // Non-ProviderExceptions bubble up → queue retries with back-off
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Send an in-app notification to the admin who triggered the sync, if known.
     * No-ops silently when there is no triggering user (scheduled sync).
     */
    private function notifyUser(bool $success, string $title, string $body, string $actionUrl): void
    {
        if (! $this->triggeredByUserId) {
            return; // scheduled / system-triggered — no specific user to notify
        }

        $user = User::find($this->triggeredByUserId);
        if (! $user) {
            return;
        }

        $user->notify(new AppNotification(
            notifType: $success
                ? AppNotification::TYPE_SERMON_SYNC_DONE
                : AppNotification::TYPE_SERMON_SYNC_FAILED,
            title:     $title,
            body:      $body,
            actionUrl: $actionUrl,
        ));
    }
}
