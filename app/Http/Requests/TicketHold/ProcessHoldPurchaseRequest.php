<?php

namespace App\Http\Requests\TicketHold;

use App\Modules\TicketHold\Models\PurchaseLink;
use Illuminate\Foundation\Http\FormRequest;

class ProcessHoldPurchaseRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_definition_id' => ['required', 'integer', 'exists:ticket_definitions,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Please select at least one ticket to purchase.',
            'items.min' => 'Please select at least one ticket to purchase.',
            'items.*.ticket_definition_id.required' => 'Each item must specify a ticket type.',
            'items.*.ticket_definition_id.exists' => 'The selected ticket type is invalid.',
            'items.*.quantity.required' => 'Each item must specify a quantity.',
            'items.*.quantity.min' => 'Each item must have a quantity of at least 1.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Validates that requested ticket definitions belong to the hold's allocations.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $code = $this->route('code');
            $link = PurchaseLink::where('code', $code)->with('ticketHold.allocations')->first();

            if (! $link) {
                $validator->errors()->add('link', 'Invalid purchase link.');

                return;
            }

            $validTicketIds = $link->ticketHold->allocations->pluck('ticket_definition_id')->toArray();

            foreach ($this->input('items', []) as $index => $item) {
                $ticketId = $item['ticket_definition_id'] ?? null;
                if ($ticketId && ! in_array($ticketId, $validTicketIds)) {
                    $validator->errors()->add(
                        "items.{$index}.ticket_definition_id",
                        'This ticket is not available through this purchase link.'
                    );
                }
            }
        });
    }
}
