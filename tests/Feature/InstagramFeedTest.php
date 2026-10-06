<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Services\Instagram\InstagramService;
use App\Services\Instagram\InstagramTokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Instagram gallery feed, against faked responses in the shape Meta's
 * Instagram API with Instagram Login returns (graph.instagram.com).
 *
 * The real API cannot be called from the test suite — that needs the church's
 * token — so `php artisan instagram:check` exists to verify the live integration.
 */
class InstagramFeedTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'IGAA-test-token-SECRET-123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.instagram.access_token' => self::TOKEN, 'services.instagram.cache_minutes' => 60]);
        Storage::fake('local');
        Storage::fake('public');
        Cache::flush();
        // The fixtures embed expiry timestamps; a fixed clock keeps them identical.
        $this->freezeTime();

        $church = Church::create(['name' => 'COP Amsterdam', 'is_active' => true]);
        app()->instance('church', $church);
        app()->instance('church.id', $church->id);
    }

    private function service(): InstagramService
    {
        return app(InstagramService::class);
    }

    /** A Meta CDN URL, with an expiry in the `oe` parameter like the real ones. */
    private function cdn(string $name, int $expiresInDays = 7): string
    {
        return "https://scontent-ams2-1.cdninstagram.com/v/t51.29350-15/{$name}.jpg"
            . '?_nc_ht=scontent-ams2-1.cdninstagram.com&oh=00_AbC&oe=' . dechex(now()->addDays($expiresInDays)->getTimestamp());
    }

    private function feedResponse(array $items): array
    {
        return ['data' => $items, 'paging' => ['cursors' => ['before' => 'a', 'after' => 'b']]];
    }

    private function sampleItems(): array
    {
        return [
            [   // a single photo
                'id' => '17900000000000001', 'media_type' => 'IMAGE',
                'media_url' => $this->cdn('photo'), 'caption' => 'Sunday worship ❤️ #copamsterdam',
                'permalink' => 'https://www.instagram.com/p/ABC123/', 'timestamp' => '2026-10-04T10:15:00+0000',
            ],
            [   // a carousel: two photos and a video
                'id' => '17900000000000002', 'media_type' => 'CAROUSEL_ALBUM',
                'media_url' => $this->cdn('album-cover'), 'caption' => 'Youth week highlights',
                'permalink' => 'https://www.instagram.com/p/DEF456/', 'timestamp' => '2026-10-02T18:00:00+0000',
                'children' => ['data' => [
                    ['id' => '17900000000000021', 'media_type' => 'IMAGE', 'media_url' => $this->cdn('c1')],
                    ['id' => '17900000000000022', 'media_type' => 'IMAGE', 'media_url' => $this->cdn('c2')],
                    ['id' => '17900000000000023', 'media_type' => 'VIDEO',
                     'media_url' => 'https://scontent.cdninstagram.com/o1/v/clip.mp4?oe=' . dechex(now()->addDays(7)->getTimestamp()),
                     'thumbnail_url' => $this->cdn('c3-poster')],
                ]],
            ],
            [   // a Reel with a playable file (original audio)
                'id' => '17900000000000003', 'media_type' => 'VIDEO',
                'media_url' => 'https://scontent.cdninstagram.com/o1/v/reel.mp4?oe=' . dechex(now()->addDays(7)->getTimestamp()),
                'thumbnail_url' => $this->cdn('reel-cover'), 'caption' => 'Choir rehearsal',
                'permalink' => 'https://www.instagram.com/reel/GHI789/', 'timestamp' => '2026-09-30T19:00:00+0000',
            ],
            [   // a Reel with licensed music: Meta omits media_url
                'id' => '17900000000000004', 'media_type' => 'VIDEO',
                'thumbnail_url' => $this->cdn('music-reel-cover'),
                'permalink' => 'https://www.instagram.com/reel/JKL012/', 'timestamp' => '2026-09-28T12:00:00+0000',
            ],
        ];
    }

    private function fakeMeta(array $items): void
    {
        Http::fake(['graph.instagram.com/*' => Http::response($this->feedResponse($items))]);
    }

    /**
     * First call succeeds with the sample feed, the next returns $then.
     * (A second Http::fake() would add a stub behind the first, not replace it.)
     */
    private function fakeMetaThen($then): void
    {
        Http::fake(['graph.instagram.com/*' => Http::sequence()
            ->push($this->feedResponse($this->sampleItems()))
            ->pushResponse($then)]);
    }

    private function postsById(): array
    {
        return collect($this->service()->posts())->keyBy('id')->all();
    }

    // ── Normal posts ─────────────────────────────────────────────────────────

    public function test_a_photo_post_is_normalised_with_its_own_permalink(): void
    {
        $this->fakeMeta($this->sampleItems());
        $this->assertSame('refreshed', $this->service()->refresh());

        $p = $this->postsById()['17900000000000001'];
        $this->assertSame('image', $p['type']);
        $this->assertSame($this->sampleItems()[0]['media_url'], $p['thumb']);
        $this->assertSame('https://www.instagram.com/p/ABC123/', $p['permalink']);
        $this->assertSame('Sunday worship ❤️ #copamsterdam', $p['caption']);
        $this->assertSame(1, $p['count']);
    }

    public function test_the_request_asks_only_for_the_fields_the_gallery_uses(): void
    {
        $this->fakeMeta([]);
        $this->service()->refresh();

        Http::assertSent(function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return str_starts_with($r->url(), 'https://graph.instagram.com/v26.0/me/media')
                && $q['fields'] === 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,children{id,media_type,media_url,thumbnail_url}'
                && (int) $q['limit'] === 12;
        });
        Http::assertSentCount(1);
    }

    // ── Carousels ────────────────────────────────────────────────────────────

    public function test_a_carousel_is_one_post_with_its_children_inside(): void
    {
        $this->fakeMeta($this->sampleItems());
        $this->service()->refresh();

        $posts = $this->service()->posts();
        $this->assertCount(4, $posts, 'a 3-item carousel must not become 3 tiles');

        $album = $this->postsById()['17900000000000002'];
        $this->assertSame('carousel', $album['type']);
        $this->assertSame(3, $album['count']);
        $this->assertSame(['image', 'image', 'video'], array_column($album['media'], 'type'));
        $this->assertSame($album['media'][0]['src'], $album['thumb'], 'tile uses the first child');
    }

    // ── Reels and video ──────────────────────────────────────────────────────

    public function test_a_reel_is_recognised_from_its_permalink_and_keeps_a_cover(): void
    {
        $this->fakeMeta($this->sampleItems());
        $this->service()->refresh();

        $reel = $this->postsById()['17900000000000003'];
        $this->assertSame('video', $reel['type']);
        $this->assertTrue($reel['is_reel']);
        $this->assertStringContainsString('reel-cover', $reel['thumb']);
        $this->assertStringContainsString('reel.mp4', $reel['media'][0]['src']);
    }

    public function test_a_video_with_licensed_music_has_no_playable_file_but_still_shows(): void
    {
        $this->fakeMeta($this->sampleItems());
        $this->service()->refresh();

        $p = $this->postsById()['17900000000000004'];
        $this->assertNull($p['media'][0]['src'], 'Meta omits media_url for licensed audio');
        $this->assertStringContainsString('music-reel-cover', $p['thumb']);
        $this->assertNull($p['caption'], 'caption is optional');
    }

    // ── Untrusted data ───────────────────────────────────────────────────────

    public function test_media_from_unexpected_hosts_and_invented_links_are_dropped(): void
    {
        $this->fakeMeta([
            ['id' => '1', 'media_type' => 'IMAGE', 'media_url' => 'https://evil.example/x.jpg',
             'permalink' => 'https://www.instagram.com/p/AAA/'],
            ['id' => '2', 'media_type' => 'IMAGE', 'media_url' => 'http://scontent.cdninstagram.com/x.jpg',
             'permalink' => 'https://www.instagram.com/p/BBB/'],
            ['id' => '3', 'media_type' => 'IMAGE', 'media_url' => $this->cdn('ok'),
             'permalink' => 'https://evil.example/p/CCC/'],
            ['id' => '4', 'media_type' => 'IMAGE', 'media_url' => $this->cdn('ok'),
             'permalink' => 'javascript:alert(1)'],
            ['id' => 'not-numeric', 'media_type' => 'IMAGE', 'media_url' => $this->cdn('ok'),
             'permalink' => 'https://www.instagram.com/p/DDD/'],
            ['id' => '6', 'media_type' => 'STORY', 'media_url' => $this->cdn('ok'),
             'permalink' => 'https://www.instagram.com/p/EEE/'],
        ]);
        $this->service()->refresh();

        $this->assertSame([], $this->service()->posts());
    }

    // ── Caching ──────────────────────────────────────────────────────────────

    public function test_reading_posts_never_calls_meta(): void
    {
        $this->fakeMeta($this->sampleItems());
        $this->service()->refresh();
        Http::fake(); // reset the record

        $this->service()->posts();
        $this->service()->posts(6);

        Http::assertNothingSent();
    }

    public function test_a_fresh_feed_is_not_fetched_again(): void
    {
        $this->fakeMeta($this->sampleItems());

        $this->assertSame('refreshed', $this->service()->refresh());
        $this->assertSame('fresh', $this->service()->refresh());
        Http::assertSentCount(1);
    }

    public function test_a_stale_feed_is_refreshed(): void
    {
        $this->fakeMeta($this->sampleItems());
        $this->service()->refresh();

        $this->travel(61)->minutes();
        $this->assertSame('refreshed', $this->service()->refresh());
        Http::assertSentCount(2);
    }

    public function test_posts_with_expired_links_are_dropped(): void
    {
        $this->fakeMeta([[
            'id' => '17900000000000009', 'media_type' => 'IMAGE', 'media_url' => $this->cdn('soon', 1),
            'permalink' => 'https://www.instagram.com/p/OLD/',
        ]]);
        $this->service()->refresh();
        $this->assertCount(1, $this->service()->posts());

        $this->travel(2)->days();
        $this->assertSame([], $this->service()->posts(), 'an image whose URL has expired would not load');
    }

    // ── Failure modes ────────────────────────────────────────────────────────

    public function test_when_meta_fails_the_previous_feed_is_kept(): void
    {
        $this->fakeMetaThen(Http::response(['error' => ['message' => 'Unknown error', 'code' => 1]], 500));
        $this->service()->refresh();
        $this->travel(61)->minutes();

        $this->assertSame('failed', $this->service()->refresh());
        $this->assertCount(4, $this->service()->posts(), 'stale copy still served');
    }

    public function test_an_expired_token_is_logged_and_backed_off(): void
    {
        Log::spy();
        Http::fake(['graph.instagram.com/*' => Http::response(['error' => [
            'message' => 'Error validating access token: Session has expired.', 'type' => 'OAuthException', 'code' => 190,
        ]], 400)]);

        $this->assertSame('failed', $this->service()->refresh());
        Log::shouldHaveReceived('error')->withArgs(fn ($m) => str_contains($m, 'code 190'))->once();

        // Backed off: the next attempt does not hammer Meta.
        $this->assertSame('backoff', $this->service()->refresh());
        Http::assertSentCount(1);
    }

    public function test_a_rate_limit_backs_off_and_keeps_serving_the_cache(): void
    {
        $this->fakeMetaThen(Http::response(['error' => ['message' => 'Application request limit reached', 'code' => 4]], 400));
        $this->service()->refresh();
        $this->travel(61)->minutes();

        $this->assertSame('failed', $this->service()->refresh());
        $this->assertSame('backoff', $this->service()->refresh());
        $this->assertCount(4, $this->service()->posts());
    }

    public function test_a_timeout_is_handled_and_the_token_never_reaches_the_log(): void
    {
        Log::spy();
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 8001 milliseconds for '
                . 'https://graph.instagram.com/v26.0/me/media?fields=id&access_token=' . self::TOKEN);
        });

        $this->assertSame('failed', $this->service()->refresh());

        Log::shouldHaveReceived('warning')->withArgs(
            fn ($m) => str_contains($m, 'timed out') && str_contains($m, '[redacted]') && ! str_contains($m, self::TOKEN)
        )->once();
    }

    public function test_an_invalid_response_keeps_the_previous_feed(): void
    {
        $this->fakeMetaThen(Http::response('<html>not json</html>', 200));
        $this->service()->refresh();
        $this->travel(61)->minutes();

        $this->assertSame('failed', $this->service()->refresh());
        $this->assertCount(4, $this->service()->posts());
    }

    public function test_without_a_token_nothing_is_called_and_nothing_breaks(): void
    {
        config(['services.instagram.access_token' => '']);
        Http::fake();

        $this->assertSame('unconfigured', $this->service()->refresh());
        $this->assertSame([], $this->service()->posts());
        $this->get('/gallery')->assertOk();
        $this->get('/')->assertOk();

        Http::assertNothingSent();
    }

    public function test_an_empty_account_shows_no_section_and_breaks_nothing(): void
    {
        $this->fakeMeta([]);
        $this->assertSame('refreshed', $this->service()->refresh());

        $this->assertSame([], $this->service()->posts());
        $this->get('/gallery')->assertOk()->assertInertia(fn ($page) => $page->where('instagramPosts', []));
    }

    // ── Pages ────────────────────────────────────────────────────────────────

    public function test_a_page_is_never_held_up_waiting_for_meta(): void
    {
        $this->fakeMeta($this->sampleItems());

        // Nothing cached yet: the page renders straight away without posts…
        $this->get('/gallery')->assertOk()->assertInertia(fn ($page) => $page->where('instagramPosts', []));

        // …and the refresh it scheduled ran after the response was sent.
        Http::assertSentCount(1);
        $this->get('/gallery')->assertInertia(fn ($page) => $page->has('instagramPosts', 4));
    }

    public function test_the_token_is_never_sent_to_the_browser(): void
    {
        $this->fakeMeta($this->sampleItems());
        $this->service()->refresh();

        foreach (['/gallery', '/'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString(self::TOKEN, $html, $url);
            $this->assertStringNotContainsString('access_token', $html, $url);
        }
    }

    public function test_the_homepage_gets_six_plus_spares_and_respects_its_toggle(): void
    {
        $items = [];
        foreach (range(1, 9) as $i) {
            $items[] = ['id' => (string) (17910000000000000 + $i), 'media_type' => 'IMAGE', 'media_url' => $this->cdn("p{$i}"),
                        'permalink' => "https://www.instagram.com/p/P{$i}/"];
        }
        $this->fakeMeta($items);
        $this->service()->refresh();

        // Eight sent, six shown: two spares backfill a tile whose image fails.
        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('instagramPosts', 8)
            ->where('instagramPosts.0.id', '17910000000000001')
            ->where('instagramPosts.7.id', '17910000000000008'));

        $church = app('church');
        $church->update(['settings' => ['homepage' => ['section_visibility' => ['instagram' => false]]]]);
        app()->instance('church', $church->fresh());

        $this->get('/')->assertInertia(fn ($page) => $page->where('instagramPosts', []));
    }

    // ── Storage ──────────────────────────────────────────────────────────────

    public function test_no_instagram_media_is_downloaded_or_stored(): void
    {
        $this->fakeMeta($this->sampleItems());
        $this->service()->refresh();
        $this->get('/gallery');

        // Only Meta's API was called — never its media CDN.
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'cdninstagram.com') || str_contains($r->url(), 'fbcdn.net'));
        // Nothing written to public storage, and nothing on the private disk
        // (the token file only appears after a token refresh).
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ── Token refresh ────────────────────────────────────────────────────────

    public function test_a_refreshed_token_is_stored_encrypted_and_used(): void
    {
        Http::fake(['graph.instagram.com/refresh_access_token*' => Http::response([
            'access_token' => 'IGAA-new-token-NEW-456', 'token_type' => 'bearer', 'expires_in' => 5183944,
        ])]);

        $this->assertSame('refreshed', $this->service()->refreshToken());

        $stored = Storage::disk('local')->get('instagram/token.json');
        $this->assertStringNotContainsString('IGAA-new-token-NEW-456', $stored, 'stored encrypted');
        $this->assertSame('IGAA-new-token-NEW-456', app(InstagramTokenStore::class)->current());

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://graph.instagram.com/refresh_access_token')
            && str_contains($r->url(), 'grant_type=ig_refresh_token'));

        // Weekly: not refreshed again straight away.
        $this->assertSame('not-due', $this->service()->refreshToken());
    }

    public function test_pasting_a_new_token_into_env_takes_over_from_the_stored_one(): void
    {
        Http::fake(['graph.instagram.com/refresh_access_token*' => Http::response(['access_token' => 'REFRESHED-A', 'expires_in' => 100])]);
        $this->service()->refreshToken();
        $this->assertSame('REFRESHED-A', app(InstagramTokenStore::class)->current());

        config(['services.instagram.access_token' => 'BRAND-NEW-B']);
        $this->assertSame('BRAND-NEW-B', app(InstagramTokenStore::class)->current());
    }

    public function test_a_failed_token_refresh_keeps_the_working_token(): void
    {
        Log::spy();
        Http::fake(['graph.instagram.com/*' => Http::response(['error' => ['message' => 'Token too new', 'code' => 10]], 400)]);

        $this->assertSame('failed', $this->service()->refreshToken());
        $this->assertSame(self::TOKEN, app(InstagramTokenStore::class)->current());
    }
}
