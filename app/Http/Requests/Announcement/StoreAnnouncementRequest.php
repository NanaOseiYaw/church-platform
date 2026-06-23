<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Announcement::class);
    }

    public function rules(): array
    {
        return [
            'title'         => ['required', 'string', 'max:200'],
            'body'          => ['required', 'string'],
            'priority'      => ['required', 'in:low,medium,high,urgent'],
            'visibility'    => ['required', 'in:public,members_only,department_only,private'],
            'is_featured'   => ['boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'category'      => ['nullable', 'string', 'max:50'],
            'is_pinned'     => ['boolean'],
            'published_at'  => ['nullable', 'string'],  // 'now' | ISO-8601 | empty (draft)
            'expires_at'    => ['nullable', 'date'],
        ];
    }
}
