<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GalleryImage extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'uploaded_by', 'title', 'caption',
        'image_path', 'disk', 'display_order', 'is_published',
    ];

    protected $casts = [
        'is_published'  => 'boolean',
        'display_order' => 'integer',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->image_path);
    }
}
