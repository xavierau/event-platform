<?php

namespace App\Modules\Wallet\Models;

use App\Models\User;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\WalletFactory;

class Wallet extends Model
{
    use HasFactory;

    protected $table = 'user_wallets';

    protected $fillable = [
        'user_id',
        'points_balance',
        'kill_points_balance',
        'total_points_earned',
        'total_points_spent',
        'total_kill_points_earned',
        'total_kill_points_spent',
    ];

    protected $casts = [
        'points_balance' => 'integer',
        'kill_points_balance' => 'integer',
        'total_points_earned' => 'integer',
        'total_points_spent' => 'integer',
        'total_kill_points_earned' => 'integer',
        'total_kill_points_spent' => 'integer',
    ];

    /**
     * Get the user that owns the wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all wallet transactions.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Check if the wallet has enough points for a transaction.
     */
    public function hasEnoughPoints(int $amount): bool
    {
        return $this->points_balance >= $amount;
    }

    /**
     * Check if the wallet has enough kill points for a transaction.
     */
    public function hasEnoughKillPoints(int $amount): bool
    {
        return $this->kill_points_balance >= $amount;
    }

    /**
     * Add points to the wallet.
     */
    public function addPoints(int $amount): void
    {
        $this->increment('points_balance', $amount);
        $this->increment('total_points_earned', $amount);
    }

    /**
     * Spend points from the wallet.
     */
    public function spendPoints(int $amount): bool
    {
        if (!$this->hasEnoughPoints($amount)) {
            return false;
        }

        $this->decrement('points_balance', $amount);
        $this->increment('total_points_spent', $amount);

        return true;
    }

    /**
     * Add kill points to the wallet.
     */
    public function addKillPoints(int $amount): void
    {
        $this->increment('kill_points_balance', $amount);
        $this->increment('total_kill_points_earned', $amount);
    }

    /**
     * Spend kill points from the wallet.
     */
    public function spendKillPoints(int $amount): bool
    {
        if (!$this->hasEnoughKillPoints($amount)) {
            return false;
        }

        $this->decrement('kill_points_balance', $amount);
        $this->increment('total_kill_points_spent', $amount);

        return true;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return WalletFactory::new();
    }
}
