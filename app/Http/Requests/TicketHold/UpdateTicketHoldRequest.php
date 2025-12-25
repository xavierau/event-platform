<?php

namespace App\Http\Requests\TicketHold;

use App\Enums\RoleNameEnum;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketHoldRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'allocations' => ['sometimes', 'required', 'array', 'min:1'],
            'allocations.*.ticket_definition_id' => ['required_with:allocations', 'integer', 'exists:ticket_definitions,id'],
            'allocations.*.allocated_quantity' => ['required_with:allocations', 'integer', 'min:1'],
            'allocations.*.pricing_mode' => ['required_with:allocations', 'string', Rule::enum(PricingModeEnum::class)],
            'allocations.*.custom_price' => ['required_if:allocations.*.pricing_mode,fixed', 'nullable', 'integer', 'min:0'],
            'allocations.*.discount_percentage' => ['required_if:allocations.*.pricing_mode,percentage_discount', 'nullable', 'integer', 'min:0', 'max:100'],
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
                    'You do not have permission to update ticket holds for this organizer.'
                );
            }
        });
    }

    /**
     * Validate that the user has access to the ticket hold's organizer.
     */
    private function validateOrganizerAccess(): bool
    {
        $user = auth()->user();
        $isPlatformAdmin = $user->hasRole(RoleNameEnum::ADMIN);

        if ($isPlatformAdmin) {
            return true;
        }

        $ticketHold = $this->route('ticket_hold');

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
            'allocations.min' => 'At least one ticket allocation is required.',
            'allocations.*.ticket_definition_id.required_with' => 'Each allocation must specify a ticket type.',
            'allocations.*.ticket_definition_id.exists' => 'The selected ticket type is invalid.',
            'allocations.*.allocated_quantity.required_with' => 'Each allocation must specify a quantity.',
            'allocations.*.allocated_quantity.min' => 'Each allocation must have at least 1 ticket.',
            'allocations.*.pricing_mode.required_with' => 'Each allocation must specify a pricing mode.',
            'allocations.*.custom_price.required_if' => 'A custom price is required when using fixed pricing.',
            'allocations.*.discount_percentage.required_if' => 'A discount percentage is required when using percentage discount.',
            'allocations.*.discount_percentage.max' => 'Discount percentage cannot exceed 100%.',
        ];
    }
}
