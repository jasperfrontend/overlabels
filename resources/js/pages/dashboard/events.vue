<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import EventsTable from '@/components/EventsTable.vue';
import RekaToast from '@/components/RekaToast.vue';
import EmptyState from '@/components/EmptyState.vue';
import { RefreshCw } from 'lucide-vue-next';
import type { AppPageProps } from '@/types';

interface UnifiedEvent {
  id: number;
  source: string;
  event_type: string;
  created_at: string;
  event_data?: Record<string, unknown> | null;
  normalized_payload?: Record<string, unknown> | null;
}

defineProps<{
  events: UnifiedEvent[];
}>();

const page = usePage<AppPageProps>();
const toastMessage = ref<string | null>(null);
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showInfo = ref(false);

watch(
  () => page.props.flash?.message,
  (newMessage) => {
    if (newMessage) {
      toastMessage.value = newMessage;
      toastType.value = (page.props.flash?.type as typeof toastType.value) || 'info';
    }
  },
  { immediate: true }
);

const refreshing = ref(false);

function refresh() {
  if (refreshing.value) return;
  refreshing.value = true;
  router.reload({
    only: ['events'],
    onFinish: () => {
      setTimeout(() => {
        refreshing.value = false;
      }, 600);
    }
  });
}
</script>

<template>
  <Head>
    <title>Stream Events</title>
    <meta name="description" content="Recent stream events - Overlabels" />
  </Head>

  <div class="mx-auto max-w-3xl px-2 py-2">
    <div class="mb-2 flex items-center gap-2">
      <button class="btn btn-chill btn-xs gap-1.5" :disabled="refreshing" @click="refresh">
        <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': refreshing }" />
        {{ refreshing ? 'Working' : 'Refresh' }}
      </button>

      <button
        class="ml-auto grid h-7 w-7 cursor-pointer place-items-center rounded-full border border-violet-400/40 text-violet-400 transition hover:bg-violet-400/10"
        type="button"
        aria-label="Show info"
        @click="showInfo = true"
      >
        ?
      </button>
    </div>

    <div class="transition-opacity duration-300" :class="refreshing ? 'opacity-40' : 'opacity-100'">
      <EventsTable v-if="events.length > 0" :events="events" />

      <EmptyState v-else
                  message="No events yet. Events will appear here once your Twitch EventSub subscriptions are active." />
    </div>
  </div>

  <div
    v-if="showInfo"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
    @click.self="showInfo = false"
  >
    <div class="w-full max-w-md rounded-xl bg-base-100 p-5 shadow-xl bg-background">
      <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium leading-6">
          Your recent events. click an event and tap Yes to replay the event in your overlay(s)
        </p>

        <button class="text-lg leading-none text-base-content/60 hover:text-base-content" type="button"
                @click="showInfo = false">
          ×
        </button>
      </div>
    </div>
  </div>

  <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
</template>
