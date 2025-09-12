<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // For now, check if user is authenticated (will add proper admin check later)
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'mobile_number' => 'nullable|string|regex:/^[\+]?[1-9][\d]{0,15}$/',
            'password' => ['required', 'confirmed', Password::defaults()],
            'membership_level_id' => 'nullable|exists:membership_levels,id',
            'membership_duration_months' => 'nullable|integer|min:1|max:120',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The user name is required.',
            'email.required' => 'The email address is required.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'A password is required.',
            'password.confirmed' => 'The password confirmation does not match.',
            'mobile_number.regex' => 'The mobile number format is invalid.',
            'membership_level_id.exists' => 'The selected membership level is invalid.',
            'membership_duration_months.min' => 'Membership duration must be at least 1 month.',
            'membership_duration_months.max' => 'Membership duration cannot exceed 120 months.',
        ];
    }
}
