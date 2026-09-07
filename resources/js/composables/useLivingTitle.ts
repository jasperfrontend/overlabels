import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export interface LivingTitleState {
  enabled: boolean;
  paused: boolean;
  paused_title: string | null;
  last_written?: string | null;
  written_at?: number | null;
  last_error?: string | null;
}

// One state for the whole app, not one per caller. The header and the
// settings page both read it, and a broadcast must flip both at once.
const wsState = ref<LivingTitleState | null>(null);
let listening = false;

/**
 * The living title's state as every app page knows it: the shared Inertia
 * prop on first paint, then whatever `living-title.updated` last said.
 *
 * The listener rides the per-user `alerts.{twitch_id}` channel that
 * useStreamState already opens and is registered once for the app's life.
 * It never calls Echo.leave(): that unsubscribes the whole channel, and the
 * stream-status listener is on it too.
 *
 * A fresh Inertia page load re-shares the prop, so the server's answer wins
 * again whenever a navigation delivers one - a missed broadcast can go
 * stale for at most one page view.
 */
export function useLivingTitle() {
  const page = usePage<AppPageProps>();

  const serverState = computed(() => page.props.livingTitle ?? null);

  watch(serverState, () => {
    wsState.value = null;
  });

  const state = computed<LivingTitleState | null>(() => wsState.value ?? serverState.value);

  const enabled = computed(() => state.value?.enabled ?? false);
  const paused = computed(() => state.value?.paused ?? false);
  const pausedTitle = computed(() => state.value?.paused_title ?? null);
  const lastError = computed(() => state.value?.last_error);
  const lastWritten = computed(() => state.value?.last_written);

  if (!listening) {
    const twitchId = (page.props.auth as { user?: { twitch_id?: string | null } } | undefined)?.user?.twitch_id;
    if (twitchId && typeof window !== 'undefined' && window.Echo) {
      listening = true;
      window.Echo.private(`alerts.${twitchId}`).listen('.living-title.updated', (data: LivingTitleState) => {
        wsState.value = data;
      });
    }
  }

  return { state, enabled, paused, pausedTitle, lastError, lastWritten };
}
