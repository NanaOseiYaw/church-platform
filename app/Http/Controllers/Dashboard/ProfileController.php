<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User profile settings — accessible by every authenticated user regardless
 * of role (unlike ChurchSettingsController which is admin-only).
 *
 * Covers: display name update + password change.
 */
class ProfileController extends Controller
{
    use LogsAuditEvents;
    /** GET /dashboard/profile */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard/Profile/Index', [
            'profile' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'avatar'=> $user->avatar,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    /** PATCH /dashboard/profile */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Profile updated.');
    }

    /** POST /dashboard/profile/avatar */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:2048'],
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');
        $url  = Storage::disk('public')->url($path);

        $request->user()->update(['avatar' => $url]);

        return back()->with('success', 'Avatar updated.');
    }

    /** PATCH /dashboard/profile/password */
    public function updatePassword(Request $request): RedirectResponse
    {
        // Use church's configured minimum password length (default: 8)
        $minLen   = 8;
        $churchId = app('church.id');
        if ($churchId) {
            $church = \App\Models\Church::find($churchId);
            $minLen = (int) ($church?->settings['security']['password_min_length'] ?? 8);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min($minLen)],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->string('password')),
        ]);

        $this->auditLog('auth.password.changed', $request->user());

        return back()->with('success', 'Password changed successfully.');
    }
}
