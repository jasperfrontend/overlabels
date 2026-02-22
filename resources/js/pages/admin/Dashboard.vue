<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Users, Layers, Zap, Clock } from 'lucide-vue-next';

interface StatCards {
  users: number;
  templates: number;
  events: number;
  pending_events: number;
}

interface RecentUser {
  id: number;
  name: string;
  email: string;
  twitch_id: string | null;
  role: string;
  created_at: string;
}

interface AuditLog {
  id: number;
  action: string;
  target_type: string | null;
  target_id: number | null;
  ip_address: string | null;
  created_at: string;
  admin: { id: number; name: string } | null;
}

defineProps<{
  stats: StatCards;
  recentSignups: RecentUser[];
  recentAuditLogs: AuditLog[];
}>();

const breadcrumbs = [{ title: 'Admin', href: route('admin.dashboard') }];
</script>

<template>
  <Head><title>Admin Dashboard</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4">
      <h1 class="text-2xl font-bold">Admin Dashboard</h1>

      <!-- Stat cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="text-sm font-medium">Total Users</CardTitle>
            <Users class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.users }}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="text-sm font-medium">Templates</CardTitle>
            <Layers class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.templates }}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="text-sm font-medium">Total Events</CardTitle>
            <Zap class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.events }}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="text-sm font-medium">Pending Events</CardTitle>
            <Clock class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.pending_events }}</div>
          </CardContent>
        </Card>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Recent signups -->
        <Card>
          <CardHeader>
            <CardTitle>Recent Signups</CardTitle>
          </CardHeader>
          <CardContent>
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b text-left text-muted-foreground">
                  <th class="pb-2">Name</th>
                  <th class="pb-2">Role</th>
                  <th class="pb-2">Joined</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in recentSignups" :key="user.id" class="border-b last:border-0">
                  <td class="py-2">
                    <a :href="route('admin.users.show', user.id)" class="hover:underline">{{ user.name }}</a>
                  </td>
                  <td class="py-2">
                    <Badge :variant="user.role === 'admin' ? 'default' : 'secondary'">{{ user.role }}</Badge>
                  </td>
                  <td class="py-2 text-muted-foreground">{{ user.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>

        <!-- Recent audit logs -->
        <Card>
          <CardHeader>
            <CardTitle>Recent Audit Activity</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <div v-for="log in recentAuditLogs" :key="log.id" class="flex items-start justify-between border-b py-2 last:border-0 text-sm">
                <div>
                  <span class="font-medium">{{ log.admin?.name ?? 'Unknown' }}</span>
                  <span class="text-muted-foreground ml-1">{{ log.action }}</span>
                  <span v-if="log.target_type" class="text-muted-foreground ml-1">on {{ log.target_type }}#{{ log.target_id }}</span>
                </div>
                <span class="text-xs text-muted-foreground whitespace-nowrap ml-2">{{ log.created_at }}</span>
              </div>
              <p v-if="recentAuditLogs.length === 0" class="text-muted-foreground text-sm">No audit activity yet.</p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
