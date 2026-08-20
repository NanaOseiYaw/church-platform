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

        // 1. Authenticated user — use their church.
        //
        // An authenticated user NEVER falls through to the single-tenant
        // fallback below. users.church_id is nullOnDelete, so removing a church
        // leaves its members authenticated with church_id = NULL; letting those
        // users drop through would hand them `Church::first()` — silently making
        // an orphaned church_admin an administrator of an unrelated tenant.
        // A user with no church simply has no tenant context (null is safe
        // everywhere downstream: handle() and HandleInertiaRequests both accept it).
        if ($user = $request->user()) {
            return $user->church_id
                ? Church::find($user->church_id)
                : null;
        }

        // 2. Subdomain — future: resolve church by domain
        // $host = $request->getHost();
        // $church = Church::where('domain', $host)->first();
        // if ($church) return $church;

        // 3. Single-tenant fallback — used by unauthenticated guests
        return Church::query()->orderBy('id')->first();
    }
}
