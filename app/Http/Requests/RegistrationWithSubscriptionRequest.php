<?php

namespace App\Http\Requests;

use App\DataTransferObjects\Registration\RegistrationWithSubscriptionData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistrationWithSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'mobile_number' => 'required|string|regex:/^[\+]?[1-9][\d]{0,15}$/',
            'password' => ['required', 'confirmed', Password::defaults()],
            'selected_price_id' => 'required|string|exists:membership_levels,stripe_price_id',
        ];
    }
    
    public function toDTO(): RegistrationWithSubscriptionData
    {
        return RegistrationWithSubscriptionData::from($this->validated());
    }
}