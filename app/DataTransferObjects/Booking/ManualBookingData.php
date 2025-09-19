<?php

namespace App\DataTransferObjects\Booking;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class ManualBookingData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('users', 'id')]
        public int $user_id,

        #[Required, IntegerType, Exists('events', 'id')]
        public int $event_id,

        #[Required, IntegerType, Exists('ticket_definitions', 'id')]
        public int $ticket_definition_id,

        #[Required, IntegerType, Min(1)]
        public int $quantity,

        #[Nullable, Numeric, Min(0)]
        public ?int $price_override = null, // Price in cents, null to use ticket definition price

        #[Nullable, StringType]
        public ?string $admin_notes = null,

        #[Required, StringType]
        public string $reason,

        #[Required, IntegerType, Exists('users', 'id')]
        public int $created_by_admin_id,
    ) {}

    /**
     * Custom validation rules for business logic
     */
    public static function rules(): array
    {
        return [
            'user_id' => [
                function ($attribute, $value, $fail) {
                    // Ensure user exists and is active
                    $user = \App\Models\User::find($value);
                    if (!$user || !$user->email_verified_at) {
                        $fail('The selected user must be verified and active.');
                    }
                },
            ],
            'ticket_definition_id' => [
                function ($attribute, $value, $fail) {
                    // Ensure ticket definition is active
                    $ticketDefinition = \App\Models\TicketDefinition::find($value);
                    if (!$ticketDefinition || $ticketDefinition->status !== 'active') {
                        $fail('The selected ticket definition must be active.');
                    }
                },
            ],
            'event_id' => [
                function ($attribute, $value, $fail) {
                    // Ensure event exists and is not ended
                    $event = \App\Models\Event::find($value);
                    if (!$event) {
                        $fail('The selected event does not exist.');
                        return;
                    }

                    // Check if event has ended (if event has end date)
                    if (method_exists($event, 'hasEnded') && $event->hasEnded()) {
                        $fail('Cannot create booking for an event that has already ended.');
                    }
                },
            ],
            'quantity' => [
                function ($attribute, $value, $fail) {
                    $ticketDefinitionId = request()->input('ticket_definition_id');
                    if (!$ticketDefinitionId) {
                        return;
                    }

                    $ticketDefinition = \App\Models\TicketDefinition::find($ticketDefinitionId);
                    if (!$ticketDefinition) {
                        return;
                    }

                    // Check min/max per order constraints
                    if ($ticketDefinition->min_per_order && $value < $ticketDefinition->min_per_order) {
                        $fail("Minimum quantity for this ticket is {$ticketDefinition->min_per_order}.");
                    }

                    if ($ticketDefinition->max_per_order && $value > $ticketDefinition->max_per_order) {
                        $fail("Maximum quantity for this ticket is {$ticketDefinition->max_per_order}.");
                    }

                    // Check availability (if limited)
                    if ($ticketDefinition->total_quantity !== null) {
                        // Calculate currently booked quantity
                        $bookedQuantity = \App\Models\Booking::where('ticket_definition_id', $ticketDefinitionId)
                            ->whereIn('status', ['confirmed', 'used', 'pending_confirmation'])
                            ->sum('quantity');

                        $availableQuantity = $ticketDefinition->total_quantity - $bookedQuantity;

                        if ($value > $availableQuantity) {
                            $fail("Only {$availableQuantity} tickets are available.");
                        }
                    }
                },
            ],
            'created_by_admin_id' => [
                function ($attribute, $value, $fail) {
                    // Ensure admin user exists and has admin role
                    $admin = \App\Models\User::find($value);
                    if (!$admin || !$admin->hasRole(\App\Enums\RoleNameEnum::ADMIN->value)) {
                        $fail('Only admin users can create manual bookings.');
                    }
                },
            ],
        ];
    }
}