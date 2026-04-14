<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import HelpCardGrid from '@/components/help/HelpCardGrid.vue';
import { Terminal, AlertTriangle } from 'lucide-vue-next';

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

      <div class="mb-8 rounded-lg border border-amber-500 bg-amber-500/20 p-5">
        <h2 class="mb-2 font-semibold text-xl text-amber-500">
          <AlertTriangle class="mr-2 inline-block h-5 w-5" />
          VERY MUCH a work in progress
        </h2>
        <p class="text-foreground">
          The Twitch Bot <code>@overlabels</code> works and it can manipulate Control values of your overlays. 100% of the commands
          are working, but there are still some rough edges
        </p>
      </div>

      <p class="text-lg text-foreground">
        The @overlabels bot joins your channel as a moderator and lets you - and optionally your mods, VIPs,
        or viewers - change overlay controls directly from chat. No tab-switching, no dashboards during a stream.
      </p>
    </div>


    <HelpCardGrid :pages="pages" />
  </HelpLayout>
</template>
