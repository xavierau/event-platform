<?php

namespace App\Modules\Coupon\Enums;

enum CouponTypeEnum: string
{
    case SINGLE_USE = 'single_use';
    case MULTI_USE = 'multi_use';
}
