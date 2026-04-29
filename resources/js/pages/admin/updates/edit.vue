<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Codemirror } from 'vue-codemirror';
import { html as cmHtml } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { ArrowLeft, Save, Trash2 } from 'lucide-vue-next';
import type { Update } from '@/types';

const props = defineProps<{
  update: Update | null;
}>();

const isEditing = computed(() => !!props.update);

const breadcrumbs = computed(() => [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Updates', href: route('admin.updates.index') },
  {
    title: isEditing.value ? `Edit: ${props.update!.title}` : 'New post',
    href: isEditing.value
      ? route('admin.updates.edit', props.update!.id)
      : route('admin.updates.create'),
  },
]);

function toLocalDateTimeInput(iso: string | null): string {
  if (!iso) {
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
  }
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const tagsInput = ref<string>(
  (props.update?.tags ?? []).join(', ')
);

const form = useForm({
  title: props.update?.title ?? '',
  slug: props.update?.slug ?? '',
  tags: props.update?.tags ?? [] as string[],
  excerpt: props.update?.excerpt ?? '',
  body: props.update?.body ?? '',
  published_at: toLocalDateTimeInput(props.update?.published_at ?? null),
});

function syncTagsFromInput() {
  form.tags = tagsInput.value
    .split(',')
    .map((t) => t.trim())
    .filter(Boolean);
}

function submit() {
  syncTagsFromInput();
  // datetime-local is naive (no timezone). Reinterpret it as the browser's
  // local time and ship UTC, otherwise Laravel parses it as UTC and a post
  // saved at "now" lands in the future for any non-UTC streamer.
  if (form.published_at) {
    const local = new Date(form.published_at);
    if (!Number.isNaN(local.getTime())) {
      form.transform((data) => ({
        ...data,
        published_at: local.toISOString(),
      }));
    }
  }
  if (isEditing.value) {
    form.put(route('admin.updates.update', props.update!.id));
  } else {
    form.post(route('admin.updates.store'));
  }
}

function handleDelete() {
  if (!props.update) return;
  if (confirm(`Delete "${props.update.title}"? This cannot be undone.`)) {
    router.delete(route('admin.updates.destroy', props.update.id));
  }
}

const isDark = ref(document.documentElement.classList.contains('dark'));
let observer: MutationObserver | null = null;

onMounted(() => {
  observer = new MutationObserver(() => {
    isDark.value = document.documentElement.classList.contains('dark');
  });
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

onUnmounted(() => {
  observer?.disconnect();
});

const baseTheme = EditorView.theme({
  '&': { height: '100%', fontSize: '14px' },
  '.cm-scroller': { overflow: 'auto' },
  '.cm-content': { padding: '8px 8px 3rem' },
  '.cm-focused .cm-cursor': { borderLeftColor: '#3b82f6' },
});

const editorKey = computed(() => (isDark.value ? 'dark' : 'light'));
const bodyExtensions = computed(() => [cmHtml(), baseTheme, ...(isDark.value ? [oneDark] : [])]);
</script>

<template>
  <Head>
    <title>{{ isEditing ? `Edit: ${props.update!.title}` : 'New update' }}</title>
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <form @submit.prevent="submit" class="flex flex-col gap-4 p-4">
      <PageHeader :title="isEditing ? 'Edit update' : 'New update'" title-class="text-2xl font-bold">
        <template #actions>
          <a :href="route('admin.updates.index')" class="btn btn-secondary mr-2 cursor-pointer">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back
          </a>
          <button v-if="isEditing" type="button" @click="handleDelete" class="btn btn-destructive mr-2 cursor-pointer">
            <Trash2 class="mr-2 h-4 w-4" />
            Delete
          </button>
          <button type="submit" :disabled="form.processing" class="btn btn-primary cursor-pointer">
            <Save class="mr-2 h-4 w-4" />
            {{ isEditing ? 'Save changes' : 'Publish' }}
          </button>
        </template>
      </PageHeader>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <label for="field-title" class="text-sm font-medium">Title</label>
            <Input id="field-title" v-model="form.title" placeholder="What are you announcing?" required />
            <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
          </div>

          <div class="flex flex-col gap-1">
            <label for="field-slug" class="text-sm font-medium">
              Slug <span class="text-muted-foreground">(optional - auto-generated from title)</span>
            </label>
            <Input id="field-slug" v-model="form.slug" placeholder="my-update-post" />
            <p v-if="form.errors.slug" class="text-xs text-destructive">{{ form.errors.slug }}</p>
          </div>

          <div class="flex flex-col gap-1">
            <label for="field-excerpt" class="text-sm font-medium">
              Excerpt <span class="text-muted-foreground">(markdown, shown in lists)</span>
            </label>
            <textarea
              id="field-excerpt"
              v-model="form.excerpt"
              rows="3"
              class="input-border w-full rounded-sm p-2 font-mono text-sm"
              placeholder="A short summary - markdown OK."
            />
            <p v-if="form.errors.excerpt" class="text-xs text-destructive">{{ form.errors.excerpt }}</p>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium">
              Body <span class="text-muted-foreground">(markdown, HTML allowed)</span>
            </label>
            <div class="overflow-hidden rounded-sm border border-sidebar-border" style="height: 600px">
              <Codemirror
                :key="'body-' + editorKey"
                v-model="form.body"
                class="h-full"
                :indent-with-tab="true"
                :tab-size="2"
                :extensions="bodyExtensions"
                placeholder="Write your update in markdown. HTML is also supported."
              />
            </div>
            <p v-if="form.errors.body" class="text-xs text-destructive">{{ form.errors.body }}</p>
          </div>
        </div>

        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <label for="field-published" class="text-sm font-medium">Post date</label>
            <input
              id="field-published"
              v-model="form.published_at"
              type="datetime-local"
              class="input-border h-10 w-full rounded-sm px-2"
            />
            <p v-if="form.errors.published_at" class="text-xs text-destructive">{{ form.errors.published_at }}</p>
          </div>

          <div class="flex flex-col gap-1">
            <label for="field-tags" class="text-sm font-medium">
              Tags <span class="text-muted-foreground">(comma-separated)</span>
            </label>
            <Input
              id="field-tags"
              v-model="tagsInput"
              placeholder="kits, kofi, release"
            />
            <p v-if="form.errors.tags" class="text-xs text-destructive">{{ form.errors.tags }}</p>
            <p class="text-xs text-muted-foreground">Stored exactly as you type them - no HTML, case preserved.</p>
          </div>
        </div>
      </div>
    </form>
  </AppLayout>
</template>
