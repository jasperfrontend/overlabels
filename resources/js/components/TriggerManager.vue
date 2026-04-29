<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChevronRight, ChevronsDownUp, ChevronsUpDown, Save, Zap } from 'lucide-vue-next';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
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

interface TriggerRow {
  event_type: string;
  event_label: string;
  duration_ms: number;
  enabled: boolean;
}

interface ExternalTriggerRow extends TriggerRow {
  service: string;
}

const DEFAULT_DURATION_MS = 5000;

const twitchRows = ref<TriggerRow[]>(
  Object.entries(props.triggers.eventTypes).map(([eventType, label]) => {
    const existing = props.triggers.assigned.twitch.find((t) => t.event_type === eventType);
    return {
      event_type: eventType,
      event_label: label,
      duration_ms: existing?.duration_ms ?? DEFAULT_DURATION_MS,
      enabled: existing?.enabled ?? false,
    };
  }),
);

const externalRows = ref<ExternalTriggerRow[]>(
  props.triggers.connectedServices.flatMap((service) => {
    const eventTypes = props.triggers.externalEventTypes[service] ?? {};
    return Object.entries(eventTypes).map(([eventType, label]) => {
      const existing = props.triggers.assigned.external.find(
        (e) => e.service === service && e.event_type === eventType,
      );
      return {
        service,
        event_type: eventType,
        event_label: label,
        duration_ms: existing?.duration_ms ?? DEFAULT_DURATION_MS,
        enabled: existing?.enabled ?? false,
      };
    });
  }),
);

const externalRowsByService = computed(() => {
  const grouped: Record<string, ExternalTriggerRow[]> = {};
  for (const row of externalRows.value) {
    (grouped[row.service] ??= []).push(row);
  }
  return grouped;
});

const enabledCount = computed(
  () => twitchRows.value.filter((r) => r.enabled).length + externalRows.value.filter((r) => r.enabled).length,
);

const EXPANDED_KEY = 'triggers_manager_expanded';

function loadExpandedState(): Record<string, boolean> {
  try {
    const stored = localStorage.getItem(EXPANDED_KEY);
    if (stored) return JSON.parse(stored);
  } catch {
    // ignore
  }
  return {};
}

function saveExpandedState(): void {
  try {
    localStorage.setItem(EXPANDED_KEY, JSON.stringify(expandedGroups.value));
  } catch {
    // ignore
  }
}

const expandedGroups = ref<Record<string, boolean>>(loadExpandedState());

function isGroupExpanded(label: string): boolean {
  return expandedGroups.value[label] ?? true;
}

function toggleGroup(label: string): void {
  expandedGroups.value[label] = !isGroupExpanded(label);
  saveExpandedState();
}

const groupLabels = computed(() => {
  const labels = ['Twitch'];
  for (const service of props.triggers.connectedServices) {
    labels.push(SERVICE_LABELS[service] ?? service);
  }
  return labels;
});

const allExpanded = computed(() => groupLabels.value.every((l) => isGroupExpanded(l)));

function toggleAll(): void {
  const next = !allExpanded.value;
  for (const label of groupLabels.value) {
    expandedGroups.value[label] = next;
  }
  saveExpandedState();
}

function clampDuration(value: number): number {
  if (!Number.isFinite(value)) return 1;
  return Math.min(999, Math.max(1, Math.round(value)));
}

function onDurationInput(row: TriggerRow, raw: string) {
  const seconds = Number(raw);
  row.duration_ms = clampDuration(seconds || 1) * 1000;
}

const isSaving = ref(false);

