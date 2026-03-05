<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
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

const page = usePage<{ flash?: { message?: string } }>();

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

const prunePeriod = ref('90');
const showPruneConfirm = ref(false);

function submitPrune() {
  router.delete(route('admin.logs.prune'), {
    data: { period: prunePeriod.value },
    onSuccess: () => { showPruneConfirm.value = false; },
  });
}

watch(prunePeriod, () => { showPruneConfirm.value = false; });
</script>

<template>
  <Head><title>Admin — Access Logs</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Access Logs" title-class="text-2xl font-bold">
        <template #actions>
          <span class="text-sm text-muted-foreground">{{ logs.total }} total</span>
        </template>
      </PageHeader>

      <div v-if="page.props.flash?.message" class="rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-700 dark:bg-green-950 dark:text-green-300">
        {{ page.props.flash.message }}
      </div>

      <div class="flex flex-wrap gap-2">
        <input v-model="templateSlug" placeholder="Template slug…" class="rounded border px-3 py-1.5 text-sm bg-background" />
        <input v-model="from" type="date" class="rounded border px-3 py-1.5 text-sm bg-background" />
        <input v-model="to" type="date" class="rounded border px-3 py-1.5 text-sm bg-background" />
      </div>

      <div class="flex items-center gap-2 rounded border border-destructive/30 bg-destructive/5 px-3 py-2">
        <span class="text-sm text-muted-foreground">Prune entries older than</span>
        <select v-model="prunePeriod" class="rounded border px-2 py-1 text-sm bg-background" @change="showPruneConfirm = false">
          <option value="30">30 days</option>
          <option value="60">60 days</option>
          <option value="90">90 days</option>
          <option value="all">All records</option>
        </select>
        <template v-if="!showPruneConfirm">
          <button
            class="rounded border border-destructive px-3 py-1 text-sm text-destructive hover:bg-destructive hover:text-destructive-foreground"
            @click="showPruneConfirm = true"
          >
            Prune
          </button>
        </template>
        <template v-else>
          <span class="text-sm font-medium text-destructive">
            {{ prunePeriod === 'all' ? 'Delete ALL access log records?' : `Delete all entries older than ${prunePeriod} days?` }}
          </span>
          <button class="rounded border border-destructive bg-destructive px-3 py-1 text-sm text-destructive-foreground hover:bg-destructive/90" @click="submitPrune">
            Yes, prune
          </button>
          <button class="rounded border px-3 py-1 text-sm hover:bg-muted" @click="showPruneConfirm = false">
            Cancel
          </button>
        </template>
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
