<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
  createdAt: string;
  updatedAt: string;
  viewCount: number;
  forkCount: number;
  templateTags?: string[] | null;
  forkParent?: { id: number; slug: string; name: string } | null;
}>();

const forkTitle = computed(() => {
  if (!props.forkParent) return '';
  return `Copied from "${props.forkParent.name}"`;
});
</script>

<template>
  <div class="space-y-4">
    <div class="grid grid-cols-2 gap-4 rounded-sm bg-background p-4 text-sm">
      <div>
        <span class="text-muted-foreground">Created:</span>
        <span class="ml-2">{{ new Date(createdAt).toLocaleDateString() }}</span>
      </div>
      <div>
        <span class="text-muted-foreground">Last updated:</span>
        <span class="ml-2">{{ new Date(updatedAt).toLocaleDateString() }}</span>
      </div>
      <div>
        <span class="text-muted-foreground">Views:</span>
        <span class="ml-2">{{ viewCount }}</span>
      </div>
      <div>
        <span class="text-muted-foreground">Copies:</span>
        <span class="ml-2">{{ forkCount }}</span>
      </div>
      <div v-if="forkParent" class="col-span-2">
        <span class="text-muted-foreground">Copied from:</span>
        <Link :href="`/templates/${forkParent.id}`" class="ml-2 text-foreground/60 transition-colors hover:text-foreground hover:underline" :title="forkTitle">
          {{ forkParent.name }}
        </Link>
      </div>
    </div>

    <div v-if="templateTags && templateTags.length > 0" class="rounded-sm bg-background p-4 text-sm">
      <p class="mb-2 text-muted-foreground">Template Tags Used</p>
      <div class="flex flex-wrap gap-1">
        <code v-for="tag in templateTags" :key="tag" class="btn btn-chill btn-xs btn-dead">
          {{ tag }}
        </code>
      </div>
    </div>
  </div>
</template>
