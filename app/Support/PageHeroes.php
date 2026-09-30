<?php

namespace App\Support;

use App\Models\Church;

/**
 * Header background images for the public site.
 *
 * Every page can have its own image, with a site-wide default behind it and the
 * brand gradient behind that:
 *
 *     per-page image  →  site-wide default  →  gradient
 *
 * The homepage is deliberately read from and written to its long-standing
 * `homepage.hero_image` setting rather than being migrated into this map. That
 * keeps existing data working untouched while still letting one settings screen
 * manage every hero image on the site.
 */
final class PageHeroes
{
    /** Settings key for the homepage, which predates this map. */
    public const HOMEPAGE_KEY = 'home';

    /**
     * Every page with its own header, in the order shown in Settings.
     * `path` is matched against the request URL to resolve the image at runtime.
     *
     * @var array<int, array{key: string, label: string, path: string, group?: string}>
     */
    public const PAGES = [
        ['key' => 'home',          'label' => 'Homepage',          'path' => '/'],
        ['key' => 'about',         'label' => 'About',             'path' => '/about'],
        ['key' => 'leadership',    'label' => 'Leadership',        'path' => '/about/leadership',  'group' => 'About'],
        ['key' => 'history',       'label' => 'Our History',       'path' => '/about/history',     'group' => 'About'],
        ['key' => 'beliefs',       'label' => 'Beliefs & Tenets',  'path' => '/about/beliefs',     'group' => 'About'],
        ['key' => 'core-values',   'label' => 'Core Values',       'path' => '/about/core-values', 'group' => 'About'],
        ['key' => 'ministries',    'label' => 'Ministries',        'path' => '/ministries'],
        ['key' => 'events',        'label' => 'Events',            'path' => '/events'],
        ['key' => 'sermons',       'label' => 'Sermons',           'path' => '/sermons'],
        ['key' => 'series',        'label' => 'Series',            'path' => '/series'],
        ['key' => 'gallery',       'label' => 'Gallery',           'path' => '/gallery'],
        ['key' => 'announcements', 'label' => 'Announcements',     'path' => '/announcements'],
        ['key' => 'prayer',        'label' => 'Prayer',            'path' => '/prayer'],
        ['key' => 'contact',       'label' => 'Contact',           'path' => '/contact'],
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_column(self::PAGES, 'key');
    }

    public static function isValidKey(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /**
     * The per-page image map sent to the frontend, keyed by page key.
     * Only pages that actually have an image are included.
     *
     * @return array<string, string>
     */
    public static function map(?Church $church = null): array
    {
        $church ??= app('church');
        $settings = $church?->settings ?? [];

        $map = array_filter($settings['website']['page_hero_images'] ?? []);

        // The homepage keeps its original settings location.
        if (! empty($settings['homepage']['hero_image'])) {
            $map[self::HOMEPAGE_KEY] = $settings['homepage']['hero_image'];
        }

        return $map;
    }

    /** The fallback used by any page without its own image. */
    public static function default(?Church $church = null): ?string
    {
        $church ??= app('church');

        return $church?->settings['website']['page_hero_image'] ?? null;
    }

    /**
     * Where an uploaded image for $key should be stored.
     * Returns [settings namespace, dot-path within that namespace].
     *
     * @return array{0: string, 1: string}
     */
    public static function storageTarget(string $key): array
    {
        return $key === self::HOMEPAGE_KEY
            ? ['homepage', 'hero_image']
            : ['website', "page_hero_images.{$key}"];
    }
}
