import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h } from 'vue';
import { createI18n } from 'vue-i18n';
import { ZiggyVue } from 'ziggy-js';
import ChatbotWidget from './components/chatbot/ChatbotWidget.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) =>
            resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')) as any,
        setup({ App, props, plugin }) {
            const i18n = createI18n({
                locale: props.initialPage.props.locale as string,
                fallbackLocale: 'en',
                messages: props.initialPage.props.translations as Record<string, any>,
                legacy: false,
            });

            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue, {
                    ...props.initialPage.props.ziggy,
                    location: new URL(props.initialPage.props.ziggy.location),
                })
                .use(i18n)
                .component('ChatbotWidget', ChatbotWidget);
        },
    }),
);
