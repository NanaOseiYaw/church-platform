<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\EventsController;
use App\Http\Controllers\Public\SermonsController;
use App\Http\Controllers\Public\DepartmentsController;
use App\Http\Controllers\Public\YouthController;
use App\Http\Controllers\Public\AnnouncementsController    as PublicAnnouncementsController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DonationController;
use App\Http\Controllers\Public\LivestreamController;
use App\Http\Controllers\Public\GiveController;
use App\Http\Controllers\Public\SeriesController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\EventsController as DashboardEventsController;
use App\Http\Controllers\Dashboard\AnnouncementsController as DashboardAnnouncementsController;
use App\Http\Controllers\Dashboard\DepartmentController;
use App\Http\Controllers\Dashboard\MembersController;
use App\Http\Controllers\Dashboard\ChurchSettingsController;
use App\Http\Controllers\Dashboard\FilesController;
use App\Http\Controllers\Dashboard\NotificationsController;
use App\Http\Controllers\Dashboard\TasksController;
use App\Http\Controllers\Dashboard\AttendanceController;
use App\Http\Controllers\Dashboard\SchedulingController;
use App\Http\Controllers\Dashboard\ServicePlanController;
use App\Http\Controllers\Dashboard\ServingPositionController;
use App\Http\Controllers\Dashboard\AssignmentController;
use App\Http\Controllers\Dashboard\SermonsController as DashboardSermonsController;
use App\Http\Controllers\Dashboard\SermonChannelController;
use App\Http\Controllers\Dashboard\SermonSeriesController;
use App\Http\Controllers\Dashboard\MediaController;
use App\Http\Controllers\Public\SermonWatchController;
use App\Http\Controllers\Dashboard\ReportsController;
use App\Http\Controllers\Dashboard\SearchController;
use App\Http\Controllers\Dashboard\AuditLogController;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Dashboard\CommunicationController;
use App\Http\Controllers\Dashboard\BroadcastController;
use App\Http\Controllers\Dashboard\BroadcastTemplateController;
use App\Http\Controllers\Dashboard\BroadcastAudienceController;
use App\Http\Controllers\Dashboard\GalleryController as DashboardGalleryController;
use App\Http\Controllers\Dashboard\PrayerRequestController as DashboardPrayerRequestController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\PrayerRequestController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;

// ── SEO crawl endpoints — no privacy middleware (crawlers need these always) ──
Route::get('/robots.txt',  RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// ── Public routes (privacy-mode aware) ────────────────────────────────────────
// EnforcePrivacyMode redirects unauthenticated guests to /login when the
// church has privacy_mode enabled in Settings → Public Website.
Route::middleware('privacy')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/about', AboutController::class)->name('about');
    Route::get('/events', EventsController::class)->name('events');
    Route::get('/events/{event}', [EventsController::class, 'show'])->name('events.show');
    Route::get('/sermons',        SermonsController::class)->name('sermons');
    Route::get('/sermons/{slug}', SermonWatchController::class)->name('sermons.watch');
    Route::get('/series',          [SeriesController::class, 'index'])->name('series');
    Route::get('/series/{series}', [SeriesController::class, 'show'])->name('series.show');
    Route::get('/ministries', DepartmentsController::class)->name('ministries');
    Route::get('/ministries/youth', YouthController::class)->name('ministries.youth');
    Route::get('/announcements', PublicAnnouncementsController::class)->name('announcements');
    Route::get('/contact', [ContactController::class, 'index'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::get('/give', DonationController::class)->name('donate');
    Route::get('/live',    LivestreamController::class)->name('livestream');
    Route::get('/gallery', GalleryController::class)->name('gallery');
    Route::get('/prayer',  [PrayerRequestController::class, 'index'])->name('prayer');
    Route::post('/prayer', [PrayerRequestController::class, 'store'])->name('prayer.store');

    // Stripe Checkout
    Route::post('/give/checkout', [GiveController::class, 'checkout'])->name('give.checkout');
    Route::get('/give/thank-you', [GiveController::class, 'success'])->name('give.success');
});

// ── Church onboarding (guests only) ───────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/onboarding',          [OnboardingController::class, 'show'])  ->name('onboarding');
    Route::post('/onboarding',         [OnboardingController::class, 'store']) ->name('onboarding.store');
});
// Welcome screen requires auth (user was just logged in by OnboardChurch action)
Route::middleware('auth')->get('/onboarding/welcome', [OnboardingController::class, 'welcome'])->name('onboarding.welcome');

