<?php

namespace App\Modules\Membership\Models;

use App\Models\User;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMembership extends Model
{
    use HasFactory;

    protected $table = 'user_memberships';

    protected $fillable = [
        'user_id',
        'membership_level_id',
        'started_at',
        'expires_at',
        'status',
        'payment_method',
        'transaction_reference',
        'auto_renew',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'status' => MembershipStatus::class,
        'payment_method' => PaymentMethod::class,
        'auto_renew' => 'boolean',
    ];

    /**
     * Get the user that owns the membership.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the membership level.
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class, 'membership_level_id');
    }

    /**
     * Scope for active memberships.
     */
    public function scopeActive($query)
    {
        return $query->where('status', MembershipStatus::ACTIVE)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired memberships.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->orWhere('status', MembershipStatus::EXPIRED);
    }

    /**
     * Scope for memberships expiring soon.
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', MembershipStatus::ACTIVE)
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    /**
     * Check if the membership is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === MembershipStatus::ACTIVE &&
            $this->expires_at > now();
    }

    /**
     * Check if the membership has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at <= now() ||
            $this->status === MembershipStatus::EXPIRED;
    }

    /**
     * Check if the membership is expiring soon.
     */
    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->isActive() &&
            $this->expires_at <= now()->addDays($days);
    }

    /**
     * Get the number of days until expiration.
     */
    public function getDaysUntilExpiration(): int
    {
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Extend the membership by the specified number of months.
     */
    public function extend(int $months): void
    {
        $this->expires_at = $this->expires_at->addMonths($months);
        $this->save();
    }

    /**
     * Cancel the membership.
     */
    public function cancel(): void
    {
        $this->status = MembershipStatus::CANCELLED;
        $this->auto_renew = false;
        $this->save();
    }

    /**
     * Expire the membership.
     */
    public function expire(): void
    {
        $this->status = MembershipStatus::EXPIRED;
        $this->auto_renew = false;
        $this->save();
    }

    /**
     * Renew the membership.
     */
    public function renew(int $months = null): void
    {
        $months = $months ?? $this->level->duration_months;

        $this->expires_at = ($this->isActive() ? $this->expires_at : now())->addMonths($months);
        $this->status = MembershipStatus::ACTIVE;
        $this->save();
    }
}
