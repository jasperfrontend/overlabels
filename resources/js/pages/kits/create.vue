<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, Package } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import PublicToggle from '@/components/PublicToggle.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import EmptyState from '@/components/EmptyState.vue';
import ImageDropZone from '@/components/ImageDropZone.vue';
import type { OverlayTemplate } from '@/types';
import KitTriggerPicker, { type KitTrigger } from '@/components/kits/KitTriggerPicker.vue';

interface Props {
  templates: OverlayTemplate[];
  alertEvents: Array<{ service: string; events: Array<{ event_type: string; label: string }> }>;
  authorTriggers: Record<string, Array<{ service: string; event_type: string; duration_ms: number }>>;
}

const props = defineProps<Props>();

const form = useForm({
  title: '',
  description: '',
  is_public: false,
  thumbnail_url: '' as string,
  template_ids: [] as number[],
  alert_triggers: [] as KitTrigger[],
});

const selectedTemplates = computed(() => {
  return props.templates.filter((t) => form.template_ids.includes(t.id));
});

const selectedAlerts = computed(() => selectedTemplates.value.filter((t) => t.type === 'alert').map((t) => ({ id: t.id, name: t.name })));

// Validation errors arrive keyed per row (alert_triggers.2.event_type).
const triggerError = computed(() => {
  const errors = form.errors as Record<string, string>;
  const key = Object.keys(errors).find((k) => k.startsWith('alert_triggers'));
  return key ? errors[key] : '';
});

const toggleTemplate = (templateId: number, checked: boolean) => {
  if (checked) {
    // Add a template if not already included
    if (!form.template_ids.includes(templateId)) {
      form.template_ids = [...form.template_ids, templateId];
    }
  } else {
    // Remove template
    form.template_ids = form.template_ids.filter((id) => id !== templateId);
  }
};

const submit = () => {
  form.post('/kits', {
    preserveScroll: true,
  });
};
</script>

<template>
  <AppLayout>
    <Head title="Create Kit" />

    <div class="container mx-auto max-w-4xl px-4 py-8">
      <!-- Back button -->
      <Link :href="route('kits.index')" class="mb-6 inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Kits
      </Link>

      <div class="mb-8">
        <h1 class="text-3xl font-bold">Create Template Kit</h1>
        <p class="mt-2 text-muted-foreground">Organize your overlay templates into a reusable collection</p>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Basic Information -->
        <Card>
          <CardHeader>
            <CardTitle>Kit Information</CardTitle>
            <CardDescription> Give your kit a name and description to help others understand what it contains </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <Label for="title">Title *</Label>
              <Input
                id="title"
                v-model="form.title"
                type="text"
                placeholder="My Awesome Stream Kit"
                :class="{ 'border-red-500': form.errors.title }"
                required
              />
              <p v-if="form.errors.title" class="mt-1 text-sm text-red-500">{{ form.errors.title }}</p>
            </div>

            <div>
              <Label for="description">Description</Label>
              <Textarea
                id="description"
                v-model="form.description"
                placeholder="Describe what this kit contains and who might find it useful..."
                :rows="4"
                :class="{ 'border-red-500': form.errors.description }"
              />
              <p v-if="form.errors.description" class="mt-1 text-sm text-red-500">{{ form.errors.description }}</p>
            </div>

            <PublicToggle v-model="form.is_public" label="Kit" />
          </CardContent>
        </Card>

        <!-- Thumbnail Upload -->
        <Card>
          <CardHeader>
            <CardTitle>Kit Thumbnail</CardTitle>
            <CardDescription> Upload a thumbnail image for your kit (2560x1440px recommended, max 10MB) </CardDescription>
          </CardHeader>
          <CardContent>
            <ImageDropZone v-model="form.thumbnail_url" kind="kit_thumbnail" compact />
            <p v-if="form.errors.thumbnail_url" class="mt-2 text-sm text-red-500">{{ form.errors.thumbnail_url }}</p>
          </CardContent>
        </Card>

        <!-- Template Selection -->
        <Card>
          <CardHeader>
            <CardTitle>Select Templates *</CardTitle>
            <CardDescription> Choose which of your templates to include in this kit </CardDescription>
          </CardHeader>
          <CardContent>
            <EmptyState
              v-if="templates.length === 0"
              dashed
              :icon="Package"
              message="You don't have any templates yet. Create some templates first before making a kit."
            />

            <div v-else class="space-y-2">
              <div
                v-for="template in templates"
                :key="template.id"
                class="flex items-center space-x-3 rounded-lg border p-3 transition-colors"
                :class="{ 'border-primary bg-primary/5': form.template_ids.includes(template.id) }"
              >
                <Checkbox
                  :id="`template-${template.id}`"
                  :checked="form.template_ids.includes(template.id)"
                  @click="() => toggleTemplate(template.id, !form.template_ids.includes(template.id))"
                />
                <label :for="`template-${template.id}`" class="flex flex-1 cursor-pointer items-center justify-between">
                  <div>
                    <span class="font-medium">{{ template.name }}</span>
                    <span class="ml-2 text-sm text-muted-foreground">({{ template.type }})</span>
                  </div>
                  <span class="text-xs text-muted-foreground">{{ template.slug }}</span>
                </label>
              </div>
            </div>

            <p v-if="form.errors.template_ids" class="mt-2 text-sm text-red-500">{{ form.errors.template_ids }}</p>

            <div v-if="selectedTemplates.length > 0" class="mt-4 rounded-lg bg-muted p-3">
              <p class="text-sm font-medium">Selected: {{ selectedTemplates.length }} template{{ selectedTemplates.length !== 1 ? 's' : '' }}</p>
            </div>
          </CardContent>
        </Card>

        <!-- Which event fires each alert: what the kit page previews with,
             and what copying the kit wires onto the copier's Triggers. -->
        <Card v-if="selectedAlerts.length > 0">
          <CardHeader>
            <CardTitle>Alert triggers</CardTitle>
            <CardDescription>
              Which events fire each alert. Visitors can play them on the kit page before copying, and copying the kit wires these events to the
              copied alerts.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <KitTriggerPicker v-model="form.alert_triggers" :alerts="selectedAlerts" :events="alertEvents" :author-triggers="authorTriggers" />
            <p v-if="triggerError" class="mt-2 text-sm text-red-500">{{ triggerError }}</p>
          </CardContent>
        </Card>

        <!-- Form Actions -->
        <div class="flex justify-end gap-4">
          <Link :href="route('kits.index')" class="btn btn-secondary"> Cancel </Link>
          <button type="submit" :disabled="form.processing || form.template_ids.length === 0" class="btn btn-primary">
            {{ form.processing ? 'Creating...' : 'Create Kit' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