// ── Auth routes (guests only) ──────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',    [LoginController::class, 'create'])->name('login');
    Route::post('/login',   [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register',[RegisterController::class, 'store'])->name('register.store');

    Route::get('/forgot-password',         [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password',        [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}',  [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password',         [PasswordResetController::class, 'reset'])->name('password.update');
});

// ── Authenticated routes ───────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Dashboard home
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Global search (JSON API — consumed by GlobalSearchModal)
    Route::get('/dashboard/search', SearchController::class)->name('dashboard.search');

    // Events
    Route::prefix('dashboard/events')->name('dashboard.events.')->group(function () {
        Route::get('/',                        [DashboardEventsController::class, 'index'])      ->name('index');
        Route::get('/create',                   [DashboardEventsController::class, 'create'])     ->name('create');
        Route::post('/',                        [DashboardEventsController::class, 'store'])      ->name('store');
        Route::get('/{event}',                  [DashboardEventsController::class, 'show'])       ->name('show');
        Route::get('/{event}/edit',             [DashboardEventsController::class, 'edit'])       ->name('edit');
        Route::put('/{event}',                  [DashboardEventsController::class, 'update'])     ->name('update');
        Route::delete('/{event}',               [DashboardEventsController::class, 'destroy'])    ->name('destroy');
        Route::post('/{event}/rsvp',            [DashboardEventsController::class, 'rsvp'])       ->name('rsvp');
        Route::delete('/{event}/rsvp',          [DashboardEventsController::class, 'cancelRsvp'])->name('rsvp.cancel');
    });

    // Announcements
    Route::prefix('dashboard/announcements')->name('dashboard.announcements.')->group(function () {
        Route::get('/',                                [DashboardAnnouncementsController::class, 'index'])    ->name('index');
        Route::get('/create',                          [DashboardAnnouncementsController::class, 'create'])   ->name('create');
        Route::post('/',                               [DashboardAnnouncementsController::class, 'store'])    ->name('store');
        Route::get('/{announcement}',                  [DashboardAnnouncementsController::class, 'show'])     ->name('show');
        Route::get('/{announcement}/edit',             [DashboardAnnouncementsController::class, 'edit'])     ->name('edit');
        Route::put('/{announcement}',                  [DashboardAnnouncementsController::class, 'update'])   ->name('update');
        Route::delete('/{announcement}',               [DashboardAnnouncementsController::class, 'destroy'])  ->name('destroy');
        Route::patch('/{announcement}/publish',        [DashboardAnnouncementsController::class, 'publish'])  ->name('publish');
        Route::patch('/{announcement}/pin',            [DashboardAnnouncementsController::class, 'pin'])      ->name('pin');
        Route::post('/{announcement}/read',            [DashboardAnnouncementsController::class, 'markRead']) ->name('read');
    });

    // Departments
    Route::prefix('dashboard/departments')->name('dashboard.departments.')->group(function () {
        Route::get('/',                               [DepartmentController::class, 'index'])        ->name('index');
        Route::get('/create',                         [DepartmentController::class, 'create'])       ->name('create');
        Route::post('/',                              [DepartmentController::class, 'store'])        ->name('store');
        Route::get('/{department}',                   [DepartmentController::class, 'show'])         ->name('show');
        Route::get('/{department}/edit',              [DepartmentController::class, 'edit'])         ->name('edit');
        Route::put('/{department}',                   [DepartmentController::class, 'update'])       ->name('update');
        Route::delete('/{department}',                [DepartmentController::class, 'destroy'])      ->name('destroy');
        Route::post('/{department}/members',              [DepartmentController::class, 'addMember'])         ->name('members.add');
        Route::patch('/{department}/members/{user}',      [DepartmentController::class, 'updateMemberRole'])  ->name('members.role');
        Route::delete('/{department}/members/{user}',     [DepartmentController::class, 'removeMember'])      ->name('members.remove');
    });

    // Members
    Route::prefix('dashboard/members')->name('dashboard.members.')->group(function () {
        Route::get('/',                [MembersController::class, 'index'])         ->name('index');
        Route::post('/',               [MembersController::class, 'store'])         ->name('store');
        Route::get('/{user}',          [MembersController::class, 'show'])          ->name('show');
        Route::put('/{user}/profile',  [MembersController::class, 'updateProfile']) ->name('profile.update');
        Route::patch('/{user}/role',   [MembersController::class, 'assignRole'])    ->name('role.assign');
        Route::patch('/{user}/activate', [MembersController::class, 'toggleActive'])->name('activate.toggle');
    });

    // ── Church Administration Centre (admin only) ────────────────────────────
    // GET  /dashboard/settings → redirects to /dashboard/settings/overview
    // Each section has GET (render) + PUT (save) pairs where implemented.
    Route::prefix('dashboard/settings')->name('dashboard.settings.')->group(function () {

        // Hub
        Route::get('/', [ChurchSettingsController::class, 'index'])->name('index');

        // 1. Overview
        Route::get('/overview', [ChurchSettingsController::class, 'overview'])->name('overview');

        // 2. Church Profile
        Route::get('/profile', [ChurchSettingsController::class, 'profile'])       ->name('profile');
        Route::put('/profile', [ChurchSettingsController::class, 'updateProfile']) ->name('profile.update');

        // 3. Branding & Theme
        Route::get('/branding',          [ChurchSettingsController::class, 'branding'])       ->name('branding');
        Route::put('/branding',          [ChurchSettingsController::class, 'updateBranding']) ->name('branding.update');
        Route::post('/branding/logo',    [ChurchSettingsController::class, 'uploadLogo'])     ->name('branding.logo');
        Route::post('/branding/favicon', [ChurchSettingsController::class, 'uploadFavicon'])  ->name('branding.favicon');

        // 4. Social Media
        Route::get('/social', [ChurchSettingsController::class, 'social'])       ->name('social');
        Route::put('/social', [ChurchSettingsController::class, 'updateSocial']) ->name('social.update');

        // 5. Public Website
        Route::get('/website', [ChurchSettingsController::class, 'website'])       ->name('website');
        Route::put('/website', [ChurchSettingsController::class, 'updateWebsite']) ->name('website.update');

        // 6. Service Times
        Route::get('/services', [ChurchSettingsController::class, 'services'])       ->name('services');
        Route::put('/services', [ChurchSettingsController::class, 'updateServices']) ->name('services.update');

        // 7. Livestream
        Route::get('/livestream', [ChurchSettingsController::class, 'livestream'])       ->name('livestream');
        Route::put('/livestream', [ChurchSettingsController::class, 'updateLivestream']) ->name('livestream.update');

        // 8. Donations
        Route::get('/donations',        [ChurchSettingsController::class, 'donations'])        ->name('donations');
        Route::put('/donations',        [ChurchSettingsController::class, 'updateDonations'])  ->name('donations.update');
        Route::post('/donations/stripe',[ChurchSettingsController::class, 'updateStripeKeys']) ->name('donations.stripe');

        // 8b. Homepage content
        Route::get('/homepage',             [ChurchSettingsController::class, 'homepage'])       ->name('homepage');
        Route::put('/homepage',             [ChurchSettingsController::class, 'updateHomepage']) ->name('homepage.update');
        Route::post('/homepage/hero-image', [ChurchSettingsController::class, 'uploadHeroImage'])->name('homepage.hero-image');

        // 9. Communication
        Route::get('/communication', [ChurchSettingsController::class, 'communication'])       ->name('communication');
        Route::put('/communication', [ChurchSettingsController::class, 'updateCommunication']) ->name('communication.update');

        // 10. Membership
        Route::get('/membership', [ChurchSettingsController::class, 'membership'])       ->name('membership');
        Route::put('/membership', [ChurchSettingsController::class, 'updateMembership']) ->name('membership.update');

        // 11. Department governance settings
        Route::get('/depts', [ChurchSettingsController::class, 'departmentSettings'])       ->name('depts');
        Route::put('/depts', [ChurchSettingsController::class, 'updateDepartmentSettings']) ->name('depts.update');

        // 12. Notifications
        Route::get('/notifications', [ChurchSettingsController::class, 'notifications'])       ->name('notifications');
        Route::put('/notifications', [ChurchSettingsController::class, 'updateNotifications']) ->name('notifications.update');

        // 13. Roles & Permissions (read-only view)
        Route::get('/roles', [ChurchSettingsController::class, 'roles'])->name('roles');

        // 14. Media Library
        Route::get('/media', [ChurchSettingsController::class, 'media'])       ->name('media');
        Route::put('/media', [ChurchSettingsController::class, 'updateMedia']) ->name('media.update');

        // 15. Security
        Route::get('/security', [ChurchSettingsController::class, 'security'])       ->name('security');
        Route::put('/security', [ChurchSettingsController::class, 'updateSecurity']) ->name('security.update');

        // 16. SEO & Analytics
        Route::get('/seo', [ChurchSettingsController::class, 'seo'])                              ->name('seo');
        Route::put('/seo', [ChurchSettingsController::class, 'updateSeo'])                         ->name('seo.update');
        Route::post('/seo/og-image', [ChurchSettingsController::class, 'uploadSeoOgImage'])        ->name('seo.og-image');

        // 17. Integrations
        Route::get('/integrations', [ChurchSettingsController::class, 'integrations'])->name('integrations');

        // 18. Audit Logs
        Route::get('/audit', [ChurchSettingsController::class, 'audit'])->name('audit');

        // 19. Advanced
        Route::get('/advanced', [ChurchSettingsController::class, 'advanced'])       ->name('advanced');
        Route::put('/advanced', [ChurchSettingsController::class, 'updateAdvanced']) ->name('advanced.update');

        // 22. About Page Content
        Route::get('/about-content', [ChurchSettingsController::class, 'aboutContent'])       ->name('about-content');
        Route::put('/about-content', [ChurchSettingsController::class, 'updateAboutContent']) ->name('about-content.update');

        // ── Backward-compat: kept so old Contact.vue links/bookmarks still work ──
        Route::get('/contact', [ChurchSettingsController::class, 'contact'])       ->name('contact');
        Route::put('/contact', [ChurchSettingsController::class, 'updateContact']) ->name('contact.update');

        // ── Backward-compat: old monolithic PUT ───────────────────────────────
        Route::put('/', [ChurchSettingsController::class, 'update'])->name('update');
    });

    // Notifications
    Route::prefix('dashboard/notifications')->name('dashboard.notifications.')->group(function () {
        Route::get('/',                         [NotificationsController::class, 'index'])       ->name('index');
        Route::get('/recent',                   [NotificationsController::class, 'recent'])      ->name('recent');
        Route::patch('/{id}/read',              [NotificationsController::class, 'markRead'])    ->name('read');
        Route::post('/read-all',                [NotificationsController::class, 'markAllRead']) ->name('read-all');
    });

    // Files / Media
    Route::prefix('dashboard/files')->name('dashboard.files.')->group(function () {
        Route::post('/',                         [FilesController::class, 'store'])   ->name('store');
        Route::get('/{file}/download',           [FilesController::class, 'download'])->name('download');
        Route::delete('/{file}',                 [FilesController::class, 'destroy']) ->name('destroy');
    });

    // Attendance
    Route::prefix('dashboard/attendance')->name('dashboard.attendance.')->group(function () {
        Route::get('/',                    [AttendanceController::class, 'index'])          ->name('index');
        Route::get('/create',              [AttendanceController::class, 'create'])         ->name('create');
        Route::post('/',                   [AttendanceController::class, 'store'])          ->name('store');
        // /members/{user} MUST precede /{session} to prevent route model binding collision
        Route::get('/members/{user}',      [AttendanceController::class, 'memberHistory'])  ->name('member');
        Route::get('/{session}',           [AttendanceController::class, 'show'])           ->name('show');
        Route::delete('/{session}',        [AttendanceController::class, 'destroy'])        ->name('destroy');
        Route::patch('/{session}/status',  [AttendanceController::class, 'updateStatus'])   ->name('status');
        Route::post('/{session}/save',     [AttendanceController::class, 'saveAttendance']) ->name('save');
    });

    // Scheduling
    Route::prefix('dashboard/scheduling')->name('dashboard.scheduling.')->group(function () {

        Route::get('/',            [SchedulingController::class, 'dashboard'])  ->name('dashboard');
        Route::get('/my-schedule', [SchedulingController::class, 'mySchedule']) ->name('my-schedule');

        Route::prefix('plans')->name('plans.')->group(function () {
            Route::get('/',                           [ServicePlanController::class, 'index'])          ->name('index');
            Route::get('/create',                     [ServicePlanController::class, 'create'])         ->name('create');
            Route::post('/',                          [ServicePlanController::class, 'store'])          ->name('store');
            Route::get('/{plan}',                     [ServicePlanController::class, 'show'])           ->name('show');
            Route::put('/{plan}',                     [ServicePlanController::class, 'update'])         ->name('update');
            Route::delete('/{plan}',                  [ServicePlanController::class, 'destroy'])        ->name('destroy');
            Route::patch('/{plan}/publish',           [ServicePlanController::class, 'publish'])        ->name('publish');
            Route::patch('/{plan}/archive',           [ServicePlanController::class, 'archive'])        ->name('archive');
            Route::post('/{plan}/positions',          [ServicePlanController::class, 'addPosition'])    ->name('positions.add');
            Route::delete('/{plan}/positions/{pp}',   [ServicePlanController::class, 'removePosition']) ->name('positions.remove');
        });

        Route::prefix('positions')->name('positions.')->group(function () {
            Route::get('/',         [ServingPositionController::class, 'index'])   ->name('index');
            Route::post('/',        [ServingPositionController::class, 'store'])   ->name('store');
            Route::put('/{pos}',    [ServingPositionController::class, 'update'])  ->name('update');
            Route::delete('/{pos}', [ServingPositionController::class, 'destroy']) ->name('destroy');
        });

        Route::prefix('assignments')->name('assignments.')->group(function () {
            Route::post('/',                       [AssignmentController::class, 'store'])   ->name('store');
            Route::get('/{assignment}',            [AssignmentController::class, 'show'])    ->name('show');
            Route::delete('/{assignment}',         [AssignmentController::class, 'destroy']) ->name('destroy');
            Route::patch('/{assignment}/respond',  [AssignmentController::class, 'respond']) ->name('respond');
        });
    });

    // ── Communication Center ───────────────────────────────────────────────────
    Route::prefix('dashboard/communication')->name('dashboard.communication.')->group(function () {

        Route::get('/', [CommunicationController::class, 'index'])->name('index');

        Route::prefix('broadcasts')->name('broadcasts.')->group(function () {
            Route::get('/',               [BroadcastController::class, 'index'])->name('index');
            Route::get('/create',              [BroadcastController::class, 'create'])->name('create');
            Route::post('/',               [BroadcastController::class, 'store'])->name('store');
            Route::post('/preview-anonymous', [BroadcastController::class, 'previewAnonymous'])->name('preview-anonymous');
            // Static sub-paths BEFORE /{broadcast} wildcard to avoid collision
            Route::get('/{broadcast}/edit',     [BroadcastController::class, 'edit'])->name('edit');
            Route::put('/{broadcast}',          [BroadcastController::class, 'update'])->name('update');
            Route::get('/{broadcast}',          [BroadcastController::class, 'show'])->name('show');
            Route::delete('/{broadcast}',       [BroadcastController::class, 'destroy'])->name('destroy');
            Route::post('/{broadcast}/preview', [BroadcastController::class, 'preview'])->name('preview');
        });

        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/',             [BroadcastTemplateController::class, 'index'])->name('index');
            Route::post('/',            [BroadcastTemplateController::class, 'store'])->name('store');
            Route::put('/{template}',   [BroadcastTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}',[BroadcastTemplateController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('audiences')->name('audiences.')->group(function () {
            // IMPORTANT: resolve-count MUST come before /{audience} wildcard
            Route::get('/resolve-count', [BroadcastAudienceController::class, 'resolveCount'])->name('resolve-count');
            Route::get('/',              [BroadcastAudienceController::class, 'index'])->name('index');
            Route::post('/',             [BroadcastAudienceController::class, 'store'])->name('store');
            Route::put('/{audience}',    [BroadcastAudienceController::class, 'update'])->name('update');
            Route::delete('/{audience}', [BroadcastAudienceController::class, 'destroy'])->name('destroy');
        });
    });

    // Sermons
    // IMPORTANT: all static segments (/channel, /create, /series) MUST be
    // declared before {sermon} and {sermon}/edit to avoid wildcard collision.
    Route::prefix('dashboard/sermons')->name('dashboard.sermons.')->group(function () {
        Route::get('/',    [DashboardSermonsController::class, 'index']) ->name('index');
        Route::post('/',   [DashboardSermonsController::class, 'store']) ->name('store');

        // Static paths — must come before /{sermon}
        Route::get('/create',   [DashboardSermonsController::class, 'create'])          ->name('create');

        // Series management (static prefix /series before /{sermon})
        Route::get('/series',              [SermonSeriesController::class, 'index'])    ->name('series.index');
        Route::post('/series',             [SermonSeriesController::class, 'store'])    ->name('series.store');
        Route::put('/series/{series}',     [SermonSeriesController::class, 'update'])   ->name('series.update');
        Route::delete('/series/{series}',  [SermonSeriesController::class, 'destroy'])  ->name('series.destroy');

        // Channel management (static prefix /channel before /{sermon})
        Route::get('/channel',                    [DashboardSermonsController::class,   'channelPage']) ->name('channel');
        Route::post('/channel',                   [SermonChannelController::class,       'store'])      ->name('channel.store');
        Route::post('/channel/{connection}/sync', [SermonChannelController::class,       'sync'])       ->name('channel.sync');
        Route::delete('/channel/{connection}',    [SermonChannelController::class,       'destroy'])    ->name('channel.destroy');

        // Wildcard paths — /{sermon}/edit must precede /{sermon} (same depth but edit is static)
        Route::get('/{sermon}/edit',      [DashboardSermonsController::class, 'edit'])          ->name('edit');
        Route::patch('/{sermon}',         [DashboardSermonsController::class, 'update'])        ->name('update');
        Route::delete('/{sermon}',        [DashboardSermonsController::class, 'destroy'])       ->name('destroy');
        Route::patch('/{sermon}/feature', [DashboardSermonsController::class, 'toggleFeature']) ->name('feature');
        Route::patch('/{sermon}/visibility', [DashboardSermonsController::class, 'cycleVisibility'])->name('visibility');
        Route::get('/{sermon}',           [DashboardSermonsController::class, 'show'])          ->name('show');
    });

    // Media library
    Route::get('/dashboard/media', [MediaController::class, 'index'])->name('dashboard.media.index');

    // Gallery management
    Route::prefix('dashboard/gallery')->name('dashboard.gallery.')->group(function () {
        Route::get('/',                   [DashboardGalleryController::class, 'index'])         ->name('index');
        Route::post('/',                  [DashboardGalleryController::class, 'store'])         ->name('store');
        Route::patch('/{image}/publish',  [DashboardGalleryController::class, 'togglePublish']) ->name('publish');
        Route::delete('/{image}',         [DashboardGalleryController::class, 'destroy'])       ->name('destroy');
    });

    // Prayer requests management
    Route::prefix('dashboard/prayer-requests')->name('dashboard.prayer.')->group(function () {
        Route::get('/',                    [DashboardPrayerRequestController::class, 'index'])        ->name('index');
        Route::patch('/{prayerRequest}/answer', [DashboardPrayerRequestController::class, 'markAnswered'])->name('answer');
        Route::delete('/{prayerRequest}',  [DashboardPrayerRequestController::class, 'destroy'])     ->name('destroy');
    });

    // Reports
    Route::get('/dashboard/reports',        [ReportsController::class, 'index'])  ->name('dashboard.reports.index');
    Route::get('/dashboard/reports/export', [ReportsController::class, 'export']) ->name('dashboard.reports.export');

    // User profile (accessible by every authenticated user)
    Route::prefix('dashboard/profile')->name('dashboard.profile.')->group(function () {
        Route::get('/',           [ProfileController::class, 'index'])->name('index');
        Route::patch('/',         [ProfileController::class, 'update'])->name('update');
        Route::patch('/password', [ProfileController::class, 'updatePassword'])->name('password');
        Route::post('/avatar',    [ProfileController::class, 'uploadAvatar'])->name('avatar');
    });

    // Tasks
    Route::prefix('dashboard/tasks')->name('dashboard.tasks.')->group(function () {
        Route::get('/',                                        [TasksController::class, 'index'])        ->name('index');
        Route::get('/create',                                  [TasksController::class, 'create'])       ->name('create');
        Route::post('/',                                       [TasksController::class, 'store'])        ->name('store');
        Route::get('/{task}',                                  [TasksController::class, 'show'])         ->name('show');
        Route::get('/{task}/edit',                             [TasksController::class, 'edit'])         ->name('edit');
        Route::put('/{task}',                                  [TasksController::class, 'update'])       ->name('update');
        Route::delete('/{task}',                               [TasksController::class, 'destroy'])      ->name('destroy');
        Route::patch('/{task}/status',                         [TasksController::class, 'updateStatus']) ->name('status');
        Route::post('/{task}/comments',                        [TasksController::class, 'comment'])      ->name('comment');
        Route::delete('/{task}/comments/{comment}',            [TasksController::class, 'deleteComment'])->name('comment.delete');
    });

    // Audit Log
    Route::get('/dashboard/audit', [AuditLogController::class, 'index'])->name('dashboard.audit.index');
});

// ── Super Admin — platform owner panel ────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/',                         [SuperAdminController::class, 'index'])->name('index');
        Route::get('/churches/create',          [SuperAdminController::class, 'create'])->name('churches.create');
        Route::post('/churches',                [SuperAdminController::class, 'store'])->name('churches.store');
        Route::patch('/{church}/toggle-active', [SuperAdminController::class, 'toggleActive'])->name('churches.toggle-active');
        Route::post('/{church}/impersonate',    [SuperAdminController::class, 'impersonate'])->name('churches.impersonate');
        Route::delete('/impersonate',           [SuperAdminController::class, 'stopImpersonating'])->name('impersonate.stop');
    });
