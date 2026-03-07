<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';

interface BanEntry {
  id: number;
  bannable_type: string | null;
  bannable_id: number | null;
  ip: string | null;
  comment: string | null;
  expired_at: string | null;
  created_at: string;
  bannable: { id: number; name: string; twitch_id: string | null } | null;
  created_by: { id: number; name: string } | null;
}

interface Paginator {
  data: BanEntry[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
  bans: Paginator;
  filters: {
    status?: string;
    type?: string;
    search?: string;
  };
  stats: {
    active: number;
    user_bans: number;
    ip_bans: number;
  };
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Bans', href: route('admin.bans.index') },
];

// Filters
const status = ref(props.filters.status ?? 'active');
const type = ref(props.filters.type ?? '');
const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout>;

function applyFilters() {
  router.get(route('admin.bans.index'), {
    status: status.value || undefined,
    type: type.value || undefined,
    search: search.value || undefined,
  }, { preserveState: true, replace: true });
}

watch([status, type, search], () => {
  clearTimeout(debounce);
  debounce = setTimeout(applyFilters, 400);
});

// Create ban form
const showForm = ref(false);
const form = useForm({
  type: 'ip' as 'user' | 'ip',
  user_id: '' as string | number,
  ip: '',
  comment: '',
  duration: 'permanent',
});

function submitBan() {
  form.post(route('admin.bans.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      showForm.value = false;
    },
  });
}

// Unban
function removeBan(id: number) {
  if (confirm('Remove this ban?')) {
    router.delete(route('admin.bans.destroy', id), { preserveScroll: true });
  }
}

function banType(ban: BanEntry): string {
  return ban.bannable_type ? 'User' : 'IP';
}

function banTarget(ban: BanEntry): string {
  if (ban.bannable) return ban.bannable.name;
  if (ban.ip) return ban.ip;
  return '—';
}

function formatExpiry(ban: BanEntry): string {
  if (!ban.expired_at) return 'Permanent';
  return new Date(ban.expired_at).toLocaleString();
}

const durations = [
  { value: '1h', label: '1 hour' },
  { value: '6h', label: '6 hours' },
  { value: '24h', label: '24 hours' },
  { value: '7d', label: '7 days' },
  { value: '30d', label: '30 days' },
  { value: 'permanent', label: 'Permanent' },
];
</script>

