<?php

namespace App\Services\Instagram;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The church's recent Instagram posts, via Meta's Instagram API with
 * Instagram Login (graph.instagram.com, permission instagram_business_basic).
 *
 * What is cached is metadata only — IDs, captions, permalinks, types,
 * timestamps and Meta's own CDN URLs. No image or video is ever downloaded or
 * stored; visitors' browsers load the media straight from Meta's CDN.
 *
 * Reads never call Meta. posts() returns whatever is cached and, if that copy
 * is due a refresh, schedules one to run after the response has been sent —
 * so a slow or unavailable Meta API never slows a page down. The scheduler
 * also refreshes the feed in the background (routes/console.php), so in normal
 * running no visitor is ever the one who triggers it.
 *
 * Meta's CDN URLs are temporary: Meta documents that they stop working once
 * content is deleted or "has expired", without stating a lifetime. The feed is
 * therefore refreshed well within any plausible lifetime, URLs that carry an
 * expiry (the `oe` parameter) are dropped once past it, and the gallery hides
 * any tile whose image still fails to load.
 *
 * Failures (expired token, rate limit, timeout, malformed response) are logged
 * server-side with the token redacted, back off before retrying, and leave the
 * previous feed in place. Visitors see the last good copy, or no section.
 */
class InstagramService
{
    /** Bump the suffix if the cached shape changes, so old entries are ignored. */
    public const FEED_KEY = 'instagram:feed:v1';

    private const BACKOFF_KEY = 'instagram:backoff_until';
    private const LOCK_KEY    = 'instagram:refresh-lock';
    private const HOST        = 'https://graph.instagram.com';

    /**
     * Only the fields the gallery uses. Carousel children are expanded in the
     * same request: one call instead of one per carousel, no public endpoint
     * that forwards requests to Meta, and children's URLs refresh together with
     * their parent's. Their images are still only loaded by the browser when
     * the carousel is opened.
     */
    private const FIELDS = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,'
                         . 'children{id,media_type,media_url,thumbnail_url}';

    /** Hosts Meta serves Instagram media from. Anything else is discarded. */
    private const MEDIA_HOSTS = ['cdninstagram.com', 'fbcdn.net'];

    private bool $refreshScheduled = false;

    public function __construct(private readonly InstagramTokenStore $tokens) {}

    // ── Reading ──────────────────────────────────────────────────────────────

    /**
     * Posts ready to render, newest first. Never calls Meta and never throws.
     *
     * @return array<int, array<string, mixed>>
     */
    public function posts(?int $limit = null): array
    {
        try {
            $feed = Cache::get(self::FEED_KEY);

            if ($this->needsRefresh($feed)) {
                $this->scheduleRefresh();
            }

            if (! is_array($feed) || ! is_array($feed['posts'] ?? null)) {
                return [];
            }

            // Drop posts whose CDN URLs have passed their stated expiry: their
            // images would no longer load.
            $cutoff = now()->getTimestamp() + 60;
            $posts  = array_values(array_filter(
                $feed['posts'],
                fn (array $p) => ($p['expires_at'] ?? PHP_INT_MAX) > $cutoff,
            ));

            return $limit !== null ? array_slice($posts, 0, $limit) : $posts;
        } catch (Throwable $e) {
            Log::warning('[Instagram] reading the cached feed failed: ' . $this->redact($e->getMessage()));

            return [];
        }
    }

    // ── Refreshing ───────────────────────────────────────────────────────────

