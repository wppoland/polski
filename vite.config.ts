import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        outDir: 'build',
        manifest: true,
        rollupOptions: {
            input: {
                admin: resolve(__dirname, 'resources/js/admin/index.tsx'),
                'frontend-checkout': resolve(__dirname, 'resources/js/frontend/checkout.ts'),
                'frontend-omnibus': resolve(__dirname, 'resources/js/frontend/omnibus-badge.ts'),
                'frontend-price-toggle': resolve(__dirname, 'resources/js/frontend/price-toggle.ts'),
                'frontend-consent': resolve(__dirname, 'resources/js/frontend/consent.ts'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: '[name][extname]',
            },
            external: [
                'react',
                'react-dom',
                'wp',
                '@wordpress/element',
                '@wordpress/components',
                '@wordpress/data',
                '@wordpress/i18n',
                '@wordpress/api-fetch',
            ],
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
});
