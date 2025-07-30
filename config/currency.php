<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains currency mappings and configurations used throughout
    | the application for consistent currency display and formatting.
    |
    */

    'symbols' => [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CNY' => '¥',
        'HKD' => 'HK$',
        'SGD' => 'S$',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'TWD' => 'NT$',
        'KRW' => '₩',
        'THB' => '฿',
        'MYR' => 'RM',
        'PHP' => '₱',
        'VND' => '₫',
        'IDR' => 'Rp',
        'INR' => '₹',
    ],

    'default' => 'HKD',

    /*
    |--------------------------------------------------------------------------
    | Currency Display Settings
    |--------------------------------------------------------------------------
    |
    | Configure how currencies should be displayed in different contexts
    |
    */

    'display' => [
        'decimal_places' => 2,
        'thousands_separator' => ',',
        'decimal_separator' => '.',
    ],
];
