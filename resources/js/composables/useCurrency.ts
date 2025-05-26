import { usePage } from '@inertiajs/vue3';

export function useCurrency() {
    const page = usePage();

    const currencySymbols = (page.props.currencySymbols as Record<string, string>) || {};

    const getSymbol = (currencyCode: string): string => {
        return currencySymbols[currencyCode.toUpperCase()] || currencyCode;
    };

    const formatPrice = (priceInCents: number, currencyCode: string): string => {
        const symbol = getSymbol(currencyCode);
        const price = priceInCents / 100;
        return `${symbol}${price.toFixed(2)}`;
    };

    const formatPriceRange = (minPriceInCents: number, maxPriceInCents: number, currencyCode: string): string => {
        const symbol = getSymbol(currencyCode);
        const minPrice = minPriceInCents / 100;
        const maxPrice = maxPriceInCents / 100;

        if (minPrice === maxPrice) {
            return `${symbol}${minPrice.toFixed(2)}`;
        }

        return `${symbol}${minPrice.toFixed(2)}-${maxPrice.toFixed(2)}`;
    };

    return {
        currencySymbols,
        getSymbol,
        formatPrice,
        formatPriceRange,
    };
}
