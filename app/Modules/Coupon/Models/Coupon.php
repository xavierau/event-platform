<?php

namespace App\Modules\Coupon\Models;

use App\Models\Organizer;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use Database\Factories\Modules\Coupon\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    protected $fillable = [
        'organizer_id',
        'name',
        'description',
        'code',
        'type',
        'discount_value',
        'discount_type',
        'max_issuance',
        'used_count',
        'is_active',
        'valid_from',
        'expires_at',
        'redemption_methods',
        'merchant_pin',
    ];

    protected $casts = [
        'type' => CouponTypeEnum::class,
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'discount_value' => 'integer',
        'max_issuance' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'redemption_methods' => 'array',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupon::class);
    }

    public function getCurrentIssueCount()
    {
        return $this->userCoupons()->sum('quantity');
    }

    public function getRemainingIssuance()
    {
        if ($this->max_issuance === null) return PHP_INT_MAX;

        return $this->max_issuance - $this->getCurrentIssueCount();
    }

    public function hasEnoughIssuance(int $requiredQuantity)
    {
        if ($this->max_issuance === null) return true;

        return $this->getRemainingIssuance() >= $requiredQuantity;
    }
}
