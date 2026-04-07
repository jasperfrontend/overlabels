<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface AuditLog {
  id: number;
  action: string;
  target_type: string | null;
  target_id: number | null;
  metadata: Record<string, unknown> | null;
  ip_address: string | null;
  created_at: string;
  admin: { id: number; name: string } | null;
}

interface Paginator {
  data: AuditLog[];
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
  logs: Paginator;
  filters: { admin_id?: number; action?: string; from?: string; to?: string };
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Audit Log', href: route('admin.audit.index') }
];

const action = ref(props.filters.action ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

let debounce: ReturnType<typeof setTimeout>;

function applyFilters() {
  router.get(route('admin.audit.index'), {
    action: action.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined
  }, { preserveState: true, replace: true });
}

watch([action, from, to], () => {
  clearTimeout(debounce);
  debounce = setTimeout(applyFilters, 400);
});
</script>

<template>
  <Head><title>Admin — Audit Log</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Audit Log" title-class="text-2xl font-bold">
        <template #actions>
          <span class="text-sm text-muted-foreground">{{ logs.total }} entries</span>
        </template>
      </PageHeader>

      <div class="flex flex-wrap gap-2">
        <input v-model="action" placeholder="Filter by action…"
               class="rounded border px-3 py-1.5 text-sm bg-background" />
        <input v-model="from" type="date" class="rounded border px-3 py-1.5 text-sm bg-background" />
        <input v-model="to" type="date" class="rounded border px-3 py-1.5 text-sm bg-background" />
      </div>

      <!-- Card view (< lg) -->
      <div class="lg:hidden space-y-2">
        <EmptyState v-if="logs.data.length === 0" message="No audit entries found." />
        <div v-for="log in logs.data" :key="`card-${log.id}`" class="rounded border p-3 text-sm">
          <div class="flex items-start justify-between gap-2">
            <div class="font-mono text-xs font-medium">{{ log.action }}</div>
            <span class="shrink-0 text-xs text-muted-foreground">{{ log.created_at }}</span>
          </div>
          <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span>{{ log.admin?.name ?? 'Unknown' }}</span>
            <span v-if="log.target_type">{{ log.target_type }}#{{ log.target_id }}</span>
            <span v-if="log.ip_address">{{ log.ip_address }}</span>
          </div>
        </div>
      </div>

      <!-- Table (≥ lg) -->
      <div class="hidden lg:block overflow-x-auto rounded border border-sidebar">
        <table class="w-full text-sm">
          <thead class="bg-card text-left text-muted-foreground">
          <tr>
            <th class="px-3 py-2">Admin</th>
            <th class="px-3 py-2">Action</th>
            <th class="px-3 py-2">Target</th>
            <th class="px-3 py-2">IP</th>
            <th class="px-3 py-2">When</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="log in logs.data" :key="log.id" class="border-t border-sidebar">
            <td class="px-3 py-2 font-medium">{{ log.admin?.name ?? 'Unknown' }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ log.action }}</td>
            <td class="px-3 py-2 text-xs text-muted-foreground">
              <span v-if="log.target_type">{{ log.target_type }}#{{ log.target_id }}</span>
              <span v-else>—</span>
            </td>
            <td class="px-3 py-2 text-xs text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
            <td class="px-3 py-2 text-xs text-muted-foreground">{{ log.created_at }}</td>
          </tr>
          <EmptyState v-if="logs.data.length === 0" :colspan="5" message="No audit entries found." />
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
