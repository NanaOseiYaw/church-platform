<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Deleting a public-disk upload when all that was stored is its URL.
 *
 * The settings uploads (logo, favicon, hero and header images, social share
 * image, avatars) store only `Storage::disk('public')->url($path)`, so when one
 * is replaced the old file has to be found by working back from that URL.
 * Because the URL comes from stored settings rather than from the upload we
 * just handled, every step is guarded: only a URL on this site's public disk,
 * only inside the folder that kind of upload is written to, never a path that
 * climbs out of it, and only a file that actually exists.
 */
final class PublicUploads
{
    /**
     * The public-disk path behind $url, or null if $url is not one of our
     * uploads in $directory.
     */
    public static function pathFromUrl(?string $url, string $directory): ?string
    {
        if (! $url) {
            return null;
        }

        $base = parse_url(Storage::disk('public')->url(''));
        $own  = parse_url($url);

        if (! isset($own['path'], $base['path'])) {
            return null;
        }

        // A URL with a host must be on our own host. A relative URL is ours by
        // definition. This stops a file hosted elsewhere under the same path
        // from deleting a local file that happens to share its name.
        //
        // The disk's URL is normally absolute (APP_URL/storage), but it can be
        // configured relative, so fall back to APP_URL's host rather than
        // skipping the check — skipping it is exactly what let a foreign URL
        // through when the test disk had no host.
        if (isset($own['host'])) {
            $ourHost = $base['host'] ?? parse_url((string) config('app.url'), PHP_URL_HOST);

            if (! $ourHost || strcasecmp($own['host'], $ourHost) !== 0) {
                return null;
            }
        }

        $prefix = rtrim($base['path'], '/') . '/';
        if (! str_starts_with($own['path'], $prefix)) {
            return null;
        }

        $relative = rawurldecode(substr($own['path'], strlen($prefix)));
        $folder   = trim($directory, '/') . '/';

        if (! str_starts_with($relative, $folder)
            || str_contains($relative, '..')
            || str_contains($relative, "\0")
            || str_contains($relative, '\\')) {
            return null;
        }

        return $relative;
    }

    /** Delete the upload behind $url if it is ours and inside $directory. */
    public static function deleteUrl(?string $url, string $directory): bool
    {
        $path = self::pathFromUrl($url, $directory);

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }
}
