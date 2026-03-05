<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, router } from '@inertiajs/vue3';

interface SessionUser {
  id: number;
  name: string;
  email: string;
  twitch_id: string | null;
}

interface Session {
  id: string;
  user_id: number | null;
  ip_address: string | null;
  last_activity: number;
  last_activity_human: string;
  user: SessionUser | null;
}

interface Paginator {
  data: Session[];
  meta: { current_page: number; last_page: number; total: number; per_page: number };
}

defineProps<{ sessions: Paginator }>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Sessions', href: route('admin.sessions.index') },
];

function invalidate(id: string) {
  if (confirm('Invalidate this session?')) {
    router.delete(route('admin.sessions.destroy', id));
  }
}
</script>

<template>
  <Head><title>Admin — Sessions</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Active Sessions" title-class="text-2xl font-bold">
        <template #actions>
          <span class="text-sm text-muted-foreground">{{ sessions.meta.total }} total</span>
        </template>
      </PageHeader>

      <!-- Card view (< lg) -->
      <div class="lg:hidden space-y-2">
        <EmptyState v-if="sessions.data.length === 0" message="No sessions found." />
        <div v-for="session in sessions.data" :key="`card-${session.id}`" class="rounded border p-3 text-sm">
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="font-medium">
                <a v-if="session.user" :href="route('admin.users.show', session.user.id)" class="hover:underline">{{ session.user.name }}</a>
                <span v-else class="text-muted-foreground">Guest</span>
              </div>
              <div class="font-mono text-xs text-muted-foreground">{{ session.id.substring(0, 24) }}…</div>
            </div>
            <button @click="invalidate(session.id)" class="shrink-0 text-xs text-destructive hover:underline">Invalidate</button>
          </div>
          <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span>{{ session.ip_address ?? 'No IP' }}</span>
            <span>Active {{ session.last_activity_human }}</span>
          </div>
        </div>
      </div>

      <!-- Table (≥ lg) -->
      <div class="hidden lg:block overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">User</th>
              <th class="px-3 py-2">IP Address</th>
              <th class="px-3 py-2">Last Activity</th>
              <th class="px-3 py-2">Session ID</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="session in sessions.data" :key="session.id" class="border-t">
              <td class="px-3 py-2">
                <div v-if="session.user">
                  <a :href="route('admin.users.show', session.user.id)" class="hover:underline">{{ session.user.name }}</a>
                </div>
                <span v-else class="text-muted-foreground">Guest</span>
              </td>
              <td class="px-3 py-2 text-muted-foreground">{{ session.ip_address ?? '—' }}</td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ session.last_activity_human }}</td>
              <td class="px-3 py-2 font-mono text-xs text-muted-foreground">{{ session.id.substring(0, 16) }}…</td>
              <td class="px-3 py-2">
                <button @click="invalidate(session.id)" class="text-xs text-destructive hover:underline">Invalidate</button>
              </td>
            </tr>
            <EmptyState v-if="sessions.data.length === 0" :colspan="5" message="No sessions found." />
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
