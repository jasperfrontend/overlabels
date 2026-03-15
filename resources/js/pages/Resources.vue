<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Free Resources',
    href: '/resources',
  },
];

const colors = [
  { name: 'HSL Picker', url: 'https://www.hslpicker.com', description: 'Pick any color and get HSL, hex, and RGB values instantly.' },
  { name: 'Coolors', url: 'https://coolors.co/generate', description: 'Generate beautiful color palettes with one click.' },
  { name: 'ColorHub', url: 'https://www.colorhub.app/browse', description: 'Browse curated color palettes by category.' },
  { name: 'Color Hunt', url: 'https://colorhunt.co/', description: 'Community-driven collection of trending color palettes.' },
  { name: 'Color Hex', url: 'https://www.color-hex.com/color-palettes/', description: 'Thousands of user-submitted color palettes.' },
  { name: 'mycolor.space', url: 'https://mycolor.space/', description: 'Generate matching color palettes from a single color.' },
];

const icons = [
  { name: 'Icones', url: 'https://icones.js.org/', description: 'Massive icon explorer with instant SVG copy. Covers 100+ icon sets.' },
  { name: 'Tabler Icons', url: 'https://tabler.io/icons', description: 'Clean, consistent stroke icons. All free and open source.' },
  { name: 'CopyIcon', url: 'https://copyicon.com/', description: 'One-click SVG copy for popular icon libraries.' },
  { name: 'Composables Icons', url: 'https://composables.com/icons', description: 'Browse and copy icons from multiple collections.' },
];

const fonts = [
  { name: 'Bunny Fonts', url: 'https://fonts.bunny.net/', description: 'Privacy-friendly, GDPR-compliant drop-in replacement for Google Fonts.' },
  { name: 'Google Fonts', url: 'https://fonts.google.com/', description: 'The largest free and open source font library.' },
];
</script>

<template>
  <Head>
    <title>Free Resources - Overlabels</title>
    <meta
      name="description"
      content="Free tools and resources for building beautiful Twitch overlays. Color palettes, icon libraries, and fonts - all free to use."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/resources" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Free Resources - Overlabels" />
    <meta
      property="og:description"
      content="Free tools and resources for building beautiful Twitch overlays. Color palettes, icon libraries, and fonts - all free to use."
    />
    <meta property="og:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - Twitch overlay management" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Free Resources - Overlabels" />
    <meta
      name="twitter:description"
      content="Free tools and resources for building beautiful Twitch overlays. Color palettes, icon libraries, and fonts - all free to use."
    />
    <meta name="twitter:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - Twitch overlay management" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col items-center gap-8 px-4 py-10">
      <div class="w-full max-w-3xl space-y-10">
        <div class="text-center">
          <h1 class="text-4xl font-extrabold tracking-tight">Free Resources</h1>
          <p class="mt-3 text-lg text-muted-foreground">
            Tools we love for building overlays. All free, no sign-up required.
          </p>
        </div>

        <!-- Colors -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Colors</h2>
          <p class="text-muted-foreground">Pick colors, generate palettes, and find the right look for your overlays.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in colors"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Icons -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Icons (1-click SVG copy)</h2>
          <p class="text-muted-foreground">Browse thousands of icons and copy the SVG straight into your overlay HTML.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in icons"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Fonts -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Fonts</h2>
          <p class="text-muted-foreground">Free font libraries you can use in your overlays via CSS imports.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in fonts"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Note -->
        <section class="rounded-lg border bg-sidebar-accent p-5 text-center">
          <p class="text-sm text-muted-foreground">
            Know a great free resource that should be on this list?
            Let us know at <a href="mailto:admin@overlabels.com" class="text-violet-400 hover:underline">admin@overlabels.com</a>.
          </p>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
