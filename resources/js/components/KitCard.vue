<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Globe, Lock, Eye, GitFork, Package, Pencil, Trash2 } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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

const props = defineProps<{
  kit: Kit;
  showOwner?: boolean;
  currentUserId?: number;
  allowDelete?: boolean;
}>();

const isOwnKit = props.currentUserId && props.kit.owner?.id === props.currentUserId;

const handleFork = () => {
  if (confirm('Are you sure you want to fork this kit to your own account?')) {
    router.post(`/kits/${props.kit.id}/fork`);
  }
};

const handleDelete = () => {
  if (confirm('Are you sure you want to delete this kit? This action cannot be undone.')) {
    router.delete(`/kits/${props.kit.id}`);
  }
};

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};
</script>

<template>
  <Card class="group relative flex h-full pt-0 flex-col overflow-hidden">
    <!-- Thumbnail -->
    <div v-if="kit.thumbnail_url" class="aspect-video rounded-sm rounded-b-none w-full overflow-hidden bg-muted">
      <img
        :src="kit.thumbnail_url"
        :alt="kit.title"
        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
      />
    </div>
    <div v-else class="flex aspect-video w-full items-center justify-center">
      <Package class="h-12 w-12 text-primary/40" />
    </div>

    <CardHeader class="px-4 pb-4 py-4">
      <div class="space-y-2">
        <div class="flex items-start justify-between gap-2">
          <CardTitle class="min-w-0 flex-1 text-base">
            <Link
              :href="`/kits/${kit.id}`"
              class="block truncate transition-colors hover:text-accent-foreground/80"
              :title="`View kit: ${kit.title}`"
            >
              {{ kit.title }}
            </Link>
          </CardTitle>
          <div class="flex flex-shrink-0 items-center gap-2">
            <div
              class="rounded-full p-1.5"
              :class="kit.is_public ? 'bg-green-500/10' : 'bg-violet-500/10'"
              :title="kit.is_public ? 'Public kit' : 'Private kit'"
            >
              <component :is="kit.is_public ? Globe : Lock" :class="['size-4', kit.is_public ? 'text-green-600' : 'text-violet-600']" />
            </div>
          </div>
        </div>
        <CardDescription v-if="kit.description" class="line-clamp-2 text-sm">
          {{ kit.description }}
        </CardDescription>
      </div>
    </CardHeader>

    <CardContent class="flex flex-1 flex-col justify-between space-y-2">
      <div class="space-y-3">
        <!-- Templates count -->
        <div v-if="kit.templates" class="flex items-center gap-2 text-sm mb-4">
          <Package class="size-4" />
          <span>Contains {{ kit.templates.length }} template{{ kit.templates.length !== 1 ? 's' : '' }}</span>
        </div>

        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4 text-sm bg-sidebar p-0.5 px-2 rounded-full text-slate-500 dark:text-slate-400 dark:hover:text-slate-200 transition">
            <div class="flex items-center gap-1.5" title="Forks">
              <GitFork class="size-4" />
              <span>{{ kit.fork_count || 0 }}</span>
            </div>
          </div>

          <span class="text-sm bg-sidebar p-0.5 px-2 rounded-full text-slate-500 dark:text-slate-400 dark:hover:text-slate-200 transition" title="Last updated">
            {{ formatDate(kit.updated_at) }}
          </span>
        </div>

        <div v-if="showOwner && kit.owner" class="flex items-center gap-2 border-t pt-3">
          <img v-if="kit.owner.avatar" :src="kit.owner.avatar" :alt="kit.owner.name" class="h-6 w-6 rounded-full" />
          <span class="truncate text-sm"> by {{ kit.owner.name }} </span>
        </div>
      </div>

      <div class="flex gap-2 pt-2">
        <Link v-if="isOwnKit" :href="`/kits/${kit.id}/edit`" class="btn btn-sm btn-secondary flex text-center">
          Edit
          <Pencil class="ml-2 size-4" />
        </Link>

        <button
          v-if="!isOwnKit || kit.is_public"
          class="btn btn-sm btn-warning"
          @click="handleFork"
          title="Fork kit"
        >
          Fork
          <GitFork class="ml-1 size-4" />
        </button>

        <Link :href="`/kits/${kit.id}`" class="btn btn-sm btn-primary">
          View
          <Eye class="ml-2 size-4" />
        </Link>

        <button
          v-if="isOwnKit && allowDelete && kit.fork_count === 0"
          class="btn btn-sm btn-danger"
          @click="handleDelete"
          title="Delete kit"
        >
          <Trash2 class="size-4" />
        </button>
      </div>
    </CardContent>
  </Card>
</template>
