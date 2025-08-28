<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PlusIcon, Package } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import KitCard from '@/components/KitCard.vue';
import Heading from '@/components/Heading.vue';
import { BreadcrumbItem } from '@/types';

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

interface Props {
  kits: {
    data: Kit[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  auth?: {
    user?: {
      id: number;
    };
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Template Kits',
    href: route('kits.index'),
  }
]

defineProps<Props>();
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="My Kits" />

    <div class="mx-auto p-4">
      <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-sm px-3 py-1 relative -top-1.5 mr-1 border border-dashed border-violet-400/40 rounded-full bg-violet-500/10 text-violet-400">NEW</span>
          <Heading
            title="Template Kits"
            description="Kits offer a bundle of premade templates and overlay alerts. Fork them to your own account and assign them to your Events!"
          />
        </div>
        <Link :href="route('kits.create')" class="btn btn-primary">
          Create Kit
          <PlusIcon class="ml-2 h-4 w-4" />
        </Link>
      </div>

      <div v-if="kits.data.length > 0" class="grid md:grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4">
        <KitCard
          v-for="kit in kits.data"
          :key="kit.id"
          :kit="kit"
          :current-user-id="auth?.user?.id"
          :allow-delete="true"
        />
      </div>

      <div v-else class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 p-12 text-center">
        <Package class="mb-4 h-12 w-12 text-muted-foreground/50" />
        <h3 class="mb-2 text-lg font-semibold">No kits yet</h3>
        <p class="mb-6 max-w-sm text-sm text-muted-foreground">
          Create your first template kit to organize and share your overlay templates.
        </p>
        <Link :href="route('kits.create')" class="btn btn-primary">
          <PlusIcon class="mr-2 h-4 w-4" />
          Create Your First Kit
        </Link>
      </div>

      <!-- Pagination -->
      <div v-if="kits.last_page > 1" class="mt-8 flex justify-center gap-2">
        <Link
          v-for="page in kits.last_page"
          :key="page"
          :href="`/kits?page=${page}`"
          :class="[
            'rounded px-3 py-1 text-sm',
            page === kits.current_page
              ? 'bg-primary text-primary-foreground'
              : 'bg-muted hover:bg-muted/80'
          ]"
        >
          {{ page }}
        </Link>
      </div>

      <div class="mt-6">
        <Heading title="What are Kits?" />
        <p>Kits are a collection of premade of templates and alert overlays. You can use kits to quickly share a
          set of templates with anybody you like.</p>

        <p>By default, kits are private. You can set a kit to be publicly available,
          then everybody who has an Overlabels account can fork your Kit and use it themselves.</p>
        <Heading class="mt-4" title="Can I create a Kit?" />
        <p>Everybody can create Kits! In fact, it would be <span class="bg-cyan-400/50 px-1">amazing</span> if you did.</p>
      </div>

    </div>
  </AppLayout>
</template>
