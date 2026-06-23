<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honour the church's password_min_length security setting (default: 8)
        $minLen   = 8;
        $churchId = app('church.id');
        if ($churchId) {
            $church = Church::find($churchId);
            $minLen = (int) ($church?->settings['security']['password_min_length'] ?? 8);
        }

        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:100'],
            'email'                 => ['required', 'email', 'max:150', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:' . $minLen, 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        // Attach to the first / only church for single-tenant mode
        $data['church_id'] = Church::query()->value('id');

        $this->authService->register($data);

        // Regenerate the session ID after login to prevent session fixation.
        // (AuthService::register calls Auth::login() which writes into the
        // pre-existing session; regenerate() issues a new ID while keeping data.)
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
