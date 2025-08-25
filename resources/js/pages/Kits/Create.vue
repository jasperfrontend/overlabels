<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { ArrowLeft, Upload, X, Package } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';

interface Template {
  id: number;
  name: string;
  type: string;
  slug: string;
}

interface Props {
  templates: Template[];
}

const props = defineProps<Props>();

const form = useForm({
  title: '',
  description: '',
  is_public: false,
  thumbnail: null as File | null,
  template_ids: [] as number[],
});

const thumbnailPreview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const selectedTemplates = computed(() => {
  return props.templates.filter(t => form.template_ids.includes(t.id));
});

// Debug watcher to track the form state
watch(() => form.template_ids, (newVal, oldVal) => {
  console.log('Template IDs changed:', {
    oldVal,
    newVal,
    length: newVal.length,
    buttonShouldBeDisabled: form.processing || newVal.length === 0
  });
}, { deep: true });

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (file) {
    // Validate file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
      alert('File size must be less than 10MB');
      return;
    }

    // Validate file type
    if (!file.type.startsWith('image/')) {
      alert('Please select an image file. Only jpg and png are supported.');
      return;
    }

    form.thumbnail = file;

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      thumbnailPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
  }
};

const removeThumbnail = () => {
  form.thumbnail = null;
  thumbnailPreview.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

const toggleTemplate = (templateId: number, checked: boolean) => {
  console.log('toggleTemplate called:', { templateId, checked, currentIds: form.template_ids });

  if (checked) {
    // Add a template if not already included
    if (!form.template_ids.includes(templateId)) {
      form.template_ids = [...form.template_ids, templateId];
      console.log('Added template:', templateId, 'New array:', form.template_ids);
    }
  } else {
    // Remove template
    form.template_ids = form.template_ids.filter(id => id !== templateId);
    console.log('Removed template:', templateId, 'New array:', form.template_ids);
  }
};

const submit = () => {
  form.post('/kits', {
    forceFormData: true,
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
        <p class="mt-2 text-muted-foreground">
          Organize your overlay templates into a reusable collection
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Basic Information -->
        <Card>
          <CardHeader>
            <CardTitle>Kit Information</CardTitle>
            <CardDescription>
              Give your kit a name and description to help others understand what it contains
            </CardDescription>
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

            <div class="flex items-center justify-between rounded-lg border p-4">
              <div class="space-y-0.5">
                <Label for="is_public">Make this kit public</Label>
                <p class="text-sm text-muted-foreground">
                  Public kits can be discovered and forked by other users
                </p>
              </div>
              <Switch
                id="is_public"
                v-model:checked="form.is_public"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Thumbnail Upload -->
        <Card>
          <CardHeader>
            <CardTitle>Kit Thumbnail</CardTitle>
            <CardDescription>
              Upload a thumbnail image for your kit (2560x1440px recommended, max 10MB)
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="thumbnailPreview" class="mb-4">
              <div class="relative inline-block">
                <img
                  :src="thumbnailPreview"
                  alt="Thumbnail preview"
                  class="h-48 w-auto rounded-lg object-cover"
                />
                <button
                  type="button"
                  @click="removeThumbnail"
                  class="absolute -right-2 -top-2 rounded-full bg-red-500 p-1 text-white hover:bg-red-600"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>

            <div v-else>
              <input
                ref="fileInput"
                type="file"
                accept="image/*"
                @change="handleFileSelect"
                class="hidden"
                id="thumbnail-upload"
              />
              <label
                for="thumbnail-upload"
                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 p-6 transition-colors hover:border-muted-foreground/50"
              >
                <Upload class="mb-2 h-8 w-8 text-muted-foreground" />
                <span class="text-sm text-muted-foreground">Click to upload thumbnail</span>
                <span class="mt-1 text-xs text-muted-foreground">PNG, JPG up to 10MB</span>
              </label>
            </div>
            <p v-if="form.errors.thumbnail" class="mt-2 text-sm text-red-500">{{ form.errors.thumbnail }}</p>
          </CardContent>
        </Card>

        <!-- Template Selection -->
        <Card>
          <CardHeader>
            <CardTitle>Select Templates *</CardTitle>
            <CardDescription>
              Choose which of your templates to include in this kit
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="templates.length === 0" class="rounded-lg border-2 border-dashed border-muted-foreground/25 p-8 text-center">
              <Package class="mx-auto mb-2 h-8 w-8 text-muted-foreground/50" />
              <p class="text-sm text-muted-foreground">
                You don't have any templates yet. Create some templates first before making a kit.
              </p>
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="template in templates"
                :key="template.id"
                class="flex items-center space-x-3 rounded-lg border p-3 transition-colors"
                :class="{ 'bg-primary/5 border-primary': form.template_ids.includes(template.id) }"
              >
                <Checkbox
                  :id="`template-${template.id}`"
                  :checked="form.template_ids.includes(template.id)"
                  @click="() => toggleTemplate(template.id, !form.template_ids.includes(template.id))"
                />
                <label
                  :for="`template-${template.id}`"
                  class="flex flex-1 cursor-pointer items-center justify-between"
                >
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
              <p class="text-sm font-medium">
                Selected: {{ selectedTemplates.length }} template{{ selectedTemplates.length !== 1 ? 's' : '' }}
              </p>
            </div>
          </CardContent>
        </Card>

        <!-- Form Actions -->
        <div class="flex justify-end gap-4">
          <Link :href="route('kits.index')" class="btn btn-secondary">
            Cancel
          </Link>
          <button
            type="submit"
            :disabled="form.processing || form.template_ids.length === 0"
            class="btn btn-primary"
          >
            {{ form.processing ? 'Creating...' : 'Create Kit' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
