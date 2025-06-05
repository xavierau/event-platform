<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\TransactionStatusEnum;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_amount',
        'currency',
        'status', // e.g., pending, completed, failed, refunded
        'payment_gateway', // e.g., stripe, paypal
        'payment_gateway_transaction_id',
        'payment_intent_id', // For storing Stripe payment intent ID
        'notes',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'integer', // Storing amount in cents
        'metadata' => 'json',
        'status' => TransactionStatusEnum::class,
    ];

    /**
     * Get the user that made the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all of the bookings for the transaction.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
