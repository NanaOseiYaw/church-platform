<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $announcement = $this->route('announcement');
        return $this->user()->can('update', $announcement);
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
            'published_at'  => ['nullable', 'string'],
            'expires_at'    => ['nullable', 'date'],
        ];
    }
}
