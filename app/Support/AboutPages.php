<?php

namespace App\Support;

use App\Models\Church;

/**
 * Which About sub-pages are ready to be shown publicly.
 *
 * Beliefs and Core Values are denomination-wide content shipped in
 * config/cop.php, so they are always ready. Leadership and History are local to
 * the assembly and ship as templates — they stay hidden from the navbar
 * dropdown, hidden from the About sub-navigation, and 404 for the public until
 * an admin fills them in under Settings → About Page.
 *
 * This is the single source of truth: the navbar, the sub-navigation and the
 * controller all read from here, so a page can never be linked but unreachable,
 * or reachable but showing placeholder text to visitors. Once real content is
 * saved, each page appears everywhere automatically — no deploy needed.
 */
final class AboutPages
{
    /** Pages that are always available — content comes from config, not the DB. */
    private const ALWAYS_READY = ['beliefs', 'core-values'];

    /** Pages gated on local content existing, mapped to their settings key. */
    private const GATED = [
        'leadership' => 'leadership',
        'history'    => 'history',
    ];

    /**
     * @return array<int, string> Slugs of the sub-pages safe to show publicly.
     */
    public static function ready(?Church $church = null): array
    {
        $church ??= app('church');
        $about    = $church?->settings['about'] ?? [];

        $ready = self::ALWAYS_READY;

        foreach (self::GATED as $slug => $settingsKey) {
            if (! empty($about[$settingsKey])) {
                $ready[] = $slug;
            }
        }

        return $ready;
    }

    /** True when the given sub-page may be shown to the public. */
    public static function isReady(string $slug, ?Church $church = null): bool
    {
        return in_array($slug, self::ready($church), true);
    }
}
