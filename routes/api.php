<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhook\StripeWebhookController;

// Stripe webhook — no authentication, no CSRF (called by Stripe's servers)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

// Future: API endpoints for mobile app / integrations
// All routes will be versioned and Sanctum-protected

Route::prefix('v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    // Auth
    Route::post('/auth/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/auth/me',      [\App\Http\Controllers\Api\AuthController::class, 'me']);
});
