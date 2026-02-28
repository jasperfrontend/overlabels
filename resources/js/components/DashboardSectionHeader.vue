<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { List, Plus } from 'lucide-vue-next';

defineProps<{
  title: string;

  viewHref: string;
  viewTitle?: string | null | undefined;

  createHref?: string | null | undefined;
  createTitle?: string | null | undefined;

  createIconOnly?: boolean; // if later you want labels conditionally
}>();
</script>

<template>
  <div class="flex flex-col justify-between md:flex-row">
    <Heading :title="title" />

    <div class="mt-2 flex gap-2 md:mt-0 md:flex-row">
      <a
        class="btn btn-sm btn-chill flex-1 md:flex-none"
        :href="viewHref"
        :title="viewTitle ?? `View all ${title.toLowerCase()}`"
        aria-label="View all"
      >
        <List class="h-4 w-4" />
      </a>

      <a
        v-if="createHref"
        class="btn btn-sm btn-primary flex-1 md:flex-none"
        :href="createHref"
        :title="createTitle ?? `Create new`"
        aria-label="Create new"
      >
        <Plus class="h-4 w-4" />
      </a>
    </div>
  </div>
</template>
