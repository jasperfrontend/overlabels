<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import EventsTable from '@/components/EventsTable.vue';
import RekaToast from '@/components/RekaToast.vue';
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
</script>

<template>
  <Head>
    <title>Stream Events</title>
    <meta name="description" content="Recent stream events - Overlabels" />
  </Head>

  <div class="mx-auto max-w-3xl px-2 py-2">
    <div class="mb-2 flex items-center justify-between gap-2">
      <h1 class="text-sm font-medium">Stream Events</h1>
      <Link :href="route('dashboard.events')" class="rounded-full bg-sidebar px-2 py-0.5 text-sm text-muted-foreground hover:text-foreground"
        >Refresh</Link
      >
    </div>

    <EventsTable v-if="events.length > 0" :events="events" />

    <p v-else class="py-4 text-center text-sm text-muted-foreground">
      No events yet. Events will appear here once your Twitch EventSub subscriptions are active.
    </p>
  </div>

  <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
</template>
