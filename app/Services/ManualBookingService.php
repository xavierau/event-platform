<?php

namespace App\Services;

use App\DataTransferObjects\Booking\ManualBookingData;
use App\Enums\BookingStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Helpers\QrCodeHelper;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ManualBookingService
{
    /**
     * Create a manual booking bypassing the payment flow.
     *
     * This method creates a confirmed transaction and booking that works with
     * the existing QR code check-in system.
     */
    public function createManualBooking(ManualBookingData $data): array
    {
        return DB::transaction(function () use ($data) {
            // Get required models
            $user = User::findOrFail($data->user_id);
            $event = Event::findOrFail($data->event_id);
            $ticketDefinition = TicketDefinition::findOrFail($data->ticket_definition_id);
            $admin = User::findOrFail($data->created_by_admin_id);

            // Calculate price (use override if provided, otherwise ticket definition price)
            $pricePerTicket = $data->price_override ?? $ticketDefinition->price;
            $totalAmount = $pricePerTicket * $data->quantity;
            $currency = $ticketDefinition->currency;

            // Create the transaction with manual booking markers
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'status' => TransactionStatusEnum::CONFIRMED, // Immediately confirmed
                'payment_gateway' => 'manual', // Special gateway for manual bookings
                'payment_gateway_transaction_id' => null,
                'payment_intent_id' => null,
                'notes' => "Manual booking created by admin: {$admin->name} ({$admin->email}). Reason: {$data->reason}",
                'metadata' => json_encode([
                    'manual_booking' => true,
                    'created_by_admin_id' => $admin->id,
                    'admin_name' => $admin->name,
                    'reason' => $data->reason,
                    'price_override_used' => $data->price_override !== null,
                    'original_ticket_price' => $ticketDefinition->price,
                ]),
                'created_by_admin_id' => $admin->id,
                'is_manual_booking' => true,
                'admin_notes' => $data->admin_notes,
            ]);

            // Create individual booking records (one per ticket)
            $createdBookings = [];

            for ($i = 0; $i < $data->quantity; $i++) {
                $qrCodeIdentifier = QrCodeHelper::generate();

                $booking = Booking::create([
                    'transaction_id' => $transaction->id,
                    'ticket_definition_id' => $ticketDefinition->id,
                    'booking_number' => $qrCodeIdentifier, // Use same as QR code for simplicity
                    'event_id' => $event->id,
                    'quantity' => 1, // Each booking record represents one ticket
                    'price_at_booking' => $pricePerTicket,
                    'currency_at_booking' => $currency,
                    'status' => BookingStatusEnum::CONFIRMED, // Immediately confirmed
                    'metadata' => json_encode([
                        'manual_booking' => true,
                        'created_by_admin_id' => $admin->id,
                        'admin_name' => $admin->name,
                        'reason' => $data->reason,
                        'ticket_number' => $i + 1,
                        'total_tickets_in_order' => $data->quantity,
                    ]),
                    'qr_code_identifier' => $qrCodeIdentifier,
                    'max_allowed_check_ins' => 1, // Standard check-in limit
                ]);

                $createdBookings[] = $booking;
            }

            // Log the manual booking creation
            Log::info('Manual booking created', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'event_id' => $event->id,
                'ticket_definition_id' => $ticketDefinition->id,
                'quantity' => $data->quantity,
                'total_amount' => $totalAmount,
                'created_by_admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'reason' => $data->reason,
                'booking_numbers' => collect($createdBookings)->pluck('booking_number')->toArray(),
            ]);

            // Send confirmation email to user
            try {
                $this->sendBookingConfirmationEmail($user, $transaction, $createdBookings, $event, $admin);
            } catch (\Exception $e) {
                // Log email failure but don't fail the booking creation
                Log::error('Failed to send manual booking confirmation email', [
                    'transaction_id' => $transaction->id,
                    'user_email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'success' => true,
                'transaction' => $transaction->fresh(['user', 'createdByAdmin']),
                'bookings' => collect($createdBookings)->map(fn($booking) => $booking->fresh(['ticketDefinition', 'event'])),
                'booking_numbers' => collect($createdBookings)->pluck('booking_number')->toArray(),
                'qr_codes' => collect($createdBookings)->pluck('qr_code_identifier')->toArray(),
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'message' => "Manual booking created successfully. {$data->quantity} ticket(s) confirmed for {$user->name}.",
            ];
        });
    }

    /**
     * Get data needed for the manual booking creation form.
     */
    public function getFormData(): array
    {
        return [
            'users' => $this->getUsersForSelection(),
            'events' => $this->getEventsForSelection(),
            'currencies' => $this->getAvailableCurrencies(),
        ];
    }

    /**
     * Get ticket definitions for a specific event.
     */
    public function getTicketDefinitionsForEvent(int $eventId): Collection
    {
        return TicketDefinition::whereHas('eventOccurrences.event', function ($query) use ($eventId) {
            $query->where('id', $eventId);
        })
        ->where('status', \App\Enums\TicketDefinitionStatus::ACTIVE)
        ->orderBy('name')
        ->select(['id', 'name', 'description', 'price', 'currency', 'total_quantity', 'min_per_order', 'max_per_order'])
        ->get();
    }

    /**
     * Get users suitable for manual booking selection.
     */
    private function getUsersForSelection(): Collection
    {
        return User::whereNotNull('email_verified_at')
            ->orderBy('name')
            ->select(['id', 'name', 'email'])
            ->get();
    }

    /**
     * Get events suitable for manual booking selection.
     */
    private function getEventsForSelection(): Collection
    {
        return Event::with(['organizer:id,name'])
            ->whereHas('eventOccurrences') // Only events with occurrences
            ->orderBy('name')
            ->get(['id', 'name', 'organizer_id']);
    }

    /**
     * Get available currencies from ticket definitions.
     */
    private function getAvailableCurrencies(): array
    {
        return TicketDefinition::distinct()
            ->whereNotNull('currency')
            ->pluck('currency')
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Send booking confirmation email to the user.
     */
    private function sendBookingConfirmationEmail(
        User $user,
        Transaction $transaction,
        array $bookings,
        Event $event,
        User $admin
    ): void {
        // This would integrate with your existing email system
        // For now, just log that email should be sent
        Log::info('Manual booking confirmation email should be sent', [
            'to' => $user->email,
            'transaction_id' => $transaction->id,
            'event_name' => $event->getTranslation('name', 'en'),
            'booking_count' => count($bookings),
            'created_by_admin' => $admin->name,
        ]);

        // TODO: Implement actual email sending using your existing booking confirmation mail class
        // Example:
        // Mail::to($user)->send(new BookingConfirmationMail($transaction, $bookings, $event));
    }

    /**
     * Get booking statistics for manual bookings.
     */
    public function getManualBookingStatistics(): array
    {
        $baseQuery = Transaction::where('is_manual_booking', true);

        return [
            'total_manual_transactions' => $baseQuery->count(),
            'total_manual_bookings' => Booking::whereHas('transaction', function ($query) {
                $query->where('is_manual_booking', true);
            })->count(),
            'confirmed_manual_bookings' => Booking::whereHas('transaction', function ($query) {
                $query->where('is_manual_booking', true);
            })->where('status', BookingStatusEnum::CONFIRMED)->count(),
            'total_revenue_from_manual_bookings' => $baseQuery
                ->where('status', TransactionStatusEnum::CONFIRMED)
                ->sum('total_amount'),
            'recent_manual_bookings' => Booking::with([
                    'user' => function ($query) {
                        $query->select('users.id', 'name', 'email');
                    },
                    'event' => function ($query) {
                        $query->select('events.id', 'name');
                    },
                    'transaction.createdByAdmin' => function ($query) {
                        $query->select('users.id', 'name');
                    }
                ])
                ->whereHas('transaction', function ($query) {
                    $query->where('is_manual_booking', true);
                })
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Validate manual booking data before creation.
     */
    public function validateManualBooking(ManualBookingData $data): array
    {
        $errors = [];

        // Check user exists and is verified
        $user = User::find($data->user_id);
        if (!$user) {
            $errors['user_id'] = 'User does not exist.';
        } elseif (!$user->email_verified_at) {
            $errors['user_id'] = 'User must have a verified email address.';
        }

        // Check event exists
        $event = Event::find($data->event_id);
        if (!$event) {
            $errors['event_id'] = 'Event does not exist.';
        }

        // Check ticket definition exists and is active
        $ticketDefinition = TicketDefinition::find($data->ticket_definition_id);
        if (!$ticketDefinition) {
            $errors['ticket_definition_id'] = 'Ticket definition does not exist.';
        } elseif ($ticketDefinition->status !== \App\Enums\TicketDefinitionStatus::ACTIVE) {
            $errors['ticket_definition_id'] = 'Ticket definition is not active.';
        }

        // Check admin exists and has admin role
        $admin = User::find($data->created_by_admin_id);
        if (!$admin) {
            $errors['created_by_admin_id'] = 'Admin user does not exist.';
        } elseif (!$admin->hasRole(\App\Enums\RoleNameEnum::ADMIN->value)) {
            $errors['created_by_admin_id'] = 'Admin user lacks admin privileges.';
        }

        return $errors;
    }
}