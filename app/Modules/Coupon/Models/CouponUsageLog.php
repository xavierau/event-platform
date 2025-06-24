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
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
        'context' => 'array',
    ];

    public function userCoupon(): BelongsTo
    {
        return $this->belongsTo(UserCoupon::class);
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by_user_id');
    }
}
