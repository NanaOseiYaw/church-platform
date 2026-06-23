<?php

namespace App\Http\Requests\Sermons;

use Illuminate\Foundation\Http\FormRequest;

class ConnectChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sermons.manage_channels');
    }

    public function rules(): array
    {
        return [
            'channel_input' => ['required', 'string', 'max:255'],
            'provider'      => ['required', 'string', 'in:youtube'],
            'sync_frequency_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'custom_api_key'       => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel_input.required' => 'Please provide a YouTube channel URL or ID.',
            'provider.in'            => 'Only YouTube connections are supported right now.',
        ];
    }
}
