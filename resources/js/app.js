import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, Fragment, h } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import NavigationLoader from '@/components/ui/NavigationLoader.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Vistoria';

createInertiaApp({
    title: (title) => (title ? `${title} | ${appName}` : appName),
    progress: false,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(Fragment, [
                h(App, props),
                h(NavigationLoader),
            ]),
        })
            .use(plugin)
            .mount(el);
    },
});
