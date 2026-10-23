import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/guest.css',
                'resources/js/app.js',
                'resources/js/inertia.js',
            ],
            refresh: true,
        }),
        VitePWA({
            strategies: 'generateSW',
            registerType: 'autoUpdate',

            // Output SW to public/ root so it has scope '/' and triggers
            // the "Add to Home Screen" install prompt
            outDir: 'public',
            base: '/',
            filename: 'sw.js',
            manifestFilename: 'manifest.webmanifest',

            workbox: {
                // Cache built static assets; do NOT intercept page navigations
                globPatterns: ['build/**/*.{js,css,woff2,woff,png,svg,ico}'],
                navigateFallback: null,
                runtimeCaching: [],
                swDest: 'public/sw.js',
            },

            manifest: {
                name: 'स्थानीय तह वस्तुगत विवरण प्रणाली',
                short_name: 'LGPAS',
                description: 'स्थानीय तह वस्तुगत विवरण तथा विश्लेषण प्रणाली',
                theme_color: '#182433',
                background_color: '#f4f6fb',
                display: 'standalone',
                orientation: 'portrait-primary',
                lang: 'ne',
                scope: '/',
                start_url: '/public',
                // Long-press on the installed icon: straight to the field lookup.
                shortcuts: [
                    {
                        name: 'सदस्य खोज',
                        short_name: 'सदस्य खोज',
                        url: '/member-search',
                        icons: [{ src: '/pwa-192.png', sizes: '192x192', type: 'image/png' }],
                    },
                ],
                icons: [
                    {
                        src: '/pwa-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any maskable',
                    },
                    {
                        src: '/pwa-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable',
                    },
                ],
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
