// src/i18n.js
import { createI18n } from 'vue-i18n';

// Import your language files
import en from '../../lang/en.json';
import zhCN from '../../lang/zh-CN.json';
import zhTW from '../../lang/zh-TW.json';

const messages = {
    en,
    'zh-TW': zhTW,
    'zh-CN': zhCN,
};

const i18n = createI18n({
    locale: 'en', // set locale
    fallbackLocale: {
        'zh-TW': ['en'],
        'zh-CN': ['en'],
        'default': ['en']
    },
    silentFallbackWarn: true,
    silentTranslationWarn: true,
    messages, // set locale messages
});

export default i18n;
