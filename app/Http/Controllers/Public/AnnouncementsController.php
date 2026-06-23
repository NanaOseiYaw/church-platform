<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicAnnouncementResource;
use App\Services\AnnouncementService;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementsController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function __invoke(): Response
    {
        $churchId = app('church.id');

        $announcements = $churchId !== null
            ? $this->announcements->publicFeed(churchId: $churchId, limit: 50, featuredOnly: false)
            : collect();

        // Derive unique categories from real data (plus 'All' sentinel)
        $categories = collect(['All'])
            ->merge(
                $announcements->pluck('category')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
            )
            ->all();

        return Inertia::render('Public/Announcements', [
            'announcements' => $announcements->map(fn ($a) => PublicAnnouncementResource::make($a)->toArray(request()))->values(),
            'categories'    => $categories,
        ]);
    }
}
