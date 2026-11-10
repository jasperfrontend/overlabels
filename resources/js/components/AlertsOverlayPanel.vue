<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { CircleCheck, CircleDashed } from '@lucide/vue';
import AddToObsButton from '@/components/AddToObsButton.vue';

// The account's Alerts overlay: the one static overlay every untargeted alert
// renders in once OBS has loaded it (App\Services\Alerts\AlertsOverlay). Shown
// wherever someone deals with alerts: a kit with alerts, an alert's own pages,
// and creating an alert. The page must send the state as the `alertsOverlay`
// prop, which is what the reload after the OBS dialog asks for.
export interface AlertsOverlayState {
  overlay: { id: number; name: string; slug: string; type: string } | null;
  live: boolean;
}

const props = withDefaults(
  defineProps<{
    state: AlertsOverlayState;
    // The page props that carry `state`, reloaded after the OBS dialog closes.
    reload?: string[];
  }>(),
  { reload: () => ['alertsOverlay'] },
);

const creating = ref(false);
function create() {
  creating.value = true;
  // preserveState: on the create page this sits under a half-written alert.
  router.post(route('alerts-overlay.store'), {}, { preserveScroll: true, preserveState: true, onFinish: () => (creating.value = false) });
}

// After the OBS dialog closes, look again: the first load is what moves alerts in.
function refresh() {
  router.reload({ only: props.reload });
}
</script>

<template>
  <div class="rounded-lg border border-sidebar bg-card p-4">
    <h3 class="mb-2 text-sm font-semibold">Alerts overlay</h3>
    <div v-if="!state.overlay" class="flex flex-wrap items-center gap-4">
      <p class="flex-1 text-sm text-foreground">
        Alerts show up inside one overlay in OBS, your Alerts overlay. Create it once, add it to OBS, and every alert you assign appears there.
      </p>
      <button type="button" class="btn btn-primary btn-sm cursor-pointer" :disabled="creating" @click="create">Create my Alerts overlay</button>
    </div>
    <div v-else class="flex flex-wrap items-center gap-x-2 gap-y-2 text-sm">
      <CircleCheck v-if="state.live" class="h-4 w-4 shrink-0 text-green-500" />
      <CircleDashed v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
      <span v-if="state.live" class="flex-1 text-foreground">In OBS. Your alerts show here.</span>
      <span v-else class="flex-1 text-foreground">
        Not in OBS yet. Add it as a browser source on top of your scene. Until then, your alerts keep showing in all your overlays.
      </span>
      <AddToObsButton :template="state.overlay" :variant="state.live ? 'again' : 'compact'" @done="refresh" />
    </div>
  </div>
</template>
