// src/i18n.js
import { createI18n } from 'vue-i18n';

// Import your language files
import en from '../../lang/en.json';
import zhCN from '../../lang/zh-CN.json';
import zhHK from '../../lang/zh-HK.json';

const messages = {
    en,
    'zh-HK': zhHK,
    'zh-CN': zhCN,
};

const i18n = createI18n({
    locale: 'en', // set locale
    fallbackLocale: 'en', // set fallback locale
    messages, // set locale messages
});

export default i18n;