    /**
     * Fetch the latest posts from Meta and cache them.
     *
     * @return string one of: refreshed, fresh, unconfigured, backoff, locked, failed
     */
    public function refresh(bool $force = false): string
    {
        $token = $this->tokens->current();
        if ($token === null) {
            return 'unconfigured';
        }

        if (! $force && Cache::get(self::BACKOFF_KEY, 0) > now()->getTimestamp()) {
            return 'backoff';
        }

        if (! $force && ! $this->needsRefresh(Cache::get(self::FEED_KEY))) {
            return 'fresh';
        }

        // One refresh at a time, however many requests or workers ask for it.
        $lock = Cache::lock(self::LOCK_KEY, 60);
        if (! $lock->get()) {
            return 'locked';
        }

        try {
            $response = $this->client()->get($this->url('/me/media'), [
                'fields'       => self::FIELDS,
                'limit'        => $this->postLimit(),
                'access_token' => $token,
            ]);

            if ($response->failed()) {
                $this->handleApiError($response, 'feed');

                return 'failed';
            }

            $data = $response->json('data');
            if (! is_array($data)) {
                Log::warning('[Instagram] feed response had no data array; keeping the previous feed.');
                $this->backOff(10);

                return 'failed';
            }

            $posts = array_values(array_filter(array_map(
                fn ($item) => is_array($item) ? $this->normalizePost($item) : null,
                array_slice($data, 0, $this->postLimit()),
            )));

            Cache::forever(self::FEED_KEY, [
                'fetched_at' => now()->getTimestamp(),
                'posts'      => $posts,
            ]);
            Cache::forget(self::BACKOFF_KEY);

            return 'refreshed';
        } catch (Throwable $e) {
            // Timeouts and connection failures land here. Guzzle includes the
            // request URL — and so the token — in its message; redact() strips it.
            Log::warning('[Instagram] feed request failed: ' . $this->redact($e->getMessage()));
            $this->backOff(10);

            return 'failed';
        } finally {
            $lock->release();
        }
    }

    /**
     * Exchange the current long-lived token for a fresh 60-day one.
     *
     * Meta only refreshes a token that is at least 24 hours old and still
     * valid, and a token left unrefreshed for 60 days expires for good. Run
     * daily by the scheduler; it only actually refreshes once a week.
     *
     * @return string one of: refreshed, not-due, unconfigured, failed
     */
    public function refreshToken(bool $force = false): string
    {
        $token = $this->tokens->current();
        if ($token === null) {
            return 'unconfigured';
        }

        $refreshedAt = $this->tokens->status()['refreshed_at'];
        if (! $force && $refreshedAt && CarbonImmutable::parse($refreshedAt)->gt(now()->subDays(6))) {
            return 'not-due';
        }

        try {
            $response = $this->client()->get(self::HOST . '/refresh_access_token', [
                'grant_type'   => 'ig_refresh_token',
                'access_token' => $token,
            ]);

            if ($response->failed()) {
                $this->handleApiError($response, 'token refresh');

                return 'failed';
            }

            $newToken = $response->json('access_token');
            if (! is_string($newToken) || $newToken === '') {
                Log::warning('[Instagram] token refresh response had no access_token.');

                return 'failed';
            }

            $this->tokens->save($newToken, (int) $response->json('expires_in') ?: null);
            Log::info('[Instagram] access token refreshed.');

            return 'refreshed';
        } catch (Throwable $e) {
            Log::warning('[Instagram] token refresh failed: ' . $this->redact($e->getMessage()));

            return 'failed';
        }
    }

    /**
     * A live check against Meta for setup and troubleshooting. Returns only
     * safe values — never the token.
     */
    public function check(): array
    {
        $result = ['token' => $this->tokens->status()];
        $token  = $this->tokens->current();
        if ($token === null) {
            return $result + ['ok' => false, 'problem' => 'INSTAGRAM_ACCESS_TOKEN is not set.'];
        }

        try {
            $me = $this->client()->get($this->url('/me'), ['fields' => 'user_id,username', 'access_token' => $token]);
            if ($me->failed()) {
                return $result + ['ok' => false, 'problem' => $this->describeError($me)];
            }

            $media = $this->client()->get($this->url('/me/media'), [
                'fields' => self::FIELDS, 'limit' => 3, 'access_token' => $token,
            ]);
            if ($media->failed()) {
                return $result + ['ok' => false, 'account' => $me->json('username'), 'problem' => $this->describeError($media)];
            }

            $raw    = is_array($media->json('data')) ? $media->json('data') : [];
            $sample = array_values(array_filter(array_map(fn ($i) => is_array($i) ? $this->normalizePost($i) : null, $raw)));

            return $result + [
                'ok'      => true,
                'account' => $me->json('username'),
                'sample'  => array_map(fn (array $p) => [
                    'type'        => $p['type'] . ($p['is_reel'] ? ' (reel)' : ''),
                    'items'       => $p['count'],
                    'has_caption' => $p['caption'] !== null,
                    'playable'    => collect($p['media'])->contains(fn ($m) => $m['type'] === 'video' && $m['src']),
                    'permalink'   => $p['permalink'],
                ], $sample),
                'returned_raw' => count($raw),
                'kept'         => count($sample),
            ];
        } catch (Throwable $e) {
            return $result + ['ok' => false, 'problem' => $this->redact($e->getMessage())];
        }
    }

