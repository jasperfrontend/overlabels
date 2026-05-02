import { ref, onUnmounted } from 'vue';

interface PositionUpdate {
  lat: number;
  lng: number;
  speed: number;
  bearing: number;
}

/**
 * Subscribes to the streamer's public `map.{twitchId}` channel and exposes
 * the latest position. The channel is intentionally public-by-design: it's
 * only fed when the streamer has opted into map sharing, and it carries only
 * the GPS-shaped fields the public map page needs (lat/lng/speed/bearing/
 * tracking). All other control updates - donations, alerts, donor names,
 * stream status - stay on the private alerts channel.
 */
export function useMapWebSocket(twitchId: string) {
  const position = ref<PositionUpdate | null>(null);
  const connected = ref(false);
  // null = unknown, true = session active, false = session ended.
  // Fed by `gps_tracking` map.position payloads ('1'/'0').
  const trackingActive = ref<boolean | null>(null);

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
    channel = echo.channel(`map.${twitchId}`);

    channel.listen('.map.position', (event: any) => {
      connected.value = true;

      const key = event.key as string;

      switch (key) {
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
        case 'gps_tracking':
          trackingActive.value = event.value === '1' || event.value === 1 || event.value === true;
          break;
      }
    });
  }

  onUnmounted(() => {
    if (flushTimer) clearTimeout(flushTimer);
    if (channel) {
      echo?.leave(`map.${twitchId}`);
    }
  });

  return { position, connected, trackingActive };
}
