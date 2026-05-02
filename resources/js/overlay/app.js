import { createApp } from 'vue'
import { createPinia } from 'pinia'
import OverlayRenderer from '../components/OverlayRenderer.vue'
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const pinia = createPinia();

// Set up Echo for overlay (Reverb uses the Pusher protocol under the hood)
window.Pusher = Pusher;

// Custom authorizer: overlays are session-less, so we can't hit the default
// `/broadcasting/auth` (which requires a logged-in session). Instead we POST
// the URL-fragment token to our overlay-specific endpoint. The server only
// signs auth responses for `private-alerts.<owner_twitch_id>` and
// `private-twitch-events.<owner_twitch_id>` belonging to that token.
function overlayAuthorizer(channel) {
  return {
    authorize: (socketId, callback) => {
      const overlay = window.__OVERLAY__ || {};
      if (!overlay.slug || !overlay.token) {
        callback(new Error('Overlay token unavailable for channel auth'), null);
        return;
      }
      fetch('/api/overlay/broadcasting/auth', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          slug: overlay.slug,
          token: overlay.token,
          socket_id: socketId,
          channel_name: channel.name,
        }),
      })
        .then((res) => {
          if (!res.ok) return Promise.reject(res);
          return res.json();
        })
        .then((data) => callback(null, data))
        .catch((err) => callback(err, null));
    },
  };
}

try {
  window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: overlayAuthorizer,
  });
} catch (err) {
  console.error('Failed to initialize Echo:', err);
  window.Echo = null;
}

// Mount the Vue app once the DOM is ready and window.__OVERLAY__ is available
document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('overlay-content');
    if (!mount || !window.__OVERLAY__) return;

    const { slug, token } = window.__OVERLAY__;

    createApp(OverlayRenderer, { slug, token }).use(pinia).mount(mount);
});
