import Pusher from 'pusher-js';
import { ref, onMounted, onUnmounted } from 'vue';

const hasNewVersion = ref(false);
let pusher: Pusher | null = null;
let activeInstances = 0;

function subscribe() {
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  const host = import.meta.env.VITE_REVERB_HOST;

  if (!key || !host) return;

  pusher = new Pusher(key, {
    wsHost: host,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    cluster: '',
  });
  const channel = pusher.subscribe('app-updates');

  channel.bind('version.updated', () => {
    hasNewVersion.value = true;
  });
}

function unsubscribe() {
  if (pusher) {
    pusher.unsubscribe('app-updates');
    pusher.disconnect();
    pusher = null;
  }
}

export function useVersionCheck() {
  onMounted(() => {
    activeInstances++;
    if (activeInstances === 1) {
      subscribe();
    }
  });

  onUnmounted(() => {
    activeInstances--;
    if (activeInstances === 0) {
      unsubscribe();
    }
  });

  const refresh = () => window.location.reload();

  return { hasNewVersion, refresh };
}
