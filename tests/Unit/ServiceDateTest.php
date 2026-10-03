<?php

namespace Tests\Unit;

use App\Services\Sermons\ServiceDate;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The date a sermon happened, worked out from what YouTube and the title say.
 * Titles here are real ones from the COP Amsterdam channel.
 */
class ServiceDateTest extends TestCase
{
    private function day(?\Carbon\CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    // ── Precedence ───────────────────────────────────────────────────────────

    public function test_a_live_streams_start_time_wins(): void
    {
        // Streamed Sunday 27 Sept, published after processing at 02:11 on Monday.
        $date = ServiceDate::resolve(
            '2026-09-27T08:05:00Z',
            null,
            'NATIONAL YOUTH WEEK 2026 ClIMAX SERVICE || COP AMSTERDAM CENTRAL YOUTH || 27-09-2026',
            Carbon::parse('2026-09-28T02:11:25Z'),
        );

        $this->assertSame('2026-09-27', $this->day($date));
    }

    public function test_the_recording_date_is_used_when_there_was_no_stream(): void
    {
        $date = ServiceDate::resolve(null, '2026-08-16T00:00:00Z', 'Untitled', Carbon::parse('2026-08-26T10:00:00Z'));

        $this->assertSame('2026-08-16', $this->day($date));
    }

    public function test_the_title_date_is_used_when_youtube_has_nothing_better(): void
    {
        // Uploaded four days after the service, not streamed.
        $date = ServiceDate::resolve(
            null,
            null,
            'SUPERNATURAL ENCOUNTER || SUNDAY SERVICE || THE COP AMSTERDAM CENTRAL || 23 AUGUST 2026',
            Carbon::parse('2026-08-27T18:00:00Z'),
        );

        $this->assertSame('2026-08-23', $this->day($date));
    }

    public function test_the_upload_date_is_the_last_resort(): void
    {
        $uploaded = Carbon::parse('2026-06-02T09:00:00Z');

        $date = ServiceDate::resolve(null, null, 'ABSOLUTE WORSHIP || OF THE COP HOLLAND', $uploaded);

        $this->assertSame('2026-06-02', $this->day($date));
    }

    // ── Reading dates out of titles ──────────────────────────────────────────

    public function test_the_title_formats_this_channel_uses(): void
    {
        $uploaded = Carbon::parse('2026-09-30T12:00:00Z');

        $cases = [
            'THANKSGIVING SERVICE|| THE COP AMSTERDAM CENTRAL || 20 SEPTEMBER 2026' => '2026-09-20',
            "NATIONAL CHILDREN'S CONFERENCE|| COP - HOLLAND ||12-09-2026"         => '2026-09-12',
            'SUNDAY SERVICE || THE COP AMSTERDAM CENTRAL || 14TH SEPTEMBER 2026'   => '2026-09-14',
            'LORD\'S SUPPER SUNDAY || 06 SEPTEMBER 2026'                          => '2026-09-06',
            'FRIDAY SERVICE || 5 SEPT 2026'                                       => '2026-09-05',
            'Youth night 19.09.2026'                                              => '2026-09-19',
            'Prayer meeting 21/09/2026'                                           => '2026-09-21',
        ];

        foreach ($cases as $title => $expected) {
            $this->assertSame($expected, $this->day(ServiceDate::fromTitle($title, $uploaded)), $title);
        }
    }

    public function test_numbers_that_are_not_dates_are_ignored(): void
    {
        $uploaded = Carbon::parse('2026-09-30T12:00:00Z');

        foreach ([
            'SURVIVING DIFFICULT TIMES - 1 KINGS 17:7-24 || BY ELDER WILLIAM NTOW',
            'BIBLICAL PRINCIPLES OF GIVING  (PART 1) || PASTOR DR. P.Y. ASANTE',
            'Psalm 23:1-6 and Vision 2028',
            'Service 31-02-2026',  // not a real day
        ] as $title) {
            $this->assertNull(ServiceDate::fromTitle($title, $uploaded), $title);
        }
    }

    public function test_a_title_date_far_from_the_upload_is_not_trusted(): void
    {
        $uploaded = Carbon::parse('2026-09-30T12:00:00Z');

        // A year earlier — an anniversary or a re-upload, not this video's date.
        $this->assertNull(ServiceDate::fromTitle('Founders day || 20 SEPTEMBER 2025', $uploaded));
        // After the upload — cannot be when it was recorded.
        $this->assertNull(ServiceDate::fromTitle('Upcoming: 25 OCTOBER 2026', $uploaded));
    }

    public function test_title_dates_are_stored_at_midday_so_timezones_cannot_move_them(): void
    {
        $date = ServiceDate::fromTitle('SUNDAY SERVICE || 09 AUGUST 2026', Carbon::parse('2026-08-10T01:00:00Z'));

        // Midday keeps the calendar day for any offset within ±11 hours — the
        // site formats in UTC and its visitors are in Amsterdam.
        $this->assertSame('2026-08-09 12:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-09', $date->copy()->setTimezone('Europe/Amsterdam')->format('Y-m-d'));
        $this->assertSame('2026-08-09', $date->copy()->setTimezone('America/Los_Angeles')->format('Y-m-d'));
    }
}
