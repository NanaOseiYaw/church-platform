<?php

namespace App\Services\Sermons;

use App\Models\ChannelConnection;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Services\Sermons\Contracts\SermonProviderContract;
use App\Services\Sermons\Exceptions\ProviderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates sermon synchronisation across all providers.
 *
 * Controllers and Jobs use this service — they never touch providers directly.
 * This keeps controllers thin and makes adding new providers trivial:
 * register the class in the provider() method and return it for the right key.
 */
class SermonSyncService
{
    public function __construct(
        private readonly YouTubeProvider $youTube,
    ) {}

    // ── Provider resolution ────────────────────────────────────────────────────

    public function provider(string $key): SermonProviderContract
    {
        return match ($key) {
            'youtube' => $this->youTube,
            default   => throw new \InvalidArgumentException("Unknown sermon provider: {$key}"),
        };
    }

    // ── Channel management ─────────────────────────────────────────────────────

    /**
     * Validate and persist a new channel connection, then return it.
     * Does not trigger a sync — the caller dispatches the sync job separately.
     *
     * @throws ProviderException on API error or invalid channel
     */
    public function connectChannel(
        int    $churchId,
        string $provider,
        string $channelInput,
        int    $syncFrequencyHours = 24,
        ?string $customApiKey = null,
    ): ChannelConnection {
        $p    = $this->provider($provider);
        $info = $p->validateChannel($channelInput, $customApiKey);

        $connection = ChannelConnection::updateOrCreate(
            [
                'church_id'  => $churchId,
                'provider'   => $provider,
                'channel_id' => $info['channel_id'],
            ],
            [
                'channel_title'        => $info['title'],
                'channel_thumbnail'    => $info['thumbnail'],
                'channel_description'  => $info['description'],
                'uploads_playlist_id'  => $info['uploads_playlist_id'],
                'subscriber_count'     => $info['subscriber_count'],
                'video_count'          => $info['video_count'],
                'is_active'            => true,
                'sync_frequency_hours' => $syncFrequencyHours,
                'settings'             => $customApiKey ? ['api_key' => $customApiKey] : null,
            ],
        );

        return $connection;
    }

    // ── Synchronisation ────────────────────────────────────────────────────────

    /**
     * Sync all videos from a channel connection into local sermon records.
     *
     * Returns a summary array:
     *   ['created' => int, 'updated' => int, 'skipped' => int, 'total' => int]
     *
     * Each video is upserted by (church_id, provider, provider_video_id) —
     * so re-syncing the same channel is always safe and idempotent.
     */
    public function syncChannel(ChannelConnection $connection): array
    {
        $ctx = "#{$connection->id} ({$connection->channel_title})";

        Log::info("[SermonSyncService] Resolving provider '{$connection->provider}' for {$ctx}");
        $p = $this->provider($connection->provider);

        Log::info("[SermonSyncService] Fetching videos from provider for {$ctx}");
        $videos = $p->fetchVideos($connection);
        Log::info("[SermonSyncService] Provider returned " . count($videos) . " video(s) for {$ctx}");

        if (empty($videos)) {
            Log::warning("[SermonSyncService] No videos returned for {$ctx} — check uploads_playlist_id and API key.");
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($videos as $video) {
            $result = $this->upsertSermon($connection, $video);
            Log::debug("[SermonSyncService] Video {$video['provider_video_id']} → {$result}");

            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                default   => $skipped++,
            };
        }

        Log::info("[SermonSyncService] Updating last_synced_at for {$ctx}");
        $connection->update([
            'last_synced_at' => now(),
            'next_sync_at'   => now()->addHours($connection->sync_frequency_hours),
            'video_count'    => count($videos),
        ]);
        Log::info("[SermonSyncService] last_synced_at updated to " . now()->toDateTimeString() . " for {$ctx}");

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total'   => count($videos),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Upsert a single sermon record and return 'created', 'updated', or 'skipped'.
     *
     * Lookup key: (church_id, provider, provider_video_id) — provider-scoped IDs
     * are unique per church, so no cross-tenant collisions are possible.
     */
    private function upsertSermon(ChannelConnection $connection, array $video): string
    {
        // Include soft-deleted records to prevent re-creating after an admin hides it.
        $sermon = Sermon::withTrashed()->firstOrNew([
            'church_id'         => $connection->church_id,
            'provider'          => $connection->provider,
            'provider_video_id' => $video['provider_video_id'],
        ]);

        $isNew = ! $sermon->exists;

        // If admin manually deleted/hid this video, respect the decision.
        if ($sermon->trashed()) {
            return 'skipped';
        }

        // Derive a slug from the title (collision-safe via HasUniqueSlug).
        // Preserve existing slug on updates.
        if ($isNew || ! $sermon->slug) {
            $sermon->slug = Sermon::makeUniqueSlug(
                source:      $video['title'],
                scopeColumn: 'church_id',
                scopeValue:  $connection->church_id,
                ignoreId:    $sermon->exists ? $sermon->id : null,
            );
        }

        // Kept current on every sync: these describe the video itself, and an
        // admin has no reason to edit them by hand.
        $sermon->fill([
            'provider_channel_id' => $connection->id,
            'thumbnail_url'       => $video['thumbnail_url'],
            'embed_url'           => $video['embed_url'],
            'video_url'           => $video['source_url'],
            'duration_seconds'    => $video['duration_seconds'],
            'synced_at'           => now(),
            // Defaults for new synced sermons (admin can override later)
            'visibility'          => $sermon->exists ? $sermon->visibility : 'public',
            'is_public'           => $sermon->exists ? $sermon->is_public : true,
        ]);

        $serviceDate = $video['service_date'] ?? $video['published_at'];

        // Title, description and date belong to the admin once imported. These
        // used to be overwritten from YouTube on every hourly sync, so tidying a
        // title or correcting a date on the website was silently undone within
        // the hour. They are now written only when the sermon is first created.
        if ($isNew) {
            $sermon->fill([
                'title'       => $video['title'],
                'description' => $video['description'],
                'preached_at' => $serviceDate,
            ]);
        } elseif ($this->dateStillFromUpload($sermon, $video)) {
            // Sermons imported before ServiceDate existed were dated by their
            // upload. Correct those — but only while the stored date is still
            // exactly the upload timestamp, which means nobody has edited it.
            $sermon->preached_at = $serviceDate;
        }

        $sermon->save();

        return $isNew ? 'created' : 'updated';
    }

    /** Whether an existing sermon's date is unset, or still the untouched YouTube upload date. */
    private function dateStillFromUpload(Sermon $sermon, array $video): bool
    {
        if (! $sermon->preached_at) {
            return true;
        }

        $uploadedAt = $video['published_at'] ?? null;

        return $uploadedAt !== null
            && $sermon->preached_at->getTimestamp() === $uploadedAt->getTimestamp();
    }
}
