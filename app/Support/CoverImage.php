<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The optional cover image or flyer on an event or announcement.
 *
 * Kept out of the models' normal save path on purpose: their form requests
 * hand `validated()` straight to a service that mass-assigns it, and
 * `cover_image` is fillable, so an uploaded file left in that array would be
 * written to the column as a temporary path. Controllers strip the two fields
 * before saving and call apply() afterwards.
 */
final class CoverImage
{
    /**
     * Content AND filename are both checked, as for every upload on the
     * platform (see the security invariants). The `image` rule excludes SVG.
     * 10 MB, matching gallery photos: flyers exported from Canva or a phone are
     * often 2–8 MB, and the server accepts up to 50 MB.
     */
    public static function rules(): array
    {
        return [
            'cover_image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:10240'],
            'remove_cover_image' => ['boolean'],
        ];
    }

    /** The request fields this class owns, to strip before a mass-assigning save. */
    public const FIELDS = ['cover_image', 'remove_cover_image'];

    /**
     * Store a newly uploaded image, or clear the current one on request, and
     * delete the file that was replaced. Does nothing if neither was asked for,
     * so saving a form without touching the image keeps it.
     */
    public static function apply(Request $request, Model $model, string $directory): void
    {
        $old = $model->cover_image;

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store($directory, 'public');
            $model->forceFill(['cover_image' => Storage::disk('public')->url($path)])->save();
        } elseif ($request->boolean('remove_cover_image')) {
            $model->forceFill(['cover_image' => null])->save();
        } else {
            return;
        }

        if ($old && $old !== $model->cover_image) {
            PublicUploads::deleteUrl($old, $directory);
        }
    }
}
