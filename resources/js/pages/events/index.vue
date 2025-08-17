<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { AlertTriangleIcon, CheckCircle2Icon, CircleIcon, Sparkles } from 'lucide-vue-next';
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
  }
];

const localMappings = ref<EventMapping[]>(
  props.mappings.map(mapping => ({
    ...mapping,
    duration_ms: mapping.duration_ms || 5000,
    transition_type: mapping.transition_type || 'fade',
    enabled: mapping.enabled || false,
  }))
);

// Computed properties
const activeEvents = computed(() => {
  return localMappings.value.filter(m => m.enabled && m.template_id).length;
});

const enabledWithoutTemplate = computed(() => {
  return localMappings.value.filter(m => m.enabled && !m.template_id).length;
});

const totalEnabled = computed(() => {
  return localMappings.value.filter(m => m.enabled).length;
});

const isSaving = ref(false);
const expandedEvent = ref<string | null>(null);

// Toast state
const toastMessage = ref<string>('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);

const getTemplateName = (templateId: number | null): string => {
  if (!templateId) return 'No template selected';
  const template = props.alertTemplates.find(t => t.id === templateId);
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
    const mappingsToSave = localMappings.value.map(mapping => ({
      event_type: mapping.event_type,
      template_id: mapping.template_id,
      duration_ms: mapping.duration_ms,
      transition_type: mapping.transition_type,
      enabled: mapping.enabled
    }));

    const response = await axios.put('/events/bulk', {
      mappings: mappingsToSave
    });
    showToast.value = true;
    toastMessage.value = "Settings saved successfully!";
    toastType.value = 'success';

  } catch (error:any) {
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
      <div class="flex justify-between items-start mb-8">
        <Heading title="Event Alert Configuration" description="Connect Twitch events to your alert templates" />

        <div class="flex gap-3">
          <Link
            :href="route('templates.create', { type: 'alert' })"
            class="btn btn-secondary"
          >
            Create Template
          </Link>
          <button
            @click="saveAllMappings"
            :disabled="isSaving"
            class="btn btn-primary min-w-[140px]"
          >
            {{ isSaving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>

      <!-- Status Summary - Only show if there are templates -->
      <div v-if="alertTemplates.length > 0" class="mb-8 flex gap-4">
        <div class="flex items-center gap-2 text-sm">
          <CheckCircle2Icon class="w-4 h-4 text-green-500" />
          <span class="text-muted-foreground">
            <span class="font-medium text-foreground">{{ activeEvents }}</span> active
          </span>
        </div>
        <div v-if="enabledWithoutTemplate > 0" class="flex items-center gap-2 text-sm">
          <AlertTriangleIcon class="w-4 h-4 text-yellow-500" />
          <span class="text-muted-foreground">
            <span class="font-medium text-foreground">{{ enabledWithoutTemplate }}</span> missing template
          </span>
        </div>
        <div class="flex items-center gap-2 text-sm">
          <CircleIcon class="w-4 h-4 text-muted-foreground" />
          <span class="text-muted-foreground">
            <span class="font-medium text-foreground">{{ localMappings.length - totalEnabled }}</span> disabled
          </span>
        </div>
      </div>

      <!-- No Templates Warning -->
      <div v-if="alertTemplates.length === 0" class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-6 mb-8">
        <div class="flex gap-3">
          <AlertTriangleIcon class="h-5 w-5 text-yellow-500 flex-shrink-0 mt-0.5" />
          <div>
            <h3 class="font-medium text-foreground mb-1">No Alert Templates Available</h3>
            <p class="text-sm text-muted-foreground mb-3">
              Create your first alert template to start configuring events.
            </p>
            <Link
              :href="route('templates.create')"
              class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:text-primary/80"
            >
              <Sparkles class="w-4 h-4" />
              Create your first template
            </Link>
          </div>
        </div>
      </div>

      <!-- Events List -->
      <div class="space-y-2">
        <div
          v-for="mapping in localMappings"
          :key="mapping.event_type"
          class="group"
        >
          <!-- Event Row -->
          <div
            class="flex items-center gap-4 p-4 rounded-lg hover:bg-accent/50 transition-colors cursor-pointer"
            :class="{
              'bg-accent/30': mapping.enabled && expandedEvent === mapping.event_type,
              'bg-card': !mapping.enabled || expandedEvent !== mapping.event_type
            }"
            @click="toggleEvent(mapping.event_type)"
          >
            <!-- Enable Toggle -->
            <label
              class="relative inline-flex items-center cursor-pointer"
              @click.stop
            >
              <input
                type="checkbox"
                v-model="mapping.enabled"
                class="sr-only peer"
              />
              <span class="w-10 h-6 bg-gray-300 dark:bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-4 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-400 dark:peer-checked:bg-green-700 dark:after:bg-gray-100"></span>
            </label>

            <!-- Event Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3">
                <h3 class="font-medium text-foreground">
                  {{ eventTypes[mapping.event_type] }}
                </h3>
                <span class="text-xs text-muted-foreground font-mono">
                  {{ mapping.event_type }}
                </span>
              </div>

              <!-- Quick Status when enabled -->
              <div v-if="mapping.enabled" class="flex items-center gap-4 mt-1 text-sm">
                <span
                  class="flex items-center gap-1.5"
                  :class="mapping.template_id ? 'text-muted-foreground' : 'text-yellow-600'"
                >
                  <span class="font-medium">Template:</span>
                  {{ getTemplateName(mapping.template_id) }}
                </span>
                <span class="text-muted-foreground">•</span>
                <span class="text-muted-foreground">
                  {{ mapping.duration_ms / 1000 }}s
                </span>
                <span class="text-muted-foreground">•</span>
                <span class="text-muted-foreground">
                  {{ transitionTypes[mapping.transition_type] }}
                </span>
              </div>
            </div>

            <!-- Status Indicator -->
            <div class="flex items-center gap-2">
              <div
                v-if="mapping.enabled && !mapping.template_id"
                class="w-2 h-2 bg-yellow-500 rounded-full"
                title="Template not selected"
              ></div>
              <div
                v-else-if="mapping.enabled"
                class="w-2 h-2 bg-green-500 rounded-full"
                title="Active"
              ></div>
              <svg
                class="w-5 h-5 text-muted-foreground transition-transform"
                :class="{ 'rotate-180': expandedEvent === mapping.event_type }"
                fill="none"
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
            class="ml-14 mr-4 mb-2 p-4 bg-background/50 rounded-lg border border-border/50"
            @click.stop
          >
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <!-- Template Selection -->
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">
                  Alert Template
                </label>
                <select
                  v-model="mapping.template_id"
                  class="w-full rounded-md border bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                  :class="{
                    'border-yellow-500 bg-yellow-500/10': !mapping.template_id,
                    'border-input': mapping.template_id
                  }"
                >
                  <option :value="null">Select a template...</option>
                  <option
                    v-for="template in alertTemplates"
                    :key="template.id"
                    :value="template.id"
                  >
                    {{ template.name }}
                  </option>
                </select>
                <p v-if="!mapping.template_id" class="text-xs text-yellow-600 mt-1">
                  Select a template to show alerts
                </p>
              </div>

              <!-- Duration Slider -->
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">
                  Duration: {{ mapping.duration_ms / 1000 }}s
                </label>
                <div class="flex items-center gap-3">
                  <span class="text-xs text-muted-foreground">1s</span>
                  <input
                    v-model.number="mapping.duration_ms"
                    type="range"
                    min="1000"
                    max="30000"
                    step="500"
                    class="flex-1 h-2 bg-accent rounded-lg appearance-none cursor-pointer accent-primary"
                  />
                  <span class="text-xs text-muted-foreground">30s</span>
                </div>
              </div>

              <!-- Transition Type -->
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">
                  Transition Effect
                </label>
                <select
                  v-model="mapping.transition_type"
                  class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                >
                  <option
                    v-for="(label, value) in transitionTypes"
                    :key="value"
                    :value="value"
                  >
                    {{ label }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Preview Description -->
            <div
              v-if="mapping.template_id"
              class="mt-4 p-3 bg-accent/30 rounded-md"
            >
              <p class="text-sm text-muted-foreground">
                <span class="font-medium">Preview:</span>
                When a {{ eventTypes[mapping.event_type].toLowerCase() }} occurs,
                the "{{ getTemplateName(mapping.template_id) }}" alert will {{ transitionTypes[mapping.transition_type].toLowerCase() }} in
                for {{ mapping.duration_ms / 1000 }} seconds.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Save Button -->
      <div class="mt-8 pt-6 border-t border-border flex justify-end">
        <button
          @click="saveAllMappings"
          :disabled="isSaving"
          class="btn btn-primary min-w-[160px]"
        >
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
input[type="range"]::-webkit-slider-thumb {
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: hsl(var(--primary));
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
  border: 2px solid white;
}

input[type="range"]::-moz-range-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: hsl(var(--primary));
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
  border: 2px solid white;
}

input[type="range"]::-webkit-slider-thumb:hover {
  transform: scale(1.1);
}

input[type="range"]::-moz-range-thumb:hover {
  transform: scale(1.1);
}

/* Dark mode adjustments for range track */
@media (prefers-color-scheme: dark) {
  input[type="range"] {
    background: rgba(255, 255, 255, 0.1);
  }
}
</style>
