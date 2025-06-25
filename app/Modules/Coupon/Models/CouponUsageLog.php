<?php

namespace App\Modules\Coupon\Models;

use App\Models\User;
use Database\Factories\Modules\Coupon\CouponUsageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsageLog extends Model
{
    use HasFactory;

    protected static function newFactory(): CouponUsageLogFactory
    {
        return CouponUsageLogFactory::new();
    }

    protected $fillable = [
        'user_coupon_id',
        'redeemed_by_user_id',
        'redeemed_at',
        'context',
        'user_id',
        'used_at',
        'location',
        'details',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'details' => 'array',
    ];

    public function userCoupon(): BelongsTo
    {
        return $this->belongsTo(UserCoupon::class);
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
