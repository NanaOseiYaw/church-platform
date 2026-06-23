<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Fine-grained authorization is handled in the controller via
        // $this->authorize('markAttendance', $session).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'attendances'             => ['required', 'array', 'min:1'],
            'attendances.*.user_id'   => ['required', 'integer', 'exists:users,id'],
            'attendances.*.status'    => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'attendances.*.notes'     => ['nullable', 'string', 'max:500'],
            'attendances.*.source'    => ['nullable', Rule::in(['manual', 'qr', 'self_checkin', 'imported'])],
        ];
    }
}
