<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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

interface AccessLog {
  id: number;
  template_slug: string | null;
  ip_address: string | null;
  user_agent: string | null;
  accessed_at: string;
}

const props = defineProps<{
  token: Token;
  accessLogs: AccessLog[];
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Tokens', href: route('admin.tokens.index') },
  { title: props.token.name, href: route('admin.tokens.show', props.token.id) },
];
</script>

<template>
  <Head><title>Admin — Token: {{ token.name }}</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4 max-w-3xl">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">{{ token.name }}</h1>
        <Badge :variant="token.is_active ? 'default' : 'secondary'">{{ token.is_active ? 'active' : 'inactive' }}</Badge>
      </div>

      <Card>
        <CardContent class="pt-4 grid grid-cols-2 gap-3 text-sm">
          <div>
            <span class="text-muted-foreground">Prefix</span>
            <div class="font-mono">{{ token.token_prefix }}…</div>
          </div>
          <div>
            <span class="text-muted-foreground">Owner</span>
            <div>
              <a v-if="token.user" :href="route('admin.users.show', token.user.id)" class="hover:underline">{{ token.user.name }}</a>
              <span v-else>—</span>
            </div>
          </div>
          <div>
            <span class="text-muted-foreground">Uses</span>
            <div>{{ token.access_count }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Expires</span>
            <div>{{ token.expires_at ?? 'Never' }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Last Used</span>
            <div>{{ token.last_used_at ?? 'Never' }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Created</span>
            <div>{{ token.created_at }}</div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Access Log (last 50)</CardTitle></CardHeader>
        <CardContent>
          <table class="w-full text-xs">
            <thead class="text-left text-muted-foreground">
              <tr>
                <th class="pb-2">Template</th>
                <th class="pb-2">IP</th>
                <th class="pb-2">Accessed At</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in accessLogs" :key="log.id" class="border-t">
                <td class="py-1.5 font-mono">{{ log.template_slug ?? '—' }}</td>
                <td class="py-1.5 text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
                <td class="py-1.5 text-muted-foreground">{{ log.accessed_at }}</td>
              </tr>
              <tr v-if="accessLogs.length === 0">
                <td colspan="3" class="py-4 text-center text-muted-foreground">No access logs.</td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
