<?php

namespace App\Modules\Coupon\Models;

use App\Models\Organizer;
use App\Modules\Coupon\Enums\CouponTypeEnum;
<<<<<<< HEAD
use Database\Factories\Modules\Coupon\CouponFactory;
=======
>>>>>>> feature/coupon-module
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

<<<<<<< HEAD
    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

=======
>>>>>>> feature/coupon-module
    protected $fillable = [
        'organizer_id',
        'name',
        'description',
        'code',
        'type',
        'discount_value',
        'discount_type',
        'max_issuance',
        'valid_from',
        'expires_at',
<<<<<<< HEAD
        'redemption_methods',
        'merchant_pin',
=======
>>>>>>> feature/coupon-module
    ];

    protected $casts = [
        'type' => CouponTypeEnum::class,
<<<<<<< HEAD
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'discount_value' => 'integer',
        'max_issuance' => 'integer',
        'redemption_methods' => 'array',
=======
        'discount_value' => 'integer',
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'max_issuance' => 'integer',
>>>>>>> feature/coupon-module
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupon::class);
    }
<<<<<<< HEAD
=======

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Coupon\CouponFactory::new();
    }
>>>>>>> feature/coupon-module
}
