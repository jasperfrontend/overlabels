<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Globe, Lock, Eye, GitFork, Bell, Layers } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

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
  router.post(`/templates/${props.template.id}/fork`);
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
  <Card
    class="group relative flex h-full flex-col overflow-hidden transition-all hover:border-accent-foreground/15 hover:shadow-md dark:hover:border-accent-foreground/40"
  >
    <CardHeader class="px-4 pb-4">
      <div class="space-y-2">
        <div class="flex items-start justify-between gap-2">
          <CardTitle class="min-w-0 flex-1 text-base">
            <Link
              :href="`/templates/${template.id}`"
              class="block truncate transition-colors hover:text-accent-foreground/80"
              :title="`View details: ${template.name}`"
            >
              {{ template.name }}
            </Link>
          </CardTitle>
          <div class="flex flex-shrink-0 items-center gap-2">
            <div
              class="rounded-full p-1.5"
              :class="template.is_public ? 'bg-green-500/10' : 'bg-violet-500/10'"
              :title="template.is_public ? 'Public template' : 'Private template'"
            >
              <component :is="template.is_public ? Globe : Lock" :class="['h-4 w-4', template.is_public ? 'text-green-600' : 'text-violet-600']" />
            </div>
          </div>
        </div>
        <CardDescription v-if="template.description" class="line-clamp-2 text-sm">
          {{ template.description }}
        </CardDescription>
      </div>
    </CardHeader>

    <CardContent class="flex flex-1 flex-col justify-between space-y-4">
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4 text-sm text-muted-foreground">
            <div class="flex items-center gap-1.5" title="Views">
              <Eye class="h-4 w-4" />
              <span>{{ template.view_count || 0 }}</span>
            </div>
            <div class="flex items-center gap-1.5" title="Forks">
              <GitFork class="h-4 w-4" />
              <span>{{ template.fork_count || 0 }}</span>
            </div>

          </div>

          <span class="text-xs text-muted-foreground" title="Last updated">
            {{ formatDate(template.updated_at) }}
          </span>
        </div>

        <div v-if="showOwner && template.owner" class="flex items-center gap-2 border-t pt-3">
          <img v-if="template.owner.avatar" :src="template.owner.avatar" :alt="template.owner.name" class="h-6 w-6 rounded-full" />
          <span class="truncate text-sm text-muted-foreground"> by {{ template.owner.name }} </span>
        </div>
      </div>

      <div class="flex gap-2 pt-2">
        <Button v-if="isOwnTemplate" size="sm" variant="outline" class="flex-1" asChild title="Edit template">
          <Link :href="`/templates/${template.id}/edit`"> Edit </Link>
        </Button>
        <Button v-else-if="template.is_public" size="sm" variant="outline" class="flex-1" @click="handleFork" title="Fork template">
          <GitFork class="mr-1 h-3 w-3" />
          Fork
        </Button>
        <Button size="sm" variant="outline" class="flex-1" asChild>
          <Link v-if="template.is_public" :href="`/overlay/${template.slug}/public`" target="_blank"> Preview </Link>
          <a
            v-else
            onclick='alert("This template is private. You can only preview it from the Edit screen.\n\nEdit the template and click the Preview button from there. Add your own API key to the template URL to see it in action.")'
            class="cursor-not-allowed"
          >
            Preview
          </a>
        </Button>
      </div>
    </CardContent>
  </Card>
</template>
