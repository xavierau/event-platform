<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\RedemptionMethodEnum;

class ValidateMerchantPinAction
{
    /**
     * Validate merchant PIN for PIN-based coupon redemption
     *
     * @param UserCoupon $userCoupon The coupon to validate PIN against
     * @param string|null $providedPin The PIN provided by the merchant
     * @return array Array with 'valid' boolean and 'reasons' array
     */
    public function execute(UserCoupon $userCoupon, ?string $providedPin): array
    {
        $reasons = [];
        $valid = true;

        // Clean up provided PIN (trim whitespace)
        $providedPin = $providedPin ? trim($providedPin) : $providedPin;

        // Check if coupon supports PIN redemption
        if (!$this->supportsPinRedemption($userCoupon)) {
            $reasons[] = 'Coupon does not support PIN redemption';
            $valid = false;
            return ['valid' => $valid, 'reasons' => $reasons];
        }

        // Check if PIN is provided
        if (empty($providedPin)) {
            $reasons[] = 'PIN is required for PIN redemption';
            $valid = false;
            return ['valid' => $valid, 'reasons' => $reasons];
        }

        // Check if coupon has merchant PIN configured
        $merchantPin = $userCoupon->coupon->merchant_pin;
        if (empty($merchantPin)) {
            $reasons[] = 'Coupon PIN is not configured';
            $valid = false;
            return ['valid' => $valid, 'reasons' => $reasons];
        }

        // Validate PIN format and match
        if (!$this->isValidPinFormat($providedPin) || $providedPin !== $merchantPin) {
            $reasons[] = 'Invalid merchant PIN';
            $valid = false;
        }

        return ['valid' => $valid, 'reasons' => $reasons];
    }

    /**
     * Check if the coupon supports PIN redemption
     *
     * @param UserCoupon $userCoupon
     * @return bool
     */
    private function supportsPinRedemption(UserCoupon $userCoupon): bool
    {
        $redemptionMethods = $userCoupon->coupon->redemption_methods;
        
        if (empty($redemptionMethods) || !is_array($redemptionMethods)) {
            return false;
        }

        return in_array(RedemptionMethodEnum::PIN->value, $redemptionMethods);
    }

    /**
     * Validate PIN format (6-digit numeric)
     *
     * @param string $pin
     * @return bool
     */
    private function isValidPinFormat(string $pin): bool
    {
        // Must be exactly 6 characters and all numeric
        return strlen($pin) === 6 && ctype_digit($pin);
    }
}