<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DonationController extends Controller
{
    /** Default funds shown when none have been configured in Settings → Donations. */
    private const DEFAULT_FUNDS = [
        ['id' => 'general',     'name' => 'General Fund',  'description' => 'Support our day-to-day ministry and operations.',            'icon' => 'church'],
        ['id' => 'building',    'name' => 'Building Fund', 'description' => 'Help us build a permanent home for our congregation.',        'icon' => 'building'],
        ['id' => 'missions',    'name' => 'Missions Fund', 'description' => 'Support our local and global outreach efforts.',               'icon' => 'globe'],
        ['id' => 'benevolence', 'name' => 'Benevolence',   'description' => 'Help families in need within our community.',                 'icon' => 'heart'],
    ];

    public function __invoke(): Response
    {
        $church           = app('church');
        $donationSettings = $church?->settings['donations'] ?? [];
        $funds            = $donationSettings['funds'] ?? self::DEFAULT_FUNDS;

        return Inertia::render('Public/Donate', [
            'funds'            => $funds,
            'suggestedAmounts' => $donationSettings['suggested_amounts'] ?? [25, 50, 100, 250, 500],
            'stripeEnabled'    => ! empty($donationSettings['stripe_secret_key']),
        ]);
    }
}
