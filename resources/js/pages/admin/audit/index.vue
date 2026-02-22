<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
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
  { title: 'Audit Log', href: route('admin.audit.index') },
];

const action = ref(props.filters.action ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

let debounce: ReturnType<typeof setTimeout>;
function applyFilters() {
  router.get(route('admin.audit.index'), {
    action: action.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined,
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
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Audit Log</h1>
        <span class="text-sm text-muted-foreground">{{ logs.total }} entries</span>
      </div>

      <div class="flex flex-wrap gap-2">
        <input v-model="action" placeholder="Filter by action…" class="rounded border px-3 py-1.5 text-sm bg-background" />
        <input v-model="from" type="date" class="rounded border px-3 py-1.5 text-sm bg-background" />
        <input v-model="to" type="date" class="rounded border px-3 py-1.5 text-sm bg-background" />
      </div>

      <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Admin</th>
              <th class="px-3 py-2">Action</th>
              <th class="px-3 py-2">Target</th>
              <th class="px-3 py-2">IP</th>
              <th class="px-3 py-2">When</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs.data" :key="log.id" class="border-t">
              <td class="px-3 py-2 font-medium">{{ log.admin?.name ?? 'Unknown' }}</td>
              <td class="px-3 py-2 font-mono text-xs">{{ log.action }}</td>
              <td class="px-3 py-2 text-xs text-muted-foreground">
                <span v-if="log.target_type">{{ log.target_type }}#{{ log.target_id }}</span>
                <span v-else>—</span>
              </td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ log.created_at }}</td>
            </tr>
            <tr v-if="logs.data.length === 0">
              <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">No audit entries found.</td>
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
