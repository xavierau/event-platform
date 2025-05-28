<?php

namespace App\Modules\Wallet\Models;

use App\Models\User;
use App\Modules\Wallet\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'wallet_transactions';

    protected $fillable = [
        'user_id',
        'wallet_id',
        'transaction_type',
        'amount',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'transaction_type' => WalletTransactionType::class,
    ];

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet that the transaction belongs to.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the reference model (polymorphic relationship).
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Scope for filtering by transaction type.
     */
    public function scopeOfType($query, WalletTransactionType $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope for filtering by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if this is a points transaction.
     */
    public function isPointsTransaction(): bool
    {
        return in_array($this->transaction_type, [
            WalletTransactionType::EARN_POINTS,
            WalletTransactionType::SPEND_POINTS,
        ]);
    }

    /**
     * Check if this is a kill points transaction.
     */
    public function isKillPointsTransaction(): bool
    {
        return in_array($this->transaction_type, [
            WalletTransactionType::EARN_KILL_POINTS,
            WalletTransactionType::SPEND_KILL_POINTS,
        ]);
    }
}
