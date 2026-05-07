import { ref, onUnmounted } from 'vue';

interface PositionUpdate {
  lat: number;
  lng: number;
  speed: number;
  bearing: number;
}

/**
 * Subscribes to the streamer's public `map.{slug}` channel and exposes
 * the latest position. The slug is the same Sqids-encoded Twitch ID that
 * appears in the page URL, so the numeric Twitch ID never leaks via the
 * WebSocket frames either. The channel is intentionally public-by-design:
 * it's only fed when the streamer has opted into map sharing, and it
 * carries only the GPS-shaped fields the public map page needs (lat/lng/
 * speed/bearing/tracking). All other control updates - donations, alerts,
 * donor names, stream status - stay on the private alerts channel.
 */
export function useMapWebSocket(slug: string) {
  const position = ref<PositionUpdate | null>(null);
  const connected = ref(false);
  // null = unknown, true = session active, false = session ended.
  // Fed by `tracking` map.position payloads ('1'/'0').
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
    channel = echo.channel(`map.${slug}`);

    channel.listen('.map.position', (event: any) => {
      connected.value = true;

      const key = event.key as string;

      switch (key) {
        case 'lat':
          pendingLat = parseFloat(event.value);
          scheduleFlush();
          break;
        case 'lng':
          pendingLng = parseFloat(event.value);
          scheduleFlush();
          break;
        case 'speed':
          pendingSpeed = parseFloat(event.value);
          break;
        case 'bearing':
          pendingBearing = parseFloat(event.value);
          break;
        case 'tracking':
          trackingActive.value = event.value === '1' || event.value === 1 || event.value === true;
          break;
      }
    });
  }

  onUnmounted(() => {
    if (flushTimer) clearTimeout(flushTimer);
    if (channel) {
      echo?.leave(`map.${slug}`);
    }
  });

  return { position, connected, trackingActive };
}
