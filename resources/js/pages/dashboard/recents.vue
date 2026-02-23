<script setup lang="ts">
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import TemplateTable from '@/components/TemplateTable.vue';
import EventsTable from '@/components/EventsTable.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ExternalLink, FileText, Radio } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import type { AppPageProps } from '@/types';

interface Template {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  type: 'static' | 'alert';
  is_public: boolean;
  view_count: number;
  fork_count: number;
  owner?: {
    id: number;
    name: string;
    avatar?: string;
  };
  created_at: string;
  updated_at: string;
}

interface TwitchEvent {
  id: number;
  event_type: string;
  event_data: Record<string, unknown>;
  created_at: string;
}

defineProps<{
  recentTemplates: Template[];
  recentEvents: TwitchEvent[];
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

const breadcrumbs = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Activity',
    href: '/dashboard/recents',
  },
];
</script>

<template>
  <Head>
    <title>My Activity</title>
    <meta name="description" content="Your recent templates and stream events - Overlabels" />
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-8 p-4">
      <!-- Recent Stream Events -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Radio class="mr-2 h-6 w-6" />
            <Heading title="Recent stream activity" />
            <Link :href="route('dashboard.recents')" class="text-sm bg-sidebar py-0.5 px-2 rounded-full text-muted-foreground hover:text-foreground">Refresh</Link>
          </div>
          <a href="/dashboard/events" target="_blank" class="btn btn-primary">
            Embed view
            <ExternalLink class="ml-2 h-4 w-4" />
          </a>
        </div>

        <EventsTable v-if="recentEvents.length > 0" :events="recentEvents" />

        <Card v-else class="-mt-0.5 border border-sidebar bg-sidebar-accent">
          <CardHeader>
            <CardTitle class="text-md">No Events Yet</CardTitle>
            <CardDescription class="-mt-0.5 text-sm">
              Stream events will appear here once your Twitch EventSub subscriptions are active.
            </CardDescription>
          </CardHeader>
        </Card>
      </section>

      <!-- Recently Updated Templates -->
      <section class="space-y-4">
        <div class="flex items-center gap-3">
          <FileText class="mr-2 h-6 w-6" />
          <Heading title="Recently Updated Templates" />
        </div>

        <TemplateTable v-if="recentTemplates.length > 0" :templates="recentTemplates" :show-owner="false" />

        <Card v-else class="-mt-0.5 border border-sidebar bg-sidebar-accent">
          <CardHeader>
            <CardTitle class="text-md">No Templates Yet</CardTitle>
            <CardDescription class="-mt-0.5 text-sm"> Create your first template to get started! </CardDescription>
          </CardHeader>
        </Card>
      </section>

      <div class="h-px w-full bg-muted-foreground/10" />
    </div>

    <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
