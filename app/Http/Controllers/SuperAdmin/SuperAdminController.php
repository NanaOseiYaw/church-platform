<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\OnboardChurch;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminStoreRequest;
use App\Models\Church;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    public function __construct(private OnboardChurch $onboardChurch) {}

    // ── GET /super-admin ─────────────────────────────────────────────────────────
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Index', [
            'churches' => Church::withCount('users')
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    // ── GET /super-admin/churches/create ─────────────────────────────────────────
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Create', [
            'timezones'    => $this->timezoneOptions(),
            'denominations' => $this->denominationOptions(),
        ]);
    }

    // ── POST /super-admin/churches ───────────────────────────────────────────────
    public function store(SuperAdminStoreRequest $request): RedirectResponse
    {
        // shouldLogin: false — super admin must NOT be logged out after church creation
        $this->onboardChurch->execute($request->validated(), shouldLogin: false);

        return redirect()->route('super-admin.index')
            ->with('success', 'Church created successfully.');
    }

    // ── PATCH /super-admin/{church}/toggle-active ────────────────────────────────
    public function toggleActive(Church $church): RedirectResponse
    {
        $church->update(['is_active' => ! $church->is_active]);

        // After update(), $church->is_active reflects the NEW value.
        $label = $church->is_active ? 'reactivated' : 'suspended';

        return back()->with('success', "Church {$label} successfully.");
    }

    // ── POST /super-admin/{church}/impersonate ───────────────────────────────────
    public function impersonate(Church $church): RedirectResponse
    {
        session()->put('super_admin_impersonating', $church->id);

        return redirect()->route('dashboard');
    }

    // ── DELETE /super-admin/impersonate ──────────────────────────────────────────
    public function stopImpersonating(): RedirectResponse
    {
        session()->forget('super_admin_impersonating');

        return redirect()->route('super-admin.index');
    }

    // ── Option lists (intentionally duplicated from OnboardingController) ─────────
    // These are copied rather than shared to avoid coupling two unrelated controllers.

    private function timezoneOptions(): array
    {
        $zones = [
            'UTC'                 => 'UTC — Coordinated Universal Time',
            'America/New_York'    => 'Eastern Time (ET)',
            'America/Chicago'     => 'Central Time (CT)',
            'America/Denver'      => 'Mountain Time (MT)',
            'America/Los_Angeles' => 'Pacific Time (PT)',
            'America/Anchorage'   => 'Alaska Time (AKT)',
            'Pacific/Honolulu'    => 'Hawaii Time (HT)',
            'Europe/London'       => 'London (GMT / BST)',
            'Europe/Paris'        => 'Central European Time (CET)',
            'Europe/Berlin'       => 'Berlin (CET)',
            'Africa/Lagos'        => 'West Africa Time (WAT)',
            'Africa/Nairobi'      => 'East Africa Time (EAT)',
            'Africa/Johannesburg' => 'South Africa Time (SAST)',
            'Africa/Accra'        => 'Ghana Standard Time (GST)',
            'Africa/Cairo'        => 'Egypt Standard Time (EET)',
            'Asia/Dubai'          => 'Gulf Standard Time (GST)',
            'Asia/Kolkata'        => 'India Standard Time (IST)',
            'Asia/Singapore'      => 'Singapore Time (SGT)',
            'Asia/Tokyo'          => 'Japan Standard Time (JST)',
            'Australia/Sydney'    => 'Australian Eastern Time (AEST)',
        ];

        return array_map(
            fn ($label, $value) => ['value' => $value, 'label' => $label],
            $zones,
            array_keys($zones),
        );
    }

    private function denominationOptions(): array
    {
        return [
            'Non-denominational',
            'Baptist',
            'Pentecostal / Charismatic',
            'Methodist',
            'Presbyterian',
            'Anglican / Episcopal',
            'Lutheran',
            'Catholic',
            'Seventh-day Adventist',
            'Reformed / Calvinist',
            'Evangelical',
            'Assembly of God',
            'Church of Christ',
            'Orthodox',
            'Other',
        ];
    }
}
