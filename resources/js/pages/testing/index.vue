<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Copy, Check, Terminal, ExternalLink } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
  twitchId: string;
  webhookUrl: string;
  webhookSecret: string;
  hasWebhookSecret: boolean;
}>();

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Testing Guide', href: '/testing' },
];

const copiedCommand = ref<string | null>(null);

const eventCommands = [
  {
    type: 'channel.follow',
    label: 'New Follower',
    description: 'Simulates someone following your channel',
  },
  {
    type: 'channel.subscribe',
    label: 'New Subscription',
    description: 'Simulates a new subscription',
  },
  {
    type: 'channel.subscription.gift',
    label: 'Gift Subscription',
    description: 'Simulates a gifted subscription',
  },
  {
    type: 'channel.subscription.message',
    label: 'Resubscription',
    description: 'Simulates a resubscription message',
  },
  {
    type: 'channel.cheer',
    label: 'Bits Cheer',
    description: 'Simulates a bits cheer event',
  },
  {
    type: 'channel.raid',
    label: 'Raid',
    description: 'Simulates an incoming raid',
  },
  {
    type: 'channel.channel_points_custom_reward_redemption.add',
    label: 'Channel Points Redemption',
    description: 'Simulates a channel point redemption',
  },
];

function getCommand(eventType: string): string {
  return `twitch event trigger ${eventType} --transport=webhook -F ${props.webhookUrl} -s ${props.webhookSecret} -b ${props.twitchId}`;
}

async function copyCommand(eventType: string) {
  const command = getCommand(eventType);
  await navigator.clipboard.writeText(command);
  copiedCommand.value = eventType;
  setTimeout(() => {
    copiedCommand.value = null;
  }, 2000);
}
</script>

<template>
  <Head>
    <title>Testing Guide</title>
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-4">
      <div class="flex items-center gap-3">
        <Terminal class="h-6 w-6 text-purple-400" />
        <h1 class="text-2xl font-semibold">Testing Guide</h1>
      </div>

      <p class="text-sm text-muted-foreground max-w-2xl">
        Use the
        <a
          href="https://dev.twitch.tv/docs/cli/"
          target="_blank"
          rel="noopener"
          class="text-purple-400 hover:underline inline-flex items-center gap-1"
        >
          Twitch CLI
          <ExternalLink class="h-3 w-3" />
        </a>
        to trigger test events against your webhook. Each command below is pre-filled with your
        Twitch ID, webhook URL, and secret.
      </p>

      <div v-if="!hasWebhookSecret" class="rounded-lg border border-amber-500/30 bg-amber-950/20 p-4 text-sm text-amber-300">
        You don't have a per-user webhook secret yet. These commands use the global secret,
        which will work but won't be unique to your account. Complete onboarding to get a personal secret.
      </div>

      <div class="grid gap-4">
        <Card v-for="event in eventCommands" :key="event.type" class="border-sidebar">
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-base">{{ event.label }}</CardTitle>
              <Button
                variant="ghost"
                size="sm"
                class="gap-1.5 text-xs"
                @click="copyCommand(event.type)"
              >
                <Check v-if="copiedCommand === event.type" class="h-3.5 w-3.5 text-green-400" />
                <Copy v-else class="h-3.5 w-3.5" />
                {{ copiedCommand === event.type ? 'Copied!' : 'Copy' }}
              </Button>
            </div>
            <p class="text-xs text-muted-foreground">{{ event.description }}</p>
          </CardHeader>
          <CardContent>
            <pre
              class="overflow-x-auto rounded-md bg-slate-950 p-3 text-xs text-green-300 font-mono whitespace-pre-wrap break-all"
            >{{ getCommand(event.type) }}</pre>
          </CardContent>
        </Card>
      </div>

      <div class="text-sm text-muted-foreground space-y-2 pb-8">
        <p>
          <strong>Prerequisites:</strong> Install the
          <a
            href="https://dev.twitch.tv/docs/cli/"
            target="_blank"
            rel="noopener"
            class="text-purple-400 hover:underline"
          >Twitch CLI</a> and run <code class="rounded bg-slate-800 px-1.5 py-0.5 text-xs">twitch configure</code> first.
        </p>
        <p>
          Full event reference:
          <a
            href="https://dev.twitch.tv/docs/eventsub/eventsub-reference/"
            target="_blank"
            rel="noopener"
            class="text-purple-400 hover:underline inline-flex items-center gap-1"
          >
            Twitch EventSub Reference
            <ExternalLink class="h-3 w-3" />
          </a>
        </p>
      </div>
    </div>
  </AppLayout>
</template>
