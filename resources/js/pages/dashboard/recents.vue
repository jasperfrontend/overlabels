<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import TemplateCard from '@/components/TemplateCard.vue';
import KitCard from '@/components/KitCard.vue';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Users, Package } from 'lucide-vue-next';
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

interface Kit {
  id: number;
  title: string;
  description: string | null;
  thumbnail: string | null;
  thumbnail_url?: string | null;
  is_public: boolean;
  fork_count: number;
  owner?: {
    id: number;
    name: string;
    avatar?: string;
  };
  templates?: Array<{
    id: number;
    name: string;
    type: string;
  }>;
  created_at: string;
  updated_at: string;
}

defineProps<{
  userName?: string;
  userId?: number;
  userAlertTemplates?: Template[];
  userStaticTemplates?: Template[];
  communityTemplates: Template[];
  recentKits?: Kit[];
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
    <title>Recently added Templates and Kits</title>
    <meta name="description" content="The Newest Templates, Alerts and Kits - Overlabels">
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4">

      <!-- Community Templates Section -->
      <section class="space-y-8 mt-1 ">
        <div class="flex items-center gap-3">
          <Users class="w-6 h-6 mr-2 text-primary" />
          <Heading title="Recently created templates" />
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

        <Card v-else class="bg-sidebar-accent border border-sidebar -mt-0.5">
          <CardHeader>
            <CardTitle class="text-md">No Community Templates Yet</CardTitle>
            <CardDescription class="text-base -mt-0.5 text-sm">
              Be the first to share a template with the community!
            </CardDescription>
          </CardHeader>
        </Card>

        <div class="flex">
          <a :href="route('templates.index')" class="btn btn-sm btn-cancel">Browse All Templates</a>
        </div>
      </section>

      <div class="mt-6 mb-2 h-px w-full bg-muted-foreground/10" />

      <!-- Recent kits Section -->
      <section v-if="recentKits && recentKits.length > 0" class="space-y-6 mt-4">
        <div class="flex items-center gap-3">
          <Package class="w-6 h-6 mr-1 text-primary" />
          <Heading title="Recent Template Kits" description="Collections of templates ready to fork and use" />
        </div>

        <div class="grid gap-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3 min-[2560px]:grid-cols-5">
          <KitCard
            v-for="kit in recentKits"
            :key="kit.id"
            :kit="kit"
            :show-owner="true"
            :current-user-id="userId"
          />
        </div>

        <div class="flex py-6">
          <a :href="route('kits.index')" class="btn btn-sm btn-cancel">Browse All Kits</a>
        </div>
      </section>

    </div>
  </AppLayout>
</template>
