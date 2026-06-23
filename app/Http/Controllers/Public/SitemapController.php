<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Sermon;
use App\Models\SermonSeries;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * GET /sitemap.xml
     *
     * Returns a dynamically-generated XML sitemap for the current church tenant.
     * - When privacy_mode is enabled, returns 404 (the site is not public).
     * - Includes static public pages + individual sermon + series URLs.
     * - BelongsToChurch global scope on Sermon/SermonSeries auto-filters by church_id.
     */
    public function __invoke(): Response
    {
        $church = app('church');

        // Respect privacy mode — don't expose URLs for private sites
        if ($church?->settings['website']['privacy_mode'] ?? false) {
            abort(404);
        }

        $baseUrl = rtrim(config('app.url'), '/');

        // ── Static pages ──────────────────────────────────────────────────────
        $staticPages = [
            ['loc' => $baseUrl . '/',              'changefreq' => 'weekly',  'priority' => '1.0'],
            ['loc' => $baseUrl . '/about',         'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $baseUrl . '/events',        'changefreq' => 'daily',   'priority' => '0.8'],
            ['loc' => $baseUrl . '/sermons',       'changefreq' => 'weekly',  'priority' => '0.8'],
            ['loc' => $baseUrl . '/series',        'changefreq' => 'weekly',  'priority' => '0.7'],
            ['loc' => $baseUrl . '/ministries',    'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $baseUrl . '/announcements', 'changefreq' => 'daily',   'priority' => '0.7'],
            ['loc' => $baseUrl . '/contact',       'changefreq' => 'yearly',  'priority' => '0.6'],
            ['loc' => $baseUrl . '/give',          'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => $baseUrl . '/live',          'changefreq' => 'weekly',  'priority' => '0.6'],
        ];

        // ── Individual sermons ────────────────────────────────────────────────
        // BelongsToChurch scope + publiclyVisible scope applied automatically.
        $sermons = Sermon::publiclyVisible()
            ->whereNotNull('slug')
            ->orderByDesc('preached_at')
            ->get(['slug', 'preached_at']);

        // ── Sermon series ─────────────────────────────────────────────────────
        $churchId  = app('church.id');
        $seriesList = $churchId
            ? SermonSeries::where('church_id', $churchId)
                ->where('is_active', true)
                ->orderByDesc('started_at')
                ->get(['slug', 'started_at'])
            : collect();

        $xml = view('sitemap', compact('staticPages', 'sermons', 'seriesList', 'baseUrl'));

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}
