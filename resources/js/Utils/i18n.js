import { ref } from 'vue';

export const currentLocale = ref(document.documentElement.lang || 'en');

export function getTranslation(translatable, locale, fallbackLocale = 'en') {
    if (!translatable) return '';
    if (typeof translatable === 'string') return translatable; // Already a string (not translatable or single locale)

    if (typeof translatable === 'object' && translatable !== null) {
        if (translatable[locale]) {
            return translatable[locale];
        }
        if (translatable[fallbackLocale]) {
            return translatable[fallbackLocale];
        }
        // If specific locales not found, return the first available translation or an empty string
        const firstKey = Object.keys(translatable)[0];
        return firstKey ? translatable[firstKey] : '';
    }
    return ''; // Fallback for unexpected types
}

// Function to set the current locale (e.g., if user changes language)
export function setCurrentLocale(locale) {
    currentLocale.value = locale;
    document.documentElement.lang = locale;
    // You might want to store this preference (e.g., in localStorage) and inform backend
}
