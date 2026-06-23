<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Models\Broadcast;
use App\Models\BroadcastAudience;
use App\Services\BroadcastService;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastAudienceController extends Controller
{
    use ResolvesChurchData, LogsAuditEvents;

    public function __construct(private readonly BroadcastService $service) {}

    /** GET /dashboard/communication/audiences */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $audiences = BroadcastAudience::where('church_id', $this->resolvedChurchId())->orderByDesc('created_at')
            ->get(['id', 'name', 'description', 'audience_type', 'audience_config', 'member_count', 'created_at']);

        return Inertia::render('Dashboard/Communication/Audiences/Index', [
            'audiences'   => $audiences,
            'departments' => $this->activeDepartments(),
            'canManage'   => $request->user()->can('communication.manage'),
        ]);
    }

    /** GET /dashboard/communication/audiences/resolve-count */
    public function resolveCount(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $request->validate([
            'audience_type'   => 'required|in:all_members,role,department,event_attendees,volunteers',
            'audience_config' => 'nullable|array',
        ]);

        // Build a transient (never-persisted) Broadcast to reuse resolveAudience()
        $proxy               = new Broadcast();
        $proxy->church_id    = $this->resolvedChurchId();
        $proxy->audience_type   = $request->input('audience_type');
        $proxy->audience_config = $request->input('audience_config');

        $count = $this->service->resolveAudience($proxy)->count();

        return response()->json(['count' => $count]);
    }

    /** POST /dashboard/communication/audiences */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('communication.manage'), 403);

        $data = $request->validate([
            'name'            => 'required|string|max:120',
            'description'     => 'nullable|string|max:500',
            'audience_type'   => 'required|in:all_members,role,department,event_attendees,volunteers',
            'audience_config' => 'nullable|array',
        ]);

        // Snapshot current member count
        $proxy               = new Broadcast();
        $proxy->church_id    = $this->resolvedChurchId();
        $proxy->audience_type   = $data['audience_type'];
        $proxy->audience_config = $data['audience_config'] ?? null;
        $count = $this->service->resolveAudience($proxy)->count();

        $audience = BroadcastAudience::create([
            ...$data,
            'church_id'    => $this->resolvedChurchId(),
            'created_by'   => $request->user()->id,
            'member_count' => $count,
        ]);

        $this->auditLog('communication.audience_created', $audience);

        return back()->with('success', 'Audience saved.');
    }

    /** PUT /dashboard/communication/audiences/{audience} */
    public function update(Request $request, BroadcastAudience $audience): RedirectResponse
    {
        abort_unless($request->user()->can('communication.manage'), 403);

        $data = $request->validate([
            'name'            => 'required|string|max:120',
            'description'     => 'nullable|string|max:500',
            'audience_type'   => 'required|in:all_members,role,department,event_attendees,volunteers',
            'audience_config' => 'nullable|array',
        ]);

        $proxy               = new Broadcast();
        $proxy->church_id    = $this->resolvedChurchId();
        $proxy->audience_type   = $data['audience_type'];
        $proxy->audience_config = $data['audience_config'] ?? null;
        $count = $this->service->resolveAudience($proxy)->count();

        $audience->update([...$data, 'member_count' => $count]);

        $this->auditLog('communication.audience_updated', $audience);

        return back()->with('success', 'Audience updated.');
    }

    /** DELETE /dashboard/communication/audiences/{audience} */
    public function destroy(BroadcastAudience $audience): RedirectResponse
    {
        abort_unless(request()->user()->can('communication.manage'), 403);

        $this->auditLog('communication.audience_deleted', $audience);
        $audience->delete();

        return back()->with('success', 'Audience deleted.');
    }
}
