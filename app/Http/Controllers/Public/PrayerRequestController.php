<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class PrayerRequestController extends Controller
{
    /** GET /prayer — public submission form */
    public function index(): Response
    {
        return Inertia::render('Public/PrayerRequests');
    }

    /** POST /prayer — submit a prayer request */
    public function store(Request $request): RedirectResponse
    {
        $churchId = app('church.id');
        abort_unless($churchId !== null, 404);

        $validated = $request->validate([
            'name'         => ['nullable', 'string', 'max:150'],
            'email'        => ['nullable', 'email', 'max:200'],
            'request'      => ['required', 'string', 'max:2000'],
            'is_anonymous' => ['boolean'],
            'is_private'   => ['boolean'],
        ]);

        PrayerRequest::create([
            'church_id'    => $churchId,
            'name'         => $validated['is_anonymous'] ? null : ($validated['name'] ?? null),
            'email'        => $validated['is_anonymous'] ? null : ($validated['email'] ?? null),
            'request'      => $validated['request'],
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
            'is_private'   => (bool) ($validated['is_private']   ?? false),
        ]);

        // Notify church admins so prayer requests aren't missed (in-app notification).
        // Non-fatal: a notification failure must never block the public submission.
        try {
            $admins = User::where('church_id', $churchId)->role('church_admin')->get();
            Notification::send($admins, new AppNotification(
                notifType: AppNotification::TYPE_PRAYER_REQUEST,
                title:     'New prayer request',
                body:      'A new prayer request has been submitted.',
                actionUrl: '/dashboard/prayer-requests',
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Your prayer request has been submitted. We are praying with you.');
    }
}
