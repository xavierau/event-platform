<?php

namespace App\Models;

use App\Enums\CheckInMethod;
use App\Enums\CheckInStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckInLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'event_occurrence_id',
        'check_in_timestamp',
        'method',
        'device_identifier',
        'location_description',
        'operator_user_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'check_in_timestamp' => 'datetime',
        'method' => CheckInMethod::class,
        'status' => CheckInStatus::class,
    ];

    /**
     * Get the booking that this check-in log belongs to.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the event occurrence that this check-in was for.
     */
    public function eventOccurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }

    /**
     * Get the operator (staff member) who processed this check-in.
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    /**
     * Get the user associated with this check-in through the booking.
     * Access via: $checkInLog->booking->user
     */

    /**
     * Scope to get successful check-ins only.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', CheckInStatus::SUCCESSFUL);
    }

    /**
     * Scope to get failed check-ins only.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', '!=', CheckInStatus::SUCCESSFUL);
    }

    /**
     * Scope to filter by check-in method.
     */
    public function scopeByMethod($query, CheckInMethod $method)
    {
        return $query->where('method', $method);
    }
}
