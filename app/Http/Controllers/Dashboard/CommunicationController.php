<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Models\Broadcast;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController extends Controller
{
    use ResolvesChurchData;

    /** GET /dashboard/communication */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $churchId = $this->resolvedChurchId();
        $now      = now();

        $sentThisMonth = Broadcast::where('church_id', $churchId)
            ->where('status', 'sent')
            ->whereMonth('sent_at', $now->month)
            ->whereYear('sent_at', $now->year)
            ->count();

        // Delivery rate across all sent broadcasts this month
        $monthBroadcasts = Broadcast::where('church_id', $churchId)
            ->where('status', 'sent')
            ->whereMonth('sent_at', $now->month)
            ->whereYear('sent_at', $now->year)
            ->selectRaw('SUM(recipient_count) as total_r, SUM(delivered_count) as total_d')
            ->first();

        $deliveryRate = ($monthBroadcasts->total_r > 0)
            ? round(($monthBroadcasts->total_d / $monthBroadcasts->total_r) * 100)
            : 0;

        $scheduledCount = Broadcast::where('church_id', $churchId)
            ->where('status', 'scheduled')
            ->count();

        $upcoming = Broadcast::where('church_id', $churchId)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->limit(3)
            ->get(['id', 'title', 'audience_type', 'scheduled_at', 'recipient_count']);

        $recent = Broadcast::where('church_id', $churchId)
            ->whereIn('status', ['sent', 'failed'])
            ->with('creator:id,name,avatar')
            ->orderByDesc('sent_at')
            ->limit(10)
            ->get(['id', 'title', 'status', 'audience_type', 'recipient_count', 'delivered_count', 'failed_count', 'sent_at', 'created_by']);

        return Inertia::render('Dashboard/Communication/Dashboard', [
            'stats' => [
                'sent_this_month' => $sentThisMonth,
                'delivery_rate'   => $deliveryRate,
                'scheduled_count' => $scheduledCount,
            ],
            'upcoming' => $upcoming,
            'recent'   => $recent,
            'canSend'  => $request->user()->can('communication.send') || $request->user()->can('communication.send_dept'),
        ]);
    }
}
