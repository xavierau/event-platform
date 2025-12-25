<?php

namespace App\Modules\TicketHold\Models;

use App\Models\User;
use Database\Factories\PurchaseLinkAccessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseLinkAccess extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return PurchaseLinkAccessFactory::new();
    }

    protected $table = 'purchase_link_accesses';

    protected $fillable = [
        'purchase_link_id',
        'user_id',
        'ip_address',
        'user_agent',
        'referer',
        'session_id',
        'resulted_in_purchase',
        'accessed_at',
    ];

    protected $casts = [
        'resulted_in_purchase' => 'boolean',
        'accessed_at' => 'datetime',
    ];

    // Relationships

    public function purchaseLink(): BelongsTo
    {
        return $this->belongsTo(PurchaseLink::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): HasOne
    {
        return $this->hasOne(PurchaseLinkPurchase::class, 'access_id');
    }

    // Methods

    /**
     * Mark this access as having resulted in a purchase.
     */
    public function markAsPurchased(): void
    {
        $this->update(['resulted_in_purchase' => true]);
    }
}
