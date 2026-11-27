<!--
  A plain <script> block alongside <script setup>: the latter allows type
  exports but not runtime ones, so firstAssignedEvent() has to live here to be
  importable. The two blocks share module scope, so <script setup> below uses
  TriggerData directly.
-->
<script lang="ts">
interface TwitchAssignment {
  event_type: string;
  condition_type: string | null;
  condition_value: number | null;
  duration_ms: number;
  enabled: boolean;
}

interface ExternalAssignment {
  service: string;
  event_type: string;
  condition_type: string | null;
  condition_value: number | null;
  duration_ms: number;
  enabled: boolean;
}

export interface TriggerData {
  eventTypes: Record<string, string>;
  externalEventTypes: Record<string, Record<string, string>>;
  /** Event types that support a variant condition, mapped to their unit label. */
  amountFields: Record<string, { path: string; unit: string }>;
  /** External (service -> event types) that support an amount condition. */
  externalAmountEventTypes: Record<string, string[]>;
  connectedServices: string[];
  assigned: {
    twitch: TwitchAssignment[];
    external: ExternalAssignment[];
  };
}

/**
 * The first event bound to a template, for the icon and label beside its title
 * on the show and edit pages. Twitch mappings win over external ones, matching
 * firstEvent() in TemplateCollection.vue, so the list and the two detail pages
 * never disagree about which binding they are showing.
 *
 * `source` is always set - the icon and the colour need to know a Twitch
 * binding is Twitch. `service` is external-only, because it drives the label
 * prefix: passing 'twitch' into eventLabel() would render "Follow" as
 * "Twitch Follow".
 *
 * Returns null when `triggers` is null, which buildTriggerData() makes the case
 * for static overlays and for anyone viewing a template they do not own.
 */
export function firstAssignedEvent(triggers?: TriggerData | null): { eventType: string; source: string; service?: string } | null {
  const assigned = triggers?.assigned;
  if (!assigned) return null;

  const twitch = assigned.twitch?.[0];
  if (twitch) return { eventType: twitch.event_type, source: 'twitch' };

  const ext = assigned.external?.[0];
  if (ext) return { eventType: ext.event_type, source: ext.service, service: ext.service };

  return null;
}
</script>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import ActiveFirstPicker, { type PickerGroup } from '@/components/ActiveFirstPicker.vue';
import TriggerRow from '@/components/TriggerRow.vue';
import { SERVICE_LABELS } from '@/utils/services';
import { TWITCH_TRIGGER_GROUPS, stripServicePrefix, twitchTriggerGroup } from '@/utils/triggerGroups';

const props = defineProps<{
  templateId: number;
  triggers: TriggerData;
}>();

const emit = defineEmits<{
  saved: [];
  error: [message: string];
}>();

interface Row {
  event_type: string;
  event_label: string;
  condition_type: string | null;
  condition_value: number | null;
  duration_ms: number;
  enabled: boolean;
  /** Filled for external rows, empty for Twitch. Drives the "kofi:donation" key text. */
  service: string;
  /** The picker tile this row sits under. */
  group_key: string;
  /** "Twitch" or the service's name, on the active row and on a search result. */
  source_label: string;
}

const DEFAULT_DURATION_MS = 5000;

const twitchRows: Row[] = Object.entries(props.triggers.eventTypes).map(([eventType, label]) => {
  const existing = props.triggers.assigned.twitch.find((t) => t.event_type === eventType);
  return {
    event_type: eventType,
    event_label: label,
    condition_type: existing?.condition_type ?? null,
    condition_value: existing?.condition_value ?? null,
    duration_ms: existing?.duration_ms ?? DEFAULT_DURATION_MS,
    enabled: existing?.enabled ?? false,
    service: '',
    group_key: twitchTriggerGroup(eventType).key,
    source_label: 'Twitch',
  };
});

const externalRows: Row[] = props.triggers.connectedServices.flatMap((service) => {
  const serviceLabel = SERVICE_LABELS[service] ?? service;
  return Object.entries(props.triggers.externalEventTypes[service] ?? {}).map(([eventType, label]) => {
    const existing = props.triggers.assigned.external.find((e) => e.service === service && e.event_type === eventType);
    return {
      event_type: eventType,
      event_label: stripServicePrefix(label, serviceLabel),
      condition_type: existing?.condition_type ?? null,
      condition_value: existing?.condition_value ?? null,
      duration_ms: existing?.duration_ms ?? DEFAULT_DURATION_MS,
      enabled: existing?.enabled ?? false,
      service,
      group_key: `service:${service}`,
      source_label: serviceLabel,
    };
  });
});

