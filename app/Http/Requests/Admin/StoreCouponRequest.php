<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleNameEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Basic authorization - user must be authenticated
        // Additional organizer validation happens in withValidator()
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organizer_id' => ['required', 'integer', 'exists:organizers,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['required', 'string', 'unique:coupons,code'],
            'type' => ['required', 'string', 'in:single_use,multi_use'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'discount_type' => ['required', 'string', 'in:fixed,percentage'],
            'max_issuance' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:valid_from'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->validateOrganizerAccess()) {
                $validator->errors()->add(
                    'organizer_id',
                    'You do not have permission to create coupons for this organizer.'
                );
            }
        });
    }

    /**
     * Validate that the user has access to the specified organizer
     */
    private function validateOrganizerAccess(): bool
    {
        $user = auth()->user();
        $isPlatformAdmin = $user->hasRole(RoleNameEnum::ADMIN);

        if ($isPlatformAdmin) {
            return true;
        }

        $organizerId = $this->input('organizer_id');

        return $user->organizers->pluck('id')->contains($organizerId);
    }
}
