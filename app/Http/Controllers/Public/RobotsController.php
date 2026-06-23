<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * GET /robots.txt
     *
     * Dynamically generates robots.txt based on church settings:
     * - privacy_mode ON  → Disallow all crawling
     * - robots = "noindex,nofollow" → Disallow all crawling
     * - robots = other noindex → Disallow crawling
     * - default → Allow all + link to sitemap
     */
    public function __invoke(): Response
    {
        $church     = app('church');
        $settings   = $church?->settings ?? [];
        $seoSettings = $settings['seo'] ?? [];
        $privacyMode = $settings['website']['privacy_mode'] ?? false;
        $robots      = $seoSettings['robots'] ?? 'index,follow';

        $baseUrl  = rtrim(config('app.url'), '/');
        $disallow = $privacyMode || str_starts_with($robots, 'noindex');

        if ($disallow) {
            $content = <<<TXT
User-agent: *
Disallow: /
TXT;
        } else {
            $content = <<<TXT
User-agent: *
Allow: /

Sitemap: {$baseUrl}/sitemap.xml
TXT;
        }

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
