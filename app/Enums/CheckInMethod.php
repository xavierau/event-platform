<?php

namespace App\Enums;

enum CheckInMethod: string
{
    case QR_SCAN = 'QR_SCAN';
    case MANUAL_ENTRY = 'MANUAL_ENTRY';
    case API_INTEGRATION = 'API_INTEGRATION';

    /**
     * Get the label for the enum case.
     */
    public function label(): string
    {
        return match ($this) {
            self::QR_SCAN => 'QR Code Scan',
            self::MANUAL_ENTRY => 'Manual Entry',
            self::API_INTEGRATION => 'API Integration',
        };
    }
}
