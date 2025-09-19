<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleNameEnum;
use App\Models\Event;
use App\Models\TicketDefinition;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ManualBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only platform admins can create manual bookings
        return auth()->check() && auth()->user()->hasRole(RoleNameEnum::ADMIN->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'ticket_definition_id' => ['required', 'integer', 'exists:ticket_definitions,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price_override' => ['nullable', 'integer', 'min:0'], // Price in cents
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateUser($validator);
            $this->validateEvent($validator);
            $this->validateTicketDefinition($validator);
            $this->validateQuantity($validator);
            $this->validateEventTicketRelationship($validator);
        });
    }

    /**
     * Validate user requirements
     */
    private function validateUser($validator): void
    {
        $userId = $this->input('user_id');
        if (!$userId) {
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            $validator->errors()->add('user_id', 'The selected user does not exist.');
            return;
        }

        if (!$user->email_verified_at) {
            $validator->errors()->add('user_id', 'The selected user must have a verified email address.');
        }
    }

    /**
     * Validate event requirements
     */
    private function validateEvent($validator): void
    {
        $eventId = $this->input('event_id');
        if (!$eventId) {
            return;
        }

        $event = Event::find($eventId);
        if (!$event) {
            $validator->errors()->add('event_id', 'The selected event does not exist.');
            return;
        }

        // Check if event has ended (assuming events have end dates)
        if (method_exists($event, 'hasEnded') && $event->hasEnded()) {
            $validator->errors()->add('event_id', 'Cannot create booking for an event that has already ended.');
        }
    }

    /**
     * Validate ticket definition requirements
     */
    private function validateTicketDefinition($validator): void
    {
        $ticketDefinitionId = $this->input('ticket_definition_id');
        if (!$ticketDefinitionId) {
            return;
        }

        $ticketDefinition = TicketDefinition::find($ticketDefinitionId);
        if (!$ticketDefinition) {
            $validator->errors()->add('ticket_definition_id', 'The selected ticket definition does not exist.');
            return;
        }

        if ($ticketDefinition->status !== \App\Enums\TicketDefinitionStatus::ACTIVE) {
            $validator->errors()->add('ticket_definition_id', 'The selected ticket definition must be active.');
        }

        // Validate availability window if set
        $now = now();
        if ($ticketDefinition->availability_window_start_utc && $now < $ticketDefinition->availability_window_start_utc) {
            $validator->errors()->add('ticket_definition_id', 'This ticket is not yet available for booking.');
        }

        if ($ticketDefinition->availability_window_end_utc && $now > $ticketDefinition->availability_window_end_utc) {
            $validator->errors()->add('ticket_definition_id', 'This ticket is no longer available for booking.');
        }
    }

    /**
     * Validate quantity requirements
     */
    private function validateQuantity($validator): void
    {
        $quantity = $this->input('quantity');
        $ticketDefinitionId = $this->input('ticket_definition_id');

        if (!$quantity || !$ticketDefinitionId) {
            return;
        }

        $ticketDefinition = TicketDefinition::find($ticketDefinitionId);
        if (!$ticketDefinition) {
            return;
        }

        // Check min/max per order constraints
        if ($ticketDefinition->min_per_order && $quantity < $ticketDefinition->min_per_order) {
            $validator->errors()->add('quantity', "Minimum quantity for this ticket is {$ticketDefinition->min_per_order}.");
        }

        if ($ticketDefinition->max_per_order && $quantity > $ticketDefinition->max_per_order) {
            $validator->errors()->add('quantity', "Maximum quantity for this ticket is {$ticketDefinition->max_per_order}.");
        }

        // Check total availability if limited
        if ($ticketDefinition->total_quantity !== null) {
            $bookedQuantity = \App\Models\Booking::where('ticket_definition_id', $ticketDefinitionId)
                ->whereIn('status', ['confirmed', 'used', 'pending_confirmation'])
                ->sum('quantity');

            $availableQuantity = $ticketDefinition->total_quantity - $bookedQuantity;

            if ($quantity > $availableQuantity) {
                $validator->errors()->add('quantity', "Only {$availableQuantity} tickets are available.");
            }
        }
    }

    /**
     * Validate that the ticket definition belongs to the selected event
     */
    private function validateEventTicketRelationship($validator): void
    {
        $eventId = $this->input('event_id');
        $ticketDefinitionId = $this->input('ticket_definition_id');

        if (!$eventId || !$ticketDefinitionId) {
            return;
        }

        // Check if ticket definition belongs to the selected event through event occurrences
        $ticketDefinition = TicketDefinition::with('eventOccurrences.event')->find($ticketDefinitionId);
        if (!$ticketDefinition) {
            return;
        }

        $ticketEventIds = $ticketDefinition->eventOccurrences->pluck('event.id')->unique();

        if (!$ticketEventIds->contains($eventId)) {
            $validator->errors()->add('ticket_definition_id', 'The selected ticket definition does not belong to the selected event.');
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
            'event_id' => 'event',
            'ticket_definition_id' => 'ticket type',
            'price_override' => 'custom price',
            'admin_notes' => 'admin notes',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a user for this booking.',
            'user_id.exists' => 'The selected user does not exist.',
            'event_id.required' => 'Please select an event for this booking.',
            'event_id.exists' => 'The selected event does not exist.',
            'ticket_definition_id.required' => 'Please select a ticket type for this booking.',
            'ticket_definition_id.exists' => 'The selected ticket type does not exist.',
            'quantity.required' => 'Please specify the number of tickets.',
            'quantity.min' => 'At least 1 ticket is required.',
            'price_override.min' => 'Price cannot be negative.',
            'reason.required' => 'Please provide a reason for creating this manual booking.',
        ];
    }
}