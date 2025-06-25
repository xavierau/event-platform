<?php

namespace App\Modules\Coupon\DataTransferObjects;

use App\Modules\Coupon\Enums\CouponTypeEnum;
<<<<<<< HEAD
use App\Modules\Coupon\Enums\RedemptionMethodEnum;
=======
>>>>>>> feature/coupon-module
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Rule;

class CouponData extends Data
{
    public function __construct(
        public readonly int $organizer_id,
        public readonly string $name,
        public readonly ?string $description,
<<<<<<< HEAD
        #[Rule(['required', 'string', 'unique:coupons,code'])]
=======
>>>>>>> feature/coupon-module
        public readonly string $code,
        #[Enum(CouponTypeEnum::class)]
        public readonly CouponTypeEnum $type,
        public readonly int $discount_value,
        public readonly string $discount_type,
        public readonly ?int $max_issuance,
        public readonly ?string $valid_from,
        public readonly ?string $expires_at,
<<<<<<< HEAD
        #[Rule(['required', 'array', 'min:1'])]
        public readonly array $redemption_methods = ['qr'],
        #[Rule(['nullable', 'required_if:redemption_methods.*,pin', 'digits:6'])]
        public readonly ?string $merchant_pin = null,
        public readonly ?int $id = null, // For updates
    ) {}

    public static function rules(): array
    {
        return [
            'redemption_methods.*' => ['in:qr,pin'],
        ];
    }
=======
        public readonly ?int $id = null, // For updates
    ) {}
>>>>>>> feature/coupon-module
}
