<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LivestreamController extends Controller
{
    public function __invoke(): Response
    {
        $church     = app('church');
        $lsSettings = $church?->settings['livestream'] ?? [];

        // Manual override takes precedence; otherwise auto-compute from service_times.
        $nextService = ! empty($lsSettings['next_service_date'])
            ? [
                'title' => $lsSettings['next_service_title'] ?? 'Sunday Morning Worship',
                'date'  => $lsSettings['next_service_date'],
                'time'  => $lsSettings['next_service_time'] ?? '',
            ]
            : $this->nextService($church?->service_times ?? []);

        return Inertia::render('Public/Livestream', [
            'isLive'      => (bool) ($lsSettings['auto_live'] ?? false),
            'streamUrl'   => $lsSettings['embed_url'] ?? $lsSettings['stream_url'] ?? env('LIVESTREAM_URL'),
            'chatEnabled' => (bool) ($lsSettings['chat_enabled'] ?? env('LIVESTREAM_CHAT', false)),
            'nextService' => $nextService,
            'pastStreams'  => [],
        ]);
    }

    /**
     * Given the church's service_times array, compute the next upcoming service.
     * Returns the nearest service day that hasn't passed yet in the current week;
     * if today matches, it is included (the service may still be coming today).
     */
    private function nextService(array $serviceTimes): array
    {
        if (empty($serviceTimes)) {
            return ['title' => 'Next Service', 'date' => '', 'time' => ''];
        }

        $dayMap = [
            'sunday'    => 0, 'monday' => 1, 'tuesday'  => 2, 'wednesday' => 3,
            'thursday'  => 4, 'friday' => 5, 'saturday' => 6,
        ];

        $todayDow   = (int) now()->format('w'); // 0 = Sunday
        $best       = null;
        $bestDays   = PHP_INT_MAX;

        foreach ($serviceTimes as $s) {
            $dow = $dayMap[strtolower($s['day'] ?? '')] ?? null;
            if ($dow === null) continue;

            $daysAhead = ($dow - $todayDow + 7) % 7; // 0 = today

            if ($daysAhead < $bestDays) {
                $bestDays = $daysAhead;
                $best     = $s;
            }
        }

        if (! $best) {
            return ['title' => 'Next Service', 'date' => '', 'time' => ''];
        }

        $date = $bestDays === 0 ? 'Today' : now()->addDays($bestDays)->format('M j, Y');

        return [
            'title' => $best['name'] ?? (ucfirst(strtolower($best['day'] ?? '')) . ' Service'),
            'date'  => $date,
            'time'  => $best['time'] ?? '',
        ];
    }
}