// One list in catalogue order: Twitch first, then each connected service.
const rows = ref<Row[]>([...twitchRows, ...externalRows]);

const groups = computed<PickerGroup<Row>[]>(() => [
  ...TWITCH_TRIGGER_GROUPS.map((g) => ({
    key: g.key,
    title: g.title,
    section: 'Twitch',
    items: rows.value.filter((r) => !r.service && r.group_key === g.key),
  })),
  ...props.triggers.connectedServices.map((service) => ({
    key: `service:${service}`,
    title: SERVICE_LABELS[service] ?? service,
    section: 'External sources',
    items: rows.value.filter((r) => r.service === service),
  })),
]);

const activeRows = computed(() => rows.value.filter((r) => r.enabled));

const summary = computed(() => {
  const n = activeRows.value.length;
  if (n === 0) return 'No events yet';
  return `${n} event${n === 1 ? '' : 's'} on, out of ${rows.value.length} available`;
});

function rowKeyText(row: Row): string {
  return row.service ? `${row.service}:${row.event_type}` : row.event_type;
}

function rowSearchText(row: Row): string {
  return `${row.event_label} ${row.event_type} ${row.source_label} ${rowKeyText(row)}`;
}

/** Unit label for the condition picker, or undefined if the row has no amount. */
function amountUnitFor(row: Row): string | undefined {
  if (!row.service) {
    return props.triggers.amountFields[row.event_type]?.unit;
  }
  return props.triggers.externalAmountEventTypes[row.service]?.includes(row.event_type) ? 'amount' : undefined;
}

function toggle(row: Row) {
  row.enabled = !row.enabled;
  save();
}

const isSaving = ref(false);

/**
 * Every change saves on its own (`announce` false); the Save button does the
 * same save and says so. preserveState keeps the open picker and the rows
 * where they are while the page props come back.
 */
function save(announce = false) {
  isSaving.value = true;

  const enabled = rows.value.filter((r) => r.enabled);
  const payload = {
    twitch: enabled
      .filter((r) => !r.service)
      .map((r) => ({
        event_type: r.event_type,
        condition_type: r.condition_type,
        condition_value: r.condition_value,
        duration_ms: r.duration_ms,
        enabled: r.enabled,
      })),
    external: enabled
      .filter((r) => r.service)
      .map((r) => ({
        service: r.service,
        event_type: r.event_type,
        condition_type: r.condition_type,
        condition_value: r.condition_value,
        duration_ms: r.duration_ms,
        enabled: r.enabled,
      })),
  };

  router.put(route('templates.triggers', props.templateId), payload, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      if (announce) emit('saved');
    },
    onError: () => emit('error', 'Failed to save triggers.'),
    onFinish: () => {
      isSaving.value = false;
    },
  });
}
</script>

<template>
  <ActiveFirstPicker
    :groups="groups"
    :item-key="(r: Row) => `${r.service}:${r.event_type}`"
    :item-name="(r: Row) => r.event_label"
    :item-sub="rowKeyText"
    :item-search-text="rowSearchText"
    :item-badge="(r: Row) => r.source_label"
    :is-picked="(r: Row) => r.enabled"
    add-label="Add trigger"
    :search-placeholder="`Type to search ${rows.length} triggers, for example raid or hype`"
    back-label="All groups"
    :group-hint="(g: PickerGroup<Row>) => `${g.title}, ${g.items.length} event${g.items.length === 1 ? '' : 's'}`"
    @toggle="toggle"
  >
    <template #title>This alert fires when</template>
    <template #subtitle>{{ summary }}</template>
    <template #actions>
      <button type="button" :disabled="isSaving" class="btn btn-primary btn-sm cursor-pointer" @click="save(true)">
        <Save class="h-3.5 w-3.5" />
        {{ isSaving ? 'Saving...' : 'Save triggers' }}
      </button>
    </template>

    <div v-if="activeRows.length > 0" class="flex flex-col gap-1.5">
      <TriggerRow
        v-for="row in activeRows"
        :key="`${row.service}:${row.event_type}`"
        v-model:duration-ms="row.duration_ms"
        v-model:condition-type="row.condition_type"
        v-model:condition-value="row.condition_value"
        :amount-unit="amountUnitFor(row)"
        :label="row.event_label"
        :source="row.source_label"
        :key-text="rowKeyText(row)"
        @save="save()"
        @remove="toggle(row)"
      />
    </div>
    <div v-else class="border border-dashed border-violet-400/50 p-6 text-center text-sm text-muted-foreground">
      Nothing is on, so this alert never fires. Use Add trigger to pick events.
    </div>
  </ActiveFirstPicker>
</template>
