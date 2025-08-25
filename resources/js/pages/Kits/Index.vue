<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PlusIcon, Package } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import KitCard from '@/components/KitCard.vue';
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';

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

const props = defineProps<Props>();
</script>

<template>
  <AppLayout>
    <Head title="My Kits" />

    <div class="container mx-auto px-4 py-8">
      <div class="mb-8 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold"><span class="text-sm px-3 py-1 relative -top-1.5 mr-1 border border-dashed border-violet-400/40 rounded-full bg-violet-500/10 text-violet-400">NEW</span> Template Kits</h1>
          <p class="mt-2 text-muted-foreground">
            Kits offer a bundle of existing templates and overlay alerts. Fork them to your own account and assign them to your Events!
          </p>
        </div>
        <Link :href="route('kits.create')" class="btn btn-primary">
          <PlusIcon class="mr-2 h-4 w-4" />
          Create Kit
        </Link>
      </div>

      <div v-if="kits.data.length > 0" class="grid md:grid-cols-2 lg:grid-cols-3">
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
        <Link href="/kits/create" class="btn btn-primary">
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
        <Heading title="What are Kits?">
        </Heading>
        <p>Kits are a collection of existing of templates and alert overlays. You can use kits to quickly share a
          set of templates with anybody you like.</p>

        <p>By default, kits are private. You can set a kit to be publicly available,
          then everybody who has an Overlabels account can fork your Kit and use it themselves.</p>
      </div>

    </div>
  </AppLayout>
</template>
