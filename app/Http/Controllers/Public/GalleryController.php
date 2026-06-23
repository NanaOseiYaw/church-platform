<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function __invoke(): Response
    {
        $churchId = app('church.id');

        $images = GalleryImage::where('church_id', $churchId)
            ->where('is_published', true)
            ->orderBy('display_order')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'caption', 'image_path', 'disk'])
            ->map(fn ($img) => [
                'id'      => $img->id,
                'title'   => $img->title,
                'caption' => $img->caption,
                'url'     => $img->url,
            ]);

        return Inertia::render('Public/Gallery', [
            'images' => $images,
        ]);
    }
}
