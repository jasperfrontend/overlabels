import { createApp } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import LiveMap from './LiveMap.vue';
import SessionMap from './SessionMap.vue';
import 'leaflet/dist/leaflet.css';

// Echo/Reverb for live map WebSocket
(window as any).Pusher = Pusher;

try {
  (window as any).Echo = new Echo({
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
  (window as any).Echo = null;
}

document.addEventListener('DOMContentLoaded', () => {
  const mount = document.getElementById('map-root');
  const config = (window as any).__MAP__;
  if (!mount || !config) return;

  if (config.type === 'live') {
    createApp(LiveMap, {
      twitchId: config.twitchId,
      streamerName: config.streamerName,
      delay: config.delay,
      speedUnit: config.speedUnit,
    }).mount(mount);
  } else if (config.type === 'session') {
    createApp(SessionMap, {
      twitchId: config.twitchId,
      sessionId: config.sessionId,
      streamerName: config.streamerName,
      speedUnit: config.speedUnit,
    }).mount(mount);
  }
});
