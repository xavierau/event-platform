// src/i18n.js
import { createI18n } from 'vue-i18n';

// Import your language files
import en from './locales/en.json';
import fr from './locales/fr.json';

const messages = {
    en,
    fr,
};

const i18n = createI18n({
    locale: 'en', // set locale
    fallbackLocale: 'en', // set fallback locale
    messages, // set locale messages
});

export default i18n;
