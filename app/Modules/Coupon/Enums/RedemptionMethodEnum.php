<?php

namespace App\Modules\Coupon\Enums;

enum RedemptionMethodEnum: string
{
    case QR = 'qr';
    case PIN = 'pin';
}