    // ── Freshness ────────────────────────────────────────────────────────────

    private function needsRefresh(mixed $feed): bool
    {
        if ($this->tokens->current() === null) {
            return false;
        }

        if (! is_array($feed) || ! isset($feed['fetched_at'])) {
            return true;
        }

        $ttl = max(5, (int) config('services.instagram.cache_minutes', 60)) * 60;
        $now = now()->getTimestamp();

        if ($now - (int) $feed['fetched_at'] >= $ttl) {
            return true;
        }

        // Refresh early if any URL would expire before the next normal refresh.
        $soonest = collect($feed['posts'] ?? [])->pluck('expires_at')->filter()->min();

        return $soonest !== null && $soonest < $now + $ttl;
    }

    /** Refresh after the response is sent: the visitor never waits for Meta. */
    private function scheduleRefresh(): void
    {
        if ($this->refreshScheduled || Cache::get(self::BACKOFF_KEY, 0) > now()->getTimestamp()) {
            return;
        }

        $this->refreshScheduled = true;
        dispatch(function () {
            app(self::class)->refresh();
        })->afterResponse();
    }

    private function backOff(int $minutes): void
    {
        Cache::put(self::BACKOFF_KEY, now()->addMinutes($minutes)->getTimestamp(), now()->addMinutes($minutes));
    }

    // ── Errors ───────────────────────────────────────────────────────────────

    private function handleApiError(Response $response, string $what): void
    {
        $code = (int) $response->json('error.code');

        if ($code === 190) {
            // OAuthException: token expired, revoked or invalid.
            Log::error("[Instagram] {$what}: the access token is invalid or expired (code 190). "
                . 'Generate a new token in the Meta App Dashboard and set INSTAGRAM_ACCESS_TOKEN.');
            $this->backOff(60);

            return;
        }

        if (in_array($code, [4, 17, 32, 613], true)) {
            Log::warning("[Instagram] {$what}: rate limited (code {$code}); backing off for 30 minutes.");
            $this->backOff(30);

            return;
        }

        Log::warning("[Instagram] {$what} failed: " . $this->describeError($response));
        $this->backOff(10);
    }

    private function describeError(Response $response): string
    {
        $message = $response->json('error.message');

        return sprintf('HTTP %d, code %s: %s',
            $response->status(),
            $response->json('error.code') ?? '-',
            $this->redact(is_string($message) ? $message : 'no error message'),
        );
    }

    /** Remove any access token from text before it reaches a log. */
    private function redact(string $text): string
    {
        $text = preg_replace('/(access_token=)[^&\s"\']+/i', '$1[redacted]', $text) ?? $text;
        $token = $this->tokens->current();

        return $token ? str_replace($token, '[redacted]', $text) : $text;
    }

    // ── Normalising Meta's response ──────────────────────────────────────────

    /** One gallery post, or null if the item is not something we can show safely. */
    private function normalizePost(array $m): ?array
    {
        $id        = (string) ($m['id'] ?? '');
        $permalink = $this->safePermalink($m['permalink'] ?? null);

        if (! preg_match('/^\d{1,40}$/', $id) || $permalink === null) {
            return null;
        }

        $mediaType = $m['media_type'] ?? null;
        $media = match ($mediaType) {
            'IMAGE'          => array_filter([$this->image($m)]),
            'VIDEO'          => array_filter([$this->video($m)]),
            'CAROUSEL_ALBUM' => $this->carousel($m),
            default          => [],
        };

        $media = array_values($media);
        if ($media === []) {
            return null;
        }

        $first = $media[0];
        $thumb = $first['type'] === 'image' ? $first['src'] : $first['poster'];
        if ($thumb === null) {
            return null;
        }

        return [
            'id'         => $id,
            'type'       => match ($mediaType) { 'CAROUSEL_ALBUM' => 'carousel', 'VIDEO' => 'video', default => 'image' },
            // Instagram Login does not expose media_product_type, so a Reel
            // arrives as VIDEO; Meta's own permalink for a Reel is /reel/….
            'is_reel'    => $mediaType === 'VIDEO' && str_starts_with((string) parse_url($permalink, PHP_URL_PATH), '/reel/'),
            'caption'    => $this->caption($m['caption'] ?? null),
            'permalink'  => $permalink,
            'timestamp'  => $this->timestamp($m['timestamp'] ?? null),
            'thumb'      => $thumb,
            'media'      => $media,
            'count'      => count($media),
            'expires_at' => $this->soonestExpiry($media),
        ];
    }

