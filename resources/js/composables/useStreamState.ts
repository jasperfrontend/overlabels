import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { AppPageProps } from '@/types';

export function useStreamState() {
  const page = usePage<AppPageProps>();

  // Local reactive overrides (set by WebSocket events)
  const wsState = ref<string | null>(null);
  const wsConfidence = ref<number | null>(null);
  const wsStartedAt = ref<string | null | undefined>(undefined);

  const serverState = computed(() => page.props.streamState);

  const state = computed(() => wsState.value ?? serverState.value?.state ?? 'offline');
  const confidence = computed(() => wsConfidence.value ?? serverState.value?.confidence ?? 0);
  const startedAt = computed(() =>
    wsStartedAt.value !== undefined ? wsStartedAt.value : (serverState.value?.startedAt ?? null)
  );

  const isLive = computed(() => state.value === 'live' && confidence.value >= 0.75);
  const isTransitioning = computed(() => state.value === 'starting' || state.value === 'ending');

  const uptime = ref('');
  let uptimeInterval: ReturnType<typeof setInterval> | null = null;
  let echoChannel: any = null;

  function updateUptime() {
    if (!startedAt.value) {
      uptime.value = '';
      return;
    }

    const diff = Math.floor((Date.now() - new Date(startedAt.value).getTime()) / 1000);
    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const s = diff % 60;
    uptime.value = `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  }

  onMounted(() => {
    updateUptime();
    uptimeInterval = setInterval(updateUptime, 1000);

    // Listen for real-time stream status changes via WebSocket
    const twitchId = (page.props.auth as any)?.user?.twitch_id;
    if (twitchId && window.Echo) {
      echoChannel = window.Echo.channel(`alerts.${twitchId}`);
      echoChannel.listen('.stream.status', (data: { state: string; confidence: number; startedAt: string | null }) => {
        wsState.value = data.state;
        wsConfidence.value = data.confidence;
        wsStartedAt.value = data.startedAt;
      });
    }
  });

  onUnmounted(() => {
    if (uptimeInterval) clearInterval(uptimeInterval);
    if (echoChannel) {
      echoChannel.stopListening('.stream.status');
    }
  });

  return { state, confidence, startedAt, isLive, isTransitioning, uptime };
}
