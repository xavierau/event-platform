<?php

namespace App\Modules\Coupon\Models;

use App\Models\User;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use Database\Factories\Modules\Coupon\UserCouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Coupon\Models\CouponUsageLog;

class UserCoupon extends Model
{
    use HasFactory;

    protected static function newFactory(): UserCouponFactory
    {
        return UserCouponFactory::new();
    }

    protected $fillable = [
        'user_id',
        'coupon_id',
        'unique_code',
        'status',
        'times_can_be_used',
        'times_used',
        'expires_at',
        'issued_at',
    ];

    protected $casts = [
        'status' => UserCouponStatusEnum::class,
        'expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'times_can_be_used' => 'integer',
        'times_used' => 'integer',
        'times_can_be_used' => 'integer',
        'times_used' => 'integer',
        'expires_at' => 'datetime',
        'issued_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(CouponUsageLog::class);
    }
}