    private function carousel(array $m): array
    {
        $children = $m['children']['data'] ?? [];
        $media    = [];

        foreach (is_array($children) ? $children : [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            $item = match ($child['media_type'] ?? null) {
                'IMAGE' => $this->image($child),
                'VIDEO' => $this->video($child),
                default => null,
            };
            if ($item !== null) {
                $media[] = $item;
            }
        }

        // If the children could not be read, the album's own media_url is its
        // cover image, so the post can still be shown.
        return $media !== [] ? $media : array_filter([$this->image($m)]);
    }

    private function image(array $m): ?array
    {
        $src = $this->safeMediaUrl($m['media_url'] ?? null);

        return $src ? ['type' => 'image', 'src' => $src, 'poster' => null] : null;
    }

    /**
     * Meta omits media_url for any video with copyrighted or licensed audio
     * (including Instagram's own music library), so `src` may be null. Such a
     * video is shown by its cover image and links out to Instagram to play.
     */
    private function video(array $m): ?array
    {
        $poster = $this->safeMediaUrl($m['thumbnail_url'] ?? null);
        if ($poster === null) {
            return null;
        }

        return ['type' => 'video', 'src' => $this->safeMediaUrl($m['media_url'] ?? null), 'poster' => $poster];
    }

    /** Only https URLs on Meta's own media CDNs are trusted. */
    private function safeMediaUrl(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 4096 || ! str_starts_with($url, 'https://')) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::MEDIA_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return $url;
            }
        }

        return null;
    }

    /** Only Meta's own instagram.com post, reel or video links. Never constructed. */
    private function safePermalink(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 512 || ! str_starts_with($url, 'https://')) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        return in_array($host, ['www.instagram.com', 'instagram.com'], true)
            && preg_match('#^/(p|reel|reels|tv)/[A-Za-z0-9_-]+/?$#', $path)
            ? $url
            : null;
    }

    private function caption(mixed $caption): ?string
    {
        if (! is_string($caption)) {
            return null;
        }

        // Plain text only. The frontend escapes it on output as well.
        $caption = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $caption) ?? '');

        return $caption === '' ? null : Str::limit($caption, 600);
    }

    private function timestamp(mixed $value): ?string
    {
        try {
            return is_string($value) ? CarbonImmutable::parse($value)->toIso8601String() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Meta's CDN URLs carry their expiry as a hex Unix timestamp in the `oe`
     * parameter. Undocumented, so used only to retire links early — a URL
     * without one is simply refreshed on the normal schedule.
     */
    private function soonestExpiry(array $media): ?int
    {
        $expiries = [];

        foreach ($media as $item) {
            foreach ([$item['src'], $item['poster']] as $url) {
                if (! $url) {
                    continue;
                }
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $oe = $query['oe'] ?? null;
                if (is_string($oe) && ctype_xdigit($oe) && strlen($oe) <= 10) {
                    $ts = hexdec($oe);
                    // Ignore anything implausible rather than trusting it.
                    if ($ts > now()->subYear()->getTimestamp() && $ts < now()->addYear()->getTimestamp()) {
                        $expiries[] = $ts;
                    }
                }
            }
        }

        return $expiries === [] ? null : min($expiries);
    }

    // ── HTTP ─────────────────────────────────────────────────────────────────

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(4)
            ->timeout(max(2, (int) config('services.instagram.timeout', 8)));
    }

    private function url(string $path): string
    {
        $version = trim((string) config('services.instagram.graph_version', 'v26.0'), '/');

        return self::HOST . '/' . $version . $path;
    }

    private function postLimit(): int
    {
        return max(1, min(25, (int) config('services.instagram.post_limit', 12)));
    }
}
