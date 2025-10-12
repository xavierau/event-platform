import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    ssr: {
        target: 'node',
        noExternal: ['@inertiajs/vue3'],
    },
    build: {
        target: 'esnext',
        rollupOptions: {
            output: {
                // SSR bundle should use ES2019 (supports Node 12+)
                format: 'esm',
            },
        },
    },
    // Configure esbuild to transpile SSR bundle for older Node
    esbuild: {
        target: 'es2019', // ES2019 = Node 12+ (no nullish coalescing)
    },
    server: {
        hmr: {
            host: 'EventPlatform.test',
        },
    },
});