<template>
  <Head><title>Admin — Bans</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Ban Management" title-class="text-2xl font-bold">
        <template #actions>
          <div class="flex items-center gap-3 text-sm">
            <Badge variant="outline">{{ stats.active }} active</Badge>
            <Badge variant="secondary">{{ stats.user_bans }} users</Badge>
            <Badge variant="secondary">{{ stats.ip_bans }} IPs</Badge>
            <button @click="showForm = !showForm" class="rounded bg-primary px-3 py-1.5 text-primary-foreground text-sm hover:bg-primary/90">
              {{ showForm ? 'Cancel' : 'Create Ban' }}
            </button>
          </div>
        </template>
      </PageHeader>

      <!-- Create Ban Form -->
      <div v-if="showForm" class="rounded border p-4 space-y-3">
        <h3 class="text-sm font-medium">Create New Ban</h3>
        <div class="flex flex-wrap gap-3">
          <select v-model="form.type" class="rounded border px-3 py-1.5 text-sm bg-background">
            <option value="ip">IP Address</option>
            <option value="user">User</option>
          </select>

          <Input v-if="form.type === 'ip'" v-model="form.ip" placeholder="IP address (e.g. 1.2.3.4)" class="w-48" />
          <Input v-else v-model="form.user_id" type="number" placeholder="User ID" class="w-32" />

          <Input v-model="form.comment" placeholder="Reason (optional)" class="w-64" />

          <select v-model="form.duration" class="rounded border px-3 py-1.5 text-sm bg-background">
            <option v-for="d in durations" :key="d.value" :value="d.value">{{ d.label }}</option>
          </select>

          <button @click="submitBan" :disabled="form.processing" class="rounded bg-destructive px-3 py-1.5 text-destructive-foreground text-sm hover:bg-destructive/90 disabled:opacity-50">
            Ban
          </button>
        </div>
        <div v-if="form.errors.user_id || form.errors.ip || form.errors.type" class="text-sm text-destructive">
          {{ form.errors.user_id || form.errors.ip || form.errors.type }}
        </div>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <select v-model="status" class="rounded border px-3 py-1.5 text-sm bg-background">
          <option value="active">Active</option>
          <option value="expired">Expired</option>
          <option value="all">All</option>
        </select>
        <select v-model="type" class="rounded border px-3 py-1.5 text-sm bg-background">
          <option value="">All types</option>
          <option value="user">User bans</option>
          <option value="ip">IP bans</option>
        </select>
        <Input v-model="search" placeholder="Search IP or comment…" class="w-64" />
      </div>

      <!-- Card view (< lg) -->
      <div class="lg:hidden space-y-2">
        <EmptyState v-if="bans.data.length === 0" message="No bans found." />
        <div v-for="ban in bans.data" :key="`card-${ban.id}`" class="rounded border p-3 text-sm">
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="flex items-center gap-2">
                <Badge :variant="ban.bannable_type ? 'default' : 'secondary'">{{ banType(ban) }}</Badge>
                <span class="font-medium">
                  <a v-if="ban.bannable" :href="route('admin.users.show', ban.bannable.id)" class="hover:underline">{{ banTarget(ban) }}</a>
                  <span v-else>{{ banTarget(ban) }}</span>
                </span>
              </div>
              <div v-if="ban.comment" class="mt-1 text-xs text-muted-foreground">{{ ban.comment }}</div>
            </div>
            <button @click="removeBan(ban.id)" class="shrink-0 text-xs text-destructive hover:underline">Unban</button>
          </div>
          <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <Badge v-if="!ban.expired_at" variant="destructive" class="text-[10px]">Permanent</Badge>
            <span v-else>Expires {{ formatExpiry(ban) }}</span>
            <span v-if="ban.created_by">by {{ ban.created_by.name }}</span>
            <span>{{ new Date(ban.created_at).toLocaleString() }}</span>
          </div>
        </div>
      </div>

      <!-- Table (≥ lg) -->
      <div class="hidden lg:block overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Type</th>
              <th class="px-3 py-2">Target</th>
              <th class="px-3 py-2">Comment</th>
              <th class="px-3 py-2">Expires</th>
              <th class="px-3 py-2">Created by</th>
              <th class="px-3 py-2">Created</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ban in bans.data" :key="ban.id" class="border-t">
              <td class="px-3 py-2">
                <Badge :variant="ban.bannable_type ? 'default' : 'secondary'">{{ banType(ban) }}</Badge>
              </td>
              <td class="px-3 py-2">
                <a v-if="ban.bannable" :href="route('admin.users.show', ban.bannable.id)" class="hover:underline">{{ banTarget(ban) }}</a>
                <span v-else class="font-mono">{{ banTarget(ban) }}</span>
              </td>
              <td class="px-3 py-2 text-muted-foreground max-w-xs truncate">{{ ban.comment ?? '—' }}</td>
              <td class="px-3 py-2">
                <Badge v-if="!ban.expired_at" variant="destructive">Permanent</Badge>
                <span v-else class="text-xs text-muted-foreground">{{ formatExpiry(ban) }}</span>
              </td>
              <td class="px-3 py-2 text-muted-foreground">{{ ban.created_by?.name ?? '—' }}</td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ new Date(ban.created_at).toLocaleString() }}</td>
              <td class="px-3 py-2">
                <button @click="removeBan(ban.id)" class="text-xs text-destructive hover:underline">Unban</button>
              </td>
            </tr>
            <EmptyState v-if="bans.data.length === 0" :colspan="7" message="No bans found." />
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="bans.links?.length > 3" class="flex gap-1">
        <template v-for="link in bans.links" :key="link.label">
          <a
            v-if="link.url"
            :href="link.url"
            class="rounded border px-3 py-1 text-sm"
            :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
            v-html="link.label"
          />
          <span v-else class="rounded border px-3 py-1 text-sm opacity-40" v-html="link.label" />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
