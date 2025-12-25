<?php

namespace App\Modules\TicketHold\Models;

use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use Database\Factories\PurchaseLinkPurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLinkPurchase extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return PurchaseLinkPurchaseFactory::new();
    }

    protected $fillable = [
        'purchase_link_id',
        'booking_id',
        'transaction_id',
        'user_id',
        'quantity',
        'unit_price',
        'original_price',
        'currency',
        'access_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'original_price' => 'integer',
    ];

    // Relationships

    public function purchaseLink(): BelongsTo
    {
        return $this->belongsTo(PurchaseLink::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(PurchaseLinkAccess::class, 'access_id');
    }

    // Accessors

    /**
     * Get the savings amount (difference between original and paid price).
     */
    public function getSavingsAttribute(): int
    {
        return max(0, ($this->original_price - $this->unit_price) * $this->quantity);
    }

    /**
     * Get the total amount paid for this purchase.
     */
    public function getTotalPaidAttribute(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
