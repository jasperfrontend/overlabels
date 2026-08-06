<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import CollectionList from '@/components/CollectionList.vue';
import { AlertTriangle, Megaphone } from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';
import { SERVICE_LABELS } from '@/utils/services';

interface AssignedTemplate {
  id: number;
  name: string;
  slug: string;
}

/** Fields shared by every mapping row that carries a variant condition. */
interface ConditionFields {
  condition_type: string | null;
  condition_value: number | null;
  /** Unit label when the event supports a condition (e.g. "bits"), else null. */
  condition_unit: string | null;
  /** Name of the alert that wins this exact trigger, if this row is shadowed. */
  shadowed_by: string | null;
}

interface TwitchMapping extends ConditionFields {
  event_type: string;
  event_label: string;
  duration_ms: number;
  template: AssignedTemplate | null;
}

interface ExternalMapping extends TwitchMapping {
  service: string;
}

type MappingRow = TwitchMapping | ExternalMapping;

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
  { title: 'Triggers', href: '/triggers' },
];

const totalAssigned = computed(() => props.twitchMappings.length + props.externalMappings.length);

/**
 * Twitch first, then one section per connected service. Sectioning here rather
 * than in the template keeps every trigger row rendering through one
 * CollectionList - the Twitch and external rows had drifted into two copies of
 * the same markup.
 */
const sections = computed(() => {
  const byService: Record<string, ExternalMapping[]> = {};
  for (const row of props.externalMappings) {
    (byService[row.service] ??= []).push(row);
  }

  return [
    {
      key: 'twitch',
      label: 'Twitch events',
      noun: 'Twitch events',
      external: false,
      rows: props.twitchMappings as MappingRow[],
    },
    ...props.connectedServices.map((service) => ({
      key: service,
      label: SERVICE_LABELS[service] ?? service,
      noun: `${SERVICE_LABELS[service] ?? service} events`,
      external: true,
      rows: (byService[service] ?? []) as MappingRow[],
    })),
  ];
});

function isExternal(row: MappingRow): row is ExternalMapping {
  return 'service' in row;
}

function rowKey(row: MappingRow): string {
  const scope = isExternal(row) ? `${row.service}:` : '';
  return `${scope}${row.event_type}:${row.template?.id}`;
}

/** The raw tag shown in the mono chip: `channel.cheer` or `kofi:donation`. */
function rowTag(row: MappingRow): string {
  return isExternal(row) ? `${row.service}:${row.event_type}` : row.event_type;
}

function rowHref(row: MappingRow): string | null {
  return row.template ? route('templates.show', row.template.id) : null;
}

function rowLabel(row: MappingRow): string {
  return row.template ? `${row.event_label} - ${row.template.name}` : row.event_label;
}

/**
 * A shadowed row never fires, so it takes the accent bar amber instead of the
 * default. Same channel the row skin already uses for state - no second border.
 */
function rowClass(row: MappingRow): string | undefined {
  return row.shadowed_by ? 'border-l-amber-400' : undefined;
}

/**
 * Human label for a row's variant condition, or null when the event has no
 * amount to condition on (so no condition text is shown). "amount" is the
 * unitless external/donation case - we drop the word so it doesn't read
 * "At least 50 amount".
 */
function conditionLabel(row: ConditionFields): string | null {
  if (!row.condition_unit) return null;
  const unit = row.condition_unit === 'amount' ? '' : ` ${row.condition_unit}`;
  if (row.condition_type === 'at_least') return `At least ${row.condition_value}${unit}`;
  if (row.condition_type === 'exactly') return `Exactly ${row.condition_value}${unit}`;
  return 'Any amount';
}
</script>

<template>
  <Head title="Triggers" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="px-4 py-3">
      <div class="mb-4 mt-1 flex items-center gap-2">
        <Megaphone class="h-6 w-6" />
        <Heading
          title="Triggers"
          description="Read-only view of every event currently bound to an alert template. Edit assignments from each alert template's Triggers tab."
        />
      </div>

      <p class="mb-6 text-sm text-foreground">
        {{ totalAssigned }} event{{ totalAssigned !== 1 ? 's' : '' }} are firing alerts right now.
      </p>

      <section v-for="section in sections" :key="section.key" class="mb-8">
        <h3 class="mb-2 flex items-center gap-2 text-sm font-medium uppercase tracking-wide text-muted-foreground">
          {{ section.label }}
          <span
            v-if="section.external"
            class="rounded-full border border-orange-400/40 px-2 py-0.5 text-[10px] text-orange-400"
          >
            external
          </span>
        </h3>

        <CollectionList
          :items="section.rows"
          :item-key="rowKey"
          :href="rowHref"
          :label="rowLabel"
          :row-class="rowClass"
          :empty-message="`No ${section.noun} are currently bound to an alert template.`"
          empty-dashed
        >
          <template #item="{ item: row }">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
              <span class="font-medium text-foreground">{{ row.event_label }}</span>
              <span
                class="hidden rounded-full border border-dashed border-violet-300/30 px-2 py-0.5 font-mono text-xs text-slate-500 sm:inline dark:text-slate-400"
              >
                {{ rowTag(row) }}
              </span>
            </div>

            <div v-if="row.template" class="mt-1 text-sm text-foreground">
              {{ row.template.name }}
              <span v-if="conditionLabel(row)" class="text-muted-foreground"> · {{ conditionLabel(row) }}</span>
              <span class="text-muted-foreground"> · {{ row.duration_ms / 1000 }}s</span>
            </div>

            <div
              v-if="row.shadowed_by"
              class="mt-1.5 flex items-start gap-1.5 text-xs text-amber-600 dark:text-amber-400"
            >
              <AlertTriangle class="mt-px h-3.5 w-3.5 shrink-0" />
              <span>Never fires - "{{ row.shadowed_by }}" wins this exact trigger. Change or remove this condition.</span>
            </div>
          </template>
        </CollectionList>
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
