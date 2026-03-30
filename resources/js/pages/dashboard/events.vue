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

watch(
  () => page.props.flash?.message,
  (newMessage) => {
    if (newMessage) {
      toastMessage.value = newMessage;
      toastType.value = (page.props.flash?.type as typeof toastType.value) || 'info';
    }
  },
  { immediate: true },
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
    },
  });
}
</script>

<template>
  <Head>
    <title>Stream Events</title>
    <meta name="description" content="Recent stream events - Overlabels" />
  </Head>

  <div class="mx-auto max-w-3xl px-2 py-2">
    <div class="mb-2 flex items-center justify-between gap-2">
      <h1 class="text-sm font-medium">Stream Events</h1>
      <button class="btn btn-chill btn-xs gap-1.5" :disabled="refreshing" @click="refresh">
        <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': refreshing }" />
        {{ refreshing ? 'Working' : 'Refresh' }}
      </button>
    </div>

    <div class="transition-opacity duration-300" :class="refreshing ? 'opacity-40' : 'opacity-100'">
      <EventsTable v-if="events.length > 0" :events="events" />

      <EmptyState v-else message="No events yet. Events will appear here once your Twitch EventSub subscriptions are active." />
    </div>
    <div class="text-sm text-muted-foreground pt-2">
      Short link to this page: <a href="https://bit.ly/ol-embed" target="_blank" class="text-violet-400 hover:underline">bit.ly/ol-embed</a>
    </div>
  </div>

  <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
</template>
