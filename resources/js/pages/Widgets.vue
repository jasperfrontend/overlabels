<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  twitchWidgetData: {
    type: Object,
    required: true,
  }
})

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Create Widgets',
    href: '/widgets',
  },
];

</script>

<template>
  <Head title="Create Twitch Widgets" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col items-center gap-8 px-4 py-10">
      <div class="w-full max-w-4xl">
        <h1 class="text-4xl font-extrabold tracking-tight text-center mb-6">
          Create Twitch Widgets
        </h1>
        <details class="mt-8">
          <summary class="cursor-pointer text-sm text-muted-foreground underline">
            Show raw JSON data
          </summary>
          <pre class="mt-2 max-h-96 overflow-auto bg-muted text-xs p-4 rounded-xl whitespace-pre-wrap">
            {{ props.twitchWidgetData }}
          </pre>
        </details>
      </div>
    </div>
  </AppLayout>
</template>
