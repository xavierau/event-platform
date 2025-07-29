<?php

namespace App\Modules\Coupon\Enums;

enum UserCouponStatusEnum: string
{
    case AVAILABLE = 'available';
    case ACTIVE = 'active';
    case USED = 'used';
    case EXPIRED = 'expired';
    case FULLY_USED = 'fully_used';
}
