<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Radio } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

defineProps<{
  sessions: unknown[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Stream Sessions', href: '/dashboard/stream-sessions' },
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Stream Sessions" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
      <div class="flex items-center gap-3">
        <Radio class="h-6 w-6" />
        <Heading title="Stream Sessions (raw JSON)" />
      </div>

      <p class="text-sm text-foreground max-w-2xl">
        Backend wiring only. This route returns aggregated stats per stream session;
        the UI is not built yet. Use this dump to design the page.
      </p>

      <pre class="text-xs bg-card border rounded-lg p-4 overflow-auto whitespace-pre-wrap break-all">{{ JSON.stringify(sessions, null, 2) }}</pre>
    </div>
  </AppLayout>
</template>
