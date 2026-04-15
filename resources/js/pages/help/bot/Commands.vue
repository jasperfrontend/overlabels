<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Twitch Bot', href: '/help/bot' },
  { title: 'Commands', href: '/help/bot/commands' },
];

type Tier = 'everyone' | 'subscriber' | 'vip' | 'moderator' | 'broadcaster';

interface BotCommandDoc {
  command: string;
  tier: Tier;
  summary: string;
  example: { chat: string; reply: string };
  notes?: string;
}

const tierLabel: Record<Tier, string> = {
  everyone: 'Everyone',
  subscriber: 'Subscriber+',
  vip: 'VIP+',
  moderator: 'Moderator+',
  broadcaster: 'Broadcaster',
};

const tierClass: Record<Tier, string> = {
  everyone: 'bg-emerald-500/10 text-emerald-400',
  subscriber: 'bg-sky-500/10 text-sky-400',
  vip: 'bg-pink-500/10 text-pink-400',
  moderator: 'bg-amber-500/10 text-amber-400',
  broadcaster: 'bg-violet-500/10 text-violet-400',
};

const commands: BotCommandDoc[] = [
  {
    command: '!control',
    tier: 'everyone',
    summary: 'Read the current value of one of your controls.',
    example: {
      chat: '!control level',
      reply: '@viewer level: 8',
    },
  },
  {
    command: '!set',
    tier: 'moderator',
    summary: 'Set a control to an exact value.',
    example: {
      chat: '!set level 8',
      reply: '@mod set level to 8',
    },
  },
  {
    command: '!increment [or] !inc',
    tier: 'moderator',
    summary: 'Add to a number or counter control. Amount defaults to 1.',
    example: {
      chat: '!inc deaths',
      reply: '@mod deaths: 4',
    },
    notes: 'Accepts an optional amount: !increment deaths 3',
  },
  {
    command: '!decrement [or] !dec',
    tier: 'moderator',
    summary: 'Subtract from a number or counter control. Amount defaults to 1.',
    example: {
      chat: '!dec lives 1',
      reply: '@mod lives: 2',
    },
  },
  {
    command: '!reset',
    tier: 'broadcaster',
    summary: 'Reset a number or counter control back to 0.',
    example: {
      chat: '!reset deaths',
      reply: '@broadcaster deaths: 0',
    },
  },
  {
    command: '!toggle', // enable/disable/toggle
    tier: 'moderator',
    summary: 'Toggle a boolean control.',
    example: {
      chat: '!toggle mute',
      reply: '@mod mute: true',
    },
  },
  {
    command: '!enable',
    tier: 'moderator',
    summary: 'Enable a boolean control.',
    example: {
      chat: '!enable mute',
      reply: '@mod mute: true',
    },
  },
  {
    command: '!disable',
    tier: 'moderator',
    summary: 'Disable a boolean control.',
    example: {
      chat: '!disable mute',
      reply: '@mod mute: false',
    },
  }
];
</script>

<template>
  <HelpLayout
    :breadcrumbs="breadcrumbs"
    title="Bot Commands - Overlabels Help"
    description="The full list of built-in @overlabels Twitch bot chat commands, their permission tiers, and working examples."
    canonical-url="https://overlabels.com/help/bot/commands"
  >
    <div class="mb-8">
      <h1 class="mb-4 text-4xl font-bold">Bot Commands</h1>
      <p class="text-lg text-foreground">
        These are the commands the @overlabels bot ships with. Every command targets one of your controls by
        its key. Service-managed controls (Ko-fi, StreamLabs, StreamElements counters) are intentionally
        hidden from chat - the bot only touches controls you created yourself.
      </p>
    </div>

    <div class="mb-6 rounded-lg border border-sidebar bg-sidebar p-4 text-sm">
      <p class="text-foreground">
        <strong>Permission tiers</strong> stack. A moderator can invoke any command tagged Moderator+, VIP+,
        Subscriber+, or Everyone. Broadcaster can invoke anything.
      </p>
    </div>

    <div class="space-y-4">
      <div
        v-for="cmd in commands"
        :key="cmd.command"
        class="rounded-lg bg-sidebar border border-sidebar p-5"
      >
        <div class="mb-3 flex flex-wrap items-center gap-3">
          <code class="rounded bg-card px-2 py-1 font-mono text-base font-semibold">{{ cmd.command }}</code>
          <span
            :class="tierClass[cmd.tier]"
            class="rounded px-2 py-0.5 text-md font-medium"
          >
            {{ tierLabel[cmd.tier] }}
          </span>
        </div>

        <p class="mb-4 text-sm text-foreground">{{ cmd.summary }}</p>
        <div class="rounded-md border border-sidebar bg-background/50 p-3 font-mono text-md">
          <div class="text-foreground">
            <span class="text-muted-foreground">@{{ cmd.tier === "everyone" ? "chatter" : cmd.tier }}:</span> {{ cmd.example.chat }}
          </div>
          <div class="mt-1 text-foreground">
            <span class="text-muted-foreground">@overlabels:</span> {{ cmd.example.reply }}
          </div>
        </div>

        <p v-if="cmd.notes" class="mt-3 text-md text-muted-foreground">{{ cmd.notes }}</p>
      </div>
    </div>
  </HelpLayout>
</template>
