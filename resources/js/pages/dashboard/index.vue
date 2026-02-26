<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import OnboardingWizard from '@/components/OnboardingWizard.vue';
import Heading from '@/components/Heading.vue';
import TemplateList from '@/components/TemplateList.vue';
import { Layers, Plus, Bell, MoveUpRight } from 'lucide-vue-next';
import type { OverlayTemplate } from '@/types';

defineProps<{
  userName: string;
  userId: number;
  userAlertTemplates: OverlayTemplate[];
  userStaticTemplates: OverlayTemplate[];
  needsOnboarding: boolean;
  twitchId: string;
}>();

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
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <!-- Onboarding Wizard -->
      <section v-if="needsOnboarding" class="mb-6">
        <OnboardingWizard :twitch-id="twitchId" />
      </section>
      <!-- // Onboarding Wizard -->

      <div v-else class="flex flex-col justify-between gap-6 space-y-6 lg:flex-row">
        <section v-if="userAlertTemplates.length > 0" class="flex-1">
          <div class="flex flex-col justify-between md:flex-row">
            <Heading title="My alerts" />
            <div class="flex-col-2 mt-2 flex gap-2 md:mt-0 md:flex-row">
              <a
                class="btn btn-sm btn-chill flex-1 items-center gap-2 md:flex-none"
                :href="route('templates.index', { direction: 'desc', filter: 'mine', search: '', type: 'alert' })"
                title="View all of your alerts"
              >
                <MoveUpRight class="h-4 w-4" />
              </a>
              <a class="btn btn-sm btn-primary flex-1 items-center gap-2 md:flex-none" :href="route('templates.create')" title="Create a new Alert">
                <Plus class="h-4 w-4" />
              </a>
            </div>
          </div>
          <TemplateList :templates="userAlertTemplates" :current-user-id="userId" />
        </section>

        <section v-if="userStaticTemplates.length > 0" class="flex-1">
          <div class="flex flex-col justify-between md:flex-row">
            <Heading title="My overlays" />
            <div class="flex-col-2 mt-2 flex gap-2 md:mt-0 md:flex-row">
              <a
                class="btn btn-sm btn-chill flex-1 items-center gap-2 md:flex-none"
                :href="route('templates.index', { direction: 'desc', filter: 'mine', search: '', type: 'static' })"
                title="View all of your overlays"
              >
                <MoveUpRight class="h-4 w-4" />
              </a>
              <a class="btn btn-sm btn-primary flex-1 items-center gap-2 md:flex-none" :href="route('templates.create')" title="Create a new Overlay">
                <Plus class="h-4 w-4" />
              </a>
            </div>
          </div>

          <TemplateList :templates="userStaticTemplates" :current-user-id="userId" />
        </section>
      </div>
      <!-- end onboarding pivot -->

      <div class="mt-6 mb-2 h-px w-full bg-muted-foreground/10" />

      <!-- Empty State for User Templates -->
      <section v-if="userAlertTemplates.length === 0 && userStaticTemplates.length === 0" class="space-y-6">
        <Card class="border border-sidebar">
          <CardHeader class="py-4 text-center">
            <CardTitle class="text-2xl">Get Started with Your First Template</CardTitle>
            <CardDescription class="mt-3 text-base"> Create your own custom overlays or fork one from the community to get started </CardDescription>
          </CardHeader>
          <CardContent class="flex justify-center gap-4 pb-8">
            <Link class="btn btn-sm btn-secondary" :href="route('templates.create')">
              <Plus class="mr-2 h-4 w-4" />
              Create Template
            </Link>
            <Link size="lg" class="btn btn-sm btn-primary" :href="route('templates.index')"> Browse Templates </Link>
          </CardContent>
        </Card>
      </section>
    </div>
  </AppLayout>
</template>
