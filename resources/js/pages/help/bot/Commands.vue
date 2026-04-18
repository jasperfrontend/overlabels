<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import { ShieldAlert } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Twitch Bot', href: '/help/bot' },
  { title: 'Commands', href: '/help/bot/commands' },
];

const STORAGE_KEY = 'help.bot.commands.controlsWarningAck';
const iUnderstand = ref(
  typeof localStorage !== 'undefined' && localStorage.getItem(STORAGE_KEY) === '1',
);
watch(iUnderstand, (value) => {
  if (typeof localStorage === 'undefined') return;
  if (value) localStorage.setItem(STORAGE_KEY, '1');
  else localStorage.removeItem(STORAGE_KEY);
});

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

      <div class="mb-8 rounded-lg border border-amber-500 bg-amber-500/20 p-5" v-if="!iUnderstand">
        <h2 class="mb-2 font-semibold text-xl text-amber-500">
          <ShieldAlert class="mr-2 inline-block h-5 w-5" />
          Control commands are OFF by default - you must opt in per channel
        </h2>
        <p class="mb-3 text-foreground">
          As of April 2026, every command on this page that reads or writes a control (everything except the
          two below) is gated behind a per-channel controls-access flag. The default is <strong>off</strong>.
          Until you flip it on, the bot will ignore <code>!control</code>, <code>!set</code>,
          <code>!increment</code>, <code>!decrement</code>, <code>!reset</code>, <code>!enable</code>,
          <code>!disable</code>, and <code>!toggle</code> entirely - it won't even acknowledge them in chat.
        </p>
        <p class="mb-3 text-foreground">
          To turn control commands on for your channel, type this in your own chat:
        </p>
        <div class="mb-3 rounded-md border border-sidebar bg-background/50 p-3 font-mono text-md">
          <div class="text-foreground">
            <span class="text-sky-400">@broadcaster:</span> !enablecontrols
          </div>
          <div class="mt-1 text-foreground">
            <span class="text-amber-400">@overlabels:</span> chat control commands are now enabled
          </div>
        </div>
        <p class="mb-3 text-foreground">
          To turn them back off:
        </p>
        <div class="rounded-md border border-sidebar bg-background/50 p-3 font-mono text-md">
          <div class="text-foreground">
            <span class="text-sky-400">@broadcaster:</span> !disablecontrols
          </div>
          <div class="mt-1 text-foreground">
            <span class="text-amber-400">@overlabels:</span> chat control commands are now disabled
          </div>
        </div>
        <p class="mt-3 text-md text-foreground">
          Both commands are <strong>broadcaster only</strong>. Your mods and viewers cannot flip the switch.
          Leave the flag off if your channel is doing something chat-interactive where you don't want viewers
          poking at your counters or booleans. Flip it on when you want mods or yourself to drive overlay state
          from chat.
        </p>
        <p class="mt-3 text-md text-foreground">
          <button class="btn btn-primary cursor-pointer" @click="iUnderstand = true">
            I understand, let's go!
          </button>
        </p>
      </div>

      <div
        v-else
        class="mb-8 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm"
      >
        <p class="text-foreground">
          <ShieldAlert class="mr-1 inline-block h-4 w-4 text-amber-500" />
          Bot commands not working? Type <code>!enablecontrols</code> in your chat (broadcaster only).
        </p>
        <button
          class="cursor-pointer text-sm text-amber-500 underline hover:text-amber-400"
          @click="iUnderstand = false"
        >
          Show details
        </button>
      </div>

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
