<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Evict deactivated users from active sessions.
 *
 * Auth::attempt() is now gated in AuthService, so new logins are already
 * blocked. This middleware handles the case where an admin deactivates a user
 * who is currently browsing — their next request will be caught here, the
 * session destroyed, and they will be redirected to login.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! ($request->user()->is_active ?? true)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => 'Your account has been deactivated. Please contact your church administrator.'],
                    403,
                );
            }

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact your church administrator.']);
        }

        return $next($request);
    }
}
