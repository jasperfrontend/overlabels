<script setup lang="ts">
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import EventsTable from '@/components/EventsTable.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ExternalLink, Radio, RefreshCw } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import type { AppPageProps, OverlayTemplate } from '@/types';

interface UnifiedEvent {
  id: number;
  source: string;
  event_type: string;
  created_at: string;
  event_data?: Record<string, unknown> | null;
  normalized_payload?: Record<string, unknown> | null;
}

defineProps<{
  recentTemplates: OverlayTemplate[];
  recentEvents: UnifiedEvent[];
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
    only: ['recentEvents', 'recentTemplates'],
    onFinish: () => {
      setTimeout(() => {
        refreshing.value = false;
      }, 600);
    },
  });
}

const breadcrumbs = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Recent events',
    href: '/dashboard/recents',
  },
];
</script>

<template>
  <Head>
    <title>My activity</title>
    <meta name="description" content="Your recent templates and stream events - Overlabels" />
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-8 p-4">
      <!-- Recent Stream Events -->
      <section class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <Radio class="mr-1 h-6 w-6" />
            <Heading title="Recent alerts and stream events" />
            <button class="btn btn-chill btn-xs gap-1.5" :disabled="refreshing" @click="refresh">
              <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': refreshing }" />
              {{ refreshing ? 'Working' : 'Refresh' }}
            </button>
          </div>
          <a href="/dashboard/events" target="_blank" class="btn btn-primary self-start sm:self-auto">
            Embed view
            <ExternalLink class="ml-2 h-4 w-4" />
          </a>
        </div>

        <div class="transition-opacity duration-300" :class="refreshing ? 'opacity-40' : 'opacity-100'">
          <EventsTable v-if="recentEvents.length > 0" :events="recentEvents" />

          <Card v-else class="-mt-0.5 border border-sidebar bg-sidebar-accent">
            <CardHeader>
              <CardTitle class="text-md">No Events Yet</CardTitle>
              <CardDescription class="-mt-0.5 text-sm">
                Stream events will appear here once your Twitch EventSub subscriptions are active.
              </CardDescription>
            </CardHeader>
          </Card>
        </div>
      </section>
    </div>

    <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
