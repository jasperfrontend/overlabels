import { ref, onUnmounted } from 'vue';

interface PositionUpdate {
  lat: number;
  lng: number;
  speed: number;
  bearing: number;
}

export function useMapWebSocket(twitchId: string) {
  const position = ref<PositionUpdate | null>(null);
  const connected = ref(false);

  const echo = (window as any).Echo;
  let channel: any = null;

  // Buffer lat/lng updates that arrive as separate events
  let pendingLat: number | null = null;
  let pendingLng: number | null = null;
  let pendingSpeed: number | null = null;
  let pendingBearing: number | null = null;
  let flushTimer: ReturnType<typeof setTimeout> | null = null;

  function flush() {
    if (pendingLat !== null && pendingLng !== null) {
      position.value = {
        lat: pendingLat,
        lng: pendingLng,
        speed: pendingSpeed ?? 0,
        bearing: pendingBearing ?? 0,
      };
    }
    pendingLat = null;
    pendingLng = null;
    pendingSpeed = null;
    pendingBearing = null;
    flushTimer = null;
  }

  function scheduleFlush() {
    if (!flushTimer) {
      flushTimer = setTimeout(flush, 300);
    }
  }

  if (echo) {
    channel = echo.channel(`alerts.${twitchId}`);

    channel.listen('.control.updated', (event: any) => {
      connected.value = true;

      const key = event.key as string;
      if (!key?.startsWith('overlabels-mobile:')) return;

      const controlKey = key.replace('overlabels-mobile:', '');

      switch (controlKey) {
        case 'gps_lat':
          pendingLat = parseFloat(event.value);
          scheduleFlush();
          break;
        case 'gps_lng':
          pendingLng = parseFloat(event.value);
          scheduleFlush();
          break;
        case 'gps_speed':
          pendingSpeed = parseFloat(event.value);
          break;
        case 'gps_bearing':
          pendingBearing = parseFloat(event.value);
          break;
      }
    });
  }

  onUnmounted(() => {
    if (flushTimer) clearTimeout(flushTimer);
    if (channel) {
      echo?.leave(`alerts.${twitchId}`);
    }
  });

  return { position, connected };
}
