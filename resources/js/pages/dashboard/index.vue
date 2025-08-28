<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import TemplateCard from '@/components/TemplateCard.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Layers, Plus, Bell, Users } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';

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

defineProps<{
  userName: string;
  userId: number;
  userAlertTemplates: Template[];
  userStaticTemplates: Template[];
  communityTemplates: Template[];
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
    <meta name="description" content="Dashboard for Overlabels - Your Twitch overlay hub" />
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4">


      <!-- Your Alert Templates Section -->
      <section v-if="userAlertTemplates.length > 0" class="space-y-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Bell class="mr-1 h-6 w-6 text-primary" />
            <Heading title="Your Alert Templates" />
          </div>
          <a class="btn btn-sm btn-cancel flex items-center gap-2" :href="route('templates.create')">
            New Alert
            <Plus class="h-4 w-4" />
          </a>
        </div>
        <div class="grid grid-cols-1 gap-6 min-[2560px]:grid-cols-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3">
          <TemplateCard v-for="template in userAlertTemplates" :key="template.id" :template="template" :current-user-id="userId" />
        </div>
      </section>

      <div class="mt-6 mb-2 h-px w-full bg-muted-foreground/10" />

      <!-- Your Static Templates Section -->
      <section v-if="userStaticTemplates.length > 0" class="space-y-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Layers class="h-6 w-6 text-primary" />
            <h2 class="text-2xl font-semibold">Your Static Overlays</h2>
          </div>
          <a class="btn btn-sm btn-cancel flex items-center gap-2" :href="route('templates.create')">
            New Overlay
            <Plus class="h-4 w-4" />
          </a>
        </div>
        <div class="grid grid-cols-1 gap-6 min-[2560px]:grid-cols-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3">
          <TemplateCard v-for="template in userStaticTemplates" :key="template.id" :template="template" :current-user-id="userId" />
        </div>
      </section>

      <div class="mt-6 mb-2 h-px w-full bg-muted-foreground/10" />

      <!-- Empty State for User Templates -->
      <section v-if="userAlertTemplates.length === 0 && userStaticTemplates.length === 0" class="space-y-6">
        <Card class="border border-sidebar">
          <CardHeader class="py-4 text-center">
            <CardTitle class="text-2xl">Get Started with Your First Template</CardTitle>
            <CardDescription class="mt-3 text-base"> Create your own custom overlays or fork one from the community to get started </CardDescription>
          </CardHeader>
          <CardContent class="flex justify-center gap-4 pb-8">
            <a class="btn btn-sm btn-secondary" :href="route('templates.create')">
              <Plus class="mr-2 h-4 w-4" />
              Create Template
            </a>
            <a size="lg" class="btn btn-sm btn-primary" :href="route('templates.index')">
              Browse Templates
            </a>
          </CardContent>
        </Card>
      </section>

      <!-- Community Templates Section -->
      <section class="space-y-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Users class="h-6 w-6 text-primary" />
            <h2 class="text-2xl font-semibold">From the Community</h2>
            <span class="text-base text-muted-foreground"> Public templates you can fork and customize </span>
          </div>
          <a class="btn btn-sm btn-cancel flex items-center gap-2" :href="route('dashboard.recents')">
            Community templates
            <Users class="h-4 w-4" />
          </a>
        </div>

        <div
          v-if="communityTemplates.length > 0"
          class="grid grid-cols-1 gap-6 min-[2560px]:grid-cols-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3"
        >
          <TemplateCard v-for="template in communityTemplates" :key="template.id" :template="template" :show-owner="true" :current-user-id="userId" />
        </div>

        <Card v-else class="border border-sidebar">
          <CardHeader class="py-8 flex-column justify-center gap-2 text-center">
            <CardTitle class="text-xl">No Community Templates Yet</CardTitle>
            <CardDescription class="mt-2 text-base"> Be the first to share a template with the community! </CardDescription>
          </CardHeader>
        </Card>

        <div class="flex py-6">
          <a href="/templates?direction=desc&filter=mine&search=&type=" class="btn btn-sm btn-cancel">Browse Your Templates</a>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
