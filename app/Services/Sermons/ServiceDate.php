<?php

namespace App\Services\Sermons;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * When a sermon actually happened, as opposed to when its video reached YouTube.
 *
 * The sync used to store the YouTube upload date as the sermon date. A live
 * stream is only published once it has finished processing, usually after
 * midnight, so Sunday services were dated Monday — and a video uploaded days
 * later was dated days late. On the COP Amsterdam channel that was 57 of the 72
 * videos whose titles carry a date.
 *
 * Sources, most trustworthy first:
 *
 *   1. When the live stream actually started (liveStreamingDetails). Exact.
 *   2. The recording date, when the uploader set one (recordingDetails).
 *   3. A date written in the title, which this channel does consistently —
 *      accepted only if it falls shortly before the upload, so a stray number
 *      in a title can never produce an implausible date.
 *   4. The upload date, when nothing better is known.
 *
 * Dates without a time of day are stored at 12:00 UTC so that no timezone
 * conversion can move them onto a neighbouring day.
 */
final class ServiceDate
{
    /** A date in a title is trusted only if it is at most this many days before the upload. */
    private const TITLE_WINDOW_DAYS = 60;

    private const MONTHS = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
    ];

    public static function resolve(
        ?string $liveStartedAt,
        ?string $recordedOn,
        string $title,
        ?CarbonInterface $uploadedAt,
    ): ?CarbonInterface {
        if ($liveStartedAt) {
            return Carbon::parse($liveStartedAt)->utc();
        }

        if ($recordedOn) {
            return Carbon::parse($recordedOn)->utc()->setTime(12, 0);
        }

        return self::fromTitle($title, $uploadedAt) ?? $uploadedAt;
    }

    /** The date written in $title, if there is one and it is plausible for this upload. */
    public static function fromTitle(string $title, ?CarbonInterface $uploadedAt = null): ?Carbon
    {
        $date = self::parse($title);

        if (! $date || ! $uploadedAt) {
            return $date;
        }

        $earliest = $uploadedAt->copy()->utc()->subDays(self::TITLE_WINDOW_DAYS)->startOfDay();
        $latest   = $uploadedAt->copy()->utc()->addDay()->endOfDay();

        return $date->between($earliest, $latest) ? $date : null;
    }

    private static function parse(string $title): ?Carbon
    {
        $t = strtoupper($title);

        // 27-09-2026, 27.09.2026, 27/09/2026 — day first, as this channel writes them.
        if (preg_match('/(?<!\d)(\d{1,2})\s*[-.\/]\s*(\d{1,2})\s*[-.\/]\s*(20\d{2})(?!\d)/', $t, $m)) {
            return self::make((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // 16 AUGUST 2026, 14TH JUNE 2026, 5 SEPT 2026
        $month = 'JAN(?:UARY)?|FEB(?:RUARY)?|MAR(?:CH)?|APR(?:IL)?|MAY|JUNE?|JULY?|AUG(?:UST)?'
               . '|SEPT?(?:EMBER)?|OCT(?:OBER)?|NOV(?:EMBER)?|DEC(?:EMBER)?';

        if (preg_match('/(?<!\d)(\d{1,2})(?:ST|ND|RD|TH)?\s+(' . $month . ')\.?,?\s+(20\d{2})(?!\d)/', $t, $m)) {
            return self::make((int) $m[3], self::MONTHS[substr($m[2], 0, 3)], (int) $m[1]);
        }

        return null;
    }

    private static function make(int $year, int $month, int $day): ?Carbon
    {
        return checkdate($month, $day, $year)
            ? Carbon::create($year, $month, $day, 12, 0, 0, 'UTC')
            : null;
    }
}
