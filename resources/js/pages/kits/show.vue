<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { PencilIcon, BookCopy, Package, Globe, Lock, ArrowLeft, Trash2Icon } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import TemplateTable from '@/components/TemplateTable.vue';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem, OverlayTemplate } from '@/types';
import { computed } from 'vue';

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
  templates?: OverlayTemplate[];
  forked_from?: {
    id: number;
    title: string;
  };
  created_at: string;
  updated_at: string;
}

interface Props {
  kit: Kit;
  canEdit: boolean;
  canFork: boolean;
  auth?: {
    user?: {
      id: number;
    };
  };
}

const props = defineProps<Props>();

const handleFork = () => {
  if (confirm('Clone this kit to your account? This will also clone all templates within the kit.')) {
    router.post(`/kits/${props.kit.id}/fork`);
  }
};

const handleDelete = () => {
  if (props.kit.fork_count > 0) {
    alert('This kit cannot be deleted because it has been cloned by others.');
    return;
  }

  if (confirm('Are you sure you want to delete this kit? This action cannot be undone.')) {
    router.delete(`/kits/${props.kit.id}`, {
      onSuccess: () => router.visit('/kits'),
    });
  }
};
const totalTemplates = computed(() => {
  const templates = props.kit.templates ?? [];

  const counts = templates.reduce<Record<string, number>>((acc, t) => {
    acc[t.type] = (acc[t.type] ?? 0) + 1;
    return acc;
  }, {});

  const entries = Object.entries(counts);

  if (!entries.length) {
    return 'No templates in this kit';
  }

  const parts = entries.map(([type, count]) => {
    const label =
      type === 'static'
        ? 'static overlay'
        : type === 'alert'
          ? 'alert'
          : type;

    return `${count} ${label}${count > 1 ? 's' : ''}`;
  });

  return `${parts.join(' and ')} in this kit`;
});

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
};

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'View Kit "' + props.kit.title + '"',
    href: route('kits.index'),
  },
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head :title="kit.title" />

    <div class="space-y-4 p-4">
      <!-- Back button -->
      <Link :href="route('kits.index')" class="mb-6 inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Kits
      </Link>

      <!-- Kit header -->
      <div class="mb-8 overflow-hidden rounded-lg bg-card lg:max-w-[55%]">
        <!-- Thumbnail -->
        <div v-if="kit.thumbnail_url" class="aspect-[16/9] w-full overflow-hidden bg-muted lg:aspect-[16/9]">
          <img :src="kit.thumbnail_url" :alt="kit.title" class="h-full w-full object-cover" />
        </div>
        <div v-else class="flex aspect-[16/9] w-full items-center justify-center bg-gradient-to-br from-primary/10 to-primary/5 lg:aspect-[21/9]">
          <Package class="h-16 w-16 text-primary/40" />
        </div>

        <div class="p-6 lg:p-8">
          <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
            <div class="flex-1">
              <div class="mb-2 flex items-center gap-3">
                <h1 class="text-3xl font-bold">{{ kit.title }}</h1>
                <Badge
                  :variant="kit.is_public ? 'default' : 'secondary'"
                  class="flex items-center gap-1"
                  :class="kit.is_public ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                >
                  <component :is="kit.is_public ? Globe : Lock" class="h-3 w-3" />
                  {{ kit.is_public ? 'Public' : 'Private' }}
                </Badge>
              </div>

              <p v-if="kit.description" class="mb-4 text-muted-foreground">
                {{ kit.description }}
              </p>

              <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                <div v-if="kit.owner" class="flex items-center gap-2">
                  <img v-if="kit.owner.avatar" :src="kit.owner.avatar" :alt="kit.owner.name" class="h-6 w-6 rounded-full" />
                  <span>by {{ kit.owner.name }}</span>
                </div>

                <div v-if="kit.forked_from" class="flex items-center gap-1">
                  <BookCopy class="h-4 w-4" />
                  <span>Copied from</span>
                  <Link :href="`/kits/${kit.forked_from.id}`" class="font-medium hover:underline">
                    {{ kit.forked_from.title }}
                  </Link>
                </div>

                <div class="flex items-center gap-1">
                  <BookCopy class="h-4 w-4" />
                  <span>{{ kit.fork_count }} cop{{ kit.fork_count !== 1 ? 'ies' : 'y' }}</span>
                </div>

                <div>Created {{ formatDate(kit.created_at) }}</div>
              </div>
            </div>

            <div class="flex gap-2">
              <Link
                v-if="canEdit"
                :href="`/kits/${kit.id}/edit`"
                class="btn btn-primary"
                title="Edit kit"
              >
                <PencilIcon class="h-4 w-4" />
              </Link>

              <button
                v-if="canFork"
                @click="handleFork"
                class="btn btn-warning"
                :disabled="!kit.is_public"
                title="Copy this kit to your own account"
              >
                <BookCopy class="h-4 w-4" />
              </button>

              <button
                v-if="canEdit && kit.fork_count === 0"
                @click="handleDelete"
                class="btn btn-danger"
                title="Delete kit"
              >
                <Trash2Icon class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Templates section -->
      <div>
        <h2 class="mb-4 text-xl font-semibold">{{ totalTemplates }}</h2>
        <TemplateTable
          v-if="kit.templates && kit.templates.length > 0"
          :templates="kit.templates"
          :current-user-id="auth?.user?.id"
        />

        <div v-else class="rounded-lg border-2 border-dashed border-muted-foreground/25 p-12 text-center">
          <Package class="mx-auto mb-4 h-12 w-12 text-muted-foreground/50" />
          <p class="text-muted-foreground">No templates in this kit yet.</p>
          <Link v-if="canEdit" :href="`/kits/${kit.id}/edit`" class="btn btn-primary mt-4"> Add Templates </Link>
        </div>

      </div>
    </div>
  </AppLayout>
</template>
