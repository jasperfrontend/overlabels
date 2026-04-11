<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, Package } from 'lucide-vue-next';
import PublicToggle from '@/components/PublicToggle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import type { BreadcrumbItem, OverlayTemplate } from '@/types';
import HeadingSmall from '@/components/HeadingSmall.vue';
import EmptyState from '@/components/EmptyState.vue';
import ImageDropZone from '@/components/ImageDropZone.vue';

interface Kit {
  id: number;
  title: string;
  description: string | null;
  thumbnail: string | null;
  thumbnail_url?: string | null;
  is_public: boolean;
  fork_count: number;
}

interface Props {
  kit: Kit;
  templates: OverlayTemplate[];
  selectedTemplateIds: number[];
}

const props = defineProps<Props>();

const form = useForm({
  title: props.kit.title,
  description: props.kit.description || '',
  is_public: props.kit.is_public,
  thumbnail_url: props.kit.thumbnail_url || '',
  template_ids: [...props.selectedTemplateIds]
});

const selectedTemplates = computed(() => {
  return props.templates.filter(t => form.template_ids.includes(t.id));
});

const toggleTemplate = (templateId: number, checked: boolean) => {

  if (checked) {
    // Add a template if not already included
    if (!form.template_ids.includes(templateId)) {
      form.template_ids = [...form.template_ids, templateId];
    }
  } else {
    // Remove template
    form.template_ids = form.template_ids.filter(id => id !== templateId);
  }
};

const submit = () => {
  // Use put method for Cloudinary URL submission
  form.put(`/kits/${props.kit.id}`, {
    preserveScroll: true
  });
};

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Edit Kit "' + props.kit.title + '"',
    href: route('kits.index')
  }
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head :title="`Edit ${kit.title}`" />

    <div class="container mx-auto max-w-4xl px-4 py-8">
      <!-- Back button -->
      <Link :href="`/kits/${kit.id}`"
            class="mb-6 inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Kit
      </Link>

      <div class="mb-8">
        <h1 class="text-3xl font-bold">Edit Kit</h1>
        <p class="mt-2 text-muted-foreground">
          Update your kit's information and templates
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Basic Information -->


        <Card class="gap-4">
          <CardHeader>
            <HeadingSmall title="Kit Information" description="Update your kit's name and description" />
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <label for="title" class="block mb-2">Title *</label>
              <input
                id="title"
                v-model="form.title"
                type="text"
                placeholder="My Awesome Stream Kit"
                class="input-border w-full"
                :class="{ 'border-red-500': form.errors.title }"
                required
              />
              <p v-if="form.errors.title" class="mt-1 text-sm text-red-500">{{ form.errors.title }}</p>
            </div>

            <div>
              <label for="description" class="block mb-2">Description</label>
              <textarea
                id="description"
                v-model="form.description"
                placeholder="Describe what this kit contains and who might find it useful"
                :rows="4"
                class="input-border w-full"
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
            <HeadingSmall title="Kit Thumbnail"
                          description="Update your kit's thumbnail image (2560x1440px recommended, max 10MB). Be sure to provide a high quality thumbnail so your kit looks great in the library." />
          </CardHeader>
          <CardContent>
            <ImageDropZone
              v-model="form.thumbnail_url"
              upload-preset="overlabels-kit-thumbnails"
              folder="kits/thumbnails"
              compact
            />
            <p v-if="form.errors.thumbnail_url" class="mt-2 text-sm text-red-500">{{ form.errors.thumbnail_url }}</p>
          </CardContent>
        </Card>

        <!-- Template Selection -->
        <Card>
          <CardHeader>
            <HeadingSmall title="Select Templates *"
                          description="Choose which of your templates to include in this kit." />
          </CardHeader>
          <CardContent>
            <EmptyState
              v-if="templates.length === 0"
              dashed
              :icon="Package"
              message="You don't have any templates yet. Create some templates first."
              class="mt-4"
            />

            <div v-else class="mt-4 space-y-2">
              <div
                v-for="template in templates"
                :key="template.id"
                class="flex items-center space-x-3 rounded-lg border p-3 transition-colors"
                :class="{ 'bg-primary/5 border-primary': form.template_ids.includes(template.id) }"
              >
                <Checkbox
                  :id="`template-${template.id}`"
                  class="hidden"
                  :checked="form.template_ids.includes(template.id)"
                  @click="() => toggleTemplate(template.id, !form.template_ids.includes(template.id))"
                />
                <label
                  :for="`template-${template.id}`"
                  class="flex flex-1 cursor-pointer items-center justify-between"
                >
                  <span>
                    <span class="font-medium">{{ template.name }}</span>
                    <span class="ml-2 text-sm text-muted-foreground">({{ template.type }})</span>
                  </span>
                  <span class="text-xs text-muted-foreground">{{ template.slug }}</span>
                </label>
              </div>
            </div>

            <p v-if="form.errors.template_ids" class="mt-2 text-sm text-red-500">{{ form.errors.template_ids }}</p>

            <div v-if="selectedTemplates.length > 0" class="mt-4 rounded-lg bg-muted p-3">
              <p class="text-sm font-medium">
                Selected: {{ selectedTemplates.length }} template{{ selectedTemplates.length !== 1 ? 's' : '' }}
              </p>
            </div>
          </CardContent>
        </Card>

        <!-- Fork Information -->
        <div v-if="kit.fork_count > 0"
             class="rounded-lg border border-amber-500/50 bg-amber-50 p-4 dark:bg-amber-950/20">
          <p class="text-sm text-amber-800 dark:text-amber-200">
            <strong>Note:</strong> This kit has been forked {{ kit.fork_count }} time{{ kit.fork_count !== 1 ? 's' : ''
            }} and cannot be deleted.
          </p>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end gap-4">
          <Link :href="`/kits/${kit.id}`" class="btn btn-secondary">
            Cancel
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="btn btn-primary"
          >
            {{ form.processing ? 'Updating...' : 'Update Kit' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
