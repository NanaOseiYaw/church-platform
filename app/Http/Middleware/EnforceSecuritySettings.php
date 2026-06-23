<?php

namespace App\Http\Middleware;

use App\Models\Church;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces per-church security settings stored in church.settings['security']:
 *
 *   session_timeout_minutes — idle timeout; terminates session after inactivity
 *
 * Runs after ResolveTenant so app('church') and app('church.id') are bound.
 * Only applies to authenticated users on dashboard routes.
 */
class EnforceSecuritySettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip for unauthenticated requests and non-dashboard routes
        if (! $user || ! str_starts_with($request->path(), 'dashboard')) {
            return $next($request);
        }

        // ── Session timeout ────────────────────────────────────────────────────
        $churchId = app('church.id');
        if ($churchId) {
            $church = Church::find($churchId);
            $security = $church?->settings['security'] ?? [];
            $timeoutMinutes = (int) ($security['session_timeout_minutes'] ?? 0);

            if ($timeoutMinutes > 0) {
                $lastActivity = $request->session()->get('_last_activity', now()->timestamp);
                $inactiveFor  = now()->timestamp - (int) $lastActivity;

                if ($inactiveFor > ($timeoutMinutes * 60)) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if ($request->expectsJson()) {
                        return response()->json(
                            ['message' => 'Your session has expired due to inactivity.'],
                            401,
                        );
                    }

                    return redirect()->route('login')
                        ->with('status', 'Your session expired due to inactivity. Please log in again.');
                }
            }
        }

        // Always update last-activity timestamp on authenticated dashboard requests
        $request->session()->put('_last_activity', now()->timestamp);

        return $next($request);
    }
}
