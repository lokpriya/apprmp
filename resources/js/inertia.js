import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { registerSW } from 'virtual:pwa-register';

// Register service worker — silently updates in background, never blocks navigation
registerSW({ immediate: false });
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import AppLayout from '@/layouts/AppLayout.vue';

createInertiaApp({
    title: (title) => title ? `${title} — ${import.meta.env.VITE_APP_NAME ?? 'APPRMP'}` : (import.meta.env.VITE_APP_NAME ?? 'APPRMP'),

    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue'),
        );
        page.default.layout ??= AppLayout;
        return page;
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },

    progress: {
        color: '#6366f1',
    },
});
