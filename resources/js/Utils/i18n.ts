// Import ref from Vue
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Utility functions for internationalization
 */

/**
 * Get the current locale from the application
 */
export const currentLocale = computed(() => {
    try {
        const page = usePage();
        return (page.props.locale as string) || 'en';
    } catch {
        return 'en';
    }
});

/**
 * Get translation from a translatable object
 * @param translatable - Object with locale keys (e.g., {en: 'English', zh-TW: '中文'})
 * @param fallbackLocale - Fallback locale if current locale is not available
 * @returns Translated string
 */
export function getTranslation(translatable: Record<string, string> | string | null | undefined, fallbackLocale: string = 'en'): string {
    let translationsObject: Record<string, string> | null = null;

    if (typeof translatable === 'string') {
        try {
            // Attempt to parse if it's a JSON string representing an object
            const parsed = JSON.parse(translatable);
            if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
                translationsObject = parsed as Record<string, string>;
            } else {
                // It's a genuine string, not a JSON string of an object of translations
                return translatable;
            }
        } catch {
            // Not a valid JSON string, or not the expected object structure, treat as a regular string
            return translatable;
        }
    } else if (typeof translatable === 'object' && translatable !== null) {
        // Ensure it's not an array, expecting a Record<string, string>
        if (!Array.isArray(translatable)) {
            translationsObject = translatable as Record<string, string>;
        } else {
            // If it's an array, it's not the expected format, return empty or stringify
            return ''; // Or JSON.stringify(translatable) if that's desired for arrays
        }
    }

    // If it's null, undefined, or couldn't be formed into translationsObject, return empty string
    if (!translationsObject) {
        return '';
    }

    // If it's an object, try to get the translation
    const locale = currentLocale.value;

    // Try current locale first
    if (translationsObject[locale]) {
        return translationsObject[locale];
    }

    // Try fallback locale
    if (translationsObject[fallbackLocale]) {
        return translationsObject[fallbackLocale];
    }

    // Try the first available translation
    const firstKey = Object.keys(translationsObject)[0];
    if (firstKey && translationsObject[firstKey]) {
        return translationsObject[firstKey];
    }

    // Return empty string if nothing found
    return '';
}

/**
 * Set the current locale
 * @param _locale - The locale to set (unused)
 * @deprecated Use the LocaleSwitcher component instead
 */
export function setCurrentLocale(_locale: string) {
    console.warn('setCurrentLocale is deprecated. Use the LocaleSwitcher component instead.');
    // This function is now deprecated since locale is managed by Laravel session
}
