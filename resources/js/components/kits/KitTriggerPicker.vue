<script setup lang="ts">
import { computed, watch } from 'vue';
import { X } from '@lucide/vue';
import { serviceLabel } from '@/utils/services';

/**
 * Which event fires each alert in a kit. It is what the kit page previews
 * with, and what copying the kit wires onto the copier's Triggers. An alert
 * entering the kit starts from the author's own Triggers for it; one event can
 * belong to one alert only, because a copier can only have one alert per event.
 */
export interface KitTrigger {
  template_id: number;
  service: string;
  event_type: string;
  duration_ms: number;
}

interface EventGroup {
  service: string;
  events: Array<{ event_type: string; label: string }>;
}

const props = defineProps<{
  alerts: Array<{ id: number; name: string }>;
  events: EventGroup[];
  authorTriggers: Record<string, Array<{ service: string; event_type: string; duration_ms: number }>>;
  // False on a kit that already declares triggers: an alert its author left
  // without events stays that way instead of refilling on every visit.
  prefillOnLoad?: boolean;
}>();

const model = defineModel<KitTrigger[]>({ required: true });

const DEFAULT_MS = 5000;
const keyOf = (service: string, eventType: string) => `${service}:${eventType}`;

const labels = computed(() => {
  const out: Record<string, string> = {};
  for (const group of props.events) for (const e of group.events) out[keyOf(group.service, e.event_type)] = e.label;
  return out;
});

const claimed = computed(() => new Set(model.value.map((t) => keyOf(t.service, t.event_type))));

// An alert entering the kit starts from how its author uses it; one leaving
// takes its triggers with it.
watch(
  () => props.alerts.map((a) => a.id),
  (ids, before) => {
    const kept = model.value.filter((t) => ids.includes(t.template_id));
    const previous = before ?? (props.prefillOnLoad === false ? ids : []);
    const fresh = ids.filter((id) => !previous.includes(id) && !kept.some((t) => t.template_id === id));
    const taken = new Set(kept.map((t) => keyOf(t.service, t.event_type)));
    for (const id of fresh) {
      for (const t of props.authorTriggers[String(id)] ?? []) {
        const key = keyOf(t.service, t.event_type);
        if (taken.has(key) || !labels.value[key]) continue;
        taken.add(key);
        kept.push({ template_id: id, service: t.service, event_type: t.event_type, duration_ms: t.duration_ms || DEFAULT_MS });
      }
    }
    if (kept.length !== model.value.length || fresh.length) model.value = kept;
  },
  { immediate: true },
);

function triggersOf(id: number) {
  return model.value.filter((t) => t.template_id === id);
}

function secondsOf(id: number): number {
  return Math.round((triggersOf(id)[0]?.duration_ms ?? DEFAULT_MS) / 1000);
}

function setSeconds(id: number, value: string) {
  const ms = Math.min(60, Math.max(1, Number(value) || DEFAULT_MS / 1000)) * 1000;
  model.value = model.value.map((t) => (t.template_id === id ? { ...t, duration_ms: ms } : t));
}

function add(id: number, value: string) {
  if (!value) return;
  const [service, eventType] = value.split('|');
  if (claimed.value.has(keyOf(service, eventType))) return;
  model.value = [...model.value, { template_id: id, service, event_type: eventType, duration_ms: secondsOf(id) * 1000 }];
}

function remove(t: KitTrigger) {
  model.value = model.value.filter((x) => x !== t);
}
</script>

<template>
  <div class="space-y-4">
    <div v-for="alert in alerts" :key="alert.id" class="rounded-lg border p-3">
      <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <span class="font-medium text-foreground">{{ alert.name }}</span>
        <label class="flex items-center gap-2 text-sm text-muted-foreground">
          Shows for
          <input
            type="number"
            min="1"
            max="60"
            class="w-16 rounded-sm border bg-background px-2 py-1 text-foreground"
            :value="secondsOf(alert.id)"
            @change="setSeconds(alert.id, ($event.target as HTMLInputElement).value)"
          />
          seconds
        </label>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <span
          v-for="t in triggersOf(alert.id)"
          :key="`${t.service}:${t.event_type}`"
          class="flex items-center gap-1 border border-violet-400/60 px-2 py-1 text-sm text-foreground"
        >
          {{ labels[`${t.service}:${t.event_type}`] }}
          <button
            type="button"
            class="cursor-pointer text-muted-foreground hover:text-foreground"
            :aria-label="`Remove ${labels[`${t.service}:${t.event_type}`]}`"
            @click="remove(t)"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </span>

        <select
          class="cursor-pointer rounded-sm border bg-background px-2 py-1 text-sm text-foreground"
          :aria-label="`Add an event that fires ${alert.name}`"
          @change="
            add(alert.id, ($event.target as HTMLSelectElement).value);
            ($event.target as HTMLSelectElement).value = '';
          "
        >
          <option value="">Add an event...</option>
          <optgroup v-for="group in events" :key="group.service" :label="serviceLabel(group.service)">
            <option
              v-for="e in group.events"
              :key="e.event_type"
              :value="`${group.service}|${e.event_type}`"
              :disabled="claimed.has(`${group.service}:${e.event_type}`)"
            >
              {{ e.label }}
            </option>
          </optgroup>
        </select>
      </div>

      <p v-if="triggersOf(alert.id).length === 0" class="mt-2 text-sm text-muted-foreground">
        No events yet. The preview will still play it with a sample tip, but copying the kit will not wire it to anything.
      </p>
    </div>
  </div>
</template>
