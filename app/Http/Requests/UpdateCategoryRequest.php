<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // TODO: Add proper authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requiredLocale = config('app.locale', 'en');
        $otherLocales = array_diff(config('translatable.locales', ['en', 'zh-TW', 'zh-CN']), [$requiredLocale]);

        $rules = [
            // Name field validation (translatable)
            'name' => ['required', 'array'],
            "name.{$requiredLocale}" => ['required', 'string', 'max:255'],

            // Slug validation
            'slug' => ['required', 'string', 'max:255'],

            // Parent category validation
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],

            // Active status validation
            'is_active' => ['required', 'boolean'],

            // Icon upload validation
            'uploaded_icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:2048'],

            // Remove icon flag validation
            'remove_icon' => ['nullable', 'boolean'],
        ];

        // Optional locale validation for name
        foreach ($otherLocales as $locale) {
            $rules["name.{$locale}"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The category name is required.',
            'name.en.required' => 'The English name is required.',
            'slug.required' => 'The slug is required.',
            'is_active.required' => 'The active status is required.',
            'uploaded_icon.image' => 'The icon must be an image file.',
            'uploaded_icon.mimes' => 'The icon must be a file of type: jpg, jpeg, png, webp, gif, svg.',
            'uploaded_icon.max' => 'The icon may not be greater than 2MB.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Log the raw request data for debugging
        Log::info('Raw request data:', $this->all());

        // Ensure is_active is properly cast to boolean
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        // Ensure parent_id is properly cast
        if ($this->has('parent_id') && $this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }
    }
}
