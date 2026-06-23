<?php

namespace App\Services\Sermons\Contracts;

use App\Models\ChannelConnection;

/**
 * Contract that every sermon media provider must implement.
 *
 * Providers are responsible for:
 *  - Validating a channel/feed URL and returning normalised metadata
 *  - Fetching video/episode metadata from the provider's API
 *  - Upserting sermon records locally without creating duplicates
 *
 * Future providers: Vimeo, podcast RSS, Riverside, etc.
 */
interface SermonProviderContract
{
    /**
     * A short, lowercase key that uniquely identifies this provider.
     * Stored in the `provider` column of sermons and channel_connections.
     *
     * Example: 'youtube', 'vimeo', 'podcast'
     */
    public function getProviderKey(): string;

    /**
     * Validate a channel identifier (URL, handle, or raw ID) against the
     * provider's API and return normalised channel metadata.
     *
     * @param  string  $channelInput  User-supplied string (URL, @handle, ID, etc.)
     * @param  string|null  $apiKey   Override API key (null = use platform key)
     * @return array{
     *   channel_id:           string,
     *   title:                string,
     *   description:          string|null,
     *   thumbnail:            string|null,
     *   uploads_playlist_id:  string|null,
     *   subscriber_count:     int|null,
     *   video_count:          int|null,
     * }
     *
     * @throws \App\Services\Sermons\Exceptions\ProviderException on API error or invalid channel
     */
    public function validateChannel(string $channelInput, ?string $apiKey = null): array;

    /**
     * Fetch all video/episode metadata for a connected channel.
     *
     * Returns a flat array of normalised video data — each element is shaped
     * identically regardless of the underlying provider, so SermonSyncService
     * can handle upserts without knowing the provider internals.
     *
     * @return array<int, array{
     *   provider_video_id: string,
     *   title:             string,
     *   description:       string|null,
     *   thumbnail_url:     string|null,
     *   embed_url:         string,
     *   source_url:        string,
     *   duration_seconds:  int|null,
     *   published_at:      \Carbon\Carbon|null,
     * }>
     */
    public function fetchVideos(ChannelConnection $connection): array;

    /**
     * Build a full embed URL for a given provider-specific video ID.
     * Used when constructing embed URLs on-the-fly without stored metadata.
     */
    public function buildEmbedUrl(string $videoId): string;
}
