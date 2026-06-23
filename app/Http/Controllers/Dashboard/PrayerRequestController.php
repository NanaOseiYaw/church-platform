<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PrayerRequest;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrayerRequestController extends Controller
{
    use LogsAuditEvents;

    /** GET /dashboard/prayer-requests */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PrayerRequest::class);

        $churchId = $this->resolvedChurchId();

        $query = PrayerRequest::where('church_id', $churchId)
            ->when($request->filter === 'unanswered', fn ($q) => $q->where('is_answered', false))
            ->when($request->filter === 'answered',   fn ($q) => $q->where('is_answered', true))
            ->when($request->filter === 'private',    fn ($q) => $q->where('is_private',  true))
            ->latest();

        $requests = $query->paginate(20)->withQueryString();

        $stats = [
            'total'      => PrayerRequest::where('church_id', $churchId)->count(),
            'unanswered' => PrayerRequest::where('church_id', $churchId)->where('is_answered', false)->count(),
            'answered'   => PrayerRequest::where('church_id', $churchId)->where('is_answered', true)->count(),
        ];

        return Inertia::render('Dashboard/PrayerRequests/Index', [
            'requests' => $requests->through(fn ($r) => [
                'id'           => $r->id,
                'display_name' => $r->display_name,
                'email'        => $r->email,
                'request'      => $r->request,
                'is_anonymous' => $r->is_anonymous,
                'is_private'   => $r->is_private,
                'is_answered'  => $r->is_answered,
                'answered_at'  => $r->answered_at?->format('M j, Y'),
                'admin_notes'  => $r->admin_notes,
                'created_at'   => $r->created_at?->format('M j, Y g:i A'),
            ]),
            'stats'   => $stats,
            'filters' => $request->only('filter'),
        ]);
    }

    /** PATCH /dashboard/prayer-requests/{prayerRequest}/answer */
    public function markAnswered(Request $request, PrayerRequest $prayerRequest): RedirectResponse
    {
        $this->authorize('update', $prayerRequest);
        abort_unless($prayerRequest->church_id === $this->resolvedChurchId(), 403);

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $prayerRequest->update([
            'is_answered' => true,
            'answered_at' => now(),
            'admin_notes' => $validated['admin_notes'] ?? $prayerRequest->admin_notes,
        ]);
        $this->auditLog('prayer_request.answered', $prayerRequest);

        return back()->with('success', 'Marked as answered.');
    }

    /** DELETE /dashboard/prayer-requests/{prayerRequest} */
    public function destroy(PrayerRequest $prayerRequest): RedirectResponse
    {
        $this->authorize('delete', $prayerRequest);
        abort_unless($prayerRequest->church_id === $this->resolvedChurchId(), 403);

        $this->auditLog('prayer_request.deleted', $prayerRequest);

        $prayerRequest->delete();

        return back()->with('success', 'Prayer request deleted.');
    }
}
