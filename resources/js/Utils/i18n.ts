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
 * @param _locale - The locale to set (unused)
 * @deprecated Use the LocaleSwitcher component instead
 */
export function setCurrentLocale(_locale: string) {
    console.warn('setCurrentLocale is deprecated. Use the LocaleSwitcher component instead.');
    // This function is now deprecated since locale is managed by Laravel session
}
