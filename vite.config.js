import { defineConfig } from 'vite';

import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
                compilerOptions: {
                    isCustomElement: (tag) => [
                        'content', 'hint',
                        'name', 'clock', 'date', 'issues', 'info', 'error',
                        'content-title', 'content-list', 'article-pre', 'article-payload', 'payload-title', 'vehicle', 'payload-description', 'payload-meta', 'payload-timing'].includes(tag),
                },
            },
        }),
    ],
});
