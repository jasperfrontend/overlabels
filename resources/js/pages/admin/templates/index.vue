<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';

interface Template {
  id: number;
  name: string;
  slug: string;
  type: string;
  is_public: boolean;
  fork_count: number;
  view_count: number;
  created_at: string;
  owner: { id: number; name: string; twitch_id: string | null } | null;
}

interface Paginator {
  data: Template[];
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
  { title: 'Templates', href: route('admin.templates.index') },
];

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const owner = ref(props.filters.owner ?? '');

let debounce: ReturnType<typeof setTimeout>;

function applyFilters() {
  router.get(route('admin.templates.index'), {
    search: search.value || undefined,
    type: type.value || undefined,
    owner: owner.value || undefined,
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
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Templates</h1>
        <span class="text-sm text-muted-foreground">{{ templates.total }} total</span>
      </div>

      <div class="flex flex-wrap gap-2">
        <Input v-model="search" placeholder="Search name or slug…" class="w-64" />
        <select v-model="type" class="rounded border px-3 py-1.5 text-sm bg-background">
          <option value="">All types</option>
          <option value="static">static</option>
          <option value="alert">alert</option>
        </select>
        <Input v-model="owner" placeholder="Filter by owner…" class="w-48" />
      </div>

      <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
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
            <tr v-for="t in templates.data" :key="t.id" class="border-t">
              <td class="px-3 py-2">
                <div class="font-medium">{{ t.name }}</div>
                <div class="text-xs text-muted-foreground font-mono">{{ t.slug }}</div>
              </td>
              <td class="px-3 py-2">
                <a v-if="t.owner" :href="route('admin.users.show', t.owner.id)" class="hover:underline">{{ t.owner.name }}</a>
                <span v-else class="text-muted-foreground">—</span>
              </td>
              <td class="px-3 py-2"><Badge variant="outline">{{ t.type }}</Badge></td>
              <td class="px-3 py-2"><Badge :variant="t.is_public ? 'default' : 'secondary'">{{ t.is_public ? 'public' : 'private' }}</Badge></td>
              <td class="px-3 py-2">{{ t.fork_count }}</td>
              <td class="px-3 py-2">{{ t.view_count }}</td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ t.created_at }}</td>
              <td class="px-3 py-2">
                <a :href="route('admin.templates.show', t.id)" class="text-primary text-xs hover:underline">View</a>
              </td>
            </tr>
            <tr v-if="templates.data.length === 0">
              <td colspan="8" class="px-3 py-6 text-center text-muted-foreground">No templates found.</td>
            </tr>
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
