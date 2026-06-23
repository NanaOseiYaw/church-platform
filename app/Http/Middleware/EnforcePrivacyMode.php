<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If the church has privacy_mode enabled in Settings → Public Website,
 * guests are redirected to the login page instead of seeing public pages.
 *
 * Authenticated users always pass through regardless of the setting,
 * so staff can still access the site to review content.
 */
class EnforcePrivacyMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Already logged in — always allow.
        if ($request->user()) {
            return $next($request);
        }

        $church = app('church');

        if ($church && ! empty($church->settings['website']['privacy_mode'])) {
            return redirect()->route('login')
                ->with('error', 'This site is in private mode. Please log in to continue.');
        }

        return $next($request);
    }
}
