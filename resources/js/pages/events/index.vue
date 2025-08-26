<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { AlertTriangleIcon, CheckCircle2Icon, CircleIcon, Sparkles, Radio } from 'lucide-vue-next';
import axios from 'axios';
import type { BreadcrumbItem } from '@/types/index.js';

interface EventMapping {
  id?: number;
  event_type: string;
  template_id: number | null;
  duration_ms: number;
  transition_type: string;
  enabled: boolean;
}

interface Template {
  id: number;
  name: string;
  description: string;
}

const props = defineProps<{
  mappings: EventMapping[];
  alertTemplates: Template[];
  eventTypes: Record<string, string>;
  transitionTypes: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Event Configuration',
    href: '/events',
  },
];

const localMappings = ref<EventMapping[]>(
  props.mappings.map((mapping) => ({
    ...mapping,
    duration_ms: mapping.duration_ms || 5000,
    transition_type: mapping.transition_type || 'fade',
    enabled: mapping.enabled || false,
  })),
);

// Computed properties
const activeEvents = computed(() => {
  return localMappings.value.filter((m) => m.enabled && m.template_id).length;
});

const enabledWithoutTemplate = computed(() => {
  return localMappings.value.filter((m) => m.enabled && !m.template_id).length;
});

const totalEnabled = computed(() => {
  return localMappings.value.filter((m) => m.enabled).length;
});

const isSaving = ref(false);
const expandedEvent = ref<string | null>(null);

