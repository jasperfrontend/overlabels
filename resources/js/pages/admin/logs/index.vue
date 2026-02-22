<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface AccessLog {
  id: number;
  template_slug: string | null;
  ip_address: string | null;
  user_agent: string | null;
  accessed_at: string;
  token: {
    id: number;
    name: string;
    token_prefix: string;
    user: { id: number; name: string } | null;
  } | null;
}

interface Paginator {
  data: AccessLog[];
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
  logs: Paginator;
  filters: { template_slug?: string; from?: string; to?: string };
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Access Logs', href: route('admin.logs.index') },
];

const templateSlug = ref(props.filters.template_slug ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

let debounce: ReturnType<typeof setTimeout>;
function applyFilters() {
  router.get(route('admin.logs.index'), {
    template_slug: templateSlug.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined,
  }, { preserveState: true, replace: true });
}
watch([templateSlug, from, to], () => {
  clearTimeout(debounce);
  debounce = setTimeout(applyFilters, 400);
});
</script>

<template>
  <Head><title>Admin — Access Logs</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Access Logs</h1>
        <span class="text-sm text-muted-foreground">{{ logs.total }} total</span>
      </div>

      <div class="flex flex-wrap gap-2">
        <input v-model="templateSlug" placeholder="Template slug…" class="rounded border px-3 py-1.5 text-sm bg-background" />
        <input v-model="from" type="date" class="rounded border px-3 py-1.5 text-sm bg-background" />
        <input v-model="to" type="date" class="rounded border px-3 py-1.5 text-sm bg-background" />
      </div>

      <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Template</th>
              <th class="px-3 py-2">Token / User</th>
              <th class="px-3 py-2">IP</th>
              <th class="px-3 py-2">Accessed At</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs.data" :key="log.id" class="border-t">
              <td class="px-3 py-2 font-mono text-xs">{{ log.template_slug ?? '—' }}</td>
              <td class="px-3 py-2 text-xs">
                <span v-if="log.token">{{ log.token.name }} ({{ log.token.user?.name ?? 'unknown' }})</span>
                <span v-else class="text-muted-foreground">—</span>
              </td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ log.accessed_at }}</td>
            </tr>
            <tr v-if="logs.data.length === 0">
              <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">No logs found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex gap-1">
        <template v-for="link in logs.links" :key="link.label">
          <a v-if="link.url" :href="link.url" class="rounded border px-3 py-1 text-sm"
            :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'" v-html="link.label" />
          <span v-else class="rounded border px-3 py-1 text-sm opacity-40" v-html="link.label" />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
