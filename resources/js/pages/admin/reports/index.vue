<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();
interface ReportEntry {
  id: number;
  reason: string;
  status: 'open' | 'read';
  ip_address: string | null;
  created_at: string;
  reviewed_at: string | null;
  reviewed_by: string | null;
  reporter: {
    label: string | null;
    user_id: number | null;
    is_authenticated: boolean;
  };
  template: {
    name: string;
    slug: string;
    id: number | null;
    url: string | null;
    admin_url: string | null;
    is_public: boolean;
  };
}

interface Paginator {
  data: ReportEntry[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
  reports: Paginator;
  filters: {
    status?: string;
    search?: string;
  };
  stats: {
    open: number;
    read: number;
  };
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'User Reports', href: route('admin.reports.index') },
];

const status = ref(props.filters.status ?? 'open');
const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout>;

function applyFilters() {
  router.get(
    route('admin.reports.index'),
    {
      status: status.value || undefined,
      search: search.value || undefined,
    },
    { preserveState: true, replace: true },
  );
}

watch([status, search], () => {
  clearTimeout(debounce);
  debounce = setTimeout(applyFilters, 400);
});

function setStatus(report: ReportEntry, next: 'open' | 'read') {
  router.patch(route('admin.reports.update', report.id), { status: next }, { preserveScroll: true });
}

async function deleteReport(id: number) {
  if (await confirm({ message: 'Delete this report? The reason is copied to the audit log first.', confirmLabel: 'Delete' })) {
    router.delete(route('admin.reports.destroy', id), { preserveScroll: true });
  }
}
</script>

<template>
  <Head><title>Admin - User Reports</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="User Reports" description="Reports submitted about publicly listed overlays." title-class="text-2xl font-bold">
        <template #actions>
          <div class="flex items-center gap-3 text-sm">
            <Badge variant="outline">{{ stats.open }} open</Badge>
            <Badge variant="secondary">{{ stats.read }} read</Badge>
          </div>
        </template>
      </PageHeader>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <select v-model="status" class="cursor-pointer rounded border bg-background px-3 py-1.5 text-sm">
          <option value="open">Open</option>
          <option value="read">Read</option>
          <option value="all">All</option>
        </select>
        <Input v-model="search" placeholder="Search reason, overlay or email..." class="w-72" />
      </div>

      <!-- Card view (< lg) -->
      <div class="space-y-2 lg:hidden">
        <EmptyState v-if="reports.data.length === 0" message="No reports found." />
        <div v-for="report in reports.data" :key="`card-${report.id}`" class="rounded border p-3 text-sm">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <Badge :variant="report.status === 'open' ? 'destructive' : 'secondary'">{{ report.status }}</Badge>
                <a
                  v-if="report.template.url"
                  :href="report.template.url"
                  target="_blank"
                  rel="noopener"
                  class="cursor-pointer font-medium hover:underline"
                  >{{ report.template.name }}</a
                >
                <span v-else class="font-medium line-through">{{ report.template.name }}</span>
              </div>
              <p class="mt-1.5 whitespace-pre-wrap text-foreground">{{ report.reason }}</p>
            </div>
          </div>
          <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span>
              by
              <a v-if="report.reporter.user_id" :href="route('admin.users.show', report.reporter.user_id)" class="cursor-pointer hover:underline">{{
                report.reporter.label
              }}</a>
              <span v-else>{{ report.reporter.label }}</span>
              <Badge v-if="!report.reporter.is_authenticated" variant="outline" class="ml-1 text-[10px]"> unverified </Badge>
            </span>
            <span>{{ new Date(report.created_at).toLocaleString() }}</span>
          </div>
          <div class="mt-2 flex gap-3">
            <button v-if="report.status === 'open'" class="cursor-pointer text-xs hover:underline" @click="setStatus(report, 'read')">
              Mark as read
            </button>
            <button v-else class="cursor-pointer text-xs hover:underline" @click="setStatus(report, 'open')">Reopen</button>
            <a v-if="report.template.admin_url" :href="report.template.admin_url" class="cursor-pointer text-xs hover:underline">Inspect overlay</a>
            <button class="cursor-pointer text-xs text-destructive hover:underline" @click="deleteReport(report.id)">Delete</button>
          </div>
        </div>
      </div>

      <!-- Table (>= lg) -->
      <div class="hidden overflow-x-auto rounded border border-sidebar lg:block">
        <table class="w-full text-sm">
          <thead class="bg-card text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Reporter</th>
              <th class="px-3 py-2">Overlay</th>
              <th class="px-3 py-2">Reason</th>
              <th class="px-3 py-2">Reported</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="report in reports.data" :key="report.id" class="border-t border-sidebar align-top">
              <td class="px-3 py-2">
                <Badge :variant="report.status === 'open' ? 'destructive' : 'secondary'">{{ report.status }}</Badge>
                <div v-if="report.reviewed_by" class="mt-1 text-[10px] text-muted-foreground">by {{ report.reviewed_by }}</div>
              </td>
              <td class="px-3 py-2">
                <a v-if="report.reporter.user_id" :href="route('admin.users.show', report.reporter.user_id)" class="cursor-pointer hover:underline">{{
                  report.reporter.label
                }}</a>
                <span v-else>{{ report.reporter.label }}</span>
                <Badge v-if="!report.reporter.is_authenticated" variant="outline" class="ml-1 text-[10px]"> unverified </Badge>
                <div v-if="report.ip_address" class="mt-1 font-mono text-[10px] text-muted-foreground">
                  {{ report.ip_address }}
                </div>
              </td>
              <td class="px-3 py-2">
                <a v-if="report.template.url" :href="report.template.url" target="_blank" rel="noopener" class="cursor-pointer hover:underline">{{
                  report.template.name
                }}</a>
                <span v-else class="line-through">{{ report.template.name }}</span>
                <div class="mt-1 font-mono text-[10px] text-muted-foreground">{{ report.template.slug }}</div>
                <Badge v-if="report.template.id && !report.template.is_public" variant="outline" class="mt-1 text-[10px]"> now private </Badge>
                <div v-if="!report.template.id" class="mt-1 text-[10px] text-muted-foreground">overlay deleted</div>
              </td>
              <td class="max-w-md px-3 py-2 whitespace-pre-wrap text-foreground">{{ report.reason }}</td>
              <td class="px-3 py-2 text-xs text-muted-foreground">
                {{ new Date(report.created_at).toLocaleString() }}
              </td>
              <td class="px-3 py-2">
                <div class="flex flex-col items-start gap-1">
                  <button v-if="report.status === 'open'" class="cursor-pointer text-xs hover:underline" @click="setStatus(report, 'read')">
                    Mark as read
                  </button>
                  <button v-else class="cursor-pointer text-xs hover:underline" @click="setStatus(report, 'open')">Reopen</button>
                  <a v-if="report.template.admin_url" :href="report.template.admin_url" class="cursor-pointer text-xs hover:underline">Inspect</a>
                  <button class="cursor-pointer text-xs text-destructive hover:underline" @click="deleteReport(report.id)">Delete</button>
                </div>
              </td>
            </tr>
            <EmptyState v-if="reports.data.length === 0" :colspan="6" message="No reports found." />
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="reports.links?.length > 3" class="flex gap-1">
        <template v-for="link in reports.links" :key="link.label">
          <a
            v-if="link.url"
            :href="link.url"
            class="cursor-pointer rounded border px-3 py-1 text-sm"
            :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
            v-html="link.label"
          />
          <span v-else class="rounded border px-3 py-1 text-sm opacity-40" v-html="link.label" />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
