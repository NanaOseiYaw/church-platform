<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function login(array $credentials, bool $remember = false): User
    {
        if (! Auth::attempt($credentials, $remember)) {
            // Log failed login attempt — look up user by email for context
            $failedUser = User::where('email', $credentials['email'])->first();
            if ($failedUser) {
                $this->audit->record(
                    action:   'auth.login.failed',
                    target:   $failedUser,
                    metadata: ['email' => $credentials['email']],
                    actor:    $failedUser,
                    churchId: $failedUser->church_id,
                );
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        // Block deactivated accounts — log them out immediately and surface a clear message
        if (! ($user->is_active ?? true)) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact your church administrator.',
            ]);
        }

        $this->audit->record(
            action:   'auth.login',
            actor:    $user,
            churchId: $user->church_id,
        );

        return $user;
    }

    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            $this->audit->record(
                action:   'auth.logout',
                actor:    $user,
                churchId: $user->church_id,
            );
        }

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    public function register(array $data): User
    {
        $user = User::create([
            'church_id'         => $data['church_id'] ?? null,
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('member');

        Auth::login($user);

        return $user;
    }
}
