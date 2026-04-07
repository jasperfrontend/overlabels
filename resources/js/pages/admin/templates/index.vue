<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';
import type { AdminTemplate } from '@/types';

interface Paginator {
  data: AdminTemplate[];
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
  templates: Paginator;
  filters: {
    search?: string;
    type?: string;
    is_public?: boolean;
    owner?: string;
  };
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Templates', href: route('admin.templates.index') }
];

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const owner = ref(props.filters.owner ?? '');

let debounce: ReturnType<typeof setTimeout>;

function applyFilters() {
  router.get(route('admin.templates.index'), {
    search: search.value || undefined,
    type: type.value || undefined,
    owner: owner.value || undefined
  }, { preserveState: true, replace: true });
}

watch([search, type, owner], () => {
  clearTimeout(debounce);
  debounce = setTimeout(applyFilters, 400);
});
</script>

<template>
  <Head><title>Admin — Templates</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Templates" title-class="text-2xl font-bold">
        <template #actions>
          <span class="text-sm text-muted-foreground">{{ templates.total }} total</span>
        </template>
      </PageHeader>

      <div class="flex flex-wrap gap-2">
        <Input v-model="search" placeholder="Search name or slug…" class="w-64" />
        <select v-model="type" class="rounded border px-3 py-1.5 text-sm bg-background">
          <option value="">All types</option>
          <option value="static">static</option>
          <option value="alert">alert</option>
        </select>
        <Input v-model="owner" placeholder="Filter by owner…" class="w-48" />
      </div>

      <!-- Card view (< lg) -->
      <div class="lg:hidden space-y-2">
        <EmptyState v-if="templates.data.length === 0" message="No templates found." />
        <div v-for="t in templates.data" :key="`card-${t.id}`" class="rounded border p-3 text-sm">
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="font-medium">{{ t.name }}</div>
              <div class="font-mono text-xs text-muted-foreground">{{ t.slug }}</div>
            </div>
            <a :href="route('admin.templates.show', t.id)"
               class="shrink-0 text-primary text-xs hover:underline">View</a>
          </div>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <Badge variant="outline">{{ t.type }}</Badge>
            <Badge :variant="t.is_public ? 'default' : 'secondary'">{{ t.is_public ? 'public' : 'private' }}</Badge>
          </div>
          <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span v-if="t.owner">
              Owner: <a :href="route('admin.users.show', t.owner.id)" class="hover:underline">{{ t.owner.name }}</a>
            </span>
            <span>{{ t.fork_count }} forks</span>
            <span>{{ t.view_count }} views</span>
            <span>{{ t.created_at }}</span>
          </div>
        </div>
      </div>

      <!-- Table (≥ lg) | random change to force Railway rebuild -->
      <div class="hidden lg:block overflow-x-auto rounded border border-sidebar">
        <table class="w-full text-sm">
          <thead class="bg-card text-left text-muted-foreground">
          <tr>
            <th class="px-3 py-2">Name</th>
            <th class="px-3 py-2">Owner</th>
            <th class="px-3 py-2">Type</th>
            <th class="px-3 py-2">Public</th>
            <th class="px-3 py-2">Forks</th>
            <th class="px-3 py-2">Views</th>
            <th class="px-3 py-2">Created</th>
            <th class="px-3 py-2"></th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="t in templates.data" :key="t.id" class="border-t border-sidebar">
            <td class="px-3 py-2">
              <div class="font-medium">
                <Link class="hover:underline" :href="route('admin.templates.show', t.id)">{{ t.name }}</Link>
              </div>
              <div class="text-xs text-muted-foreground font-mono">{{ t.slug }}</div>
            </td>
            <td class="px-3 py-2">
              <a v-if="t.owner" :href="route('admin.users.show', t.owner.id)" class="hover:underline">{{ t.owner.name
                }}</a>
              <span v-else class="text-muted-foreground">—</span>
            </td>
            <td class="px-3 py-2">
              <Badge variant="outline">{{ t.type }}</Badge>
            </td>
            <td class="px-3 py-2">
              <Badge :variant="t.is_public ? 'default' : 'secondary'">{{ t.is_public ? 'public' : 'private' }}</Badge>
            </td>
            <td class="px-3 py-2">{{ t.fork_count }}</td>
            <td class="px-3 py-2">{{ t.view_count }}</td>
            <td class="px-3 py-2 text-xs text-muted-foreground">{{ t.created_at }}</td>
            <td class="px-3 py-2">
              <a :href="route('admin.templates.show', t.id)" class="text-primary text-xs hover:underline">View</a>
            </td>
          </tr>
          <EmptyState v-if="templates.data.length === 0" :colspan="8" message="No templates found." />
          </tbody>
        </table>
      </div>

      <div class="flex gap-1">
        <template v-for="link in templates.links" :key="link.label">
          <a v-if="link.url" :href="link.url" class="rounded border px-3 py-1 text-sm"
             :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'" v-html="link.label" />
          <span v-else class="rounded border px-3 py-1 text-sm opacity-40" v-html="link.label" />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
