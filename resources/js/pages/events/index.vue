<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { ExternalLink, Megaphone } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import { SERVICE_LABELS } from '@/utils/services';

interface AssignedTemplate {
  id: number;
  name: string;
  slug: string;
}

interface TwitchMapping {
  event_type: string;
  event_label: string;
  duration_ms: number;
  template: AssignedTemplate | null;
}

interface ExternalMapping {
  service: string;
  event_type: string;
  event_label: string;
  duration_ms: number;
  template: AssignedTemplate | null;
}

interface UnassignedEventType {
  event_type: string;
  event_label: string;
}

const props = defineProps<{
  twitchMappings: TwitchMapping[];
  externalMappings: ExternalMapping[];
  connectedServices: string[];
  unassignedEventTypes: UnassignedEventType[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Event alerts overview', href: '/alerts' },
];

const externalByService = computed(() => {
  const grouped: Record<string, ExternalMapping[]> = {};
  for (const row of props.externalMappings) {
    (grouped[row.service] ??= []).push(row);
  }
  return grouped;
});

const totalAssigned = computed(() => props.twitchMappings.length + props.externalMappings.length);
</script>

<template>
  <Head title="Event alerts overview" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="px-4 py-3">
      <div class="mb-4 mt-1 flex items-center gap-2">
        <Megaphone class="h-6 w-6" />
        <Heading
          title="Event alerts overview"
          description="Read-only view of every event currently bound to an alert template. Edit assignments from each alert template's Triggers tab."
        />
      </div>

      <p class="mb-6 text-sm text-muted-foreground">
        {{ totalAssigned }} event{{ totalAssigned !== 1 ? 's' : '' }} are firing alerts right now.
      </p>

      <!-- Twitch group -->
      <section class="mb-8">
        <h3 class="mb-2 text-sm font-medium uppercase tracking-wide text-muted-foreground">Twitch events</h3>

        <div
          v-if="twitchMappings.length === 0"
          class="rounded-sm border border-dashed border-muted-foreground/25 p-6 text-center text-sm text-muted-foreground"
        >
          No Twitch events are currently bound to an alert template.
        </div>

        <div v-else class="space-y-2">
          <Link
            v-for="row in twitchMappings"
            :key="row.event_type"
            :href="row.template ? route('templates.show', row.template.id) : '#'"
            class="flex flex-wrap items-center gap-3 rounded-sm border border-sidebar-border bg-card p-4 transition-colors hover:border-violet-400 hover:bg-sidebar cursor-pointer"
          >
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="font-medium text-foreground">{{ row.event_label }}</span>
                <span
                  class="hidden rounded-full border border-dashed border-violet-300/30 px-2 py-0.5 text-xs font-mono text-slate-500 dark:text-slate-400 sm:inline"
                >
                  {{ row.event_type }}
                </span>
              </div>
              <div v-if="row.template" class="mt-1 text-sm text-foreground">
                {{ row.template.name }}
                <span class="text-muted-foreground"> · {{ row.duration_ms / 1000 }}s</span>
              </div>
            </div>
            <ExternalLink class="h-4 w-4 text-muted-foreground" />
          </Link>
        </div>
      </section>

      <!-- External services -->
      <section v-for="service in connectedServices" :key="service" class="mb-8">
        <h3 class="mb-2 flex items-center gap-2 text-sm font-medium uppercase tracking-wide text-muted-foreground">
          {{ SERVICE_LABELS[service] ?? service }}
          <span class="rounded-full border border-orange-400/40 px-2 py-0.5 text-[10px] text-orange-400">external</span>
        </h3>

        <div
          v-if="!externalByService[service] || externalByService[service].length === 0"
          class="rounded-sm border border-dashed border-muted-foreground/25 p-6 text-center text-sm text-muted-foreground"
        >
          No {{ SERVICE_LABELS[service] ?? service }} events are currently bound to an alert template.
        </div>

        <div v-else class="space-y-2">
          <Link
            v-for="row in externalByService[service]"
            :key="`${row.service}:${row.event_type}`"
            :href="row.template ? route('templates.show', row.template.id) : '#'"
            class="flex flex-wrap items-center gap-3 rounded-sm border border-sidebar-border bg-card p-4 transition-colors hover:border-violet-400 hover:bg-sidebar cursor-pointer"
          >
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="font-medium text-foreground">{{ row.event_label }}</span>
                <span
                  class="hidden rounded-full border border-dashed border-violet-300/30 px-2 py-0.5 text-xs font-mono text-slate-500 dark:text-slate-400 sm:inline"
                >
                  {{ row.service }}:{{ row.event_type }}
                </span>
              </div>
              <div v-if="row.template" class="mt-1 text-sm text-foreground">
                {{ row.template.name }}
                <span class="text-muted-foreground"> · {{ row.duration_ms / 1000 }}s</span>
              </div>
            </div>
            <ExternalLink class="h-4 w-4 text-muted-foreground" />
          </Link>
        </div>
      </section>

      <!-- Unassigned twitch events (informational) -->
      <section v-if="unassignedEventTypes.length > 0">
        <h3 class="mb-2 text-sm font-medium uppercase tracking-wide text-muted-foreground">
          Unassigned Twitch events
        </h3>
        <p class="mb-3 text-xs text-muted-foreground">
          These events are not currently bound to any alert template. Bind them from an alert template's Triggers tab.
        </p>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="row in unassignedEventTypes"
            :key="row.event_type"
            class="rounded-full border border-sidebar-border bg-sidebar px-3 py-1 text-xs text-muted-foreground"
            :title="row.event_type"
          >
            {{ row.event_label }}
          </span>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
