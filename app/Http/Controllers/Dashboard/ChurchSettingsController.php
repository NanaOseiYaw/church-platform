<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Traits\LogsAuditEvents;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\File;
use App\Models\User;
use App\Support\PageHeroes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class ChurchSettingsController extends Controller
{
    use LogsAuditEvents;

    // ── Shared helpers ─────────────────────────────────────────────────────────

    private function resolvedChurch(): Church
    {
        return Church::findOrFail($this->resolvedChurchId());
    }

    /** Gate used by every settings action — church admin or super admin only. */
    private function authorizeSettings(): void
    {
        $this->authorize('update', Church::class);
    }

    /**
     * Read a namespaced subset from the church's settings JSON bag.
     * Returns an empty array when the namespace doesn't exist yet.
     */
    private function getSettings(string $namespace): array
    {
        return ($this->resolvedChurch()->settings ?? [])[$namespace] ?? [];
    }

    /**
     * Merge $data into the church's settings JSON under $namespace.
     * Keys in other namespaces are untouched.
     */
    private function saveSettings(string $namespace, array $data): void
    {
        $church               = $this->resolvedChurch();
        $settings             = $church->settings ?? [];
        $settings[$namespace] = array_merge($settings[$namespace] ?? [], $data);
        $church->update(['settings' => $settings]);
    }

    /**
     * Write a settings change to the audit log.
     * Silently no-ops if the audit_logs table hasn't been migrated yet.
     */
    private function auditSettings(Request $request, string $action, array $old, array $new): void
    {
        $church = $this->resolvedChurch();
        $this->auditLog($action, $church, $old, $new);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. HUB — redirect to Overview
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings */
    public function index(): RedirectResponse
    {
        $this->authorizeSettings();
        return redirect()->route('dashboard.settings.overview');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. OVERVIEW
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/overview */
    public function overview(): Response
    {
        $this->authorizeSettings();
        $churchId = $this->resolvedChurchId();
        $church   = $this->resolvedChurch();

        $stats = [
            'members_count'     => User::where('church_id', $churchId)->count(),
            'departments_count' => Department::where('church_id', $churchId)->count(),
            'upcoming_events'   => Event::forChurch($churchId)->where('start_at', '>=', now())->count(),
            // `status` is a computed accessor on the model, NOT a column — querying
            // it silently returns 0 on SQLite (which treats an unresolved quoted
            // identifier as a string literal) and throws "Unknown column 'status'"
            // on MySQL/PostgreSQL, 500-ing this page in production. Use the scope.
            'announcements'     => Announcement::where('church_id', $churchId)
                ->published()->count(),
            'last_updated_at'   => $church->updated_at?->toISOString(),
        ];

        $recentActivity = [];
        try {
            $recentActivity = AuditLog::where('church_id', $churchId)
                ->with('user:id,name')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($log) => [
                    'id'         => $log->id,
                    'action'     => $log->action,
                    'actor'      => $log->user?->name,
                    'created_at' => $log->created_at?->toISOString(),
                ]);
        } catch (\Throwable) {
            // audit_logs may not exist yet
        }

        return Inertia::render('Dashboard/Settings/Overview', [
            'church'         => $church->only(['id', 'name', 'tagline', 'logo', 'primary_color', 'subscription_plan']),
            'stats'          => $stats,
            'recentActivity' => $recentActivity,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. CHURCH PROFILE
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/profile */
    public function profile(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Profile', [
            'church' => $this->resolvedChurch()->only([
                'id', 'name', 'display_name', 'tagline',
                'description', 'mission', 'vision',
                'founded_year', 'registration_number',
                'address', 'phone', 'email',
                'timezone', 'language',
            ]),
        ]);
    }

    /** PUT /dashboard/settings/profile */
    public function updateProfile(Request $request): RedirectResponse
    {
        $this->authorizeSettings();
        $church = $this->resolvedChurch();
        $old    = $church->only(['name', 'display_name', 'tagline', 'address', 'phone', 'email', 'timezone', 'language']);

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:100'],
            'display_name'        => ['nullable', 'string', 'max:100'],
            'tagline'             => ['nullable', 'string', 'max:150'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'mission'             => ['nullable', 'string', 'max:1000'],
            'vision'              => ['nullable', 'string', 'max:1000'],
            'founded_year'        => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'address'             => ['nullable', 'string', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'email'               => ['nullable', 'email', 'max:100'],
            'timezone'            => ['nullable', 'string', 'max:60'],
            'language'            => ['nullable', 'string', 'max:10'],
        ]);

        $church->update($validated);
        $this->auditSettings($request, 'settings.profile.updated', $old, $validated);

        return back()->with('success', 'Church profile saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. BRANDING & THEME
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/branding */
    public function branding(): Response
    {
        $this->authorizeSettings();
        $brandingSettings = $this->getSettings('branding');

        return Inertia::render('Dashboard/Settings/Branding', [
            'church' => array_merge(
                $this->resolvedChurch()->only(['id', 'name', 'tagline', 'logo', 'primary_color']),
                [
                    'secondary_color' => $brandingSettings['secondary_color'] ?? null,
                    'favicon'         => $brandingSettings['favicon']         ?? null,
                ],
            ),
        ]);
    }

    /** POST /dashboard/settings/branding/logo */
    public function uploadLogo(Request $request): RedirectResponse
    {
        $this->authorizeSettings();
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        $church = $this->resolvedChurch();

        // Store and build a publicly-accessible URL.
        // Run `php artisan storage:link` once on the server if not already done.
        $path = $request->file('logo')->store('logos', 'public');
        $url  = Storage::disk('public')->url($path);

        $church->update(['logo' => $url]);
        $this->auditSettings($request, 'settings.branding.logo.uploaded', [], ['logo' => $url]);

        return back()->with('success', 'Logo uploaded.');
    }

    /** POST /dashboard/settings/homepage/hero-image */
    public function uploadHeroImage(Request $request): RedirectResponse
    {
        $this->authorizeSettings();
        $request->validate([
            'hero_image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        $path = $request->file('hero_image')->store('hero-images', 'public');
        $url  = Storage::disk('public')->url($path);

        $this->saveSettings('homepage', ['hero_image' => $url]);

        return back()->with('success', 'Hero image uploaded.');
    }

    /**
     * POST /dashboard/settings/website/page-hero-image
     *
     * Background image for the header of every inner page (About, Events,
     * Sermons …). The homepage has its own, above. When this is unset the
     * headers fall back to the brand gradient.
     */
    public function uploadPageHeroImage(Request $request): RedirectResponse
    {
        $this->authorizeSettings();
        $validated = $request->validate([
            'page_hero_image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            // Omitted means the site-wide default; otherwise it must be a page we know.
            'page'            => ['nullable', 'string', Rule::in(PageHeroes::keys())],
        ]);

        $path = $request->file('page_hero_image')->store('hero-images', 'public');
        $url  = Storage::disk('public')->url($path);
        $page = $validated['page'] ?? null;

        $this->writeHeroImage($page, $url);
        $this->auditSettings($request, 'settings.website.page_hero_image.uploaded', [], [
            'page'  => $page ?? 'default',
            'image' => $url,
        ]);

        return back()->with('success', 'Header image uploaded.');
    }

    /**
     * Persist a hero image for a page, or for the site-wide default when
     * $page is null. The homepage writes to its original settings location.
     */
    private function writeHeroImage(?string $page, ?string $url): void
    {
        if ($page === null) {
            $this->saveSettings('website', ['page_hero_image' => $url]);

            return;
        }

        [$namespace, $path] = PageHeroes::storageTarget($page);

        if (! str_contains($path, '.')) {
            $this->saveSettings($namespace, [$path => $url]);

            return;
        }

        // Nested map (website.page_hero_images.{key}) — merge rather than replace
        // so setting one page's image never clears the others.
        [$bag, $key] = explode('.', $path, 2);
        $existing    = $this->getSettings($namespace)[$bag] ?? [];

        if ($url === null) {
            unset($existing[$key]);
        } else {
            $existing[$key] = $url;
        }

        $this->saveSettings($namespace, [$bag => $existing]);
    }

    /**
     * DELETE /dashboard/settings/website/page-hero-image
     *
     * Clears the image so inner-page headers return to the brand gradient.
     */
    public function removePageHeroImage(Request $request): RedirectResponse
    {
        $this->authorizeSettings();
        $validated = $request->validate([
            'page' => ['nullable', 'string', Rule::in(PageHeroes::keys())],
        ]);

        $page = $validated['page'] ?? null;
        $this->writeHeroImage($page, null);
        $this->auditSettings($request, 'settings.website.page_hero_image.removed', [], ['page' => $page ?? 'default']);

        return back()->with('success', $page === null
            ? 'Default header image removed.'
            : 'Header image removed — this page now uses the default.');
    }

    /** POST /dashboard/settings/branding/favicon */
    public function uploadFavicon(Request $request): RedirectResponse
    {
        $this->authorizeSettings();
        $request->validate([
            'favicon' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,ico', 'max:512'],
        ]);

        $path = $request->file('favicon')->store('favicons', 'public');
        $url  = Storage::disk('public')->url($path);

        $this->saveSettings('branding', ['favicon' => $url]);
        $this->auditSettings($request, 'settings.branding.favicon.uploaded', [], ['favicon' => $url]);

        return back()->with('success', 'Favicon uploaded.');
    }

    /** PUT /dashboard/settings/branding */
    public function updateBranding(Request $request): RedirectResponse
    {
        $this->authorizeSettings();
        $church = $this->resolvedChurch();
        $old    = $church->only(['name', 'tagline', 'primary_color']);

        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'tagline'         => ['nullable', 'string', 'max:150'],
            'primary_color'   => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $church->update(array_filter($validated, fn ($k) => in_array($k, ['name', 'tagline', 'primary_color']), ARRAY_FILTER_USE_KEY));

        if (array_key_exists('secondary_color', $validated)) {
            $this->saveSettings('branding', ['secondary_color' => $validated['secondary_color']]);
        }

        $this->auditSettings($request, 'settings.branding.updated', $old, $validated);

        return back()->with('success', 'Branding saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. CONTACT (backward-compat — kept alongside Profile)
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/contact */
    public function contact(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Contact', [
            'contactInfo' => $this->resolvedChurch()->only(['address', 'phone', 'email']),
        ]);
    }

    /** PUT /dashboard/settings/contact */
    public function updateContact(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:100'],
        ]);

        $this->resolvedChurch()->update($validated);
        $this->auditSettings($request, 'settings.contact.updated', [], $validated);

        return back()->with('success', 'Contact details saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. SOCIAL MEDIA
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/social */
    public function social(): Response
    {
        $this->authorizeSettings();

        // NOTE: prop is named 'socials' (not 'church') to avoid overwriting the
        // globally-shared 'church' prop injected by HandleInertiaRequests, which
        // TenantStore reads for church name / brand colour on every page.
        return Inertia::render('Dashboard/Settings/SocialMedia', [
            'socials' => $this->resolvedChurch()->socials ?? [],
        ]);
    }

    /** PUT /dashboard/settings/social */
    public function updateSocial(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'socials'           => ['nullable', 'array'],
            'socials.facebook'  => ['nullable', 'url', 'max:255'],
            'socials.instagram' => ['nullable', 'url', 'max:255'],
            'socials.twitter'   => ['nullable', 'url', 'max:255'],
            'socials.youtube'   => ['nullable', 'url', 'max:255'],
            'socials.tiktok'    => ['nullable', 'url', 'max:255'],
            'socials.spotify'   => ['nullable', 'url', 'max:255'],
            'socials.linkedin'  => ['nullable', 'url', 'max:255'],
        ]);

        $this->resolvedChurch()->update($validated);
        $this->auditSettings($request, 'settings.social.updated', [], $validated);

        return back()->with('success', 'Social links saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. PUBLIC WEBSITE
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/website */
    public function website(): Response
    {
        $this->authorizeSettings();

        $heroImages = PageHeroes::map($this->resolvedChurch());

        return Inertia::render('Dashboard/Settings/Website', [
            'websiteConfig' => $this->resolvedChurch()->only(['domain', 'timezone', 'language']),

            // Every page that has a header, each with its own image if one is set.
            'heroPages' => collect(PageHeroes::PAGES)
                ->map(fn ($p) => $p + ['image' => $heroImages[$p['key']] ?? null])
                ->values()
                ->all(),
            'settings'      => array_merge(
                [
                    'privacy_mode'    => false,
                    'page_hero_image' => null,
                    'footer_nav'    => [
                        'explore_links' => [
                            ['label' => 'About Us',   'href' => '/about'],
                            ['label' => 'Ministries', 'href' => '/ministries'],
                            ['label' => 'Events',     'href' => '/events'],
                            ['label' => 'Sermons',    'href' => '/sermons'],
                        ],
                        'connect_links' => [
                            ['label' => 'Announcements', 'href' => '/announcements'],
                            ['label' => 'Contact Us',    'href' => '/contact'],
                            ['label' => 'Watch Live',    'href' => '/live'],
                            ['label' => 'Give Online',   'href' => '/give'],
                        ],
                    ],
                ],
                $this->getSettings('website')
            ),
        ]);
    }

    /** PUT /dashboard/settings/website */
    public function updateWebsite(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'domain'       => ['nullable', 'string', 'max:100'],
            'timezone'     => ['nullable', 'string', 'max:60'],
            'language'     => ['nullable', 'string', 'max:10'],
            'privacy_mode' => ['boolean'],
            'footer_nav'                         => ['nullable', 'array'],
            'footer_nav.explore_links'           => ['nullable', 'array', 'max:12'],
            'footer_nav.explore_links.*.label'   => ['required', 'string', 'max:60'],
            'footer_nav.explore_links.*.href'    => ['required', 'string', 'max:255', 'regex:/^(\/[^\s]*|https?:\/\/.+)$/'],
            'footer_nav.connect_links'           => ['nullable', 'array', 'max:12'],
            'footer_nav.connect_links.*.label'   => ['required', 'string', 'max:60'],
            'footer_nav.connect_links.*.href'    => ['required', 'string', 'max:255', 'regex:/^(\/[^\s]*|https?:\/\/.+)$/'],
        ]);

        $church = $this->resolvedChurch();
        $church->update(array_filter($validated, fn ($k) => in_array($k, ['domain', 'timezone', 'language']), ARRAY_FILTER_USE_KEY));
        $this->saveSettings('website', [
            'privacy_mode' => $validated['privacy_mode'] ?? false,
            'footer_nav'   => [
                'explore_links' => $validated['footer_nav']['explore_links'] ?? [],
                'connect_links' => $validated['footer_nav']['connect_links'] ?? [],
            ],
        ]);
        $this->auditSettings($request, 'settings.website.updated', [], $validated);

        return back()->with('success', 'Website settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. SERVICE TIMES
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/services */
    public function services(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Services', [
            'serviceTimes' => $this->resolvedChurch()->service_times ?? [],
        ]);
    }

    /** PUT /dashboard/settings/services */
    public function updateServices(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'service_times'               => ['nullable', 'array'],
            'service_times.*.name'        => ['required', 'string', 'max:100'],
            'service_times.*.day'         => ['required', 'string', 'max:20'],
            'service_times.*.time'        => ['required', 'string', 'max:10'],
            'service_times.*.type'        => ['nullable', 'string', 'max:30'],
            'service_times.*.location'    => ['nullable', 'string', 'max:100'],
            'service_times.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $this->resolvedChurch()->update($validated);
        $this->auditSettings($request, 'settings.services.updated', [], $validated);

        return back()->with('success', 'Service times saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. LIVESTREAM
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/livestream */
    public function livestream(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Livestream', [
            'settings' => array_merge([
                'provider'   => '',
                'stream_url' => '',
                'embed_url'  => '',
                'auto_live'  => false,
            ], $this->getSettings('livestream')),
        ]);
    }

    /** PUT /dashboard/settings/livestream */
    public function updateLivestream(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'provider'   => ['nullable', Rule::in(['youtube', 'vimeo', 'custom', ''])],
            'stream_url' => ['nullable', 'url', 'max:500'],
            'embed_url'  => ['nullable', 'max:500'],
            'auto_live'  => ['boolean'],
        ]);

        $this->saveSettings('livestream', $validated);
        $this->auditSettings($request, 'settings.livestream.updated', [], $validated);

        return back()->with('success', 'Livestream settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10. DONATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/donations */
    public function donations(): Response
    {
        $this->authorizeSettings();
        $settings = $this->getSettings('donations');

        return Inertia::render('Dashboard/Settings/Donations', [
            'settings' => [
                'funds'                     => $settings['funds'] ?? [],
                'stripe_publishable_key'    => $settings['stripe_publishable_key'] ?? null,
                'stripe_has_secret_key'     => ! empty($settings['stripe_secret_key']),
                'stripe_has_webhook_secret' => ! empty($settings['stripe_webhook_secret']),
                'suggested_amounts'         => $settings['suggested_amounts'] ?? [25, 50, 100, 250, 500],
            ],
        ]);
    }

    /** PUT /dashboard/settings/donations */
    public function updateDonations(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'funds'               => ['nullable', 'array', 'max:10'],
            'funds.*.id'          => ['required', 'string', 'max:50'],
            'funds.*.name'        => ['required', 'string', 'max:100'],
            'funds.*.description' => ['nullable', 'string', 'max:300'],
            'funds.*.icon'        => ['nullable', 'string', 'max:30'],
            'suggested_amounts'   => ['nullable', 'array', 'max:8'],
            'suggested_amounts.*' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $this->saveSettings('donations', [
            'funds'             => $validated['funds'] ?? [],
            'suggested_amounts' => $validated['suggested_amounts'] ?? [25, 50, 100, 250, 500],
        ]);
        $this->auditSettings($request, 'settings.donations.updated', [], $validated);

        return back()->with('success', 'Donation funds saved.');
    }

    /** POST /dashboard/settings/donations/stripe */
    public function updateStripeKeys(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'stripe_publishable_key' => ['nullable', 'string', 'max:200'],
            'stripe_secret_key'      => ['nullable', 'string', 'max:200'],
            'stripe_webhook_secret'  => ['nullable', 'string', 'max:200'],
        ]);

        $toSave = [];

        // Publishable key — only overwrite when a non-empty value is submitted
        if (! empty($validated['stripe_publishable_key'])) {
            $toSave['stripe_publishable_key'] = $validated['stripe_publishable_key'];
        }

        // Secret + webhook secret: only overwrite if a non-empty value was submitted
        if (! empty($validated['stripe_secret_key'])) {
            $toSave['stripe_secret_key'] = Crypt::encryptString($validated['stripe_secret_key']);
        }

        if (! empty($validated['stripe_webhook_secret'])) {
            $toSave['stripe_webhook_secret'] = Crypt::encryptString($validated['stripe_webhook_secret']);
        }

        if (! empty($toSave)) {
            $this->saveSettings('donations', $toSave);

            // Audit the change WITHOUT recording any secret material — log only
            // which keys were rotated, never the key values themselves.
            $this->auditSettings($request, 'settings.donations.stripe_keys.updated', [], [
                'publishable_key_updated' => ! empty($validated['stripe_publishable_key']),
                'secret_key_updated'      => ! empty($validated['stripe_secret_key']),
                'webhook_secret_updated'  => ! empty($validated['stripe_webhook_secret']),
            ]);
        }

        return back()->with('success', 'Stripe settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10b. HOMEPAGE CONTENT — stats + testimonials
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/homepage */
    public function homepage(): Response
    {
        $this->authorizeSettings();
        $settings = $this->getSettings('homepage');

        return Inertia::render('Dashboard/Settings/Homepage', [
            'settings' => [
                'hero_description'     => $settings['hero_description']     ?? null,
                'hero_image'           => $settings['hero_image']           ?? null,
                'stats'                => $settings['stats']                ?? [],
                'testimonials'         => $settings['testimonials']         ?? [],
                'events_subtitle'      => $settings['events_subtitle']      ?? null,
                'ministry_heading'     => $settings['ministry_heading']     ?? null,
                'ministry_body'        => $settings['ministry_body']        ?? null,
                'sermons_subtitle'     => $settings['sermons_subtitle']     ?? null,
                'testimonials_subtitle' => $settings['testimonials_subtitle'] ?? null,
                'livestream_cta'       => $settings['livestream_cta']       ?? null,
                'sermons_page_subtitle' => $settings['sermons_page_subtitle'] ?? null,
                'section_visibility'   => $settings['section_visibility'] ?? [
                    'events'        => true,
                    'ministry'      => true,
                    'sermons'       => true,
                    'testimonials'  => true,
                    'announcements' => true,
                    'livestream'    => true,
                ],
            ],
        ]);
    }

    /** PUT /dashboard/settings/homepage */
    public function updateHomepage(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'hero_description'      => ['nullable', 'string', 'max:300'],
            'stats'                 => ['nullable', 'array', 'max:6'],
            'stats.*.label'         => ['required', 'string', 'max:50'],
            'stats.*.value'         => ['required', 'string', 'max:30'],
            'testimonials'          => ['nullable', 'array', 'max:10'],
            'testimonials.*.name'   => ['required', 'string', 'max:100'],
            'testimonials.*.text'   => ['required', 'string', 'max:500'],
            'events_subtitle'       => ['nullable', 'string', 'max:200'],
            'ministry_heading'      => ['nullable', 'string', 'max:120'],
            'ministry_body'         => ['nullable', 'string', 'max:300'],
            'sermons_subtitle'      => ['nullable', 'string', 'max:200'],
            'testimonials_subtitle' => ['nullable', 'string', 'max:200'],
            'livestream_cta'        => ['nullable', 'string', 'max:200'],
            'sermons_page_subtitle' => ['nullable', 'string', 'max:200'],
            'section_visibility'               => ['nullable', 'array'],
            'section_visibility.events'        => ['boolean'],
            'section_visibility.ministry'      => ['boolean'],
            'section_visibility.sermons'       => ['boolean'],
            'section_visibility.testimonials'  => ['boolean'],
            'section_visibility.announcements' => ['boolean'],
            'section_visibility.livestream'    => ['boolean'],
        ]);

        $this->saveSettings('homepage', [
            'hero_description'      => $validated['hero_description']      ?? null,
            'stats'                 => $validated['stats']                 ?? [],
            'testimonials'          => $validated['testimonials']          ?? [],
            'events_subtitle'       => $validated['events_subtitle']       ?? null,
            'ministry_heading'      => $validated['ministry_heading']      ?? null,
            'ministry_body'         => $validated['ministry_body']         ?? null,
            'sermons_subtitle'      => $validated['sermons_subtitle']      ?? null,
            'testimonials_subtitle' => $validated['testimonials_subtitle'] ?? null,
            'livestream_cta'        => $validated['livestream_cta']        ?? null,
            'sermons_page_subtitle' => $validated['sermons_page_subtitle'] ?? null,
            'section_visibility'    => array_merge(
                ['events' => true, 'ministry' => true, 'sermons' => true,
                 'testimonials' => true, 'announcements' => true, 'livestream' => true],
                $validated['section_visibility'] ?? []
            ),
        ]);
        $this->auditSettings($request, 'settings.homepage.updated', [], $validated);

        return back()->with('success', 'Homepage content saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 11. COMMUNICATION
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/communication */
    public function communication(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Communication', [
            'settings' => array_merge([
                'sender_name'              => '',
                'sender_email'             => '',
                'announcements_moderation' => false,
                'approval_workflow'        => false,
            ], $this->getSettings('communication')),
        ]);
    }

    /** PUT /dashboard/settings/communication */
    public function updateCommunication(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'sender_name'              => ['nullable', 'string', 'max:100'],
            'sender_email'             => ['nullable', 'email', 'max:150'],
            'announcements_moderation' => ['boolean'],
            'approval_workflow'        => ['boolean'],
        ]);

        $this->saveSettings('communication', $validated);
        $this->auditSettings($request, 'settings.communication.updated', [], $validated);

        return back()->with('success', 'Communication settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 12. MEMBERSHIP
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/membership */
    public function membership(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Membership', [
            'settings' => array_merge([
                'registration_enabled' => true,
                'approval_required'    => false,
                'email_verification'   => true,
                'default_role'         => 'member',
            ], $this->getSettings('membership')),
        ]);
    }

    /** PUT /dashboard/settings/membership */
    public function updateMembership(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'registration_enabled' => ['boolean'],
            'approval_required'    => ['boolean'],
            'email_verification'   => ['boolean'],
            'default_role'         => ['required', 'string', Rule::in(['member', 'coordinator'])],
        ]);

        $this->saveSettings('membership', $validated);
        $this->auditSettings($request, 'settings.membership.updated', [], $validated);

        return back()->with('success', 'Membership settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 13. DEPARTMENTS (governance settings — not the members/CRUD page)
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/depts */
    public function departmentSettings(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/DepartmentSettings', [
            'settings' => array_merge([
                'members_can_create'      => false,
                'coordinators_can_create' => false,
                'default_visibility'      => 'public',
            ], $this->getSettings('departments')),
        ]);
    }

    /** PUT /dashboard/settings/depts */
    public function updateDepartmentSettings(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'members_can_create'      => ['boolean'],
            'coordinators_can_create' => ['boolean'],
            'default_visibility'      => ['required', Rule::in(['public', 'members_only'])],
        ]);

        $this->saveSettings('departments', $validated);
        $this->auditSettings($request, 'settings.departments.updated', [], $validated);

        return back()->with('success', 'Department settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 14. NOTIFICATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/notifications */
    public function notifications(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/NotificationSettings', [
            'settings' => array_merge([
                'email_enabled'        => true,
                'in_app_enabled'       => true,
                'task_reminders'       => true,
                'event_reminders'      => true,
                'attendance_reminders' => false,
                'welcome_email'        => true,
                'announcement_alerts'  => true,
            ], $this->getSettings('notifications')),
        ]);
    }

    /** PUT /dashboard/settings/notifications */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'email_enabled'        => ['boolean'],
            'in_app_enabled'       => ['boolean'],
            'task_reminders'       => ['boolean'],
            'event_reminders'      => ['boolean'],
            'attendance_reminders' => ['boolean'],
            'welcome_email'        => ['boolean'],
            'announcement_alerts'  => ['boolean'],
        ]);

        $this->saveSettings('notifications', $validated);
        $this->auditSettings($request, 'settings.notifications.updated', [], $validated);

        return back()->with('success', 'Notification preferences saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 15. ROLES & PERMISSIONS
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/roles */
    public function roles(): Response
    {
        $this->authorizeSettings();
        $churchId = $this->resolvedChurchId();

        $roles = Role::with('permissions')
            ->orderBy('id')
            ->get()
            ->map(function ($role) use ($churchId) {
                return [
                    'id'           => $role->id,
                    'name'         => $role->name,
                    'display_name' => ucwords(str_replace('_', ' ', $role->name)),
                    'permissions'  => $role->permissions->pluck('name')->sort()->values(),
                    'users_count'  => User::where('church_id', $churchId)->role($role->name)->count(),
                ];
            });

        return Inertia::render('Dashboard/Settings/RolesPermissions', [
            'roles' => $roles,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 16. MEDIA LIBRARY
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/media */
    public function media(): Response
    {
        $this->authorizeSettings();
        $churchId = $this->resolvedChurchId();

        $fileStats = [
            'total_files'      => File::where('church_id', $churchId)->count(),
            'total_size_bytes' => (int) (File::where('church_id', $churchId)->sum('size') ?? 0),
        ];

        return Inertia::render('Dashboard/Settings/MediaSettings', [
            'settings'   => array_merge([
                'max_upload_mb' => 10,
                'auto_compress' => true,
            ], $this->getSettings('media')),
            'file_stats' => $fileStats,
        ]);
    }

    /** PUT /dashboard/settings/media */
    public function updateMedia(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'max_upload_mb' => ['required', 'integer', 'min:1', 'max:500'],
            'auto_compress' => ['boolean'],
        ]);

        $this->saveSettings('media', $validated);
        $this->auditSettings($request, 'settings.media.updated', [], $validated);

        return back()->with('success', 'Media settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 17. SECURITY
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/security */
    public function security(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Security', [
            'settings' => array_merge([
                'session_timeout_minutes' => 120,
                'password_min_length'     => 8,
                'max_login_attempts'      => 5,
            ], $this->getSettings('security')),
        ]);
    }

    /** PUT /dashboard/settings/security */
    public function updateSecurity(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'session_timeout_minutes' => ['required', 'integer', 'min:15', 'max:10080'],
            'password_min_length'     => ['required', 'integer', 'min:6', 'max:32'],
            'max_login_attempts'      => ['required', 'integer', 'min:3', 'max:20'],
        ]);

        $this->saveSettings('security', $validated);
        $this->auditSettings($request, 'settings.security.updated', [], $validated);

        return back()->with('success', 'Security settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 18. SEO & ANALYTICS
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/seo */
    public function seo(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Seo', [
            'settings' => array_merge([
                'meta_title'          => '',
                'meta_description'    => '',
                'google_analytics_id' => '',
                'clarity_id'          => '',
                'robots'              => 'index,follow',
                'og_image'            => null,
            ], $this->getSettings('seo')),
        ]);
    }

    /** PUT /dashboard/settings/seo */
    public function updateSeo(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'meta_title'          => ['nullable', 'string', 'max:80'],
            'meta_description'    => ['nullable', 'string', 'max:200'],
            'google_analytics_id' => ['nullable', 'string', 'max:30'],
            'clarity_id'          => ['nullable', 'string', 'max:30'],
            'robots'              => ['nullable', Rule::in(['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'])],
        ]);

        $this->saveSettings('seo', $validated);
        $this->auditSettings($request, 'settings.seo.updated', [], $validated);

        return back()->with('success', 'SEO settings saved.');
    }

    /** POST /dashboard/settings/seo/og-image */
    public function uploadSeoOgImage(Request $request): RedirectResponse
    {
        $this->authorizeSettings();
        $request->validate([
            'og_image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        $path = $request->file('og_image')->store('og-images', 'public');
        $url  = Storage::disk('public')->url($path);

        $this->saveSettings('seo', ['og_image' => $url]);
        $this->auditSettings($request, 'settings.seo.og_image.uploaded', [], ['og_image' => $url]);

        return back()->with('success', 'Social share image uploaded.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 19. INTEGRATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/integrations */
    public function integrations(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Integrations');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 20. AUDIT LOGS
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/audit */
    /**
     * GET /dashboard/settings/audit
     *
     * Redirects to the canonical full-featured audit log page.
     * The standalone /dashboard/audit view is the single source of truth —
     * keeping two separate UIs was confusing (Settings hub vs sidebar nav).
     */
    public function audit(): RedirectResponse
    {
        $this->authorizeSettings();

        return redirect()->route('dashboard.audit.index');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 21. ADVANCED
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/advanced */
    public function advanced(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Dashboard/Settings/Advanced', [
            'config' => $this->resolvedChurch()->only(['id', 'slug', 'domain', 'subscription_plan', 'is_active']),
        ]);
    }

    /** PUT /dashboard/settings/advanced */
    public function updateAdvanced(Request $request): RedirectResponse
    {
        $this->authorizeSettings();
        $church = $this->resolvedChurch();

        $validated = $request->validate([
            'domain' => [
                'nullable', 'string', 'max:100',
                Rule::unique('churches', 'domain')->ignore($church->id),
            ],
        ]);

        $church->update($validated);
        $this->auditSettings($request, 'settings.advanced.updated', [], $validated);

        return back()->with('success', 'Advanced settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 22. ABOUT PAGE CONTENT — team members + church values
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /dashboard/settings/about-content */
    public function aboutContent(): Response
    {
        $this->authorizeSettings();
        $settings = $this->getSettings('about');

        return Inertia::render('Dashboard/Settings/AboutContent', [
            'settings' => [
                'team'                => $settings['team']                ?? [],
                'values'              => $settings['values']              ?? [],
                'hero_title'          => $settings['hero_title']          ?? null,
                'hero_eyebrow'        => $settings['hero_eyebrow']        ?? null,
                'hero_subtitle'       => $settings['hero_subtitle']       ?? null,
                'leadership_subtitle' => $settings['leadership_subtitle'] ?? null,
            ],
        ]);
    }

    /** PUT /dashboard/settings/about-content */
    public function updateAboutContent(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'team'                  => ['nullable', 'array', 'max:20'],
            'team.*.name'           => ['required', 'string', 'max:100'],
            'team.*.role'           => ['required', 'string', 'max:100'],
            'team.*.bio'            => ['nullable', 'string', 'max:500'],
            'team.*.image'          => ['nullable', 'url', 'max:500'],
            'values'                => ['nullable', 'array', 'max:12'],
            'values.*.title'        => ['required', 'string', 'max:60'],
            'values.*.description'  => ['nullable', 'string', 'max:300'],
            'hero_title'          => ['nullable', 'string', 'max:120'],
            'hero_eyebrow'        => ['nullable', 'string', 'max:60'],
            'hero_subtitle'       => ['nullable', 'string', 'max:300'],
            'leadership_subtitle' => ['nullable', 'string', 'max:200'],
        ]);

        $this->saveSettings('about', [
            'team'                => $validated['team']                ?? [],
            'values'              => $validated['values']              ?? [],
            'hero_title'          => $validated['hero_title']          ?? null,
            'hero_eyebrow'        => $validated['hero_eyebrow']        ?? null,
            'hero_subtitle'       => $validated['hero_subtitle']       ?? null,
            'leadership_subtitle' => $validated['leadership_subtitle'] ?? null,
        ]);
        $this->auditSettings($request, 'settings.about.updated', [], $validated);

        return back()->with('success', 'About page content saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BACKWARD COMPAT — original monolithic PUT /dashboard/settings
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * PUT /dashboard/settings
     *
     * @deprecated  Use the section-specific PUT routes instead.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'tagline'       => ['nullable', 'string', 'max:150'],
            'address'       => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:100'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'socials'       => ['nullable', 'array'],
            'socials.*'     => ['nullable', 'url', 'max:255'],
        ]);

        $this->resolvedChurch()->update($validated);

        return back()->with('success', 'Church settings saved.');
    }
}
