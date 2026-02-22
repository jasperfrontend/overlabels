<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref } from 'vue';

interface TwitchEvent {
  id: number;
  event_type: string;
  event_data: Record<string, unknown>;
  processed: boolean;
  twitch_timestamp: string | null;
  created_at: string;
  user: { id: number; name: string; twitch_id: string | null } | null;
}

const props = defineProps<{ event: TwitchEvent }>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Events', href: route('admin.events.index') },
  { title: `#${props.event.id}`, href: route('admin.events.show', props.event.id) },
];

const processedForm = useForm({ processed: props.event.processed });
function toggleProcessed() {
  processedForm.processed = !processedForm.processed;
  processedForm.patch(route('admin.events.update', props.event.id));
}

const showDeleteConfirm = ref(false);
function submitDelete() {
  router.delete(route('admin.events.destroy', props.event.id));
}
</script>

<template>
  <Head><title>Admin — Event #{{ event.id }}</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4 max-w-3xl">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold font-mono">{{ event.event_type }}</h1>
        <Badge :variant="event.processed ? 'default' : 'secondary'">{{ event.processed ? 'processed' : 'pending' }}</Badge>
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
          <div v-if="event.twitch_timestamp">
            <span class="text-muted-foreground">Twitch Timestamp</span>
            <div>{{ event.twitch_timestamp }}</div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Event Data</CardTitle></CardHeader>
        <CardContent>
          <pre class="overflow-x-auto rounded bg-muted p-4 text-xs">{{ JSON.stringify(event.event_data, null, 2) }}</pre>
        </CardContent>
      </Card>

      <div class="flex gap-3">
        <Button variant="outline" size="sm" @click="toggleProcessed" :disabled="processedForm.processing">
          Mark as {{ event.processed ? 'pending' : 'processed' }}
        </Button>
        <Button v-if="!showDeleteConfirm" variant="destructive" size="sm" @click="showDeleteConfirm = true">Delete</Button>
        <template v-else>
          <Button variant="destructive" size="sm" @click="submitDelete">Confirm Delete</Button>
          <Button variant="outline" size="sm" @click="showDeleteConfirm = false">Cancel</Button>
        </template>
      </div>
    </div>
  </AppLayout>
</template>
