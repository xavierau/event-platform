import { usePage } from '@inertiajs/vue3';

export function useCurrency() {
    const page = usePage();

    const currencySymbols = (page.props.currencySymbols as Record<string, string>) || {};
    const defaultCurrency = (page.props.default_currency as string) || 'USD';

    const getSymbol = (currencyCode: string | null): string => {
        const code = currencyCode || defaultCurrency;
        return currencySymbols[code.toUpperCase()] || code;
    };

    const formatPrice = (priceInCents: number, currencyCode: string | null): string => {
        const symbol = getSymbol(currencyCode);
        const price = priceInCents / 100;
        return `${symbol}${price.toFixed(2)}`;
    };

    const formatPriceRange = (minPriceInCents: number, maxPriceInCents: number, currencyCode: string | null): string => {
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
