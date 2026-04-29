<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';
import { PencilIcon, PlusIcon, Trash2 } from 'lucide-vue-next';
import type { Update } from '@/types';

interface Paginator {
  data: Update[];
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
  updates: Paginator;
  filters: { search?: string };
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Updates', href: route('admin.updates.index') },
];

const search = ref(props.filters.search ?? '');

let debounce: ReturnType<typeof setTimeout>;
function applyFilters() {
  router.get(
    route('admin.updates.index'),
    { search: search.value || undefined },
    { preserveState: true, replace: true }
  );
}
watch(search, () => {
  clearTimeout(debounce);
  debounce = setTimeout(applyFilters, 400);
});

function formatDate(iso: string) {
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  }).format(new Date(iso));
}

function handleDelete(u: Update) {
  if (confirm(`Delete "${u.title}"? This cannot be undone.`)) {
    router.delete(route('admin.updates.destroy', u.id));
  }
}
</script>

<template>
  <Head><title>Admin - Updates</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Updates" title-class="text-2xl font-bold">
        <template #actions>
          <span class="mr-3 text-sm text-muted-foreground">{{ updates.total }} total</span>
          <Link :href="route('admin.updates.create')" class="btn btn-primary cursor-pointer">
            <PlusIcon class="mr-2 h-4 w-4" />
            New post
          </Link>
        </template>
      </PageHeader>

      <div class="flex flex-wrap gap-2">
        <Input v-model="search" placeholder="Search title or slug..." class="w-64" />
      </div>

      <div class="overflow-x-auto rounded border border-sidebar">
        <table class="w-full text-sm">
          <thead class="bg-card text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Title</th>
              <th class="px-3 py-2">Tags</th>
              <th class="px-3 py-2">Published</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in updates.data" :key="u.id" class="border-t border-sidebar">
              <td class="px-3 py-2">
                <div class="font-medium">
                  <Link class="hover:underline cursor-pointer" :href="route('admin.updates.edit', u.id)">{{ u.title }}</Link>
                </div>
                <div class="font-mono text-xs text-muted-foreground">{{ u.slug }}</div>
              </td>
              <td class="px-3 py-2">
                <div v-if="u.tags && u.tags.length" class="flex flex-wrap gap-1">
                  <span
                    v-for="tag in u.tags"
                    :key="tag"
                    class="inline-flex items-center rounded-sm bg-sidebar px-2 py-0.5 text-xs text-foreground"
                  >
                    {{ tag }}
                  </span>
                </div>
              </td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ formatDate(u.published_at) }}</td>
              <td class="px-3 py-2 text-right">
                <Link :href="route('admin.updates.edit', u.id)" class="inline-flex items-center text-primary text-xs hover:underline mr-3 cursor-pointer">
                  <PencilIcon class="mr-1 h-3.5 w-3.5" />
                  Edit
                </Link>
                <button @click="handleDelete(u)" class="inline-flex items-center text-destructive text-xs hover:underline cursor-pointer" type="button">
                  <Trash2 class="mr-1 h-3.5 w-3.5" />
                  Delete
                </button>
              </td>
            </tr>
            <EmptyState v-if="updates.data.length === 0" :colspan="4" message="No updates yet." />
          </tbody>
        </table>
      </div>

      <div class="flex gap-1">
        <template v-for="link in updates.links" :key="link.label">
          <a v-if="link.url" :href="link.url" class="rounded border px-3 py-1 text-sm cursor-pointer"
             :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'" v-html="link.label" />
          <span v-else class="rounded border px-3 py-1 text-sm opacity-40" v-html="link.label" />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
