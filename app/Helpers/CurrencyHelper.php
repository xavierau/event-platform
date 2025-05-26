<?php

namespace App\Helpers;

class CurrencyHelper
{
    /**
     * Get currency symbol for a given currency code
     */
    public static function getSymbol(string $currencyCode): string
    {
        $symbols = config('currency.symbols', []);
        return $symbols[strtoupper($currencyCode)] ?? $currencyCode;
    }

    /**
     * Format price with currency symbol
     */
    public static function format(int $priceInCents, string $currencyCode): string
    {
        $symbol = self::getSymbol($currencyCode);
        $price = $priceInCents / 100;
        $decimalPlaces = config('currency.display.decimal_places', 2);

        return $symbol . number_format($price, $decimalPlaces);
    }

    /**
     * Format price range with currency symbol
     */
    public static function formatRange(int $minPriceInCents, int $maxPriceInCents, string $currencyCode): string
    {
        $symbol = self::getSymbol($currencyCode);
        $minPrice = $minPriceInCents / 100;
        $maxPrice = $maxPriceInCents / 100;
        $decimalPlaces = config('currency.display.decimal_places', 2);

        if ($minPrice == $maxPrice) {
            return $symbol . number_format($minPrice, $decimalPlaces);
        }

        return $symbol . number_format($minPrice, $decimalPlaces) . '-' . number_format($maxPrice, $decimalPlaces);
    }

    /**
     * Get all currency symbols for frontend use
     */
    public static function getAllSymbols(): array
    {
        return config('currency.symbols', []);
    }

    /**
     * Get default currency code
     */
    public static function getDefault(): string
    {
        return config('currency.default', 'USD');
    }
}
