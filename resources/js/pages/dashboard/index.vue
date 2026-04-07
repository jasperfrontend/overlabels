<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import OnboardingWizard from '@/components/OnboardingWizard.vue';
import TemplateList from '@/components/TemplateList.vue';
import EventsTable from '@/components/EventsTable.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Plus } from 'lucide-vue-next';
import DashboardSectionHeader from '@/components/DashboardSectionHeader.vue';
import type { AppPageProps, OverlayTemplate } from '@/types';
import EmptyState from '@/components/EmptyState.vue';

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
  { immediate: true }
);

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
  needsOnboarding: boolean;
  twitchId: string;
}>();

const breadcrumbs = [
  {
    title: 'Dashboard',
    href: '/dashboard'
  }
];
</script>

<template>
  <Head>
    <title>Dashboard</title>
    <meta name="description" content="Dashboard for Overlabels - My Twitch overlay hub" />
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4">
      <!-- Onboarding Wizard -->
      <section v-if="props.needsOnboarding" class="mb-6">
        <OnboardingWizard :twitch-id="twitchId" />
      </section>
      <!-- // Onboarding Wizard -->

      <div v-else>
        <div class="grid grid-cols-1 justify-between gap-6 space-y-6 lg:grid-cols-2">

          <section v-if="props.userStaticTemplates.length > 0" class="flex-1 p-4">
            <DashboardSectionHeader
              title="My overlays"
              :view-href="route('templates.index', { direction: 'desc', filter: 'mine', search: '', type: 'static' })"
              view-title="View all of your overlays"
              :create-href="route('templates.create')"
              create-title="Create a new Overlay"
            />
            <TemplateList :templates="props.userStaticTemplates" :current-user-id="userId" />
          </section>

          <section v-if="props.userAlertTemplates.length > 0" class="flex-1 p-4">
            <DashboardSectionHeader
              title="My alerts"
              :view-href="route('templates.index', { direction: 'desc', filter: 'mine', search: '', type: 'alert' })"
              view-title="View all of your alerts"
              :create-href="route('templates.create')"
              create-title="Create a new Alert"
            />
            <TemplateList :templates="props.userAlertTemplates" :current-user-id="userId" />
          </section>

          <section class="flex-1 p-4">
            <DashboardSectionHeader
              title="Recent stream activity"
              :view-href="route('dashboard.recents')"
              view-title="View all of your recent activity"
            />
            <EventsTable v-if="props.userRecentEvents.length > 0" :events="props.userRecentEvents" />

            <EmptyState v-else
                        message="No events yet. Events will appear here once you have received one or more stream events." />
          </section>

        </div>
      </div>

      <div class="mt-6 mb-2 h-px w-full bg-muted-foreground/10" />

      <!-- Empty State for User Templates -->
      <section v-if="props.userAlertTemplates.length === 0 && props.userStaticTemplates.length === 0" class="space-y-6">
        <Card class="border border-sidebar">
          <CardHeader class="py-4 text-center">
            <CardTitle class="text-2xl">Get Started with Your First Template</CardTitle>
            <CardDescription class="mt-3 text-base"> Create your own custom overlays or fork one from the community to
              get started
            </CardDescription>
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
    <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
