<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    use LogsAuditEvents;

    /** GET /dashboard/gallery */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', GalleryImage::class);

        $churchId = $this->resolvedChurchId();

        $images = GalleryImage::where('church_id', $churchId)
            ->orderBy('display_order')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'caption', 'image_path', 'disk', 'display_order', 'is_published', 'created_at'])
            ->map(fn ($img) => [
                'id'            => $img->id,
                'title'         => $img->title,
                'caption'       => $img->caption,
                'url'           => $img->url,
                'is_published'  => $img->is_published,
                'display_order' => $img->display_order,
                'created_at'    => $img->created_at?->format('M j, Y'),
            ]);

        return Inertia::render('Dashboard/Gallery/Index', [
            'images' => $images,
        ]);
    }

    /** POST /dashboard/gallery */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', GalleryImage::class);

        $request->validate([
            'image'   => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'title'   => ['nullable', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $path = $request->file('image')->store('gallery', 'public');

        GalleryImage::create([
            'church_id'    => $this->resolvedChurchId(),
            'uploaded_by'  => $request->user()->id,
            'title'        => $request->input('title'),
            'caption'      => $request->input('caption'),
            'image_path'   => $path,
            'disk'         => 'public',
            'is_published' => false,
        ]);

        return back()->with('success', 'Image uploaded.');
    }

    /** PATCH /dashboard/gallery/{image}/publish */
    public function togglePublish(Request $request, GalleryImage $image): RedirectResponse
    {
        $this->authorize('update', $image);
        abort_unless($image->church_id === $this->resolvedChurchId(), 403);

        $image->update(['is_published' => ! $image->is_published]);
        $this->auditLog($image->is_published ? 'gallery.image.published' : 'gallery.image.unpublished', $image);

        return back()->with('success', $image->is_published ? 'Image published.' : 'Image unpublished.');
    }

    /** DELETE /dashboard/gallery/{image} */
    public function destroy(GalleryImage $image): RedirectResponse
    {
        $this->authorize('delete', $image);
        abort_unless($image->church_id === $this->resolvedChurchId(), 403);

        $this->auditLog('gallery.image.deleted', $image);

        Storage::disk($image->disk)->delete($image->image_path);
        $image->delete();

        return back()->with('success', 'Image deleted.');
    }
}
