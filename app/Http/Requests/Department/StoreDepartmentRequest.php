<?php

namespace App\Http\Requests\Department;

use App\Enums\DepartmentVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Department::class);
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'icon'           => ['nullable', 'string', 'max:50'],
            'color'          => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'coordinator_id' => ['nullable', 'integer', 'exists:users,id'],
            'visibility'     => ['nullable', new Enum(DepartmentVisibility::class)],
            'is_active'      => ['boolean'],
        ];
    }
}
