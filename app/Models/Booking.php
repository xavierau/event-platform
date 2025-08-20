<?php

namespace App\Models;

use App\Helpers\QrCodeHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;
use App\Enums\BookingStatusEnum;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'ticket_definition_id',
        'booking_number',
        'event_id',
        'quantity',
        'price_at_booking', // Price per ticket at the time of booking, in cents
        'currency_at_booking',
        'status', // e.g., confirmed, cancelled, used
        'metadata',
        'qr_code_identifier',
        'max_allowed_check_ins',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_at_booking' => 'integer',
        'metadata' => 'json',
        'max_allowed_check_ins' => 'integer',
        'status' => BookingStatusEnum::class,
    ];


    /**
     * Get the transaction that this booking belongs to.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the ticket definition for this booking.
     */
    public function ticketDefinition(): BelongsTo
    {
        return $this->belongsTo(TicketDefinition::class);
    }

    /**
     * Get the event occurrences for this booking through the ticket definition.
     * Note: A ticket definition can be valid for multiple event occurrences.
     */
    public function eventOccurrences()
    {
        return $this->ticketDefinition->eventOccurrences();
    }

    /**
     * Get the event for this booking through the ticket definition and event occurrences.
     * Note: This assumes all event occurrences for a ticket belong to the same event.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user associated with this booking through the transaction.
     */
    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            Transaction::class,
            'id', // Foreign key on transactions table (bookings.transaction_id relates to this)
            'id', // Foreign key on users table (transactions.user_id relates to this)
            'transaction_id', // Local key on bookings table
            'user_id' // Local key on transactions table
        );
    }



    /**
     * Get all check-in logs for this booking.
     */
    public function checkInLogs(): HasMany
    {
        return $this->hasMany(CheckInLog::class);
    }

    /**
     * Get the count of successful check-ins for this booking.
     */
    public function getSuccessfulCheckInsCountAttribute(): int
    {
        return $this->checkInLogs()->successful()->count();
    }

    /**
     * Check if this booking can be checked in (has remaining check-ins available).
     */
    public function canCheckIn(): bool
    {
        return $this->successful_check_ins_count < $this->max_allowed_check_ins;
    }

    /**
     * Check if this booking has reached its maximum allowed check-ins.
     */
    public function hasReachedMaxCheckIns(): bool
    {
        return $this->successful_check_ins_count >= $this->max_allowed_check_ins;
    }

    /**
     * Generate a unique QR code identifier for this booking.
     */
    public function generateQrCodeIdentifier(): string
    {
        return QrCodeHelper::generate();
    }

    /**
     * Scope to find booking by QR code identifier.
     */
    public function scopeByQrCode($query, string $qrCode)
    {
        return $query->where('qr_code_identifier', $qrCode);
    }

    /**
     * Check if the ticket definition is valid for a specific event occurrence.
     * This validates the many-to-many relationship between tickets and occurrences.
     */
    public function isTicketValidForOccurrence(int $eventOccurrenceId): bool
    {
        if (!$this->ticketDefinition) {
            return false;
        }

        return $this->ticketDefinition->eventOccurrences()
            ->where('event_occurrence_id', $eventOccurrenceId)
            ->exists();
    }

    /**
     * Get the assigned seat number for this booking.
     */
    public function getSeatNumberAttribute(): ?string
    {
        return $this->metadata['seat_number'] ?? null;
    }

    /**
     * Check if this booking has an assigned seat.
     */
    public function hasAssignedSeat(): bool
    {
        return !empty($this->metadata['seat_number']);
    }

    /**
     * Get seat assignment information.
     */
    public function getSeatAssignmentInfo(): ?array
    {
        if (!$this->hasAssignedSeat()) {
            return null;
        }

        return [
            'seat_number' => $this->metadata['seat_number'],
            'assigned_by' => $this->metadata['seat_assigned_by'] ?? null,
            'assigned_at' => $this->metadata['seat_assigned_at'] ?? null,
        ];
    }
}
