// Import ref from Vue
import { ref } from 'vue';

/**
 * Utility functions for internationalization
 */

/**
 * Get the current locale from the application
 */
export const currentLocale = ref('en'); // Default to 'en', should be set from Laravel

/**
 * Get translation from a translatable object
 * @param translatable - Object with locale keys (e.g., {en: 'English', zh-TW: '中文'})
 * @param fallbackLocale - Fallback locale if current locale is not available
 * @returns Translated string
 */
export function getTranslation(translatable: Record<string, string> | string | null | undefined, fallbackLocale: string = 'en'): string {
    // If it's already a string, return it
    if (typeof translatable === 'string') {
        return translatable;
    }

    // If it's null or undefined, return empty string
    if (!translatable) {
        return '';
    }

    // If it's an object, try to get the translation
    const locale = currentLocale.value;

    // Try current locale first
    if (translatable[locale]) {
        return translatable[locale];
    }

    // Try fallback locale
    if (translatable[fallbackLocale]) {
        return translatable[fallbackLocale];
    }

    // Try the first available translation
    const firstKey = Object.keys(translatable)[0];
    if (firstKey && translatable[firstKey]) {
        return translatable[firstKey];
    }

    // Return empty string if nothing found
    return '';
}

/**
 * Set the current locale
 * @param locale - The locale to set
 */
export function setCurrentLocale(locale: string) {
    currentLocale.value = locale;
}
