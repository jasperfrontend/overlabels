<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';

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
  { title: 'Tokens', href: route('admin.tokens.index') },
];

function deleteToken(id: number) {
  if (confirm('Delete this token? This will cascade access logs.')) {
    router.delete(route('admin.tokens.destroy', id));
  }
}
</script>

<template>
  <Head><title>Admin — Tokens</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Access Tokens</h1>
        <span class="text-sm text-muted-foreground">{{ tokens.total }} total</span>
      </div>

      <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
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
            <tr v-for="token in tokens.data" :key="token.id" class="border-t">
              <td class="px-3 py-2">
                <div class="font-medium">{{ token.name }}</div>
                <div class="font-mono text-xs text-muted-foreground">{{ token.token_prefix }}…</div>
              </td>
              <td class="px-3 py-2">
                <a v-if="token.user" :href="route('admin.users.show', token.user.id)" class="hover:underline">{{ token.user.name }}</a>
                <span v-else class="text-muted-foreground">—</span>
              </td>
              <td class="px-3 py-2">
                <Badge :variant="token.is_active ? 'default' : 'secondary'">{{ token.is_active ? 'active' : 'inactive' }}</Badge>
              </td>
              <td class="px-3 py-2">{{ token.access_count }}</td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ token.expires_at ?? 'Never' }}</td>
              <td class="px-3 py-2 flex gap-2">
                <a :href="route('admin.tokens.show', token.id)" class="text-primary text-xs hover:underline">View</a>
                <button @click="deleteToken(token.id)" class="text-xs text-destructive hover:underline">Delete</button>
              </td>
            </tr>
            <tr v-if="tokens.data.length === 0">
              <td colspan="6" class="px-3 py-6 text-center text-muted-foreground">No tokens found.</td>
            </tr>
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
