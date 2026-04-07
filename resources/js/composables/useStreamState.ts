import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { AppPageProps } from '@/types';

export function useStreamState() {
  const page = usePage<AppPageProps>();

  const streamState = computed(() => page.props.streamState);
  const state = computed(() => streamState.value?.state ?? 'offline');
  const confidence = computed(() => streamState.value?.confidence ?? 0);
  const startedAt = computed(() => streamState.value?.startedAt ?? null);

  const isLive = computed(() => state.value === 'live' && confidence.value >= 0.75);
  const isTransitioning = computed(() => state.value === 'starting' || state.value === 'ending');

  const uptime = ref('');
  let uptimeInterval: ReturnType<typeof setInterval> | null = null;

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
  });

  onUnmounted(() => {
    if (uptimeInterval) clearInterval(uptimeInterval);
  });

  return { state, confidence, startedAt, isLive, isTransitioning, uptime };
}
