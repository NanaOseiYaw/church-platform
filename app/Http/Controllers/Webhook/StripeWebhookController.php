<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Stripe\Event;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        // Parse the event payload WITHOUT signature verification first.
        // We need church_id from metadata to look up the right webhook secret.
        $data = json_decode($payload, true);
        if (! $data) {
            return response('Invalid payload', 400);
        }

        $event = Event::constructFrom($data);

        // Only handle completed checkout sessions
        if ($event->type !== 'checkout.session.completed') {
            return response('Ignored', 200);
        }

        $session  = $event->data->object;
        $churchId = $session->metadata->church_id ?? null;

        if (! $churchId) {
            return response('No church context', 200);
        }

        $church = Church::find((int) $churchId);
        if (! $church) {
            return response('Church not found', 200);
        }

        // Retrieve + decrypt the church's webhook signing secret
        $rawSecret = $church->settings['donations']['stripe_webhook_secret'] ?? null;
        if (! $rawSecret) {
            logger()->warning('Stripe webhook received but no webhook secret configured', [
                'church_id' => $church->id,
            ]);
            return response('Webhook secret not configured', 200);
        }

        try {
            $webhookSecret = Crypt::decryptString($rawSecret);
            // This throws SignatureVerificationException if invalid
            Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Stripe\Exception\SignatureVerificationException) {
            return response('Invalid signature', 400);
        } catch (\Throwable $e) {
            logger()->error('Stripe webhook verification failed', ['error' => $e->getMessage()]);
            return response('Configuration error', 400);
        }

        // Idempotent: skip if already recorded (duplicate Stripe delivery)
        Donation::firstOrCreate(
            ['stripe_session_id' => $session->id],
            [
                'church_id'    => $church->id,
                'fund_id'      => $session->metadata->fund_id ?? 'general',
                'amount_cents' => $session->amount_total   ?? 0,
                'currency'     => $session->currency       ?? 'usd',
                'donor_email'  => $session->customer_details?->email ?? null,
                'donor_name'   => $session->customer_details?->name  ?? null,
                'status'       => 'completed',
            ]
        );

        return response('OK', 200);
    }
}
