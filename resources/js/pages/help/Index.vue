<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import HelpCardGrid from '@/components/help/HelpCardGrid.vue';
import { Bot, BookOpen, Brackets, Heart, FileText, Pipette, Sigma, SlidersHorizontal, Swords } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
];

const pages = [
  {
    title: 'Conditional and Event Tags',
    description: 'If/else logic, comparisons, event data tags, and integration tags for Ko-fi and StreamLabs.',
    href: '/help/conditionals',
    icon: Brackets,
  },
  {
    title: 'Controls',
    description: 'Mutable values you can change live - text, numbers, toggles, timers, and more.',
    href: '/help/controls',
    icon: SlidersHorizontal,
  },
  {
    title: 'Formatting Pipes',
    description: 'Format numbers, durations, currencies, and dates with pipe syntax. Locale-aware, zero dependencies.',
    href: '/help/formatting',
    icon: Pipette,
  },
  {
    title: 'Math Engine',
    description: 'Waves, modulo wheels, pseudo-random one-liners, timestamp racing - the math behind live overlays.',
    href: '/help/math',
    icon: Sigma,
  },
  {
    title: 'Twitch Chat Bot',
    description: 'The @overlabels bot joins your channel so viewers and mods can change controls from chat.',
    href: '/help/bot',
    icon: Bot,
  },
  {
    title: 'Chat Castle',
    description: 'Chat-driven dungeon raid. Commands, rounds, energy blocks, chest contents, and how to not lose the HP pool.',
    href: '/help/gamejam',
    icon: Swords,
  },
  {
    title: 'Free Resources',
    description: 'Colors, fonts, animations, images, and other tools for building overlays.',
    href: '/help/resources',
    icon: BookOpen,
  },
  {
    title: 'Why Ko-fi',
    description: 'Why we chose Ko-fi over Patreon, Stripe, or built-in payments.',
    href: '/help/why-kofi',
    icon: Heart,
  },
  {
    title: 'Manifesto',
    description: 'What Overlabels is, why it exists, and the principles behind it.',
    href: '/help/manifesto',
    icon: FileText,
  },
];
</script>

<template>
  <HelpLayout
    :breadcrumbs="breadcrumbs"
    title="Help - Overlabels"
    description="Learn how to build Twitch overlays with Overlabels - conditional tags, controls, formatting, and more."
    canonical-url="https://overlabels.com/help"
  >
    <div class="mb-8">
      <h1 class="mb-4 text-4xl font-bold">Welcome to Overlabels Help</h1>
      <p class="text-lg text-foreground">
        Everything you need to build dynamic Twitch overlays - from template syntax to live controls.
      </p>
    </div>

    <HelpCardGrid :pages="pages" />
  </HelpLayout>
</template>
