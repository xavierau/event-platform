<?php

namespace App\Enums;

enum CheckInStatus: string
{
    case SUCCESSFUL = 'SUCCESSFUL';
    case FAILED_ALREADY_USED = 'FAILED_ALREADY_USED';
    case FAILED_MAX_USES_REACHED = 'FAILED_MAX_USES_REACHED';
    case FAILED_INVALID_CODE = 'FAILED_INVALID_CODE';
    case FAILED_NOT_YET_VALID = 'FAILED_NOT_YET_VALID';
    case FAILED_EXPIRED = 'FAILED_EXPIRED';

    /**
     * Get the label for the enum case.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUCCESSFUL => 'Successful',
            self::FAILED_ALREADY_USED => 'Failed - Already Used',
            self::FAILED_MAX_USES_REACHED => 'Failed - Max Uses Reached',
            self::FAILED_INVALID_CODE => 'Failed - Invalid Code',
            self::FAILED_NOT_YET_VALID => 'Failed - Not Yet Valid',
            self::FAILED_EXPIRED => 'Failed - Expired',
        };
    }

    /**
     * Check if the status indicates a successful check-in.
     */
    public function isSuccessful(): bool
    {
        return $this === self::SUCCESSFUL;
    }

    /**
     * Check if the status indicates a failed check-in.
     */
    public function isFailed(): bool
    {
        return !$this->isSuccessful();
    }
}
