import Pusher from 'pusher-js';
import { ref, onMounted, onUnmounted } from 'vue';

const hasNewVersion = ref(false);
let pusher: Pusher | null = null;
let activeInstances = 0;

function subscribe() {
  const key = import.meta.env.VITE_PUSHER_APP_KEY;
  const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER;

  if (!key || !cluster) return;

  pusher = new Pusher(key, { cluster });
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
