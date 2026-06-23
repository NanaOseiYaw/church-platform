<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Event::class);
    }

    public function rules(): array
    {
        return [
            'title'         => ['required', 'string', 'max:200'],
            'description'   => ['nullable', 'string'],
            'location'      => ['nullable', 'string', 'max:200'],
            'start_at'      => ['required', 'date'],
            'end_at'        => ['nullable', 'date', 'after_or_equal:start_at'],
            'all_day'       => ['boolean'],
            'visibility'    => ['required', 'in:public,members_only,department_only,private'],
            'published_at'  => ['nullable', 'string'],  // 'now' | ISO-8601 | empty (draft)
            'is_featured'   => ['boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'category'      => ['nullable', 'string', 'max:80'],
            'rsvp_enabled'  => ['boolean'],
            'capacity'      => ['nullable', 'integer', 'min:1'],
            'is_recurring'  => ['boolean'],
        ];
    }
}
