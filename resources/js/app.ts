import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Global axios defaults: send the session cookie and mark requests as XHR so
// Laravel treats them as stateful. (Previously set ad-hoc inside a component.)
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// When the session dies mid-visit (expired, cookies cleared by a cleanup tool,
// etc.), the next request comes back 401 (unauthenticated) or 419 (stale CSRF
// token). Without this, those reject into each caller's catch block as bare
// console errors and the UI just sits there. Bounce to login instead, keeping
// the current URL so the user lands back where they were after re-auth. This
// covers every direct axios call AND Inertia's own requests (it uses this same
// axios instance); Inertia auth redirects come back as 409 + X-Inertia-Location
// and are handled natively, so they never reach this branch.
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const onLoginPage = window.location.pathname.startsWith('/login');
        if ((status === 401 || status === 419) && !onLoginPage) {
            window.location.href = '/login?redirect_to=' + encodeURIComponent(window.location.href);
            // We're navigating away - swallow the rejection so callers don't
            // also flash their own error UI on the way out.
            return new Promise(() => {});
        }
        return Promise.reject(error);
    },
);

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

// Set up Echo for dashboard WebSocket events (Reverb uses the Pusher protocol)
window.Pusher = Pusher;
try {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} catch (err) {
    console.error('Failed to initialize Echo:', err);
}
