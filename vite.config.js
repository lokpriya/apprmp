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
                'resources/js/app.js',
                'resources/js/inertia.js',
            ],
            refresh: true,
        }),
        VitePWA({
            // Use generateSW strategy — zero custom service worker code needed
            strategies: 'generateSW',
            registerType: 'autoUpdate',

            // Place generated SW + manifest inside public/build so Laravel's
            // asset pipeline is not affected
            outDir: 'public/build',
            base: '/build/',

            // Only cache static build assets — do NOT cache Inertia page requests
            // (those must stay server-rendered to keep auth/data fresh)
            workbox: {
                globPatterns: ['**/*.{js,css,woff2,woff,png,svg,ico}'],
                navigateFallback: null,           // never intercept page navigations
                runtimeCaching: [],               // no runtime caching of API/pages
            },

            manifest: {
                name: 'स्थानीय तह सर्वेक्षण',
                short_name: 'सर्वेक्षण',
                description: 'स्थानीय तह वस्तुगत विवरण तथा विश्लेषण प्रणाली',
                theme_color: '#4f46e5',
                background_color: '#f5f5f5',
                display: 'standalone',
                orientation: 'portrait-primary',
                scope: '/',
                start_url: '/dashboard',
                icons: [
                    {
                        src: '/pwa-192.png',
                        sizes: '192x192',
                        type: 'image/png',
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
