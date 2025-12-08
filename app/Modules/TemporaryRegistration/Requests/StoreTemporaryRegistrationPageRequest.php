<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\Requests;

use App\Enums\RoleNameEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemporaryRegistrationPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(RoleNameEnum::ADMIN->value);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:255'],
            'title.zh-TW' => ['nullable', 'string', 'max:255'],
            'title.zh-CN' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'description.zh-TW' => ['nullable', 'string', 'max:5000'],
            'description.zh-CN' => ['nullable', 'string', 'max:5000'],
            'membership_level_id' => ['required', 'integer', 'exists:membership_levels,id'],
            'use_slug' => ['required', 'boolean'],
            'slug' => [
                'nullable',
                'required_if:use_slug,true',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('temporary_registration_pages', 'slug'),
            ],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_registrations' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'banner_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.en.required' => 'The English title is required.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and dashes.',
            'slug.unique' => 'This slug is already in use.',
            'expires_at.after' => 'The expiration date must be in the future.',
        ];
    }
}
