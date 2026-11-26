<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PencilIcon, PlusIcon, Trash2 } from '@lucide/vue';
import { useConfirm } from '@/composables/useConfirm';

interface Highlight {
  id: number;
  body: string;
  link_url: string;
  link_label: string;
  sort_order: number;
  enabled: boolean;
}

defineProps<{ highlights: Highlight[] }>();

const { confirm } = useConfirm();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Highlights', href: route('admin.highlights.index') },
];

async function handleDelete(h: Highlight) {
  if (await confirm({ message: `Delete the highlight linking to ${h.link_url}? This cannot be undone.`, confirmLabel: 'Delete' })) {
    router.delete(route('admin.highlights.destroy', h.id));
  }
}
</script>

<template>
  <Head><title>Admin - Highlights</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Dashboard highlights" title-class="text-2xl font-bold">
        <template #actions>
          <Link :href="route('admin.highlights.create')" class="btn btn-primary cursor-pointer">
            <PlusIcon class="mr-2 h-4 w-4" />
            New highlight
          </Link>
        </template>
      </PageHeader>

      <p class="text-sm text-muted-foreground">
        Short notes about features worth knowing, each with one link. Every dashboard shows the enabled ones, lowest order first.
      </p>

      <div class="overflow-x-auto rounded border border-sidebar">
        <table class="w-full text-sm">
          <thead class="bg-card text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Order</th>
              <th class="px-3 py-2">Highlight</th>
              <th class="px-3 py-2">Link</th>
              <th class="px-3 py-2">Shown</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in highlights" :key="h.id" class="border-t border-sidebar align-top">
              <td class="px-3 py-2 font-mono text-xs text-muted-foreground tabular-nums">{{ h.sort_order }}</td>
              <td class="max-w-xl px-3 py-2">
                <Link class="cursor-pointer hover:underline" :href="route('admin.highlights.edit', h.id)">{{ h.body }}</Link>
              </td>
              <td class="px-3 py-2">
                <div>{{ h.link_label }}</div>
                <div class="font-mono text-xs text-muted-foreground">{{ h.link_url }}</div>
              </td>
              <td class="px-3 py-2 text-xs">{{ h.enabled ? 'Yes' : 'No' }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <Link
                  :href="route('admin.highlights.edit', h.id)"
                  class="mr-3 inline-flex cursor-pointer items-center text-xs text-primary hover:underline"
                >
                  <PencilIcon class="mr-1 h-3.5 w-3.5" />
                  Edit
                </Link>
                <button
                  type="button"
                  class="inline-flex cursor-pointer items-center text-xs text-destructive hover:underline"
                  @click="handleDelete(h)"
                >
                  <Trash2 class="mr-1 h-3.5 w-3.5" />
                  Delete
                </button>
              </td>
            </tr>
            <EmptyState v-if="highlights.length === 0" :colspan="5" message="No highlights yet." />
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
