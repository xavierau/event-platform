<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\Models\UserCoupon;
use Exception;

class GenerateUniqueCodeAction
{
    protected int $maxAttempts = 100;
    protected int $codeLength = 12;

    public function execute(): string
    {
        $attempts = 0;

        do {
            $code = $this->generateRandomCode();
            $attempts++;

            if ($attempts > $this->maxAttempts) {
                throw new Exception('Unable to generate unique code after maximum attempts');
            }
        } while (UserCoupon::where('unique_code', $code)->exists());

        return $code;
    }

    protected function generateRandomCode(): string
    {
        // Use characters that are QR-code friendly and avoid confusing ones
        // Exclude: 0, O, I, 1 to avoid confusion when scanning
        $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';

        for ($i = 0; $i < $this->codeLength; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }
}
