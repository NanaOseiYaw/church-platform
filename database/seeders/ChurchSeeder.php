<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Baseline, production-safe tenant: one Church of Pentecost assembly plus an
 * admin and a sample member. Deliberately contains NO demo content — run
 * DemoSeeder separately (php artisan db:seed --class=DemoSeeder) to populate
 * events, sermons, members, scheduling, etc.
 */
class ChurchSeeder extends Seeder
{
    public function run(): void
    {
        $church = $this->resolveTenant([
                'name'          => 'The Church of Pentecost',
                'display_name'  => 'Amsterdam Assembly', // TODO: replace with the real local assembly name before going live
                'tagline'       => 'Possessing the Nations',
                'description'   => 'A vibrant Pentecostal assembly committed to Spirit-filled worship, '
                                 . 'discipleship, and reaching our community with the love of Christ.',
                'mission'       => 'To raise disciples who possess every sphere of life for Christ through '
                                 . 'Spirit-filled worship, genuine fellowship, and selfless service.',
                'vision'        => 'A vibrant church possessing the nations and transforming communities '
                                 . 'by the power of the Holy Spirit.',
                'founded_year'  => 1998,
                // Church of Pentecost brand palette — Dark Blue (primary).
                'primary_color' => '#1E5AA8',
                'timezone'      => 'Europe/Amsterdam',
                'language'      => 'en',
                // TODO: replace with the real Amsterdam assembly's address/phone/email before seeding production.
                'address'       => 'TODO: Street address, Amsterdam, Netherlands',
                'phone'         => 'TODO: +31 6 00 000 000',
                'email'         => 'info@copamsterdam.nl',
                'service_times' => [
                    ['day' => 'Sunday',    'times' => ['10:00 AM']],
                    ['day' => 'Wednesday', 'times' => ['7:00 PM']],
                    ['day' => 'Friday',    'times' => ['7:00 PM']],
                ],
                'socials' => [
                    'facebook'  => 'https://facebook.com/thecophq',
                    'youtube'   => 'https://youtube.com/@thecophq',
                    'instagram' => 'https://instagram.com/thecophq',
                ],
        ]);

        $this->seedAdmin($church);
        $this->seedSampleMember($church);
    }

    /**
     * Resolve the single tenant this seeder maintains.
     *
     * Keying firstOrCreate() on the slug alone is wrong when the slug changes:
     * re-seeding creates a *second* church rather than updating the existing
     * one, and because ResolveTenant falls back to `orderBy('id')->first()` for
     * guests, the public website keeps serving the old tenant while the new one
     * sits orphaned. That is exactly what happened when this seeder was
     * rebranded from the Ghana demo assembly.
     *
     * So: match on slug first; otherwise, if this install has exactly one church,
     * adopt and update it. A DB with several churches is a genuine multi-tenant
     * install and is never silently rewritten.
     */
    private function resolveTenant(array $attributes): Church
    {
        $slug = 'cop-amsterdam';

        if ($church = Church::where('slug', $slug)->first()) {
            $church->update($attributes);

            return $church;
        }

        if (Church::count() === 1) {
            $church = Church::first();
            $this->command?->info("Adopting existing church [{$church->slug}] and rebranding it to [{$slug}].");
            $church->update($attributes + ['slug' => $slug]);

            return $church;
        }

        return Church::create($attributes + ['slug' => $slug]);
    }

    /**
     * Create the initial church_admin.
     *
     * The password is NEVER hardcoded. In production it must be supplied via
     * SEED_ADMIN_PASSWORD; otherwise a cryptographically random one is generated
     * and printed once. A seeder that ships a known password like "password" is
     * a live admin backdoor from the moment the site is reachable, and relying
     * on the operator to remember to change it afterwards is not a control.
     */
    private function seedAdmin(Church $church): void
    {
        $email = env('SEED_ADMIN_EMAIL', 'admin@copamsterdam.nl');
        $name  = env('SEED_ADMIN_NAME', 'Church Admin');

        $existing = User::where('email', $email)->first();

        if ($existing) {
            $existing->assignRole('church_admin');
            $this->command?->info("Admin {$email} already exists — password left unchanged.");

            return;
        }

        $password  = env('SEED_ADMIN_PASSWORD');
        $generated = false;

        if (blank($password)) {
            $password  = Str::password(20);
            $generated = true;
        }

        $admin = User::create([
            'church_id'         => $church->id,
            'email'             => $email,
            'name'              => $name,
            'password'          => Hash::make($password),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('church_admin');

        if ($generated) {
            $this->command?->warn('──────────────────────────────────────────────────────────');
            $this->command?->warn(' Generated admin password (shown once — store it now):');
            $this->command?->warn("   {$email}");
            $this->command?->warn("   {$password}");
            $this->command?->warn('──────────────────────────────────────────────────────────');
        }
    }

    /**
     * A convenience login for local development only. Never created in
     * production, where it would be a second known-credential account.
     */
    private function seedSampleMember(Church $church): void
    {
        if (app()->environment('production')) {
            return;
        }

        $member = User::firstOrCreate(
            ['email' => 'member@copamsterdam.nl'],
            [
                'church_id'         => $church->id,
                'name'              => 'Sample Member',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $member->assignRole('member');
    }
}
