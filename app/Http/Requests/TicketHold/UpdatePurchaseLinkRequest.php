<?php

namespace App\Http\Requests\TicketHold;

use App\Enums\RoleNameEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'name' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->validateLinkAccess()) {
                $validator->errors()->add(
                    'purchase_link_id',
                    'You do not have permission to update this purchase link.'
                );
            }
        });
    }

    /**
     * Validate that the user has access to the purchase link.
     */
    private function validateLinkAccess(): bool
    {
        $user = auth()->user();
        $isPlatformAdmin = $user->hasRole(RoleNameEnum::ADMIN);

        if ($isPlatformAdmin) {
            return true;
        }

        $purchaseLink = $this->route('purchaseLink');

        if (! $purchaseLink) {
            return true;
        }

        $organizer = $purchaseLink->ticketHold?->organizer;

        if (! $organizer) {
            return true;
        }

        return $user->organizers->pluck('id')->contains($organizer->id);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }
}
