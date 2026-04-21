<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Copy, Check, Terminal, ExternalLink, Search, AlertTriangle } from 'lucide-vue-next';
import { ref, computed } from 'vue';

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

type EventFamily =
  | 'basic'
  | 'channel_points'
  | 'stream'
  | 'hype_train'
  | 'charity'
  | 'goals'
  | 'polls'
  | 'predictions';

interface EventCommand {
  type: string;
  label: string;
  description: string;
  family: EventFamily;
}

const FAMILY_LABELS: Record<EventFamily, string> = {
  basic: 'Basic',
  channel_points: 'Channel Points',
  stream: 'Stream',
  hype_train: 'Hype Train',
  charity: 'Charity',
  goals: 'Goals',
  polls: 'Polls',
  predictions: 'Predictions',
};

const FAMILY_ORDER: EventFamily[] = [
  'basic',
  'channel_points',
  'stream',
  'hype_train',
  'charity',
  'goals',
  'polls',
  'predictions',
];

const eventCommands: EventCommand[] = [
  { type: 'channel.follow', label: 'New Follower', description: 'Someone follows your channel', family: 'basic' },
  { type: 'channel.subscribe', label: 'New Subscription', description: 'A new sub (paid or prime)', family: 'basic' },
  { type: 'channel.subscription.gift', label: 'Gift Subscription', description: 'A gifter drops one or more subs', family: 'basic' },
  { type: 'channel.subscription.message', label: 'Resubscription', description: 'A resub message with streak info', family: 'basic' },
  { type: 'channel.cheer', label: 'Bits Cheer', description: 'A viewer cheers with bits', family: 'basic' },
  { type: 'channel.raid', label: 'Raid', description: 'An incoming raid from another channel', family: 'basic' },

  {
    type: 'channel.channel_points_custom_reward_redemption.add',
    label: 'Channel Points Redemption',
    description: 'A viewer redeems a custom reward',
    family: 'channel_points',
  },
  {
    type: 'channel.channel_points_custom_reward_redemption.update',
    label: 'Redemption Updated',
    description: 'A moderator fulfills/cancels a redemption',
    family: 'channel_points',
  },

  { type: 'stream.online', label: 'Stream Online', description: 'Stream goes live', family: 'stream' },
  { type: 'stream.offline', label: 'Stream Offline', description: 'Stream ends', family: 'stream' },

  { type: 'channel.hype_train.begin', label: 'Hype Train Started', description: 'A hype train kicks off', family: 'hype_train' },
  { type: 'channel.hype_train.progress', label: 'Hype Train Progress', description: 'New contribution lands during an active train', family: 'hype_train' },
  { type: 'channel.hype_train.end', label: 'Hype Train Ended', description: 'Final level + cooldown snapshot', family: 'hype_train' },

  { type: 'channel.charity_campaign.donate', label: 'Charity Donation', description: 'A viewer donates to the active campaign', family: 'charity' },
  { type: 'channel.charity_campaign.start', label: 'Charity Campaign Started', description: 'A charity campaign begins', family: 'charity' },
  { type: 'channel.charity_campaign.progress', label: 'Charity Campaign Progress', description: 'Current vs. target amount update', family: 'charity' },
  { type: 'channel.charity_campaign.stop', label: 'Charity Campaign Ended', description: 'Campaign wrap-up with final totals', family: 'charity' },

  { type: 'channel.goal.begin', label: 'Channel Goal Started', description: 'A follower/sub/bits goal begins', family: 'goals' },
  { type: 'channel.goal.progress', label: 'Channel Goal Progress', description: 'Goal current amount update', family: 'goals' },
  { type: 'channel.goal.end', label: 'Channel Goal Ended', description: 'Goal completed or expired', family: 'goals' },

  { type: 'channel.poll.begin', label: 'Poll Started', description: 'A poll opens with up to 5 choices', family: 'polls' },
  { type: 'channel.poll.progress', label: 'Poll Progress', description: 'Mid-poll vote count update', family: 'polls' },
  { type: 'channel.poll.end', label: 'Poll Ended', description: 'Final results + status', family: 'polls' },

  { type: 'channel.prediction.begin', label: 'Prediction Started', description: 'A prediction opens with up to 10 outcomes', family: 'predictions' },
  { type: 'channel.prediction.progress', label: 'Prediction Progress', description: 'New predictor or outcome update', family: 'predictions' },
  { type: 'channel.prediction.lock', label: 'Prediction Locked', description: 'Predictions close; waiting for resolution', family: 'predictions' },
  { type: 'channel.prediction.end', label: 'Prediction Ended', description: 'Winning outcome + payouts', family: 'predictions' },
];

const searchQuery = ref('');
const copiedCommand = ref<string | null>(null);

function realCommand(eventType: string): string {
  return `twitch event trigger ${eventType} --transport=webhook -F ${props.webhookUrl} -s ${props.webhookSecret} --to-user ${props.twitchId} --from-user 1234567`;
}

const REDACTED_SECRET = '•'.repeat(12);

function displayCommand(eventType: string): string {
  return `twitch event trigger ${eventType} --transport=webhook -F ${props.webhookUrl} -s ${REDACTED_SECRET} --to-user ${props.twitchId} --from-user 1234567`;
}

async function copyCommand(eventType: string) {
  await navigator.clipboard.writeText(realCommand(eventType));
  copiedCommand.value = eventType;
  setTimeout(() => {
    if (copiedCommand.value === eventType) copiedCommand.value = null;
  }, 2000);
}

