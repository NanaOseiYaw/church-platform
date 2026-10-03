<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Church;
use App\Models\Sermon;
use App\Services\Sermons\SermonSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The YouTube channel sync, end to end against faked API responses.
 *
 * Written after connecting the COP Amsterdam channel showed two problems:
 * sermons were dated by their YouTube upload (so Sunday services read as
 * Monday), and every hourly sync overwrote the title, description and date,
 * silently undoing anything an admin had corrected on the website.
 */
class YouTubeSyncTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private ChannelConnection $connection;

    /** Video ID => [title, uploaded (published) at, live start or null, duration]. */
    private array $videos = [
        'liveSunday01' => ['NATIONAL YOUTH WEEK 2026 ClIMAX SERVICE || COP AMSTERDAM CENTRAL YOUTH || 27-09-2026', '2026-09-28T02:11:25Z', '2026-09-27T08:05:00Z', 'PT2H10M'],
        'uploadLate01' => ['SUPERNATURAL ENCOUNTER || SUNDAY SERVICE || THE COP AMSTERDAM CENTRAL || 23 AUGUST 2026', '2026-08-27T18:00:00Z', null, 'PT1H30M'],
        'noDateAny001' => ['ABSOLUTE WORSHIP || OF THE COP HOLLAND || LED BY PIWC AMSTERDAM CHOIR', '2026-06-02T09:00:00Z', null, 'PT12M'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.youtube.api_key' => 'test-key']);

        $this->church = Church::create(['name' => 'COP Amsterdam', 'is_active' => true]);
        app()->instance('church', $this->church);
        app()->instance('church.id', $this->church->id);

        $this->connection = ChannelConnection::create([
            'church_id'            => $this->church->id,
            'provider'             => 'youtube',
            'channel_id'           => 'UCKWuSAWAkw2d68_g9KLo1Zw',
            'channel_title'        => 'THE CHURCH OF PENTECOST AMSTERDAM',
            'uploads_playlist_id'  => 'UUKWuSAWAkw2d68_g9KLo1Zw',
            'is_active'            => true,
            'sync_frequency_hours' => 1,
        ]);

        $this->fakeYouTube();
    }

    private function fakeYouTube(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/playlistItems')) {
                return Http::response(['items' => array_map(fn ($id, $v) => [
                    'snippet' => [
                        'title'       => $v[0],
                        'description' => "Description of {$id}",
                        'thumbnails'  => ['high' => ['url' => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg"]],
                        'publishedAt' => $v[1],
                    ],
                    'contentDetails' => ['videoId' => $id, 'videoPublishedAt' => $v[1]],
                ], array_keys($this->videos), $this->videos)]);
            }

            if (str_contains($url, '/videos')) {
                return Http::response(['items' => array_map(fn ($id, $v) => array_filter([
                    'id'                   => $id,
                    'contentDetails'       => ['duration' => $v[3]],
                    'liveStreamingDetails' => $v[2] ? ['actualStartTime' => $v[2]] : null,
                ]), array_keys($this->videos), $this->videos)]);
            }

            return Http::response([], 404);
        });
    }

    private function sync(): array
    {
        return app(SermonSyncService::class)->syncChannel($this->connection->fresh());
    }

    private function sermon(string $videoId): Sermon
    {
        return Sermon::withoutGlobalScopes()->withTrashed()->where('provider_video_id', $videoId)->firstOrFail();
    }

    // ── Dates on first import ────────────────────────────────────────────────

    public function test_a_live_stream_is_dated_by_when_it_started_not_when_it_was_published(): void
    {
        $this->sync();

        $this->assertSame('2026-09-27', $this->sermon('liveSunday01')->preached_at->format('Y-m-d'));
    }

    public function test_a_late_upload_is_dated_from_its_title(): void
    {
        $this->sync();

        $this->assertSame('2026-08-23', $this->sermon('uploadLate01')->preached_at->format('Y-m-d'));
    }

    public function test_a_video_with_no_better_date_keeps_its_upload_date(): void
    {
        $this->sync();

        $this->assertSame('2026-06-02', $this->sermon('noDateAny001')->preached_at->format('Y-m-d'));
    }

    public function test_the_service_date_comes_from_the_same_videos_call_as_the_duration(): void
    {
        $this->sync();

        // One videos.list call for the batch, asking for all three parts —
        // the date costs no extra quota.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/videos')
            && str_contains(urldecode($r->url()), 'part=contentDetails,liveStreamingDetails,recordingDetails'));
        $this->assertSame(130 * 60, $this->sermon('liveSunday01')->duration_seconds);
    }

    // ── Re-syncing ───────────────────────────────────────────────────────────

    public function test_resync_keeps_an_admins_edits(): void
    {
        $this->sync();

        $sermon = $this->sermon('liveSunday01');
        $sermon->update([
            'title'       => 'National Youth Week Climax Service',
            'description' => 'Edited on the website.',
            'preached_at' => '2026-09-26 18:00:00',
        ]);

        $this->sync();
        $sermon->refresh();

        $this->assertSame('National Youth Week Climax Service', $sermon->title);
        $this->assertSame('Edited on the website.', $sermon->description);
        $this->assertSame('2026-09-26', $sermon->preached_at->format('Y-m-d'));
    }

    public function test_resync_still_refreshes_the_video_details(): void
    {
        $this->sync();
        $this->sermon('liveSunday01')->update(['thumbnail_url' => 'https://old.example/thumb.jpg', 'duration_seconds' => 1]);

        $this->sync();

        $sermon = $this->sermon('liveSunday01');
        $this->assertSame('https://i.ytimg.com/vi/liveSunday01/hqdefault.jpg', $sermon->thumbnail_url);
        $this->assertSame(130 * 60, $sermon->duration_seconds);
    }

    public function test_resync_corrects_dates_left_by_the_old_upload_date_import(): void
    {
        $this->sync();

        // What the previous version stored: the exact upload timestamp.
        $this->sermon('liveSunday01')->update(['preached_at' => '2026-09-28 02:11:25']);
        $this->sermon('uploadLate01')->update(['preached_at' => '2026-08-27 18:00:00']);

        $this->sync();

        $this->assertSame('2026-09-27', $this->sermon('liveSunday01')->preached_at->format('Y-m-d'));
        $this->assertSame('2026-08-23', $this->sermon('uploadLate01')->preached_at->format('Y-m-d'));
    }

    public function test_resync_does_not_touch_a_date_an_admin_changed_after_the_old_import(): void
    {
        $this->sync();
        // Not the upload timestamp, so someone set it deliberately.
        $this->sermon('uploadLate01')->update(['preached_at' => '2026-08-22 10:00:00']);

        $this->sync();

        $this->assertSame('2026-08-22', $this->sermon('uploadLate01')->preached_at->format('Y-m-d'));
    }

    public function test_a_hidden_sermon_stays_hidden(): void
    {
        $this->sync();
        $this->sermon('noDateAny001')->update(['visibility' => 'private', 'is_public' => false]);

        $this->sync();

        $this->assertSame('private', $this->sermon('noDateAny001')->visibility);
    }

    public function test_resync_creates_nothing_new(): void
    {
        $first  = $this->sync();
        $second = $this->sync();

        $this->assertSame(3, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(3, Sermon::withoutGlobalScopes()->count());
    }
}
