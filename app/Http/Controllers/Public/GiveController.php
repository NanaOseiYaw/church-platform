<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class GiveController extends Controller
{
    /**
     * POST /give/checkout
     *
     * Creates a Stripe Checkout Session for the selected fund + amount.
     * Returns JSON { url } which the frontend uses to redirect the browser.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fund_id'      => ['required', 'string', 'max:50'],
            'amount_cents' => ['required', 'integer', 'min:100', 'max:99999900'],
        ]);

        $church           = app('church');
        $donationSettings = $church?->settings['donations'] ?? [];

        $rawSecretKey = $donationSettings['stripe_secret_key'] ?? null;
        if (! $rawSecretKey) {
            return response()->json(['error' => 'Online giving is not configured for this church.'], 422);
        }

        try {
            $secretKey = Crypt::decryptString($rawSecretKey);
        } catch (\Throwable) {
            return response()->json(['error' => 'Online giving configuration error.'], 422);
        }

        Stripe::setApiKey($secretKey);

        // Resolve the fund name for the Stripe line item
        $funds    = $donationSettings['funds'] ?? [];
        $fund     = collect($funds)->firstWhere('id', $validated['fund_id']);
        $fundName = $fund['name'] ?? 'Donation';

        $session = Session::create([
            'mode'              => 'payment',
            'customer_creation' => 'if_required',
            'line_items'        => [[
                'price_data' => [
                    'currency'     => 'usd',
                    'unit_amount'  => $validated['amount_cents'],
                    'product_data' => [
                        'name' => $fundName . ' — ' . ($church?->name ?? 'Church'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'metadata'    => [
                'church_id' => $church?->id,
                'fund_id'   => $validated['fund_id'],
            ],
            'success_url' => url('/give/thank-you') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => url('/give'),
        ]);

        return response()->json(['url' => $session->url]);
    }

    /**
     * GET /give/thank-you
     *
     * Shown after Stripe redirects back on a successful payment.
     */
    public function success(): Response
    {
        return Inertia::render('Public/GiveSuccess');
    }
}
