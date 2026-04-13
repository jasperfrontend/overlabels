<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';

const props = defineProps<{
  connected: boolean;
  expires_at: number | null;
  obtained_at: number | null;
  scopes: string[];
  client_id_configured: boolean;
  listener_secret_configured: boolean;
}>();

const expiresAt = computed(() => {
  if (!props.expires_at) return null;
  return new Date(props.expires_at * 1000).toLocaleString();
});

const obtainedAt = computed(() => {
  if (!props.obtained_at) return null;
  return new Date(props.obtained_at * 1000).toLocaleString();
});

const canConnect = computed(
  () => props.client_id_configured && props.listener_secret_configured,
);
</script>

<template>
  <AppLayout
    :breadcrumbs="[
      { title: 'Admin', href: route('admin.dashboard') },
      { title: 'Twitch Bot', href: route('admin.twitchbot.index') },
    ]"
  >
    <div class="mx-auto max-w-2xl space-y-6 p-6">
      <div>
        <h1 class="text-2xl font-bold">Twitch Bot (@overlabels)</h1>
        <p class="mt-2 text-sm text-foreground">
          The shared bot account that joins opted-in streamer channels. Streamers run
          <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">/mod overlabels</code>
          in their own chat to let the bot send messages.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Connection status</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex items-center gap-3">
            <span
              :class="props.connected ? 'bg-green-500' : 'bg-red-500'"
              class="inline-block h-3 w-3 rounded-full"
            />
            <span class="font-medium">
              {{ props.connected ? 'Authenticated' : 'Not connected' }}
            </span>
          </div>

          <dl v-if="props.connected" class="space-y-2 text-sm">
            <div class="flex gap-2">
              <dt class="w-32 shrink-0 font-medium text-foreground">Obtained</dt>
              <dd>{{ obtainedAt ?? '-' }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-32 shrink-0 font-medium text-foreground">Expires</dt>
              <dd>{{ expiresAt ?? '-' }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-32 shrink-0 font-medium text-foreground">Scopes</dt>
              <dd class="font-mono text-xs">{{ props.scopes.join(', ') }}</dd>
            </div>
          </dl>

          <p v-else class="text-sm text-foreground">
            No tokens stored yet. Click below to authenticate the @overlabels account via Twitch OAuth.
            You will be prompted to sign into Twitch - make sure you are signed in as @overlabels
            (use an incognito window if you are currently signed in as another Twitch account).
          </p>
        </CardContent>
      </Card>

      <Card v-if="!canConnect" class="border-yellow-500">
        <CardHeader>
          <CardTitle class="text-yellow-700 dark:text-yellow-400">Configuration required</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <p>Before connecting, set these environment variables:</p>
          <ul class="list-inside list-disc space-y-1">
            <li v-if="!props.client_id_configured">
              <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">TWITCHBOT_CLIENT_ID</code>
              and
              <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">TWITCHBOT_CLIENT_SECRET</code>
              (from the bot's Twitch Developer app)
            </li>
            <li v-if="!props.listener_secret_configured">
              <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">TWITCHBOT_LISTENER_SECRET</code>
              (shared secret the bot service uses to authenticate against the internal API - generate with
              <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">bin2hex(random_bytes(32))</code>)
            </li>
          </ul>
        </CardContent>
      </Card>

      <div class="flex gap-3">
        <Button
          :disabled="!canConnect"
          @click="() => { window.location.href = route('admin.twitchbot.redirect'); }"
        >
          {{ props.connected ? 'Reconnect' : 'Connect @overlabels account' }}
        </Button>
      </div>
    </div>
  </AppLayout>
</template>
