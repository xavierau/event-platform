<?php

namespace App\Http\Requests\EventSeo;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventSeoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user can manage the event
        $event = $this->route('event');

        return $event && auth()->user()?->can('update', $event);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'meta_title' => ['nullable', 'array'],
            'meta_title.en' => ['nullable', 'string', 'max:60'],
            'meta_title.zh-TW' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.en' => ['nullable', 'string', 'max:160'],
            'meta_description.zh-TW' => ['nullable', 'string', 'max:160'],
            'keywords' => ['nullable', 'array'],
            'keywords.en' => ['nullable', 'string', 'max:255'],
            'keywords.zh-TW' => ['nullable', 'string', 'max:255'],
            'og_title' => ['nullable', 'array'],
            'og_title.en' => ['nullable', 'string', 'max:60'],
            'og_title.zh-TW' => ['nullable', 'string', 'max:60'],
            'og_description' => ['nullable', 'array'],
            'og_description.en' => ['nullable', 'string', 'max:160'],
            'og_description.zh-TW' => ['nullable', 'string', 'max:160'],
            'og_image_url' => ['nullable', 'string', 'url', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'event_id.required' => 'The event ID is required.',
            'event_id.exists' => 'The selected event does not exist.',
            'meta_title.en.max' => 'The English meta title must not exceed 60 characters.',
            'meta_title.zh-TW.max' => 'The Traditional Chinese meta title must not exceed 60 characters.',
            'meta_description.en.max' => 'The English meta description must not exceed 160 characters.',
            'meta_description.zh-TW.max' => 'The Traditional Chinese meta description must not exceed 160 characters.',
            'keywords.en.max' => 'The English keywords must not exceed 255 characters.',
            'keywords.zh-TW.max' => 'The Traditional Chinese keywords must not exceed 255 characters.',
            'og_title.en.max' => 'The English Open Graph title must not exceed 60 characters.',
            'og_title.zh-TW.max' => 'The Traditional Chinese Open Graph title must not exceed 60 characters.',
            'og_description.en.max' => 'The English Open Graph description must not exceed 160 characters.',
            'og_description.zh-TW.max' => 'The Traditional Chinese Open Graph description must not exceed 160 characters.',
            'og_image_url.url' => 'The Open Graph image URL must be a valid URL.',
            'og_image_url.max' => 'The Open Graph image URL must not exceed 255 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure is_active is properly cast to boolean
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            ]);
        } else {
            $this->merge(['is_active' => true]);
        }

        // If event_id is not provided in the request body, get it from route parameter
        if (! $this->has('event_id') && $this->route('event')) {
            $this->merge(['event_id' => $this->route('event')->id]);
        }

        // Clean up empty strings in translatable arrays
        $translatableFields = ['meta_title', 'meta_description', 'keywords', 'og_title', 'og_description'];

        foreach ($translatableFields as $field) {
            if ($this->has($field) && is_array($this->input($field))) {
                $cleaned = array_filter($this->input($field), function ($value) {
                    return $value !== null && $value !== '';
                });
                $this->merge([$field => $cleaned ?: null]);
            }
        }
    }
}
