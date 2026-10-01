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
            'heroEyebrow'          => $aboutSettings['hero_eyebrow']        ?? null,
            'heroSubtitleOverride' => $aboutSettings['hero_subtitle']       ?? null,
            'leadershipSubtitle'   => $aboutSettings['leadership_subtitle'] ?? null,
        ]);
    }

    private function defaultTeam(): array
    {
        return [];
    }

    private function defaultValues(): array
    {
        return [
            ['title' => 'Faith',     'description' => 'Rooted in scripture, we live and grow by faith in Jesus Christ.'],
            ['title' => 'Community', 'description' => 'We believe life is better together — deeply connected and accountable.'],
            ['title' => 'Service',   'description' => 'We serve our city and world with humility and compassion.'],
            ['title' => 'Growth',    'description' => 'We pursue spiritual maturity through teaching, prayer, and community.'],
        ];
    }
}
