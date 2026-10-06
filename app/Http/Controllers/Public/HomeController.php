<?php

namespace App\Http\Controllers\Public;

use App\Enums\DepartmentVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicAnnouncementResource;
use App\Http\Resources\PublicEventResource;
use App\Models\Department;
use App\Models\Sermon;
use App\Services\AnnouncementService;
use App\Services\EventService;
use App\Services\Instagram\InstagramService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
        private readonly EventService        $events,
        private readonly InstagramService    $instagram,
    ) {}

    public function __invoke(): Response
    {
        $church           = app('church');
        $churchId         = app('church.id');
        $homepageSettings = $church?->settings['homepage'] ?? [];
        $lsSettings       = $church?->settings['livestream'] ?? [];

        $featuredEvents = $churchId !== null
            ? $this->events->publicFeed(churchId: $churchId, limit: 3, upcomingOnly: true, featuredOnly: false)
            : collect();
        $announcements  = $churchId !== null
            ? $this->announcements->publicFeed(churchId: $churchId, limit: 2, featuredOnly: false)
            : collect();

        $visibilityDefaults = [
            'events'        => true,
            'ministry'      => true,
            'sermons'       => true,
            'testimonials'  => true,
            'announcements' => true,
            'livestream'    => true,
            'instagram'     => true,
        ];
        $sectionVisibility = array_merge($visibilityDefaults, $homepageSettings['section_visibility'] ?? []);

        return Inertia::render('Public/Home', [
            'serviceTimes'       => $church?->service_times        ?? config('church.service_times'),
            'featuredEvents'     => $featuredEvents->map(fn ($e) => PublicEventResource::make($e)->toArray(request()))->values(),
            'announcements'      => $announcements->map(fn ($a) => PublicAnnouncementResource::make($a)->toArray(request()))->values(),
            'latestSermons'      => $this->getLatestSermons(),
            // Hero description + image: admin override → falls back in Vue
            'heroDescription'    => $homepageSettings['hero_description'] ?? null,
            'heroImage'          => $homepageSettings['hero_image']       ?? null,
            // Optional background video; heroImage doubles as its poster frame.
            'heroVideo'          => $homepageSettings['hero_video']       ?? null,
            'hasLivestream'      => !empty($lsSettings['embed_url']) || !empty($lsSettings['stream_url']),
            // Stats and testimonials come from Settings → Homepage; empty = section hidden
            'stats'              => $homepageSettings['stats']        ?? [],
            'testimonials'       => $homepageSettings['testimonials'] ?? [],
            // First 4 public departments — BelongsToChurch scope auto-filters to this church
            'ministryHighlights' => $this->getMinistryHighlights(),
            // Section copy overrides — null = use Vue template defaults
            'eventsSubtitle'       => $homepageSettings['events_subtitle']       ?? null,
            'ministryHeading'      => $homepageSettings['ministry_heading']      ?? null,
            'ministryBody'         => $homepageSettings['ministry_body']         ?? null,
            'sermonsSubtitle'      => $homepageSettings['sermons_subtitle']      ?? null,
            'testimonialsSubtitle' => $homepageSettings['testimonials_subtitle'] ?? null,
            'livestreamCta'        => $homepageSettings['livestream_cta']        ?? null,
            // Per-section admin visibility toggles
            'sectionVisibility'    => $sectionVisibility,
            // Six are shown (one row on desktop, two rows of three on a phone);
            // two spares let a tile whose image fails be replaced, not leave a gap.
            // Cached metadata only; never calls Meta during the request.
            'instagramPosts'       => $sectionVisibility['instagram'] ? $this->instagram->posts(8) : [],
        ]);
    }

    /**
     * Pull the first 4 active, publicly-visible departments for the homepage
     * ministry grid. BelongsToChurch global scope already filters by church_id.
     */
    private function getMinistryHighlights(): array
    {
        return Department::query()
            ->where('is_active', true)
            ->where('visibility', DepartmentVisibility::PUBLIC)
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Department $d) => [
                'name'        => $d->name,
                'description' => $d->description ?? '',
            ])
            ->all();
    }

    private function getLatestSermons(): array
    {
        return Sermon::publiclyVisible()
            // Eager-loaded: the map below reads $s->sermonSeries->title, which
            // would otherwise issue one extra query per sermon on the homepage.
            ->with('sermonSeries:id,title')
            ->whereNotNull('preached_at')
            ->latest('preached_at')
            ->limit(3)
            ->get()
            ->map(fn (Sermon $s) => [
                'id'        => $s->id,
                'title'     => $s->title,
                'speaker'   => $s->speaker ?? '',
                'date'      => $s->preached_at?->format('M j, Y') ?? '',
                'series'    => $s->series ?? ($s->sermonSeries?->title ?? ''),
                'duration'  => $s->duration,
                'thumbnail' => $s->thumbnail_url ?? $s->thumbnail,
            ])
            ->all();
    }
}
