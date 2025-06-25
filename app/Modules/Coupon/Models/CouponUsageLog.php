<?php

namespace App\Modules\Coupon\Models;

use App\Models\User;
<<<<<<< HEAD
use Database\Factories\Modules\Coupon\CouponUsageLogFactory;
=======
>>>>>>> feature/coupon-module
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsageLog extends Model
{
    use HasFactory;

<<<<<<< HEAD
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
=======
    protected $fillable = [
        'user_coupon_id',
        'user_id',
        'used_at',
        'location',
        'details',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'details' => 'array',
>>>>>>> feature/coupon-module
    ];

    public function userCoupon(): BelongsTo
    {
        return $this->belongsTo(UserCoupon::class);
    }

<<<<<<< HEAD
    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by_user_id');
=======
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Coupon\CouponUsageLogFactory::new();
>>>>>>> feature/coupon-module
    }
}
