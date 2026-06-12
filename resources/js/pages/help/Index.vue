<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import HelpCardGrid from '@/components/help/HelpCardGrid.vue';
import { Bot, BookOpen, Brackets, FunctionSquare, Heart, FileText, Lightbulb, Library, ListChecks, Palette, Pipette, Plug, Radio, Sigma, SlidersHorizontal, Sparkles, Swords } from '@lucide/vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
];

const pages = [
  {
    title: 'Why Overlabels',
    description: 'The pitch, for coders. Third-party data normalization, math on any value, Expression Controls, and why this thing is a bit special.',
    href: '/help/why-overlabels',
    icon: Sparkles,
  },
  {
    title: 'For Creators',
    description: 'What Overlabels actually is, beneath the HTML/CSS surface. The reactive value graph, the live data sources, the math layer, and an open call for collaborators.',
    href: '/help/for-creators',
    icon: Lightbulb,
  },
  {
    title: 'For Designers',
    description: 'A handoff guide for designers working on overlays: the two surfaces, the unknown-background problem, variable-length content, fluid layout, CSS animation, and a deliverables checklist.',
    href: '/help/for-designers',
    icon: Palette,
  },
  {
    title: 'Why Ko-fi',
    description: 'Why we chose Ko-fi as our first External Integration over Patreon, Stripe, or built-in payments.',
    href: '/help/why-kofi',
    icon: Heart,
  },
  {
    title: 'Reference',
    description: 'Fuzzy-searchable reference for every template tag, EventSub event, and foreach field. Press Alt+r from anywhere.',
    href: '/help/reference',
    icon: Library,
  },
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
    title: 'Expression Controls',
    description: 'Math-powered live data - no code, no server. Build chained formulas like the Haversine distance, donation progress bars, and more.',
    href: '/help/expressions',
    icon: FunctionSquare,
  },
  {
    title: 'Integration Presets',
    description: 'Every auto-managed control across Twitch, Ko-fi, Streamlabs, StreamElements, Fourthwall, BMAC, and Overlabels GPS - all in one searchable reference.',
    href: '/help/integration-presets',
    icon: Plug,
  },
  {
    title: 'Lists',
    description: 'User-owned arrays of values. Raffles, queues, quote walls, leaderboards, donation goals - all driven from the dashboard or chat with one primitive and ten verbs.',
    href: '/help/lists',
    icon: ListChecks,
  },
  {
    title: 'Lists in realtime',
    description: 'The builder guide: read a List as JSON, subscribe to live updates over WebSocket, and add your own custom page (a wheel, a leaderboard) to OBS. Step by step.',
    href: '/help/lists-realtime',
    icon: Radio,
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
    <div class="mb-10">
      <Heading
        title="Welcome to Overlabels Help"
        title-class="text-4xl font-bold mb-4"
        description="Everything you need to build dynamic Twitch overlays - from template syntax to live controls. This page is a bit of a clusterf*ck, but I'll get around to cleaning this up eventually."
      />
    </div>

    <HelpCardGrid :pages="pages" />
  </HelpLayout>
</template>
