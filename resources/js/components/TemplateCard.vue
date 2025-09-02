<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Globe, Lock, Eye, GitFork, ExternalLinkIcon, PencilIcon } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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

const props = defineProps<{
  template: Template;
  showOwner?: boolean;
  currentUserId?: number;
}>();

const isOwnTemplate = props.currentUserId && props.template.owner?.id === props.currentUserId;

const handleFork = () => {
  confirm('Are you sure you want to fork this template to your own account?') && router.post(`/templates/${props.template.id}/fork`);
};

const formatDate = (date: string) => {
  const dateObj = new Date(date);

  return {
    display: dateObj.toLocaleDateString('en-GB', {
      month: 'short',
      day: 'numeric',
    }),
    full: dateObj.toLocaleDateString('en-GB', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  };
};
</script>

<template>
  <Card class="group relative flex h-full flex-col overflow-hidden border border-sidebar-foreground/30 dark:border-sidebar hover:bg-sidebar-accent/90 transition hover:ring-2 ring-violet-300/30">
    <CardHeader class="px-4 pb-4">
      <div>
        <div class="flex items-start justify-between">
          <CardTitle class="flex-1 mb-1 text-base">
            <Link
              :href="`/templates/${template.id}`"
              class="block truncate transition-colors hover:text-accent-foreground/80"
              :title="`View details: ${template.name}`"
            >
              {{ template.name }}
            </Link>
          </CardTitle>
          <div class="flex gap-2">

            <span
              :title="`Created: ${formatDate(template.created_at).full}\nUpdated: ${formatDate(template.updated_at).full}`"
              class="text-xs bg-sidebar p-0.5 px-2 rounded-full text-slate-500 dark:text-slate-400 dark:hover:text-slate-200 transition">
              {{ formatDate(template.created_at).display }}
            </span>
          </div>
        </div>
        <CardDescription v-if="template.description" class="line-clamp-2 text-sm text-violet-900 dark:text-violet-100">
          {{ template.description }}
        </CardDescription>
      </div>
    </CardHeader>

    <CardContent class="flex flex-1 flex-col justify-between space-y-2">
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2 text-sm">

            <div
              class="flex items-center gap-1 bg-sidebar p-0.5 px-2 rounded-full text-slate-500 dark:text-slate-400 dark:hover:text-slate-200 transition"
              :title="template.is_public ? 'Public template' : 'Private template'"
            >
              <component :is="template.is_public ? Globe : Lock" class="h-4 w-4" />
              <span v-if="template.is_public">Public</span>
              <span v-else>Private</span>
            </div>

            <div class="flex items-center gap-1 bg-sidebar p-0.5 px-2 rounded-full text-slate-500 dark:text-slate-400 dark:hover:text-slate-200 transition" title="Views">
              <Eye class="h-4 w-4" />
              <span>{{ template.view_count || 0 }}</span>
            </div>

            <div class="flex items-center gap-1 bg-sidebar p-0.5 px-2 rounded-full text-slate-500 dark:text-slate-400 dark:hover:text-slate-200 transition" title="Forks">
              <GitFork class="h-4 w-4" />
              <span>{{ template.fork_count || 0 }}</span>
            </div>

          </div>


        </div>

        <div v-if="showOwner && template.owner" class="flex items-center gap-2 border-t border-t-sidebar pt-3 mt-4">
          <img v-if="template.owner.avatar" :src="template.owner.avatar" :alt="template.owner.name" class="h-6 w-6 rounded-full" />
          <span class="truncate text-sm text-sidebar-foreground/80"> by {{ template.owner.name }} </span>
        </div>
      </div>

      <div class="flex gap-2 pt-2">
        <a class="btn btn-sm btn-secondary flex text-center" title="Edit template" :href="`/templates/${template.id}`">
          View
          <Eye class="ml-2 h-4 w-4" />
        </a>

        <a v-if="isOwnTemplate" class="btn btn-sm btn-warning flex text-center" title="Edit template" :href="`/templates/${template.id}/edit`">
          Edit
          <PencilIcon class="ml-2 h-4 w-4" />
        </a>
        <button v-else-if="template.is_public" class="btn btn-sm btn-warning" @click="handleFork" title="Fork template">
          Fork
          <GitFork class="ml-1 h-4 w-4" />
        </button>
        <a class="btn btn-sm btn-primary" v-if="template.is_public" :href="`/overlay/${template.slug}/public`" target="_blank">
          Preview
          <ExternalLinkIcon class="ml-2 h-4 w-4" />
        </a>
        <a
          v-else
          onclick='alert("This template is private. You can only preview it from the Edit screen.\n\nEdit the template and click the Preview button from there. Add your own API key to the template URL to see it in action.")'
          class="btn btn-sm btn-warning cursor-not-allowed"
        >
          Preview
        </a>
      </div>
    </CardContent>
  </Card>
</template>
