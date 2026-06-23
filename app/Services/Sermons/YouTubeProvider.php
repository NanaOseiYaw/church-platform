<?php

namespace App\Services\Sermons;

use App\Models\ChannelConnection;
use App\Services\Sermons\Contracts\SermonProviderContract;
use App\Services\Sermons\Exceptions\ProviderException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Data API v3 sermon provider.
 *
 * Quota costs per full channel sync (100 videos):
 *   channels.list        → 1 unit
 *   playlistItems.list   → ~2 units (50/page × 2 pages)
 *   videos.list          → ~2 units (50/page × 2 pages for duration)
 *   Total                → ~5 units per sync
 *
 * Daily quota: 10,000 units.  At 24-hour sync frequency, a platform key
 * comfortably serves ≈2,000 churches/day before quota is exhausted.
 * Churches can supply their own key via ChannelConnection::settings.api_key.
 */
class YouTubeProvider implements SermonProviderContract
{
    private const BASE   = 'https://www.googleapis.com/youtube/v3';
    private const PER_PAGE = 50;     // YouTube max per page
    private const MAX_VIDEOS = 200;  // cap to control quota usage

    public function getProviderKey(): string
    {
        return 'youtube';
    }

    // ── Channel validation ──────────────────────────────────────────────────────

    public function validateChannel(string $channelInput, ?string $apiKey = null): array
    {
        $key = $apiKey ?? $this->platformKey();
        $id  = $this->parseChannelInput($channelInput);

        $params = [
            'key'  => $key,
            'part' => 'snippet,contentDetails,statistics',
        ];

        // Choose the right lookup parameter
        if ($id['type'] === 'handle') {
            $params['forHandle'] = $id['value'];
        } elseif ($id['type'] === 'username') {
            $params['forUsername'] = $id['value'];
        } else {
            $params['id'] = $id['value'];
        }

        $response = Http::timeout(15)
            ->get(self::BASE . '/channels', $params);

        if ($response->failed()) {
            $msg = data_get($response->json(), 'error.message', $response->status());
            throw ProviderException::apiError('youtube', (string) $msg);
        }

        $items = $response->json('items', []);
        if (empty($items)) {
            throw ProviderException::channelNotFound('youtube', $channelInput);
        }

        $item = $items[0];

        return [
            'channel_id'          => $item['id'],
            'title'               => $item['snippet']['title'] ?? 'Unknown Channel',
            'description'         => $item['snippet']['description'] ?? null,
            'thumbnail'           => $item['snippet']['thumbnails']['default']['url'] ?? null,
            'uploads_playlist_id' => $item['contentDetails']['relatedPlaylists']['uploads'] ?? null,
            'subscriber_count'    => (int) ($item['statistics']['subscriberCount'] ?? 0) ?: null,
            'video_count'         => (int) ($item['statistics']['videoCount'] ?? 0) ?: null,
        ];
    }

    // ── Video fetching ──────────────────────────────────────────────────────────

