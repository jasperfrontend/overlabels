<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import TabStrip, { type TabStripItem } from '@/components/TabStrip.vue';
import TemplateCollection from '@/components/TemplateCollection.vue';
import UpdatesList from '@/components/UpdatesList.vue';
import EventsTable from '@/components/EventsTable.vue';
import { Bell, Layers, List, Newspaper, Plus } from '@lucide/vue';
import DashboardSectionHeader from '@/components/DashboardSectionHeader.vue';
import type { AppPageProps, OverlayTemplate, Update, UsageSummary, WhatsNew } from '@/types';
import EmptyState from '@/components/EmptyState.vue';

const page = usePage<AppPageProps>();

interface UnifiedEvent {
  id: number;
  source: string;
  event_type: string;
  created_at: string;
  event_data?: Record<string, unknown> | null;
  normalized_payload?: Record<string, unknown> | null;
}

const props = defineProps<{
  userId: number;
  userAlertTemplates: OverlayTemplate[];
  userStaticTemplates: OverlayTemplate[];
  userRecentEvents: UnifiedEvent[];
  recentUpdates: Update[];
  whatsNew: WhatsNew;
  usage: UsageSummary | null;
}>();

const isAdmin = computed(() => page.props.isAdmin);

const activeTab = ref('overlays');

const mainTabs: TabStripItem[] = [
  { key: 'overlays', label: 'My overlays', icon: Layers },
  { key: 'alerts', label: 'My alerts', icon: Bell },
  { key: 'activity', label: 'Stream activity', icon: Newspaper },
];

/**
 * The view-all and create links live next to the tab strip instead of above
 * each panel: the tab label is already the section title, so a per-panel
 * DashboardSectionHeader would print it twice.
 */
const tabActions = computed(() => {
  if (activeTab.value === 'alerts') {
    return {
      viewHref: route('templates.index', { direction: 'desc', filter: 'mine', search: '', type: 'alert' }),
      viewTitle: 'View all of your alerts',
      createHref: route('templates.create'),
      createTitle: 'Create a new Alert',
    };
  }

  if (activeTab.value === 'activity') {
    return {
      viewHref: route('dashboard.recents'),
      viewTitle: 'View all of your recent activity',
      createHref: null,
      createTitle: null,
    };
  }

  return {
    viewHref: route('templates.index', { direction: 'desc', filter: 'mine', search: '', type: 'static' }),
    viewTitle: 'View all of your overlays',
    createHref: route('templates.create'),
    createTitle: 'Create a new Overlay',
  };
});

const breadcrumbs = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
];
</script>

<template>
  <Head>
    <title>Dashboard</title>
    <meta name="description" content="Dashboard for Overlabels - My Twitch overlay hub" />
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4">
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <!-- 60% -->
        <div class="p-4 lg:col-span-3">
          <TabStrip v-model="activeTab" :tabs="mainTabs">
            <template #actions>
              <a class="btn btn-sm btn-chill flex-1 md:flex-none" :href="tabActions.viewHref" :title="tabActions.viewTitle" aria-label="View all">
                <List class="h-4 w-4" />
              </a>

              <a
                v-if="tabActions.createHref"
                class="btn btn-sm btn-primary flex-1 md:flex-none"
                :href="tabActions.createHref"
                :title="tabActions.createTitle ?? 'Create new'"
                aria-label="Create new"
              >
                <Plus class="h-4 w-4" />
              </a>
            </template>
          </TabStrip>

          <div v-show="activeTab === 'overlays'" class="mt-4">
            <TemplateCollection
              :templates="props.userStaticTemplates"
              :current-user-id="userId"
              empty-message="No overlays yet. Create one to get started."
            />
          </div>

          <div v-show="activeTab === 'alerts'" class="mt-4">
            <TemplateCollection
              :templates="props.userAlertTemplates"
              :current-user-id="userId"
              empty-message="No alerts yet. Create one to get started."
            />
          </div>

          <div v-show="activeTab === 'activity'" class="mt-4">
            <EventsTable v-if="props.userRecentEvents.length > 0" :events="props.userRecentEvents" />

            <EmptyState v-else message="No events yet. Events will appear here once you have received one or more stream events." />
          </div>
        </div>

        <!-- 40% -->
        <section v-if="props.recentUpdates && props.recentUpdates.length > 0" class="p-4 lg:col-span-2">
          <DashboardSectionHeader
            title="Recent updates"
            :view-href="route('updates.index')"
            view-title="See all platform updates"
            :create-href="isAdmin ? route('admin.updates.create') : undefined"
            create-title="Write a new update"
          />
          <UpdatesList :updates="props.recentUpdates" :is-admin="isAdmin" class="mt-4" />
        </section>
      </div>

      <div class="mt-6 mb-2 h-px w-full bg-muted-foreground/10" />

      <!-- Empty State for User Templates -->
      <section v-if="props.userAlertTemplates.length === 0 && props.userStaticTemplates.length === 0" class="space-y-6">
        <Card class="border border-sidebar">
          <CardHeader class="py-4 text-center">
            <CardTitle class="text-2xl">Get Started with Your First Template</CardTitle>
            <CardDescription class="mt-3 text-base"> Create your own custom overlays or copy one from the community to get started </CardDescription>
          </CardHeader>
          <CardContent class="flex justify-center gap-4 pb-8">
            <Link class="btn btn-sm btn-secondary" :href="route('templates.create')">
              <Plus class="mr-2 h-4 w-4" />
              Create Template
            </Link>
            <Link size="lg" class="btn btn-sm btn-primary" :href="route('templates.index')"> Browse Templates</Link>
          </CardContent>
        </Card>
      </section>
    </div>
  </AppLayout>
</template>
