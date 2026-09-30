<?php

namespace App\Http\Middleware;

use App\Models\Church;
use App\Services\AnnouncementService;
use App\Services\NotificationService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        // The Church model was resolved and cached by ResolveTenant — no extra query.
        $church = app('church');

        return [
            ...parent::share($request),

            // Which About sub-pages may be linked. Leadership and History stay out
            // of this list until an admin fills in the local content, so neither the
            // navbar dropdown nor the About sub-nav ever links to a placeholder page.
            'aboutPages' => \App\Support\AboutPages::ready($church),

            // Header background images, resolved per-page → site-wide → gradient.
            // Shared here so every page using PageHero picks its image up without
            // each controller having to pass anything.
            'pageHeroImage'  => \App\Support\PageHeroes::default($church),
            'pageHeroImages' => \App\Support\PageHeroes::map($church),
            'pageHeroPaths'  => collect(\App\Support\PageHeroes::PAGES)
                ->mapWithKeys(fn ($p) => [$p['path'] => $p['key']])
                ->all(),

            'church' => $church ? [
                'name'           => $church->name,
                'tagline'        => $church->tagline ?? config('church.tagline', 'A Place to Belong'),
                'description'    => $church->description,
                'logo'           => $church->logo,
                'favicon'        => $church->settings['branding']['favicon'] ?? null,
                'primaryColor'   => $church->primary_color ?? config('church.primary_color', '#6366f1'),
                'secondaryColor' => $church->settings['branding']['secondary_color'] ?? null,
                'address'        => $church->address,
                'phone'          => $church->phone,
                'email'          => $church->email,
                'socials'        => $church->socials ?? [],
                'seo'            => $church->settings['seo'] ?? [],
                'footerNav'      => array_merge(
                    $this->defaultFooterNav(),
                    $church->settings['website']['footer_nav'] ?? []
                ),
            ] : [
                'name'           => config('church.name'),
                'tagline'        => config('church.tagline', 'A Place to Belong'),
                'description'    => null,
                'logo'           => config('church.logo'),
                'favicon'        => null,
                'primaryColor'   => config('church.primary_color', '#6366f1'),
                'secondaryColor' => null,
                'address'        => config('church.address'),
                'phone'          => config('church.phone'),
                'email'          => config('church.email'),
                'socials'        => config('church.socials', []),
                'seo'            => [],
                'footerNav'      => $this->defaultFooterNav(),
            ],

            'auth' => [
                'user' => fn () => $request->user() ? [
                    'id'          => $request->user()->id,
                    'name'        => $request->user()->name,
                    'email'       => $request->user()->email,
                    'avatar'      => $request->user()->avatar,
                    'church_id'   => $request->user()->church_id,
                    'roles'       => $request->user()->getRoleNames(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                ] : null,
                'notifications_count'        => fn () => $this->safeNotificationCount($request),
                'unread_announcements_count' => fn () => $this->safeUnreadAnnouncementsCount($request),
                'overdue_tasks_count'        => fn () => $this->safeOverdueTasksCount($request),
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],

            // Set when a super admin is impersonating a church. Contains the
            // church's display name so DashboardLayout can render the banner.
            // Null for all non-super-admin sessions and unauthenticated requests.
            'impersonating' => $request->user()?->hasRole('super_admin')
                && $request->session()->get('super_admin_impersonating')
                    ? ($church?->name ?? 'Unknown Church')
                    : null,
        ];
    }

    private function safeNotificationCount(Request $request): int
    {
        if (! $request->user()) {
            return 0;
        }

        try {
            return app(NotificationService::class)->unreadCount($request->user());
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeUnreadAnnouncementsCount(Request $request): int
    {
        $user     = $request->user();
        $churchId = app('church.id');

        if (! $user || ! $churchId) {
            return 0;
        }

        try {
            return app(AnnouncementService::class)->unreadCount((int) $churchId, $user);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Count overdue tasks assigned to the authenticated user.
     * Uses TaskService::overdueCountForUser() which mirrors scopeOverdue() and
     * TaskResource::is_overdue — all three are now the same definition.
     * Wrapping in try/catch prevents a task-table schema issue from crashing the app.
     */
    private function safeOverdueTasksCount(Request $request): int
    {
        $user = $request->user();

        if (! $user) {
            return 0;
        }

        try {
            return app(TaskService::class)->overdueCountForUser($user);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function defaultFooterNav(): array
    {
        return [
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
        ];
    }
}
