<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\OnboardChurch;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    // ── Show the multi-step wizard ────────────────────────────────────────────────

    public function show(): Response
    {
        return Inertia::render('Onboarding/Index', [
            'timezones'    => $this->timezoneOptions(),
            'colorPresets' => $this->colorPresets(),
            'denominations' => $this->denominationOptions(),
            'countries'    => $this->countryOptions(),
        ]);
    }

    // ── Handle final submission (all steps merged into one POST) ──────────────────

    public function store(OnboardingRequest $request, OnboardChurch $action): RedirectResponse
    {
        $action->execute($request->validated());

        return redirect()->route('onboarding.welcome');
    }

    // ── Welcome / success screen ──────────────────────────────────────────────────

    public function welcome(): Response
    {
        // Inertia shared props provide the authed user's church — no extra query.
        return Inertia::render('Onboarding/Welcome');
    }

    // ── Static option lists ───────────────────────────────────────────────────────

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

    private function colorPresets(): array
    {
        return [
            ['color' => '#6366f1', 'name' => 'Indigo'],
            ['color' => '#8b5cf6', 'name' => 'Violet'],
            ['color' => '#ec4899', 'name' => 'Pink'],
            ['color' => '#ef4444', 'name' => 'Red'],
            ['color' => '#f97316', 'name' => 'Orange'],
            ['color' => '#f59e0b', 'name' => 'Amber'],
            ['color' => '#10b981', 'name' => 'Emerald'],
            ['color' => '#06b6d4', 'name' => 'Cyan'],
            ['color' => '#3b82f6', 'name' => 'Blue'],
            ['color' => '#0f172a', 'name' => 'Navy'],
        ];
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

    private function countryOptions(): array
    {
        $countries = [
            'Australia', 'Austria', 'Bahamas', 'Barbados', 'Belgium',
            'Brazil', 'Cameroon', 'Canada', 'Chile', 'Colombia',
            'Denmark', 'Dominican Republic', 'Ethiopia', 'Fiji', 'France',
            'Germany', 'Ghana', 'Guyana', 'Haiti', 'India',
            'Ireland', 'Israel', 'Italy', 'Jamaica', 'Kenya',
            'Liberia', 'Mexico', 'Netherlands', 'New Zealand', 'Nigeria',
            'Norway', 'Papua New Guinea', 'Peru', 'Philippines', 'Portugal',
            'Rwanda', 'Sierra Leone', 'Singapore', 'South Africa', 'South Korea',
            'Spain', 'Sweden', 'Switzerland', 'Tanzania', 'Trinidad and Tobago',
            'Uganda', 'United Kingdom', 'United States', 'Zambia', 'Zimbabwe',
            'Other',
        ];

        return array_map(
            fn ($name) => ['value' => $name, 'label' => $name],
            $countries,
        );
    }
}
