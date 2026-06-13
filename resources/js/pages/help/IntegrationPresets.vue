<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import {
  KOFI_PRESETS,
  GPS_PRESETS,
  STREAMLABS_PRESETS,
  STREAMELEMENTS_PRESETS,
  FOURTHWALL_PRESETS,
  BMAC_PRESETS,
  TWITCH_PRESETS,
  type ServicePreset,
} from '@/components/controls/controlPresets';
import { fuzzyMatch, presetHaystack, serviceLabel } from '@/utils/services';
import {
  Coffee,
  HandHeart,
  MapPinned,
  Megaphone,
  ShoppingBag,
  Sparkles,
  Tv,
  type LucideIcon,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Integration presets', href: '/help/integration-presets' },
];

interface ServiceSection {
  source: string;
  label: string;
  description: string;
  icon: LucideIcon;
  presets: ServicePreset[];
}

const sections: ServiceSection[] = [
  {
    source: 'twitch',
    label: serviceLabel('twitch'),
    description: 'Per-stream counters that reset automatically when you go live. No setup needed - these are available the moment you connect Twitch.',
    icon: Tv,
    presets: TWITCH_PRESETS,
  },
  {
    source: 'kofi',
    label: serviceLabel('kofi'),
    description: 'Donation data from your connected Ko-fi account. Updates when a viewer ships a Ko-fi donation, subscription, or shop sale.',
    icon: Coffee,
    presets: KOFI_PRESETS,
  },
  {
    source: 'streamlabs',
    label: serviceLabel('streamlabs'),
    description: 'Donation data from your connected Streamlabs account, delivered live through the Socket.IO listener.',
    icon: Megaphone,
    presets: STREAMLABS_PRESETS,
  },
  {
    source: 'streamelements',
    label: serviceLabel('streamelements'),
    description: 'Tip data from your connected StreamElements account. Authenticated with a JWT you generate in their dashboard.',
    icon: Sparkles,
    presets: STREAMELEMENTS_PRESETS,
  },
  {
    source: 'fourthwall',
    label: serviceLabel('fourthwall'),
    description: 'Donation and tip data from Fourthwall. Useful for creators using Fourthwall for merch and supporter tiers.',
    icon: ShoppingBag,
    presets: FOURTHWALL_PRESETS,
  },
  {
    source: 'bmac',
    label: serviceLabel('bmac'),
    description: 'Supporter and membership data from Buy Me a Coffee.',
    icon: HandHeart,
    presets: BMAC_PRESETS,
  },
  {
    source: 'gps',
    label: serviceLabel('gps'),
    description: 'Live GPS data from the Overlabels GPS Android app: speed, coordinates, distance, battery, and per-session aggregates.',
    icon: MapPinned,
    presets: GPS_PRESETS,
  },
];

const search = ref('');

const filteredSections = computed(() => {
  const q = search.value.trim();
  if (!q) return sections;
  return sections
    .map((section) => ({
      ...section,
      presets: section.presets.filter((p) => fuzzyMatch(q, presetHaystack(section.source, p.label))),
    }))
    .filter((section) => section.presets.length > 0);
});

const totalPresets = computed(() => sections.reduce((sum, s) => sum + s.presets.length, 0));

function tagFor(source: string, key: string): string {
  return `[[[c:${source}:${key}]]]`;
}

const copiedTag = ref<string | null>(null);
let copyTimeout: ReturnType<typeof setTimeout> | null = null;

function copyTag(tag: string) {
  if (!navigator.clipboard) return;
  navigator.clipboard.writeText(tag).then(() => {
    copiedTag.value = tag;
    if (copyTimeout) clearTimeout(copyTimeout);
    copyTimeout = setTimeout(() => {
      copiedTag.value = null;
    }, 1500);
  });
}
</script>

<template>
  <Head>
    <title>Integration Presets - Overlabels</title>
    <meta
      name="description"
      content="Reference for every auto-managed control Overlabels exposes through its integrations - Twitch, Ko-fi, Streamlabs, StreamElements, Fourthwall, BMAC, and Overlabels GPS."
    />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <div class="mb-10">
          <Heading
            title="Integration presets"
            title-class="text-4xl font-bold mb-4"
            description="These are the auto-managed controls Overlabels exposes through its integrations. Drop the tag into any overlay template and the value updates live as events come in."
          />
          <p class="mt-4 text-foreground">
            Click any <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:source:key]]]</code>
            tag to copy it. There are {{ totalPresets }} presets across {{ sections.length }} integrations.
          </p>
        </div>

        <div class="mb-8">
          <label for="preset-search" class="mb-2 block text-sm font-medium text-foreground">
            Filter presets
          </label>
          <input
            id="preset-search"
            v-model="search"
            type="search"
            placeholder="Type to filter - e.g. donation, follower, speed, kofi..."
            class="input-border w-full"
            autocomplete="off"
          />
          <p v-if="search && filteredSections.length === 0" class="mt-3 text-sm text-muted-foreground">
            No presets match "{{ search }}". Try a shorter query or a service name.
          </p>
        </div>

        <div class="mb-8 border border-sidebar-border bg-card p-4 text-sm text-foreground">
          <p>
            Want to use one of these? Open a static template, click <strong>Add control</strong>,
            and pick the preset from the <strong>Stream Controls</strong> dropdown - or paste the tag straight into your overlay HTML.
          </p>
        </div>

        <div class="space-y-10">
          <section v-for="section in filteredSections" :key="section.source" :id="section.source">
            <div class="mb-4 flex items-start gap-3">
              <component :is="section.icon" class="mt-1 size-6 shrink-0 text-violet-400" />
              <div>
                <h2 class="text-2xl font-bold">{{ section.label }}</h2>
                <p class="mt-1 text-sm text-foreground">{{ section.description }}</p>
              </div>
            </div>

            <div class="overflow-hidden border border-sidebar-border bg-card">
              <ul class="divide-y divide-sidebar">
                <li
                  v-for="preset in section.presets"
                  :key="preset.key"
                  class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="min-w-0 flex-1">
                    <p class="font-medium text-foreground">{{ preset.label }}</p>
                    <p class="text-xs text-muted-foreground">
                      Type: <span class="font-mono">{{ preset.type }}</span>
                    </p>
                  </div>
                  <a
                    href="#"
                    class="cursor-pointer self-start rounded bg-background px-2 py-1 font-mono text-sm no-underline hover:bg-violet-500/10 hover:text-violet-500 dark:hover:text-violet-300 transition-colors sm:self-auto"
                    :title="copiedTag === tagFor(section.source, preset.key) ? 'Copied!' : 'Click to copy'"
                    @click.prevent="copyTag(tagFor(section.source, preset.key))"
                  >
                    <span v-if="copiedTag === tagFor(section.source, preset.key)">Copied!</span>
                    <code v-else>{{ tagFor(section.source, preset.key) }}</code>
                  </a>
                </li>
              </ul>
            </div>
          </section>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
