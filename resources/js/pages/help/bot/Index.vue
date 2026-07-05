<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import HelpCardGrid from '@/components/help/HelpCardGrid.vue';
import { CornerDownRight, Terminal } from '@lucide/vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Twitch Bot', href: '/help/bot' },
];

const pages = [
  {
    title: 'Expressions',
    description: 'The built-in chat expressions the bot ships with, plus one working example per expression.',
    href: '/help/bot/expressions',
    icon: Terminal,
  },
  {
    title: 'Bot Aliases',
    description: 'Short chat commands that rewrite to longer ones. !w 2 becomes !inc wins 2 before dispatch. Positional placeholders, one-hop guard, shared validation.',
    href: '/help/bot/aliases',
    icon: CornerDownRight,
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
      <Heading
        title="Twitch Chat Bot"
        title-class="mb-4 text-4xl font-bold"
        description="The @overlabels bot joins your channel as a moderator and lets you - and optionally your mods, VIPs, or viewers - change overlay controls directly from chat. No tab-switching, no dashboards during a stream."
      />
    </div>


    <HelpCardGrid :pages="pages" />
  </HelpLayout>
</template>
