import { createApp } from 'vue'
import { createPinia } from 'pinia'
import OverlayRenderer from '../components/OverlayRenderer.vue'
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const pinia = createPinia();

// Set up Echo for overlay
window.Pusher = Pusher;

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  forceTLS: true,
  encrypted: true,
});

console.log('Echo configured for overlay with key:', import.meta.env.VITE_PUSHER_APP_KEY);

// Mount the Vue app once the DOM is ready and window.__OVERLAY__ is available
document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('overlay-content');
    if (!mount || !window.__OVERLAY__) return;

    const { slug, token } = window.__OVERLAY__;

    createApp(OverlayRenderer, { slug, token }).use(pinia).mount(mount);
});
