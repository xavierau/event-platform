<?php

namespace App\Modules\Coupon\Models;

use App\Models\Organizer;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'type' => CouponTypeEnum::class,
        'discount_value' => 'integer',
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'max_issuance' => 'integer',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupon::class);
    }

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Coupon\CouponFactory::new();
    }
}
