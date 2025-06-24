<?php

namespace App\Modules\Coupon\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsageLog extends Model
{
    use HasFactory;

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
    ];

    public function userCoupon(): BelongsTo
    {
        return $this->belongsTo(UserCoupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Coupon\CouponUsageLogFactory::new();
    }
}
