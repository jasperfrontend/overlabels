<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import TemplateCard from '@/components/TemplateCard.vue';
import { Button } from '@/components/ui/button';
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
    <title>Recently added Templates</title>
    <meta name="description" content="The Newest Templates and Alerts - Overlabels">
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4">

      <!-- Community Templates Section -->
      <section class="space-y-6">
        <div class="flex items-center gap-3">
          <Users class="w-6 h-6 mr-1 text-primary" />
          <Heading title="From the Community" description="Recently added public templates you can fork and customize" />
        </div>

        <div v-if="communityTemplates.length > 0" class="grid gap-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3 min-[2560px]:grid-cols-5">
          <TemplateCard
            v-for="template in communityTemplates"
            :key="template.id"
            :template="template"
            :show-owner="true"
            :current-user-id="userId"
          />
        </div>

        <Card v-else class="border-dashed">
          <CardHeader class="flex text-center py-8">
            <CardTitle class="text-xl">No Community Templates Yet</CardTitle>
            <CardDescription class="text-base mt-2">
              Be the first to share a template with the community!
            </CardDescription>
          </CardHeader>
        </Card>

        <div class="flex py-6">
          <a :href="route('templates.index')" class="btn btn-cancel">Browse All Templates</a>
        </div>
      </section>

    </div>
  </AppLayout>
</template>
