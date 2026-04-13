<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import HelpCardGrid from '@/components/help/HelpCardGrid.vue';
import { Terminal } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Twitch Bot', href: '/help/bot' },
];

const pages = [
  {
    title: 'Commands',
    description: 'The built-in chat commands the bot ships with, plus one working example per command.',
    href: '/help/bot/commands',
    icon: Terminal,
  },
];
</script>

<template>
  <HelpLayout
    :breadcrumbs="breadcrumbs"
    title="Twitch Bot - Overlabels Help"
    description="How the @overlabels Twitch chat bot works - chat-driven controls, permission tiers, and the full command list."
    canonical-url="https://overlabels.com/help/bot"
  >
    <div class="mb-8">
      <h1 class="mb-4 text-4xl font-bold">Twitch Chat Bot</h1>
      <p class="text-lg text-foreground">
        The @overlabels bot joins your channel as a moderator and lets you - and optionally your mods, VIPs,
        or viewers - change overlay controls directly from chat. No tab-switching, no dashboards during a stream.
      </p>
    </div>

    <div class="mb-8 rounded-lg border border-border bg-sidebar-accent/30 p-5">
      <h2 class="mb-2 font-semibold text-foreground">How it works, in one paragraph</h2>
      <p class="text-sm text-foreground">
        Toggle the bot on for your account, run <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">/mod overlabels</code>
        in your own chat, and the bot will start listening. Every built-in command has a minimum permission tier
        (everyone, subscriber, VIP, moderator, or broadcaster). When a chat message matches a command and the invoker's
        badges meet the tier, the bot POSTs to Overlabels, the control updates in the database, and the overlay reflects
        it live within a frame.
      </p>
    </div>

    <HelpCardGrid :pages="pages" />
  </HelpLayout>
</template>