    public function fetchVideos(ChannelConnection $connection): array
    {
        $usingCustomKey = (bool) $connection->customApiKey();
        $key            = $connection->customApiKey() ?? $this->platformKey();
        $playlistId     = $connection->uploads_playlist_id;

        Log::info(sprintf(
            '[YouTubeProvider] fetchVideos — connection #%d, playlist: %s, key: %s',
            $connection->id,
            $playlistId ?? 'MISSING',
            $usingCustomKey ? 'custom' : 'platform',
        ));

        if (! $playlistId) {
            Log::warning("[YouTubeProvider] No uploads_playlist_id for connection #{$connection->id} — cannot fetch videos.");
            return [];
        }

        // ── Step 1: collect video IDs from the uploads playlist ──────────────
        $videoIds   = [];
        $snippets   = [];  // video_id → snippet (title, description, thumbnail, publishedAt)
        $pageToken  = null;
        $fetched    = 0;
        $page       = 0;

        do {
            $page++;
            $params = [
                'key'        => $key,
                'playlistId' => $playlistId,
                'part'       => 'snippet,contentDetails',
                'maxResults' => self::PER_PAGE,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            Log::debug("[YouTubeProvider] playlistItems page {$page} — playlist {$playlistId}");
            $response = Http::timeout(15)
                ->get(self::BASE . '/playlistItems', $params);

            if ($response->failed()) {
                $errorBody = $response->body();
                $apiMsg    = data_get($response->json(), 'error.message', $response->status());
                Log::error("[YouTubeProvider] playlistItems FAILED for connection #{$connection->id}: HTTP {$response->status()} — {$apiMsg}");
                Log::debug("[YouTubeProvider] Raw error body: {$errorBody}");
                break;
            }

            Log::debug("[YouTubeProvider] playlistItems page {$page}: " . count($response->json('items', [])) . " items returned");

            foreach ($response->json('items', []) as $item) {
                $videoId = $item['contentDetails']['videoId'] ?? null;
                if (! $videoId) continue;

                $videoIds[] = $videoId;
                $snippets[$videoId] = [
                    'title'        => $item['snippet']['title'] ?? 'Untitled',
                    'description'  => $item['snippet']['description'] ?? null,
                    'thumbnail'    => $this->bestThumbnail($item['snippet']['thumbnails'] ?? []),
                    'published_at' => $item['contentDetails']['videoPublishedAt']
                                      ?? $item['snippet']['publishedAt']
                                      ?? null,
                ];

                $fetched++;
                if ($fetched >= self::MAX_VIDEOS) break 2;
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        Log::info("[YouTubeProvider] Collected {$fetched} video ID(s) from playlist {$playlistId}");

        if (empty($videoIds)) {
            Log::warning("[YouTubeProvider] No video IDs collected — playlist may be empty or API returned no items.");
            return [];
        }

        // ── Step 2: fetch duration for each video in batches of 50 ───────────
        Log::debug("[YouTubeProvider] Fetching durations for " . count($videoIds) . " video(s) …");
        $durations = $this->fetchDurations($videoIds, $key);
        Log::debug("[YouTubeProvider] Duration fetch complete: " . count($durations) . " resolved.");

        // ── Step 3: assemble normalised video records ─────────────────────────
        Log::info("[YouTubeProvider] Assembling " . count($videoIds) . " normalised video record(s).");
        return array_map(function (string $videoId) use ($snippets, $durations): array {
            $s = $snippets[$videoId];

            return [
                'provider_video_id' => $videoId,
                'title'             => $s['title'],
                'description'       => $s['description'],
                'thumbnail_url'     => $s['thumbnail'],
                'embed_url'         => $this->buildEmbedUrl($videoId),
                'source_url'        => "https://www.youtube.com/watch?v={$videoId}",
                'duration_seconds'  => $durations[$videoId] ?? null,
                'published_at'      => $s['published_at']
                                       ? Carbon::parse($s['published_at'])
                                       : null,
            ];
        }, $videoIds);
    }

    public function buildEmbedUrl(string $videoId): string
    {
        return "https://www.youtube.com/embed/{$videoId}?rel=0&modestbranding=1";
    }

    // ── Private helpers ─────────────────────────────────────────────────────────

    /**
     * Resolve the platform-level YouTube API key from config.
     *
     * @throws ProviderException when not configured
     */
    private function platformKey(): string
    {
        $key = config('services.youtube.api_key', '');

        if (blank($key)) {
            throw ProviderException::notConfigured('youtube');
        }

        return $key;
    }

    /**
     * Parse a user-supplied channel identifier into a typed array.
     * Supports:
     *   - https://www.youtube.com/channel/UCxxxx
     *   - https://www.youtube.com/@handle
     *   - @handle
     *   - UCxxxx (raw channel ID)
     *   - legacy username
     */
    private function parseChannelInput(string $input): array
    {
        $input = trim($input);

        // Full URL with /channel/UC...
        if (preg_match('|youtube\.com/channel/(UC[A-Za-z0-9_-]+)|', $input, $m)) {
            return ['type' => 'id', 'value' => $m[1]];
        }

        // Handle URL: youtube.com/@handle or bare @handle
        if (preg_match('|(?:youtube\.com/)?@([A-Za-z0-9_.]+)|', $input, $m)) {
            return ['type' => 'handle', 'value' => '@' . $m[1]];
        }

        // Raw channel ID (UC + 22 chars)
        if (preg_match('/^UC[A-Za-z0-9_-]{22}$/', $input)) {
            return ['type' => 'id', 'value' => $input];
        }

        // Legacy username (alphanumeric)
        if (preg_match('/^[A-Za-z0-9_.]+$/', $input)) {
            return ['type' => 'username', 'value' => $input];
        }

        // Fallback: assume it's an ID
        return ['type' => 'id', 'value' => $input];
    }

    /**
     * Fetch video durations in batches of 50.
     * Returns [videoId => durationSeconds]
     *
     * @param  string[]  $videoIds
     * @return array<string, int>
     */
    private function fetchDurations(array $videoIds, string $apiKey): array
    {
        $durations = [];
        $chunks    = array_chunk($videoIds, self::PER_PAGE);

        foreach ($chunks as $chunk) {
            $response = Http::timeout(15)
                ->get(self::BASE . '/videos', [
                    'key'  => $apiKey,
                    'id'   => implode(',', $chunk),
                    'part' => 'contentDetails',
                ]);

            if ($response->failed()) {
                Log::warning('YouTube: videos.list failed — durations skipped: ' . $response->status());
                continue;
            }

            foreach ($response->json('items', []) as $item) {
                $id  = $item['id'];
                $iso = $item['contentDetails']['duration'] ?? null;

                $durations[$id] = $iso ? \App\Models\Sermon::parseIsoDuration($iso) : null;
            }
        }

        return $durations;
    }

    /**
     * Pick the highest-quality available thumbnail from the YouTube thumbnails map.
     * Prefers: maxres → high → medium → default
     */
    private function bestThumbnail(array $thumbnails): ?string
    {
        foreach (['maxres', 'high', 'medium', 'default'] as $quality) {
            $url = $thumbnails[$quality]['url'] ?? null;
            if ($url) return $url;
        }

        return null;
    }
}
