<?php

namespace App\Modules\Coupon\Enums;

enum UserCouponStatusEnum: string
{
    case ACTIVE = 'active';
    case FULLY_USED = 'fully_used';
    case EXPIRED = 'expired';
}
