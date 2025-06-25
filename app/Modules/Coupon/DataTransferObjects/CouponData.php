<?php

namespace App\Modules\Coupon\DataTransferObjects;

use App\Modules\Coupon\Enums\CouponTypeEnum;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Rule;

class CouponData extends Data
{
    public function __construct(
        public readonly int $organizer_id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $code,
        #[Enum(CouponTypeEnum::class)]
        public readonly CouponTypeEnum $type,
        public readonly int $discount_value,
        public readonly string $discount_type,
        public readonly ?int $max_issuance,
        public readonly ?string $valid_from,
        public readonly ?string $expires_at,
        public readonly ?int $id = null, // For updates
    ) {}
}
