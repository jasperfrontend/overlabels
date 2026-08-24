<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PlusIcon, Package, LayoutGrid } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import KitCard from '@/components/KitCard.vue';
import EmptyState from '@/components/EmptyState.vue';
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
  recentPublicKits?: Kit[];
  auth?: {
    user?: {
      id: number;
    };
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Overlay kits',
    href: route('kits.index'),
  },
];

defineProps<Props>();
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Overlay kits" />

    <div class="space-y-4 p-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
          <LayoutGrid class="mr-2 h-6 w-6" />
          <Heading title="Overlay Kits" />
        </div>
        <Link :href="route('kits.create')" class="btn btn-sm btn-primary self-start sm:self-auto">
          Create kit
          <PlusIcon class="ml-2 h-4 w-4" />
        </Link>
      </div>

      <div v-if="kits.data.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <KitCard v-for="kit in kits.data" :key="kit.id" :kit="kit" :current-user-id="auth?.user?.id" :allow-delete="true" />
      </div>

      <EmptyState
        v-else
        dashed
        :icon="Package"
        title="No kits yet"
        message="Create your first template kit to organize and share your overlay templates."
      >
        <template #action>
          <Link :href="route('kits.create')" class="btn btn-primary">
            <PlusIcon class="mr-2 h-4 w-4" />
            Create Your First Kit
          </Link>
        </template>
      </EmptyState>

      <!-- Pagination -->
      <div v-if="kits.last_page > 1" class="mt-8 flex justify-center gap-2">
        <Link
          v-for="page in kits.last_page"
          :key="page"
          :href="`/kits?page=${page}`"
          :class="['rounded px-3 py-1 text-sm', page === kits.current_page ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/80']"
        >
          {{ page }}
        </Link>
      </div>

      <!-- Public kits from other users -->
      <template v-if="recentPublicKits && recentPublicKits.length > 0">
        <Heading title="Public kits" />
        <p class="mb-3 text-sm text-muted-foreground">Kits shared by the community. Copy any kit to use it in your own overlays.</p>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <KitCard v-for="kit in recentPublicKits" :key="`public-${kit.id}`" :kit="kit" :current-user-id="auth?.user?.id" show-owner />
        </div>
      </template>
    </div>
  </AppLayout>
</template>
