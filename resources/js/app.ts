import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h } from 'vue';
import { createI18n } from 'vue-i18n';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import ChatbotWidget from './components/chatbot/ChatbotWidget.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')) as any,
    async setup({ el, App, props, plugin }) {
        const locale = props.initialPage.props.locale as string;

        // Load translations from API endpoint to reduce SSR payload
        const translationsResponse = await fetch('/api/translations');
        const translationsData = await translationsResponse.json();

        const i18n = createI18n({
            locale,
            fallbackLocale: 'en',
            messages: {
                [locale]: translationsData.translations || {}
            },
            legacy: false,
        });

        createSSRApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, {
                ...props.initialPage.props.ziggy,
                location: new URL(props.initialPage.props.ziggy.location),
            })
            .use(i18n)
            .component('ChatbotWidget', ChatbotWidget)
            .mount(el);

        // Initialize theme AFTER mount to avoid hydration mismatch
        initializeTheme();
    },
    progress: {
        color: '#4B5563',
    },
});
