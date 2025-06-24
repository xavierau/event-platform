<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\DataTransferObjects\CouponData;
use App\Modules\Coupon\Models\Coupon;

class UpsertCouponAction
{
    public function execute(CouponData $couponData): Coupon
    {
        $data = $couponData->toArray();

        // Remove the id from data array as it's used for the where clause
        unset($data['id']);

        $coupon = Coupon::updateOrCreate(
            ['id' => $couponData->id],
            $data
        );

        return $coupon;
    }
}
