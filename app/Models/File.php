<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'fileable_id', 'fileable_type', 'uploaded_by',
        'name', 'original_name', 'path', 'mime_type', 'extension', 'size', 'disk', 'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'size'      => 'integer',
    ];

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getFormattedSizeAttribute(): string
    {
        if (! $this->size) return '—';
        $kb = $this->size / 1024;
        return $kb < 1024 ? round($kb, 1) . ' KB' : round($kb / 1024, 1) . ' MB';
    }

    /** True when the file is an image (any image/* MIME type). */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    /** True when the file is a PDF. */
    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf'
            || strtolower($this->extension ?? '') === 'pdf';
    }

    /** True when the file is an audio file. */
    public function getIsAudioAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'audio/');
    }

    /** True when the file is a video file. */
    public function getIsVideoAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'video/');
    }

    /**
     * A single category string for the frontend icon component.
     * Values: 'image' | 'pdf' | 'audio' | 'video' | 'document'
     */
    public function getFileTypeAttribute(): string
    {
        if ($this->is_image) return 'image';
        if ($this->is_pdf)   return 'pdf';
        if ($this->is_audio) return 'audio';
        if ($this->is_video) return 'video';
        return 'document';
    }
}
