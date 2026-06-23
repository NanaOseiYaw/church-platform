<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Normalises a single File model into a stable frontend data contract.
 *
 * – url is always a usable URL: public-disk files get their storage URL;
 *   private-disk files get the secure download route.
 * – Computed boolean flags (is_image, is_pdf, etc.) let Vue render the
 *   correct icon without inspecting mime_type strings.
 */
class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'original_name' => $this->original_name,
            'mime_type'     => $this->mime_type,
            'extension'     => $this->extension,
            'size'          => $this->size,
            'formatted_size'=> $this->formatted_size,
            'is_public'     => (bool) $this->is_public,

            // Computed type helpers — Vue reads these, never the raw mime_type
            'is_image'   => $this->is_image,
            'is_pdf'     => $this->is_pdf,
            'is_audio'   => $this->is_audio,
            'is_video'   => $this->is_video,
            'file_type'  => $this->file_type,

            // URL — always usable in the browser
            'url' => $this->disk === 'public'
                ? Storage::disk('public')->url($this->path)
                : route('dashboard.files.download', $this->id),

            // Dates
            'uploaded_at'           => $this->created_at?->toJSON(),
            'uploaded_at_formatted' => $this->created_at?->format('j M Y, g:i A'),

            // Uploader — only when eager-loaded
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader ? [
                'id'     => $this->uploader->id,
                'name'   => $this->uploader->name,
                'avatar' => $this->uploader->avatar,
            ] : null),
        ];
    }
}