const filteredGrouped = computed<{ family: EventFamily; label: string; events: EventCommand[] }[]>(() => {
  const query = searchQuery.value.toLowerCase().trim();
  const groups = FAMILY_ORDER.map((family) => ({
    family,
    label: FAMILY_LABELS[family],
    events: eventCommands
      .filter((e) => e.family === family)
      .filter((e) => {
        if (!query) return true;
        return (
          e.label.toLowerCase().includes(query) ||
          e.type.toLowerCase().includes(query) ||
          e.description.toLowerCase().includes(query) ||
          FAMILY_LABELS[family].toLowerCase().includes(query)
        );
      }),
  }));
  return groups.filter((g) => g.events.length > 0);
});

const totalVisible = computed(() => filteredGrouped.value.reduce((s, g) => s + g.events.length, 0));
</script>

<template>
  <Head>
    <title>Testing Guide</title>
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <div class="flex items-center gap-3">
        <Terminal class="h-6 w-6 text-purple-400" />
        <h1 class="text-2xl font-semibold">Testing Guide</h1>
      </div>

      <p class="max-w-4xl text-sm text-foreground">
        Use the
        <a
          href="https://dev.twitch.tv/docs/cli/"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center gap-1 cursor-pointer text-purple-400 hover:underline"
        >
          Twitch CLI
          <ExternalLink class="h-3 w-3" />
        </a>
        to trigger test events against your webhook. Clicking <strong>Copy</strong> puts the full unredacted command on your clipboard;
        what you see on screen has the webhook secret masked.
      </p>

      <div class="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-950/20 p-3 text-sm text-amber-300">
        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Never paste these commands into a terminal while streaming - the clipboard contains your unredacted webhook secret.</span>
      </div>

      <div v-if="!hasWebhookSecret" class="rounded-lg border border-amber-500/30 bg-amber-950/20 p-3 text-sm text-amber-300">
        You don't have a per-user webhook secret yet. These commands use the global secret, which works but isn't unique to your account.
        Complete onboarding to get a personal secret.
      </div>

      <!-- Filter -->
      <div class="flex items-center gap-3">
        <div class="relative flex-1">
          <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
          <input
            v-model="searchQuery"
            placeholder="Filter triggers... (label, event type, family)"
            class="input-border w-full pl-8 pr-2.5 py-1.5 text-sm"
          />
        </div>
      </div>

      <div class="flex items-center text-xs text-muted-foreground">
        <span v-if="searchQuery">
          {{ totalVisible }} trigger{{ totalVisible !== 1 ? 's' : '' }} in {{ filteredGrouped.length }} famil{{ filteredGrouped.length !== 1 ? 'ies' : 'y' }}
        </span>
        <span v-else>
          {{ eventCommands.length }} triggers across {{ FAMILY_ORDER.length }} families
        </span>
      </div>

      <div v-if="searchQuery && filteredGrouped.length === 0" class="py-8 text-center">
        <p class="text-sm text-muted-foreground">No triggers match "{{ searchQuery }}"</p>
      </div>

      <div class="space-y-5">
        <section v-for="group in filteredGrouped" :key="group.family" class="space-y-1.5">
          <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {{ group.label }} <span class="font-normal">({{ group.events.length }})</span>
          </h2>
          <div class="divide-y divide-sidebar overflow-hidden rounded-md border border-sidebar bg-sidebar-accent/30">
            <div
              v-for="event in group.events"
              :key="event.type"
              class="flex flex-col gap-1.5 px-3 py-2 sm:flex-row sm:items-center sm:gap-4"
            >
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                  <span class="text-sm font-medium">{{ event.label }}</span>
                  <code class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] text-purple-300">{{ event.type }}</code>
                </div>
                <p class="text-xs text-muted-foreground">{{ event.description }}</p>
                <pre class="mt-1 overflow-x-auto rounded bg-slate-950 px-2 py-1 font-mono text-[11px] text-green-300 select-none">{{ displayCommand(event.type) }}</pre>
              </div>
              <Button
                variant="ghost"
                size="sm"
                class="h-7 shrink-0 cursor-pointer gap-1.5 text-xs"
                @click="copyCommand(event.type)"
              >
                <Check v-if="copiedCommand === event.type" class="h-3.5 w-3.5 text-green-400" />
                <Copy v-else class="h-3.5 w-3.5" />
                {{ copiedCommand === event.type ? 'Copied' : 'Copy' }}
              </Button>
            </div>
          </div>
        </section>
      </div>

      <div class="space-y-2 pb-8 text-sm text-muted-foreground">
        <p>
          <strong>Prerequisites:</strong> Install the
          <a href="https://dev.twitch.tv/docs/cli/" target="_blank" rel="noopener" class="cursor-pointer text-purple-400 hover:underline">Twitch CLI</a> and run
          <code class="rounded bg-slate-800 px-1.5 py-0.5 text-xs">twitch configure</code> first.
        </p>
        <p>
          Full event reference:
          <a
            href="https://dev.twitch.tv/docs/eventsub/eventsub-reference/"
            target="_blank"
            rel="noopener"
            class="inline-flex cursor-pointer items-center gap-1 text-purple-400 hover:underline"
          >
            Twitch EventSub Reference
            <ExternalLink class="h-3 w-3" />
          </a>
        </p>
      </div>
    </div>
  </AppLayout>
</template>
