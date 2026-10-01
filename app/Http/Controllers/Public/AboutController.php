<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    public function __invoke(): Response
    {
        $church        = app('church');
        $aboutSettings = $church?->settings['about'] ?? [];

        return Inertia::render('Public/About', [
            'mission'              => $church?->mission      ?? null,
            'vision'               => $church?->vision       ?? null,
            'description'          => $church?->description  ?? null,

            // Denomination-wide and identical for every assembly — see config/cop.php.
            // Kept separate from the church's own mission/vision above, which describe
            // this particular assembly.
            'copMission'           => config('cop.mission'),
            'vision2028'           => config('cop.vision_2028'),
            'foundedYear'          => $church?->founded_year ?? null,
            'team'                 => $aboutSettings['team']   ?? $this->defaultTeam(),
            'values'               => $aboutSettings['values'] ?? $this->defaultValues(),
            'heroTitle'            => $aboutSettings['hero_title']          ?? null,
            'heroSubtitleOverride' => $aboutSettings['hero_subtitle']       ?? null,
            'leadershipSubtitle'   => $aboutSettings['leadership_subtitle'] ?? null,
        ]);
    }

    private function defaultTeam(): array
    {
        return [];
    }

    /**
     * Deliberately empty.
     *
     * This used to return four invented values — Faith, Community, Service,
     * Growth — which read as placeholder text and, worse, sat one click away
     * from /about/core-values, where the twelve real values of The Church of
     * Pentecost are listed. Two different answers to the same question on the
     * same site is worse than one answer.
     *
     * The values grid hides itself when this is empty and the mission column
     * takes the full width, so an admin who genuinely wants a short values
     * summary here can still add one under Settings → About Page.
     */
    private function defaultValues(): array
    {
        return [];
    }
}
