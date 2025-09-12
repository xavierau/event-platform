<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ChangeMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'membership_level_id' => 'required|exists:membership_levels,id',
            'membership_duration_months' => 'nullable|integer|min:1|max:120',
            'reason' => 'required|string|max:500',
            'cancel_existing' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'membership_level_id.required' => 'A membership level must be selected.',
            'membership_level_id.exists' => 'The selected membership level is invalid.',
            'membership_duration_months.min' => 'Membership duration must be at least 1 month.',
            'membership_duration_months.max' => 'Membership duration cannot exceed 120 months.',
            'reason.required' => 'A reason for the membership change is required.',
            'reason.max' => 'The reason cannot exceed 500 characters.',
        ];
    }
}
