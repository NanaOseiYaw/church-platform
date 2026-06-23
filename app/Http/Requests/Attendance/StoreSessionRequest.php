<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\AttendanceSession::class) ?? false;
    }

    public function rules(): array
    {
        $churchId = $this->user()->church_id;

        return [
            'title'         => ['required', 'string', 'max:120'],
            'type'          => ['required', Rule::in(['service', 'meeting', 'rehearsal', 'outreach', 'volunteer', 'other'])],
            'description'   => ['nullable', 'string', 'max:1000'],
            'scheduled_at'  => ['required', 'date'],
            'ended_at'      => ['nullable', 'date', 'after:scheduled_at'],
            'department_id' => [
                'nullable', 'integer',
                Rule::exists('departments', 'id')->where('church_id', $churchId),
            ],
            'event_id' => [
                'nullable', 'integer',
                Rule::exists('events', 'id')->where('church_id', $churchId),
            ],
            'service_plan_id' => [
                'nullable', 'integer',
                Rule::exists('service_plans', 'id')->where('church_id', $churchId),
            ],
        ];
    }
}
