<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Save, Zap } from 'lucide-vue-next';
import FilterableGroupedList, { type FilterableGroup } from '@/components/FilterableGroupedList.vue';
import TriggerRow from '@/components/TriggerRow.vue';
import { SERVICE_LABELS } from '@/utils/services';

interface TwitchAssignment {
  event_type: string;
  duration_ms: number;
  enabled: boolean;
}

interface ExternalAssignment {
  service: string;
  event_type: string;
  duration_ms: number;
  enabled: boolean;
}

interface TriggerData {
  eventTypes: Record<string, string>;
  externalEventTypes: Record<string, Record<string, string>>;
  connectedServices: string[];
  assigned: {
    twitch: TwitchAssignment[];
    external: ExternalAssignment[];
  };
}

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
  duration_ms: number;
  enabled: boolean;
  /** Filled for external rows, empty for Twitch. Drives the "kofi:donation" key text. */
  service: string;
}

const DEFAULT_DURATION_MS = 5000;
const TWITCH_GROUP_KEY = 'twitch';

const twitchRows = ref<Row[]>(
  Object.entries(props.triggers.eventTypes).map(([eventType, label]) => {
    const existing = props.triggers.assigned.twitch.find((t) => t.event_type === eventType);
    return {
      event_type: eventType,
      event_label: label,
      duration_ms: existing?.duration_ms ?? DEFAULT_DURATION_MS,
      enabled: existing?.enabled ?? false,
      service: '',
    };
  }),
);

const externalRowsByService = ref<Record<string, Row[]>>(
  props.triggers.connectedServices.reduce(
    (acc, service) => {
      const eventTypes = props.triggers.externalEventTypes[service] ?? {};
      acc[service] = Object.entries(eventTypes).map(([eventType, label]) => {
        const existing = props.triggers.assigned.external.find(
          (e) => e.service === service && e.event_type === eventType,
        );
        return {
          event_type: eventType,
          event_label: label,
          duration_ms: existing?.duration_ms ?? DEFAULT_DURATION_MS,
          enabled: existing?.enabled ?? false,
          service,
        };
      });
      return acc;
    },
    {} as Record<string, Row[]>,
  ),
);

const groups = computed<FilterableGroup<Row>[]>(() => {
  const all: FilterableGroup<Row>[] = [
    {
      key: TWITCH_GROUP_KEY,
      label: 'Twitch',
      items: twitchRows.value,
      badge: `${twitchRows.value.filter((r) => r.enabled).length}/${twitchRows.value.length}`,
    },
  ];

  for (const service of props.triggers.connectedServices) {
    const rows = externalRowsByService.value[service] ?? [];
    all.push({
      key: service,
      label: SERVICE_LABELS[service] ?? service,
      items: rows,
      badge: `${rows.filter((r) => r.enabled).length}/${rows.length}`,
    });
  }

  return all;
});

const enabledCount = computed(() => groups.value.reduce((s, g) => s + g.items.filter((r) => r.enabled).length, 0));

function rowKeyText(row: Row): string {
  return row.service ? `${row.service}:${row.event_type}` : row.event_type;
}

function rowSearchText(row: Row): string {
  return `${row.event_label} ${row.event_type} ${row.service} ${rowKeyText(row)}`;
}

const isSaving = ref(false);

function save() {
  isSaving.value = true;

  const externalRows = Object.values(externalRowsByService.value).flat();

  const payload = {
    twitch: twitchRows.value
      .filter((r) => r.enabled)
      .map((r) => ({ event_type: r.event_type, duration_ms: r.duration_ms, enabled: r.enabled })),
    external: externalRows
      .filter((r) => r.enabled)
      .map((r) => ({
        service: r.service,
        event_type: r.event_type,
        duration_ms: r.duration_ms,
        enabled: r.enabled,
      })),
  };

  router.put(route('templates.triggers', props.templateId), payload, {
    preserveScroll: true,
    onSuccess: () => emit('saved'),
    onError: () => emit('error', 'Failed to save triggers.'),
    onFinish: () => {
      isSaving.value = false;
    },
  });
}
</script>

<template>
  <FilterableGroupedList
    :groups="groups"
    :item-search-text="rowSearchText"
    :items-label="{ singular: 'trigger', plural: 'triggers' }"
    placeholder="Filter triggers..."
    expanded-storage-key="triggers_manager_expanded"
  >
    <template #description>
      Pick the events that should fire this alert. One alert template can be reused across many events.
      <div class="mt-1.5 text-xs text-muted-foreground">
        {{ enabledCount }} event{{ enabledCount !== 1 ? 's' : '' }} firing this alert
      </div>
    </template>

    <template #header-actions>
      <button type="button" :disabled="isSaving" class="btn btn-primary btn-sm shrink-0 cursor-pointer" @click="save">
        <Save class="mr-1.5 h-3.5 w-3.5" />
        {{ isSaving ? 'Saving...' : 'Save triggers' }}
      </button>
    </template>

    <template #group-icon="{ group }">
      <Zap v-if="group.key === TWITCH_GROUP_KEY" class="h-3.5 w-3.5 shrink-0 text-violet-400" />
      <span
        v-else
        class="rounded-full border border-orange-400/40 px-2 py-0.5 text-[10px] text-orange-400"
      >
        external
      </span>
    </template>

    <template #item="{ item, group }">
      <TriggerRow
        v-model:enabled="item.enabled"
        v-model:duration-ms="item.duration_ms"
        :label="item.event_label"
        :key-text="rowKeyText(item)"
        :toggle-checked-class="
          group.key === TWITCH_GROUP_KEY
            ? 'peer-checked:bg-green-400 dark:peer-checked:bg-green-800'
            : 'peer-checked:bg-green-400 dark:peer-checked:bg-violet-700'
        "
        @save="save"
      />
    </template>
  </FilterableGroupedList>
</template>
