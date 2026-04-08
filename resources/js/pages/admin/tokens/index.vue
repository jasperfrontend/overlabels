<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { ref, watch } from 'vue';

interface Token {
  id: number;
  name: string;
  token_prefix: string;
  is_active: boolean;
  expires_at: string | null;
  access_count: number;
  last_used_at: string | null;
  created_at: string;
  user: { id: number; name: string; twitch_id: string | null } | null;
}

interface Paginator {
  data: Token[];
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{
  tokens: Paginator;
  filters: Record<string, string>;
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Tokens', href: route('admin.tokens.index') }
];

function deleteToken(id: number) {
  if (confirm('Delete this token? This will cascade access logs.')) {
    router.delete(route('admin.tokens.destroy', id));
  }
}

const prunePeriod = ref('12');
const showPruneConfirm = ref(false);

function submitPrune() {
  router.delete(route('admin.tokens.prune'), {
    data: { period: prunePeriod.value },
    onSuccess: () => { showPruneConfirm.value = false; },
  });
}

watch(prunePeriod, () => { showPruneConfirm.value = false; });
</script>

<template>
  <Head><title>Admin — Tokens</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Access Tokens" title-class="text-2xl font-bold">
        <template #actions>
          <span class="text-sm text-muted-foreground">{{ tokens.total }} total</span>
        </template>
      </PageHeader>

      <!-- Prune bar -->
      <div class="flex flex-wrap items-center gap-2 rounded border border-destructive/30 bg-destructive/5 px-3 py-2">
        <span class="text-sm text-muted-foreground">Prune unused tokens (0 uses) older than</span>
        <select v-model="prunePeriod" class="rounded border px-2 py-1 text-sm bg-background">
          <option value="6">6 months</option>
          <option value="12">12 months</option>
          <option value="24">24 months</option>
          <option value="all">Any age</option>
        </select>
        <template v-if="!showPruneConfirm">
          <button
            class="rounded border border-destructive px-3 py-1 text-sm text-destructive hover:bg-destructive hover:text-destructive-foreground"
            @click="showPruneConfirm = true">Prune
          </button>
        </template>
        <template v-else>
          <span class="text-sm font-medium text-destructive">
            {{ prunePeriod === 'all' ? 'Delete ALL unused tokens?' : `Delete all unused tokens older than ${prunePeriod} months?` }}
          </span>
          <button
            class="rounded border border-destructive bg-destructive px-3 py-1 text-sm text-destructive-foreground hover:bg-destructive/90"
            @click="submitPrune">Yes, prune
          </button>
          <button class="rounded border px-3 py-1 text-sm hover:bg-muted" @click="showPruneConfirm = false">Cancel
          </button>
        </template>
      </div>

      <!-- Card view (< lg) -->
      <div class="lg:hidden space-y-2">
        <EmptyState v-if="tokens.data.length === 0" message="No tokens found." />
        <div v-for="token in tokens.data" :key="`card-${token.id}`" class="rounded border p-3 text-sm">
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="font-medium">{{ token.name }}</div>
              <div class="font-mono text-xs text-muted-foreground">{{ token.token_prefix }}…</div>
            </div>
            <div class="flex shrink-0 gap-2">
              <a :href="route('admin.tokens.show', token.id)" class="text-primary text-xs hover:underline">View</a>
              <button @click="deleteToken(token.id)" class="text-xs text-destructive hover:underline">Delete</button>
            </div>
          </div>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <Badge :variant="token.is_active ? 'default' : 'secondary'">{{ token.is_active ? 'active' : 'inactive' }}
            </Badge>
          </div>
          <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span v-if="token.user">
              Owner: <a :href="route('admin.users.show', token.user.id)" class="hover:underline">{{ token.user.name
              }}</a>
            </span>
            <span>{{ token.access_count }} uses</span>
            <span>Expires: {{ token.expires_at ?? 'Never' }}</span>
          </div>
        </div>
      </div>

      <!-- Table (≥ lg) -->
      <div class="hidden lg:block overflow-x-auto rounded border border-sidebar">
        <table class="w-full text-sm">
          <thead class="bg-card text-left text-muted-foreground">
          <tr>
            <th class="px-3 py-2">Name / Prefix</th>
            <th class="px-3 py-2">Owner</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2">Uses</th>
            <th class="px-3 py-2">Expires</th>
            <th class="px-3 py-2"></th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="token in tokens.data" :key="token.id" class="border-t border-sidebar">
            <td class="px-3 py-2">
              <div class="font-medium">{{ token.name }}</div>
              <div class="font-mono text-xs text-muted-foreground">{{ token.token_prefix }}…</div>
            </td>
            <td class="px-3 py-2">
              <a v-if="token.user" :href="route('admin.users.show', token.user.id)"
                 class="hover:underline">{{ token.user.name }}</a>
              <span v-else class="text-muted-foreground">—</span>
            </td>
            <td class="px-3 py-2">
              <Badge :variant="token.is_active ? 'default' : 'secondary'">{{ token.is_active ? 'active' : 'inactive'
                }}
              </Badge>
            </td>
            <td class="px-3 py-2">{{ token.access_count }}</td>
            <td class="px-3 py-2 text-xs text-muted-foreground">{{ token.expires_at ?? 'Never' }}</td>
            <td class="px-3 py-2 flex gap-2">
              <a :href="route('admin.tokens.show', token.id)" class="text-primary text-xs hover:underline">View</a>
              <button @click="deleteToken(token.id)" class="text-xs text-destructive hover:underline">Delete</button>
            </td>
          </tr>
          <EmptyState v-if="tokens.data.length === 0" :colspan="6" message="No tokens found." />
          </tbody>
        </table>
      </div>

      <div class="flex gap-1">
        <template v-for="link in tokens.links" :key="link.label">
          <a v-if="link.url" :href="link.url" class="rounded border px-3 py-1 text-sm"
             :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'" v-html="link.label" />
          <span v-else class="rounded border px-3 py-1 text-sm opacity-40" v-html="link.label" />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