// Toast state
const toastMessage = ref<string>('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);

const getTemplateName = (templateId: number | null): string => {
  if (!templateId) return 'No template selected';
  const template = props.alertTemplates.find((t) => t.id === templateId);
  return template?.name || 'Unknown template';
};

const toggleEvent = (eventType: string) => {
  if (expandedEvent.value === eventType) {
    expandedEvent.value = null;
  } else {
    expandedEvent.value = eventType;
  }
};

const saveAllMappings = async () => {
  isSaving.value = true;

  try {
    const mappingsToSave = localMappings.value.map((mapping) => ({
      event_type: mapping.event_type,
      template_id: mapping.template_id,
      duration_ms: mapping.duration_ms,
      transition_type: mapping.transition_type,
      enabled: mapping.enabled,
    }));

    const response = await axios.put('/events/bulk', {
      mappings: mappingsToSave,
    });

    showToast.value = true;
    toastMessage.value = 'Settings saved successfully: ' + response.data.message;
    toastType.value = 'success';

  } catch (error: any) {
    if (error.response) {
      alert(`Error: ${error.response.data.error || 'Failed to save settings'}`);
    } else {
      alert('Network error occurred while saving settings');
    }
  } finally {
    isSaving.value = false;
  }
};
</script>

<template>
  <Head title="Event Alert Configuration" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <!-- Header Section -->
      <div class="mb-8 flex items-start justify-between">
        <div class="flex items-center gap-2">
          <Radio class="w-6 h-6 mr-2" />
          <Heading title="Event Alert Configuration" description="Connect Twitch events to your alert templates" />
        </div>
        <div class="flex gap-3">
          <Link :href="route('templates.create', { type: 'alert' })" class="btn btn-secondary"> Create Template </Link>
          <button @click="saveAllMappings" :disabled="isSaving" class="btn btn-primary min-w-[140px]">
            {{ isSaving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>

      <!-- Status Summary - Only show if there are templates -->
      <div v-if="alertTemplates.length > 0" class="mb-8 flex gap-4">
        <div class="flex items-center gap-2 text-sm">
          <CheckCircle2Icon class="h-4 w-4 text-green-500" />
          <span class="text-muted-foreground">
            <span class="font-medium text-foreground">{{ activeEvents }}</span> active
          </span>
        </div>
        <div v-if="enabledWithoutTemplate > 0" class="flex items-center gap-2 text-sm">
          <AlertTriangleIcon class="h-4 w-4 text-yellow-500" />
          <span class="text-muted-foreground">
            <span class="font-medium text-foreground">{{ enabledWithoutTemplate }}</span> missing template
          </span>
        </div>
        <div class="flex items-center gap-2 text-sm">
          <CircleIcon class="h-4 w-4 text-muted-foreground" />
          <span class="text-muted-foreground">
            <span class="font-medium text-foreground">{{ localMappings.length - totalEnabled }}</span> disabled
          </span>
        </div>
      </div>

      <!-- No Templates Warning -->
      <div v-if="alertTemplates.length === 0" class="mb-8 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-6">
        <div class="flex gap-3">
          <AlertTriangleIcon class="mt-0.5 h-5 w-5 flex-shrink-0 text-yellow-500" />
          <div>
            <Heading title="No Alert Templates Available" description="Create your first alert template to start configuring events." />
            <Link :href="route('templates.create')" class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:text-primary/80">
              <Sparkles class="h-4 w-4" />
              Create your first template
            </Link>
          </div>
        </div>
      </div>

      <!-- Events List -->
      <div class="space-y-2">
        <div v-for="mapping in localMappings" :key="mapping.event_type" class="group">
          <!-- Event Row -->
          <div
            class="flex cursor-pointer items-center gap-4 rounded-2xl border bg-accent/20 hover:bg-accent/50 p-4 text-center transition"
            :class="{
              'bg-accent/50 rounded-b-none border border-b-0': mapping.enabled && expandedEvent === mapping.event_type,
              'bg-card': !mapping.enabled || expandedEvent !== mapping.event_type,
            }"
            @click="toggleEvent(mapping.event_type)"
          >
            <!-- Enable Toggle -->
            <label class="relative inline-flex cursor-pointer items-center" @click.stop>
              <input type="checkbox" v-model="mapping.enabled" class="peer sr-only" />
              <span
                class="peer h-6 w-10 rounded-full bg-gray-300 peer-checked:bg-green-400 peer-focus:outline-none after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-4 dark:bg-gray-600 dark:peer-checked:bg-green-700 dark:after:bg-gray-100"
              ></span>
            </label>

            <!-- Event Info -->
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-3">
                <h3 class="font-medium text-foreground">
                  {{ eventTypes[mapping.event_type] }}
                </h3>
                <span class="font-mono text-xs text-muted-foreground">
                  {{ mapping.event_type }}
                </span>
              </div>

              <!-- Quick Status when enabled -->
              <div v-if="mapping.enabled" class="mt-1 flex items-center gap-4 text-sm">
                <span class="flex items-center gap-1.5" :class="mapping.template_id ? 'text-muted-foreground' : 'text-yellow-600'">
                  <span class="font-medium">Template:</span>
                  {{ getTemplateName(mapping.template_id) }}
                </span>
                <span class="text-muted-foreground">•</span>
                <span class="text-muted-foreground"> {{ mapping.duration_ms / 1000 }}s </span>
                <span class="text-muted-foreground">•</span>
                <span class="text-muted-foreground">
                  {{ transitionTypes[mapping.transition_type] }}
                </span>
              </div>
            </div>

            <!-- Status Indicator -->
            <div class="flex items-center gap-2">
              <div v-if="mapping.enabled && !mapping.template_id" class="h-2 w-2 rounded-full bg-yellow-500" title="Template not selected"></div>
              <div v-else-if="mapping.enabled" class="h-2 w-2 rounded-full bg-green-500" title="Active"></div>
              <svg
                class="h-5 w-5 text-muted-foreground transition-transform"
                :class="{ 'rotate-180': expandedEvent === mapping.event_type }"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </div>

          <!-- Configuration Panel -->
          <div
            v-if="mapping.enabled && expandedEvent === mapping.event_type"
            class="mb-2 border bg-accent/50 border-t-0 rounded-b-2xl p-4"
            @click.stop
          >
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
              <!-- Template Selection -->
              <div>
                <label class="mb-2 block text-sm font-medium text-foreground"> Alert Template </label>
                <select
                  v-model="mapping.template_id"
                  class="w-full rounded-md border bg-background px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                  :class="{
                    'border-yellow-500 bg-yellow-500/10 dark:bg-yellow-900': !mapping.template_id,
                    'border-input': mapping.template_id,
                  }"
                >
                  <option :value="null">Select a template...</option>
                  <option v-for="template in [...alertTemplates].sort((a, b) => b.id - a.id)" :key="template.id" :value="template.id">
                    {{ template.name }}
                  </option>
                </select>
                <p v-if="!mapping.template_id" class="mt-1 text-xs text-yellow-600">Select a template to show alerts</p>
              </div>

              <!-- Duration Slider -->
              <div>
                <label class="mb-2 block text-sm font-medium text-foreground"> Duration: {{ mapping.duration_ms / 1000 }}s </label>
                <div class="flex items-center gap-3 pt-2">
                  <span class="text-xs text-muted-foreground">1s</span>
                  <input
                    v-model.number="mapping.duration_ms"
                    type="range"
                    min="1000"
                    max="30000"
                    step="500"
                    class="h-2 flex-1 cursor-pointer appearance-none rounded-lg text-primary focus:outline-none ring-1 focus:ring-primary"
                  />
                  <span class="text-xs text-muted-foreground">30s</span>
                </div>
              </div>

              <!-- Transition Type -->
              <div>
                <label class="mb-2 block text-sm font-medium text-foreground"> Transition Effect </label>
                <select
                  v-model="mapping.transition_type"
                  class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                >
                  <option v-for="(label, value) in transitionTypes" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Preview Description -->
            <div v-if="mapping.template_id" class="mt-4 border rounded-md bg-green-400/15 text-green-700 dark:text-green-400 border-green-400 dark:border-green-400 p-3">
              <p class="text-sm">
                <span class="font-medium">Preview:</span>
                When a {{ eventTypes[mapping.event_type].toLowerCase() }} occurs, the "{{ getTemplateName(mapping.template_id) }}" alert will
                {{ transitionTypes[mapping.transition_type].toLowerCase() }} in for {{ mapping.duration_ms / 1000 }} seconds.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Save Button -->
      <div class="mt-8 flex justify-end border-t border-border pt-6">
        <button @click="saveAllMappings" :disabled="isSaving" class="btn btn-primary min-w-[160px]">
          {{ isSaving ? 'Saving Changes...' : 'Save All Changes' }}
        </button>
      </div>
    </div>

    <!-- Toast Notification -->
    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" />
  </AppLayout>
</template>

<style scoped>
/* Custom range input styling */
input[type='range']::-webkit-slider-thumb {
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: hsl(var(--primary));
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  border: 2px solid white;
}

input[type='range']::-moz-range-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: hsl(var(--primary));
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  border: 2px solid white;
}

input[type='range']::-webkit-slider-thumb:hover {
  transform: scale(1.1);
}

input[type='range']::-moz-range-thumb:hover {
  transform: scale(1.1);
}

/* Dark mode adjustments for range track */
@media (prefers-color-scheme: dark) {
  input[type='range'] {
    background: rgba(255, 255, 255, 0.1);
  }
}
</style>
