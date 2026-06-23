<?php

namespace App\Http\Requests\Sermons;

use Illuminate\Foundation\Http\FormRequest;

class StoreSermonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sermons.upload');
    }

    public function rules(): array
    {
        return [
            'title'         => ['required', 'string', 'max:255'],
            'speaker'       => ['nullable', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'series_id'     => ['nullable', 'integer', 'exists:sermon_series,id'],
            'series'        => ['nullable', 'string', 'max:255'],
            'video_url'     => ['nullable', 'url', 'max:2048'],
            'audio_url'     => ['nullable', 'url', 'max:2048'],
            'thumbnail_url' => ['nullable', 'url', 'max:2048'],
            'preached_at'   => ['nullable', 'date'],
            'visibility'    => ['required', 'in:public,members_only,unlisted'],
            'is_featured'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'series_id.exists' => 'The selected series does not exist.',
        ];
    }
}
