<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    // ── Full Inertia page ──────────────────────────────────────────────────────

    /**
     * GET /dashboard/notifications
     * Full paginated notifications page.
     */
    public function index(Request $request): Response
    {
        $user   = $request->user();
        $filter = $request->get('filter', 'all');   // 'all' | 'unread'

        return Inertia::render('Dashboard/Notifications/Index', [
            'notifications' => $this->notifications->paginate($user, $filter),
            'unreadCount'   => $this->notifications->unreadCount($user),
            'filter'        => $filter,
        ]);
    }

    // ── JSON endpoints (used by the navbar dropdown via Axios) ─────────────────

    /**
     * GET /dashboard/notifications/recent
     * Returns the last 15 notifications + unread count.
     * Called via Axios when the notification dropdown is opened.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $this->notifications->recent($user),
            'unread_count'  => $this->notifications->unreadCount($user),
        ]);
    }

    // ── Mutation endpoints ─────────────────────────────────────────────────────

    /**
     * PATCH /dashboard/notifications/{id}/read
     * Mark a single notification as read.
     * Accepts both Axios (JSON) and Inertia (redirect) callers.
     */
    public function markRead(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $this->notifications->markRead($request->user(), $id);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    /**
     * POST /dashboard/notifications/read-all
     * Mark every unread notification as read.
     */
    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }
}
