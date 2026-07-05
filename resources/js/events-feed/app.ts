import '../../css/app.css';

import { createApp } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import EventsFeed from './EventsFeed.vue';
import { initializeTheme } from '@/composables/useAppearance';

// Echo/Reverb for live feed updates (Reverb uses the Pusher protocol)
(window as any).Pusher = Pusher;

// The feed page is session-less like an overlay, so the default
// `/broadcasting/auth` (which requires a logged-in session) can't sign its
// subscriptions. POST the URL-fragment token to the overlay auth endpoint
// instead; it only ever signs the token owner's own private channels.
function feedAuthorizer(channel: { name: string }) {
  return {
    authorize: (socketId: string, callback: (err: Error | null, data: unknown) => void) => {
      const feed = (window as any).__EVENTS_FEED__ || {};
      if (!feed.token) {
        callback(new Error('Feed token unavailable for channel auth'), null);
        return;
      }
      fetch('/api/overlay/broadcasting/auth', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          slug: 'events-feed',
          token: feed.token,
          socket_id: socketId,
          channel_name: channel.name,
        }),
      })
        .then((res) => (res.ok ? res.json() : Promise.reject(res)))
        .then((data) => callback(null, data))
        .catch((err) => callback(err, null));
    },
  };
}

try {
  (window as any).Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    //@ts-ignore
    authorizer: feedAuthorizer,
  });
} catch (err) {
  console.error('Failed to initialize Echo:', err);
  (window as any).Echo = null;
}

document.addEventListener('DOMContentLoaded', () => {
  initializeTheme();

  const mount = document.getElementById('events-feed-root');
  const feed = (window as any).__EVENTS_FEED__;
  if (!mount || !feed) return;

  createApp(EventsFeed, { token: feed.token }).mount(mount);
});
