<?php

namespace App\Http\Requests\TicketHold;

use App\Enums\RoleNameEnum;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseLinkRequest extends FormRequest
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
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'quantity_mode' => ['required', 'string', Rule::enum(QuantityModeEnum::class)],
            'quantity_limit' => ['required_unless:quantity_mode,unlimited', 'nullable', 'integer', 'min:1'],
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
            if (! $this->validateHoldAccess()) {
                $validator->errors()->add(
                    'ticket_hold_id',
                    'You do not have permission to create purchase links for this ticket hold.'
                );
            }
        });
    }

    /**
     * Validate that the user has access to the ticket hold.
     */
    private function validateHoldAccess(): bool
    {
        $user = auth()->user();
        $isPlatformAdmin = $user->hasRole(RoleNameEnum::ADMIN);

        if ($isPlatformAdmin) {
            return true;
        }

        $ticketHold = $this->route('ticketHold');

        if (! $ticketHold || ! $ticketHold->organizer_id) {
            return true;
        }

        return $user->organizers->pluck('id')->contains($ticketHold->organizer_id);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity_mode.required' => 'Please select a quantity mode.',
            'quantity_limit.required_unless' => 'A quantity limit is required for fixed and maximum modes.',
            'quantity_limit.min' => 'Quantity limit must be at least 1.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }
}
