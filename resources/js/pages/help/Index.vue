<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { BookOpen, Brackets, Heart, FileText, Pipette, SlidersHorizontal } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Help',
    href: '/help'
  }
];

const pages = [
  {
    title: 'Conditional Tags',
    description: 'If/else logic, comparisons, event data tags, and integration tags for Ko-fi and StreamLabs.',
    href: '/help/conditionals',
    icon: Brackets
  },
  {
    title: 'Controls',
    description: 'Mutable values you can change live - text, numbers, toggles, timers, and more.',
    href: '/help/controls',
    icon: SlidersHorizontal
  },
  {
    title: 'Formatting Pipes',
    description: 'Format numbers, durations, currencies, and dates with pipe syntax. Locale-aware, zero dependencies.',
    href: '/help/formatting',
    icon: Pipette
  },
  {
    title: 'Free Resources',
    description: 'Colors, fonts, animations, images, and other tools for building overlays.',
    href: '/help/resources',
    icon: BookOpen
  },
  {
    title: 'Why Ko-fi',
    description: 'Why we chose Ko-fi over Patreon, Stripe, or built-in payments.',
    href: '/help/why-kofi',
    icon: Heart
  },
  {
    title: 'Manifesto',
    description: 'What Overlabels is, why it exists, and the principles behind it.',
    href: '/help/manifesto',
    icon: FileText
  }
];
</script>

<template>
  <Head>
    <title>Help - Overlabels</title>
    <meta
      name="description"
      content="Learn how to build Twitch overlays with Overlabels - conditional tags, controls, formatting, and more."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Help - Overlabels" />
    <meta
      property="og:description"
      content="Learn how to build Twitch overlays with Overlabels - conditional tags, controls, formatting, and more."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Help - Overlabels" />
    <meta
      name="twitter:description"
      content="Learn how to build Twitch overlays with Overlabels - conditional tags, controls, formatting, and more."
    />
    <meta name="twitter:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <div class="mb-8">
          <h1 class="mb-4 text-4xl font-bold">Welcome to Overlabels Help</h1>
          <p class="text-lg text-muted-foreground">
            Everything you need to build dynamic Twitch overlays - from template syntax to live controls.
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <Link
            v-for="page in pages"
            :key="page.href"
            :href="page.href"
            class="group flex gap-4 rounded-lg border border-border p-5 transition-colors hover:border-violet-400/50 hover:bg-sidebar-accent"
          >
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-violet-500/10 text-violet-400">
              <component :is="page.icon" class="h-5 w-5" />
            </div>
            <div>
              <h2 class="font-semibold text-foreground group-hover:text-violet-400">{{ page.title }}</h2>
              <p class="mt-1 text-sm text-muted-foreground">{{ page.description }}</p>
            </div>
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
