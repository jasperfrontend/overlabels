<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { AlertTriangleIcon, CheckIcon } from 'lucide-vue-next';
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

// Computed properties for the info panel
const activeEvents = computed(() => {
  return localMappings.value.filter(m => m.enabled && m.template_id).length;
});

const unassignedTemplates = computed(() => {
  const assignedTemplateIds = new Set(
    localMappings.value
      .filter(m => m.enabled && m.template_id)
      .map(m => m.template_id)
  );
  return props.alertTemplates.filter(t => !assignedTemplateIds.has(t.id)).length;
});

const isSaving = ref(false);

// Toast state
const toastMessage = ref<string>('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);

const getTemplateName = (templateId: number | null): string => {
  if (!templateId) return 'No template';
  const template = props.alertTemplates.find(t => t.id === templateId);
  return template?.name || 'Unknown template';
};

const saveAllMappings = async () => {
  isSaving.value = true;

  try {
    // Convert to plain objects to ensure proper serialization
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
      <div class="flex justify-between items-center mb-6">
        <Heading
          title="Event Alert Configuration"
          description="Configure which templates to show for each Twitch event type."
        />
        <div class="flex gap-2">
          <button
            @click="saveAllMappings"
            :disabled="isSaving"
            class="btn btn-primary"
          >
            {{ isSaving ? 'Saving...' : 'Save All Changes' }}
          </button>
          <Link
            :href="route('templates.create', { type: 'alert' })"
            class="btn btn-secondary"
          >
            Create Alert Template
          </Link>
        </div>
      </div>

      <div v-if="alertTemplates.length === 0" class="bg-accent/20 border border-accent/30 rounded-lg p-4 mb-6">
        <div class="flex">
          <AlertTriangleIcon class="h-5 w-5 text-yellow-500 dark:text-yellow-400" />
          <div class="ml-3">
            <h3 class="text-sm font-medium text-foreground">No Alert Templates</h3>
            <p class="mt-1 text-sm text-muted-foreground">
              You need to create alert templates before you can configure events.
              <Link :href="route('templates.create')" class="underline font-medium text-primary hover:text-primary/80">
                Create your first alert template
              </Link>
            </p>
          </div>
        </div>
      </div>

      <!-- Info Panel -->
      <div v-if="alertTemplates.length > 0" :class="[
        'border rounded-lg p-4 mb-6 bg-accent/20',
        unassignedTemplates > 0 ? 'bg-green-500/20 dark:bg-green-400/10 border-green-500/30 dark:border-green-400/30' : 'border-blue-500/30 dark:border-blue-400/30'
      ]">
        <div class="flex">
          <div class="ml-3">
            <h3 :class="[
              'text-sm font-medium',
              unassignedTemplates > 0 ? 'text-green-700 dark:text-green-400' : 'text-blue-700 dark:text-blue-400'
            ]">
              {{ unassignedTemplates > 0 ? 'Ready to Configure Events' : 'All Existing Templates are Assigned' }}
            </h3>
            <p class="mt-1 text-sm text-muted-foreground">
              <template v-if="unassignedTemplates > 0">
                You have {{ unassignedTemplates }} unassigned alert template{{ unassignedTemplates === 1 ? '' : 's' }} available.
                Enable events below to start showing custom alerts.
              </template>
              <template v-else-if="activeEvents > 0">
                {{ activeEvents }} event{{ activeEvents === 1 ? ' is' : 's are' }} configured with alert templates.
              </template>
              <template v-else>
                You have {{ alertTemplates.length }} alert template{{ alertTemplates.length === 1 ? '' : 's' }} available.
                Enable events below to start showing custom alerts.
              </template>
            </p>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div
          v-for="mapping in localMappings"
          :key="mapping.event_type"
          :class="[
            'border rounded-lg p-6 transition-colors',
            mapping.enabled
              ? 'bg-accent/20 border-primary/30'
              : 'bg-card border-border'
          ]"
        >
          <div class="flex items-center justify-between mb-4">
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-foreground">
                {{ eventTypes[mapping.event_type] }}
              </h3>
              <p class="text-sm text-muted-foreground">{{ mapping.event_type }}</p>
              <p v-if="mapping.enabled && mapping.template_id" class="text-sm text-green-500 dark:text-green-400 mt-1">
                <CheckIcon class="w-4 h-4 mr-1 inline-block text-green-500 dark:text-green-400" /> Using template: {{ getTemplateName(mapping.template_id) }}
              </p>
              <p v-else-if="mapping.enabled" class="text-sm text-destructive mt-1">
                ⚠ No template selected
              </p>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-sm text-muted-foreground">
                {{ mapping.enabled ? 'Enabled' : 'Disabled' }}
              </span>
              <!-- Custom Switch Component -->
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  v-model="mapping.enabled"
                  class="sr-only peer"
                />
                <div class="relative w-11 h-6 bg-input peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent-foreground/50"></div>
              </label>
            </div>
          </div>

          <div v-if="mapping.enabled" class="mt-4 p-4 rounded-md border border-border">
            <h4 class="text-sm font-medium text-foreground mb-3">Configuration</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm text-muted-foreground mb-2" for="template_select">Alert Template</label>
                <select
                  v-model="mapping.template_id"
                  id="template_select"
                  class="w-full rounded-md border border-input bg-background p-2 h-10 focus:border-primary focus:ring-primary"
                  :disabled="alertTemplates.length === 0"
                  :class="{ 'border-destructive bg-destructive/10': mapping.enabled && !mapping.template_id }"
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
                <p v-if="alertTemplates.length === 0" class="text-xs text-destructive mt-1">
                  No alert templates available. Create one first.
                </p>
              </div>

            <div>
              <label class="block text-sm text-muted-foreground mb-2">
                Duration ({{ mapping.duration_ms / 1000 }}s)
              </label>
              <div class="bg-background border rounded-lg px-2 h-10">
                <input
                  v-model.number="mapping.duration_ms"
                  type="range"
                  min="1000"
                  max="30000"
                  step="500"
                  class="w-full h-2 mt-4 bg-accent rounded-lg appearance-none cursor-pointer accent-primary"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm text-muted-foreground mb-2">Transition</label>
              <select
                v-model="mapping.transition_type"
                class="w-full rounded-md border border-input bg-background p-2 h-10 focus:border-primary focus:ring-primary"
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

          <div v-if="mapping.enabled && mapping.template_id" class="mt-4 bg-accent/10 rounded-md">
            <div class="flex items-center justify-between">
              <div class="text-sm text-muted-foreground">
                <strong>Preview:</strong> When a {{ eventTypes[mapping.event_type].toLowerCase() }} occurs,
                show "{{ getTemplateName(mapping.template_id) }}" for {{ mapping.duration_ms / 1000 }} seconds
                with {{ transitionTypes[mapping.transition_type].toLowerCase() }} transition.
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-8 flex justify-center">
        <button
          @click="saveAllMappings"
          :disabled="isSaving"
          size="lg"
          class="btn btn-primary"
        >
          {{ isSaving ? 'Saving Changes...' : 'Save All Changes' }}
        </button>
      </div>
    </div>
    </div>

    <!-- Toast Notification -->
    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" />
  </AppLayout>
</template>

