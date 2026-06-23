<?php

namespace App\Http\Middleware;

use App\Models\Church;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $church = $this->resolveChurch($request);

        // Bind the church ID (used by BelongsToChurch global scope)
        App::bind('church.id', fn () => $church?->id);

        // Bind the full Church model once per request so HandleInertiaRequests
        // can reuse it without issuing a second query.
        App::bind('church', fn () => $church);

        return $next($request);
    }

    private function resolveChurch(Request $request): ?Church
    {
        // 0. Impersonation — super admin context switch always takes priority
        if ($id = $request->session()->get('super_admin_impersonating')) {
            return Church::find($id);
        }

        // 1. Authenticated user — use their church
        if ($user = $request->user()) {
            if ($user->church_id) {
                return Church::find($user->church_id);
            }
            // Super admin has church_id = null — return null rather than falling
            // through to the single-tenant fallback, which would silently assign
            // the first church.
            if ($user->hasRole('super_admin')) {
                return null; // handle() and HandleInertiaRequests are both null-safe
            }
        }

        // 2. Subdomain — future: resolve church by domain
        // $host = $request->getHost();
        // $church = Church::where('domain', $host)->first();
        // if ($church) return $church;

        // 3. Single-tenant fallback — used by unauthenticated guests
        return Church::query()->orderBy('id')->first();
    }
}
