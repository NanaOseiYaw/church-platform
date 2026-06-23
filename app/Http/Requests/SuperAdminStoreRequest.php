<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for super-admin church creation.
 *
 * Intentionally omits the `confirmed` rules from OnboardingRequest —
 * super admins set credentials on behalf of a new church without a
 * confirmation step, and the endpoint is already behind super-admin auth.
 */
class SuperAdminStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Church Info
            'church_name'    => ['required', 'string', 'min:2', 'max:100'],
            'church_tagline' => ['nullable', 'string', 'max:160'],
            'timezone'       => ['nullable', 'string', 'max:60'],
            'denomination'   => ['nullable', 'string', 'max:100'],
            'country'        => ['nullable', 'string', 'max:80'],

            // Branding
            'primary_color'  => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Admin Account (no `confirmed` — super admin sets on behalf of another)
            'admin_name'     => ['required', 'string', 'max:100'],
            'admin_email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'church_name.required'  => 'Your church name is required.',
            'church_name.min'       => 'Church name must be at least 2 characters.',
            'admin_name.required'   => 'Full name is required.',
            'admin_email.required'  => 'An email address is required for the admin account.',
            'admin_email.unique'    => 'An account with this email already exists.',
            'admin_password.min'    => 'Password must be at least 8 characters.',
            'logo.image'            => 'Logo must be an image file.',
            'logo.max'              => 'Logo file size cannot exceed 2 MB.',
            'primary_color.regex'   => 'Please enter a valid hex color (e.g. #6366f1).',
        ];
    }
}
