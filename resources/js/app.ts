import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: typeof Pusher;
    Echo: Echo<any> | null;
  }
}

window.Pusher = Pusher;

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  forceTLS: true,
  encrypted: true,
});

const pinia = createPinia()
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} &bull; ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .mount(el);
    },
    progress: {
        color: '#1ac7b6',
    },
});

// This will set light / dark mode on a page load...
initializeTheme();