function save() {
  isSaving.value = true;

  const payload = {
    twitch: twitchRows.value
      .filter((r) => r.enabled)
      .map((r) => ({ event_type: r.event_type, duration_ms: r.duration_ms, enabled: r.enabled })),
    external: externalRows.value
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

function countEnabled(rows: TriggerRow[]): number {
  return rows.filter((r) => r.enabled).length;
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
      <p class="text-sm text-foreground">
        Pick the events that should fire this alert. One alert template can be reused across many events.
      </p>
      <button type="button" :disabled="isSaving" class="btn btn-primary btn-sm shrink-0" @click="save">
        <Save class="mr-1.5 h-3.5 w-3.5" />
        {{ isSaving ? 'Saving...' : 'Save triggers' }}
      </button>
    </div>

    <div class="mb-3 flex items-center text-xs text-muted-foreground">
      <span>
        {{ enabledCount }} event{{ enabledCount !== 1 ? 's' : '' }} firing this alert
      </span>
      <button
        v-if="groupLabels.length > 0"
        class="ml-auto flex cursor-pointer items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
        @click.prevent="toggleAll"
      >
        <ChevronsDownUp v-if="allExpanded" :size="13" />
        <ChevronsUpDown v-else :size="13" />
        {{ allExpanded ? 'Collapse all' : 'Expand all' }}
      </button>
    </div>

    <div class="space-y-1.5">
      <!-- Twitch group -->
      <Collapsible :open="isGroupExpanded('Twitch')" @update:open="toggleGroup('Twitch')">
        <CollapsibleTrigger
          class="group flex w-full cursor-pointer items-center gap-2 rounded-md bg-sidebar px-2 py-4 text-left transition-colors hover:bg-sidebar-accent/50"
          :class="{ 'rounded-b-none bg-sidebar-accent/50 pb-0': isGroupExpanded('Twitch') }"
        >
          <ChevronRight
            :size="14"
            class="shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-90"
          />
          <Zap class="h-3.5 w-3.5 shrink-0 text-violet-400" />
          <span class="text-sm font-medium">Twitch</span>
          <span class="ml-auto bg-card px-2.5 py-1.5 text-xs">
            {{ countEnabled(twitchRows) }}/{{ twitchRows.length }}
          </span>
        </CollapsibleTrigger>

        <CollapsibleContent>
          <div class="flex flex-col gap-2 bg-sidebar/50 p-4">
            <div
              v-for="row in twitchRows"
              :key="row.event_type"
              class="flex flex-wrap items-center gap-3 rounded-sm border border-sidebar-border bg-sidebar-accent p-3"
            >
              <label class="relative inline-flex cursor-pointer items-center" :title="row.enabled ? 'Disable' : 'Enable'">
                <input v-model="row.enabled" type="checkbox" class="peer sr-only" @change="save" />
                <span
                  class="peer h-6 w-10 rounded-full bg-gray-300 peer-checked:bg-green-400 peer-focus:outline-none after:absolute after:inset-s-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-4 dark:bg-gray-600 dark:peer-checked:bg-green-800 dark:after:bg-gray-100"
                />
              </label>

              <div class="min-w-0 flex-1">
                <div class="font-medium text-foreground">{{ row.event_label }}</div>
                <div class="font-mono text-xs text-muted-foreground">{{ row.event_type }}</div>
              </div>

              <div class="flex items-center gap-2" :class="{ 'opacity-40': !row.enabled }">
                <input
                  :value="row.duration_ms / 1000"
                  type="number"
                  min="1"
                  max="999"
                  step="1"
                  class="input-border h-9 w-20 rounded-sm"
                  :disabled="!row.enabled"
                  @input="onDurationInput(row, ($event.target as HTMLInputElement).value)"
                  @blur="save"
                  @keydown.enter.prevent="save"
                />
                <span class="text-xs text-muted-foreground">sec</span>
              </div>
            </div>
          </div>
        </CollapsibleContent>
      </Collapsible>

      <!-- External service groups -->
      <Collapsible
        v-for="service in props.triggers.connectedServices"
        :key="service"
        :open="isGroupExpanded(SERVICE_LABELS[service] ?? service)"
        @update:open="toggleGroup(SERVICE_LABELS[service] ?? service)"
      >
        <CollapsibleTrigger
          class="group flex w-full cursor-pointer items-center gap-2 rounded-md bg-sidebar px-2 py-4 text-left transition-colors hover:bg-sidebar-accent/50"
          :class="{ 'rounded-b-none bg-sidebar-accent/50 pb-0': isGroupExpanded(SERVICE_LABELS[service] ?? service) }"
        >
          <ChevronRight
            :size="14"
            class="shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-90"
          />
          <span class="rounded-full border border-orange-400/40 px-2 py-0.5 text-[10px] text-orange-400">
            external
          </span>
          <span class="text-sm font-medium">{{ SERVICE_LABELS[service] ?? service }}</span>
          <span class="ml-auto bg-card px-2.5 py-1.5 text-xs">
            {{ countEnabled(externalRowsByService[service] ?? []) }}/{{ (externalRowsByService[service] ?? []).length }}
          </span>
        </CollapsibleTrigger>

        <CollapsibleContent>
          <div class="flex flex-col gap-2 bg-sidebar/50 p-4">
            <div
              v-for="row in externalRowsByService[service] ?? []"
              :key="`${row.service}:${row.event_type}`"
              class="flex flex-wrap items-center gap-3 rounded-sm border border-sidebar-border bg-sidebar-accent p-3"
            >
              <label class="relative inline-flex cursor-pointer items-center" :title="row.enabled ? 'Disable' : 'Enable'">
                <input v-model="row.enabled" type="checkbox" class="peer sr-only" @change="save" />
                <span
                  class="peer h-6 w-10 rounded-full bg-gray-300 peer-checked:bg-green-400 peer-focus:outline-none after:absolute after:inset-s-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-4 dark:bg-gray-600 dark:peer-checked:bg-violet-700 dark:after:bg-gray-100"
                />
              </label>

              <div class="min-w-0 flex-1">
                <div class="font-medium text-foreground">{{ row.event_label }}</div>
                <div class="font-mono text-xs text-muted-foreground">{{ row.service }}:{{ row.event_type }}</div>
              </div>

              <div class="flex items-center gap-2" :class="{ 'opacity-40': !row.enabled }">
                <input
                  :value="row.duration_ms / 1000"
                  type="number"
                  min="1"
                  max="999"
                  step="1"
                  class="input-border h-9 w-20 rounded-sm"
                  :disabled="!row.enabled"
                  @input="onDurationInput(row, ($event.target as HTMLInputElement).value)"
                  @blur="save"
                  @keydown.enter.prevent="save"
                />
                <span class="text-xs text-muted-foreground">sec</span>
              </div>
            </div>
          </div>
        </CollapsibleContent>
      </Collapsible>
    </div>
  </div>
</template>
