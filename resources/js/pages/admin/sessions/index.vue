<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
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
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Active Sessions</h1>
        <span class="text-sm text-muted-foreground">{{ sessions.meta.total }} total</span>
      </div>

      <div class="overflow-x-auto rounded border">
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
            <tr v-if="sessions.data.length === 0">
              <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">No sessions found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
