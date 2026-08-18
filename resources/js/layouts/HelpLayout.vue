<script setup lang="ts">
/**
 * Layout for the help pages that are Vue components rather than markdown.
 *
 * That is a short list and meant to stay one: /help/gamejam and, via AppLayout
 * directly, /help/integration-presets. Both render live data - the presets page
 * reads controlPresets.ts - so freezing them into prose would drift from their
 * source. Every other help page is server-rendered Blade through
 * resources/views/layouts/help.blade.php, which is where new pages go.
 */
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Props {
  breadcrumbs: BreadcrumbItem[];
  title: string;
  description: string;
  canonicalUrl?: string;
}

const props = defineProps<Props>();

const ogImage = '/ogimage.jpg';
const ogImageAlt = 'Overlabels - build Twitch overlays with HTML, CSS, and live data';
</script>

<template>
  <Head>
    <title>{{ props.title }}</title>
    <meta name="description" :content="props.description" />

    <meta property="og:type" content="website" />
    <meta v-if="props.canonicalUrl" property="og:url" :content="props.canonicalUrl" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" :content="props.title" />
    <meta property="og:description" :content="props.description" />
    <meta property="og:image" :content="ogImage" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" :content="ogImageAlt" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" :content="props.title" />
    <meta name="twitter:description" :content="props.description" />
    <meta name="twitter:image" :content="ogImage" />
    <meta name="twitter:image:alt" :content="ogImageAlt" />
  </Head>

  <AppLayout :breadcrumbs="props.breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <slot />
      </div>
    </div>
  </AppLayout>
</template>
