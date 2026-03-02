<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref } from 'vue';

interface ExternalEvent {
  id: number;
  service: string;
  event_type: string;
  message_id: string | null;
  controls_updated: boolean;
  alert_dispatched: boolean;
  raw_payload: Record<string, unknown>;
  normalized_payload: Record<string, unknown>;
  created_at: string;
  user: { id: number; name: string; twitch_id: string | null } | null;
}

const props = defineProps<{ event: ExternalEvent }>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Events', href: route('admin.events.index', { source: 'external' }) },
  { title: `${props.event.service}/${props.event.event_type} #${props.event.id}`, href: route('admin.events.external.show', props.event.id) },
];

const showRawPayload = ref(false);
</script>

<template>
  <Head><title>Admin — External Event #{{ event.id }}</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4 max-w-3xl">
      <div class="flex items-center gap-3">
        <Badge variant="outline" class="font-mono">{{ event.service }}</Badge>
        <h1 class="text-2xl font-bold font-mono">{{ event.event_type }} <span class="text-muted-foreground text-lg">#{{ event.id }}</span></h1>
      </div>

      <Card>
        <CardContent class="pt-4 grid grid-cols-2 gap-3 text-sm">
          <div>
            <span class="text-muted-foreground">User</span>
            <div>
              <a v-if="event.user" :href="route('admin.users.show', event.user.id)" class="hover:underline">{{ event.user.name }}</a>
              <span v-else>—</span>
            </div>
          </div>
          <div>
            <span class="text-muted-foreground">Created</span>
            <div>{{ event.created_at }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Controls Updated</span>
            <div>
              <Badge :variant="event.controls_updated ? 'default' : 'secondary'">{{ event.controls_updated ? 'Yes' : 'No' }}</Badge>
            </div>
          </div>
          <div>
            <span class="text-muted-foreground">Alert Dispatched</span>
            <div>
              <Badge :variant="event.alert_dispatched ? 'default' : 'secondary'">{{ event.alert_dispatched ? 'Yes' : 'No' }}</Badge>
            </div>
          </div>
          <div v-if="event.message_id">
            <span class="text-muted-foreground">Message ID</span>
            <div class="font-mono text-xs">{{ event.message_id }}</div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Normalized Payload</CardTitle></CardHeader>
        <CardContent>
          <pre class="overflow-x-auto rounded bg-muted p-4 text-xs">{{ JSON.stringify(event.normalized_payload, null, 2) }}</pre>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center justify-between">
            Raw Payload
            <button
              class="text-sm font-normal text-muted-foreground hover:text-foreground"
              @click="showRawPayload = !showRawPayload"
            >{{ showRawPayload ? 'Hide' : 'Show' }}</button>
          </CardTitle>
        </CardHeader>
        <CardContent v-if="showRawPayload">
          <pre class="overflow-x-auto rounded bg-muted p-4 text-xs">{{ JSON.stringify(event.raw_payload, null, 2) }}</pre>
        </CardContent>
      </Card>

      <div>
        <a :href="route('admin.events.index', { source: 'external' })" class="text-sm text-muted-foreground hover:text-foreground">← Back to External Events</a>
      </div>
    </div>
  </AppLayout>
</template>
