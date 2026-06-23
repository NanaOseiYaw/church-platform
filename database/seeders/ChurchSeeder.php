<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
        $church = Church::firstOrCreate(
            ['slug' => 'cop-grace-assembly'],
            [
                'name'          => 'The Church of Pentecost',
                'display_name'  => 'Grace Assembly',
                'tagline'       => 'Possessing the Nations',
                'description'   => 'A vibrant Pentecostal assembly committed to Spirit-filled worship, '
                                 . 'discipleship, and reaching our community with the love of Christ.',
                'mission'       => 'To raise disciples who possess every sphere of life for Christ through '
                                 . 'Spirit-filled worship, genuine fellowship, and selfless service.',
                'vision'        => 'A vibrant church possessing the nations and transforming communities '
                                 . 'by the power of the Holy Spirit.',
                'founded_year'  => 1998,
                // Design system brand-500 (Dark Blue family) — NOT the old indigo #6366f1.
                'primary_color' => '#3b8ac8',
                'timezone'      => 'Africa/Accra',
                'language'      => 'en',
                'address'       => 'Pentecost Street, Kaneshie, Accra, Ghana',
                'phone'         => '+233 30 222 0000',
                'email'         => 'info@cop-grace.org',
                'service_times' => [
                    ['day' => 'Sunday',    'times' => ['7:30 AM', '10:00 AM']],
                    ['day' => 'Wednesday', 'times' => ['6:00 PM']],
                    ['day' => 'Friday',    'times' => ['6:30 PM']],
                ],
                'socials' => [
                    'facebook'  => 'https://facebook.com/thecophq',
                    'youtube'   => 'https://youtube.com/@thecophq',
                    'instagram' => 'https://instagram.com/thecophq',
                ],
            ]
        );

        // Church Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@cop-grace.org'],
            [
                'church_id'         => $church->id,
                'name'              => 'Samuel Mensah',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('church_admin');

        // Sample member
        $member = User::firstOrCreate(
            ['email' => 'member@cop-grace.org'],
            [
                'church_id'         => $church->id,
                'name'              => 'Grace Asante',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $member->assignRole('member');
    }
}
