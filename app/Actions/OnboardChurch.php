<?php

namespace App\Actions;

use App\Models\Church;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Atomically create a new Church workspace and its founding church_admin user.
 *
 * Everything runs inside a single database transaction so a failure at any
 * stage (logo storage, role assignment, …) rolls back the entire record set
 * — no orphaned Church rows, no users without roles.
 */
class OnboardChurch
{
    /**
     * @param  array{
     *     church_name:    string,
     *     church_tagline: string|null,
     *     timezone:       string|null,
     *     denomination:   string|null,
     *     country:        string|null,
     *     primary_color:  string|null,
     *     logo:           UploadedFile|null,
     *     admin_name:     string,
     *     admin_email:    string,
     *     admin_password: string,
     * } $data
     */
    public function execute(array $data, bool $shouldLogin = true): User
    {
        return DB::transaction(function () use ($data, $shouldLogin) {

            // ── 1. Create the Church record ─────────────────────────────────────
            $church = Church::create([
                'name'              => $data['church_name'],
                'tagline'           => $data['church_tagline'] ?? null,
                'timezone'          => $data['timezone'] ?? 'UTC',
                'primary_color'     => $data['primary_color'] ?? '#6366f1',
                'subscription_plan' => 'free',
                'is_active'         => true,
                'settings'          => array_filter([
                    'denomination' => $data['denomination'] ?? null,
                    'country'      => $data['country'] ?? null,
                    'onboarded_at' => now()->toISOString(),
                ]),
            ]);

            // ── 2. Store the logo (non-fatal — don't block workspace creation) ──
            if (! empty($data['logo']) && $data['logo'] instanceof UploadedFile) {
                try {
                    $path = $data['logo']->store("churches/{$church->id}/logo", 'public');
                    $church->update(['logo' => $path]);
                } catch (\Throwable) {
                    // Logo upload failure should not prevent onboarding
                }
            }

            // ── 3. Create the founding admin user ───────────────────────────────
            $user = User::create([
                'church_id'         => $church->id,
                'name'              => $data['admin_name'],
                'email'             => $data['admin_email'],
                'password'          => Hash::make($data['admin_password']),
                'email_verified_at' => now(),
            ]);

            // ── 4. Grant church_admin role (Spatie — pulls from seeded roles) ───
            $user->assignRole('church_admin');

            // ── 5. Seed starter departments ─────────────────────────────────────
            $this->seedStarterDepartments($church);

            // ── 6. Authenticate the new admin immediately (skip when called by super admin) ──
            if ($shouldLogin) {
                Auth::login($user);
            }

            return $user;
        });
    }

    // ── Starter departments ──────────────────────────────────────────────────────
    // Four sensible defaults to populate the platform; the admin can rename or
    // delete them later from Settings.

    private function seedStarterDepartments(Church $church): void
    {
        $starters = [
            ['name' => 'Worship Team',   'description' => 'Music and worship ministry',    'color' => '#8b5cf6'],
            ['name' => 'Youth Ministry', 'description' => 'Youth and young adults program', 'color' => '#f59e0b'],
            ['name' => 'Media & Tech',   'description' => 'Audio, video and technology',    'color' => '#3b82f6'],
            ['name' => 'Ushering',       'description' => 'Welcoming and seating team',     'color' => '#10b981'],
        ];

        foreach ($starters as $dept) {
            Department::create([
                'church_id'   => $church->id,
                'name'        => $dept['name'],
                'description' => $dept['description'],
                'color'       => $dept['color'],
                'is_active'   => true,
            ]);
        }
    }
}
