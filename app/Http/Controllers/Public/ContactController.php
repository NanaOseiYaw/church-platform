<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormMail;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(): Response
    {
        $church = app('church');

        return Inertia::render('Public/Contact', [
            'address'      => $church?->address       ?? config('church.address'),
            'phone'        => $church?->phone          ?? config('church.phone'),
            'email'        => $church?->email          ?? config('church.email'),
            'serviceTimes' => $church?->service_times  ?? config('church.service_times'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $church    = app('church');
        $recipient = $church?->email ?? config('church.email', env('MAIL_FROM_ADDRESS', 'hello@example.org'));

        try {
            Mail::to($recipient)->send(new ContactFormMail($validated));
        } catch (\Throwable) {
            // Log silently — visitors never see a 500.
            // Admin should verify MAIL_* env vars if messages stop arriving.
            logger()->error('ContactFormMail failed to send', [
                'to'   => $recipient,
                'from' => $validated['email'],
            ]);
        }

        return back()->with('success', 'Thank you for your message. We will be in touch soon.');
    }
}
