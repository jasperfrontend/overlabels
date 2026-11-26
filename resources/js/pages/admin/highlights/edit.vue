<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import SettingSwitch from '@/components/SettingSwitch.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { ArrowLeft, Save } from '@lucide/vue';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';

interface Highlight {
  id: number;
  body: string;
  link_url: string;
  link_label: string;
  sort_order: number;
  enabled: boolean;
}

const props = defineProps<{
  highlight: Highlight | null;
  nextSortOrder: number;
}>();

const isEditing = computed(() => props.highlight !== null);

const breadcrumbs = computed(() => [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Highlights', href: route('admin.highlights.index') },
  {
    title: isEditing.value ? 'Edit highlight' : 'New highlight',
    href: isEditing.value ? route('admin.highlights.edit', props.highlight!.id) : route('admin.highlights.create'),
  },
]);

const form = useForm({
  body: props.highlight?.body ?? '',
  link_url: props.highlight?.link_url ?? '/',
  link_label: props.highlight?.link_label ?? '',
  sort_order: props.highlight?.sort_order ?? props.nextSortOrder,
  enabled: props.highlight?.enabled ?? true,
});

function submit() {
  if (form.processing) return;
  if (isEditing.value) {
    form.put(route('admin.highlights.update', props.highlight!.id));
  } else {
    form.post(route('admin.highlights.store'));
  }
}

// Ctrl+S saves, as on the updates editor.
const { register } = useKeyboardShortcuts();
onMounted(() => {
  register('save-highlight', 'ctrl+s', () => submit(), { description: 'Save highlight' });
});
</script>

<template>
  <Head>
    <title>{{ isEditing ? 'Edit highlight' : 'New highlight' }}</title>
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <form class="flex max-w-3xl flex-col gap-4 p-4" @submit.prevent="submit">
      <PageHeader :title="isEditing ? 'Edit highlight' : 'New highlight'" title-class="text-2xl font-bold">
        <template #actions>
          <a :href="route('admin.highlights.index')" class="btn btn-cancel cursor-pointer">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back
          </a>
        </template>
      </PageHeader>

      <div class="flex flex-col gap-1">
        <label for="field-body" class="text-sm font-medium">Text</label>
        <textarea id="field-body" v-model="form.body" rows="5" class="input-border w-full p-2 text-sm" required />
        <p v-if="form.errors.body" class="text-xs text-destructive">{{ form.errors.body }}</p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
          <label for="field-link-url" class="text-sm font-medium"
            >Link <span class="text-muted-foreground">(a path, like /settings/title)</span></label
          >
          <input id="field-link-url" v-model="form.link_url" class="input-border font-mono" required />
          <p v-if="form.errors.link_url" class="text-xs text-destructive">{{ form.errors.link_url }}</p>
        </div>
        <div class="flex flex-col gap-1">
          <label for="field-link-label" class="text-sm font-medium">Link text</label>
          <input id="field-link-label" v-model="form.link_label" class="input-border" required />
          <p v-if="form.errors.link_label" class="text-xs text-destructive">{{ form.errors.link_label }}</p>
        </div>
      </div>

      <div class="flex flex-wrap items-end gap-6">
        <div class="flex flex-col gap-1">
          <label for="field-order" class="text-sm font-medium">Order <span class="text-muted-foreground">(lowest first)</span></label>
          <input id="field-order" v-model.number="form.sort_order" type="number" min="0" class="input-border w-32 tabular-nums" />
          <p v-if="form.errors.sort_order" class="text-xs text-destructive">{{ form.errors.sort_order }}</p>
        </div>
        <label class="flex cursor-pointer items-center gap-3 pb-2 text-sm">
          <SettingSwitch v-model="form.enabled" />
          Show on the dashboard
        </label>
      </div>

      <div>
        <button type="submit" :disabled="form.processing" class="btn btn-primary cursor-pointer">
          <Save class="mr-2 h-4 w-4" />
          {{ isEditing ? 'Save highlight' : 'Add highlight' }}
        </button>
      </div>
    </form>
  </AppLayout>
</template>
