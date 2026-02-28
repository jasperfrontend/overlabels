<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import type { OverlayControl } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import TemplateTagsList from '@/components/TemplateTagsList.vue';
import TemplateCodeEditor from '@/components/templates/TemplateCodeEditor.vue';
import KeyboardShortcutsDialog from '@/components/KeyboardShortcutsDialog.vue';
import {
  Brackets,
  Code,
  InfoIcon,
  RefreshCcwDot,
  Save,
  ExternalLink,
  Split,
  Trash,
  MoreVertical,
  SlidersHorizontal,
  CopyIcon,
} from 'lucide-vue-next';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { stripScriptsFromFields } from '@/utils/sanitize';
import { useLinkWarning } from '@/composables/useLinkWarning';
import { useTemplateActions } from '@/composables/useTemplateActions';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

interface TemplateTag {
  display_tag: string;
  description: string;
  category?: string;
}

interface Props {
  existingTemplate: { head: string; html: string; css: string };
  template: {
    id: number;
    name: string;
    description: string;
    head: string;
    html: string;
    css: string;
    is_public: boolean;
    slug: string;
    created_at: string;
    updated_at: string;
    view_count: number;
    fork_count: number;
    template_tags: TemplateTag[] | null;
  };
  availableTags: Array<{
    tag_name: string;
    display_name: string;
    description: string;
    data_type: string;
    category: string;
    sample_data?: string;
  }>;
  controls?: OverlayControl[];
}

const props = withDefaults(defineProps<Props>(), {
  existingTemplate: () => ({ head: '', html: '', css: '' }),
  availableTags: () => [],
  template: Object,
});

const isDark = ref(document.documentElement.classList.contains('dark'));
const { triggerLinkWarning } = useLinkWarning();

const {
  previewTemplate,
  forkTemplate,
  deleteTemplate,
  toastMessage: templateToastMessage,
  toastType: templateToastType,
  showToast: showTemplateToast,
} = useTemplateActions(props.template);

const form = useForm({
  name: props?.template?.name,
  description: props?.template?.description || '',
  head: props?.template?.head || '',
  html: props?.template?.html || '',
  css: props?.template?.css || '',
  is_public: props?.template?.is_public,
});

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Editing: ' + props.template.name,
    href: route('templates.edit', props.template),
  },
];

const mainTabs = [
  { key: 'code', label: 'Code', icon: Code },
  { key: 'meta', label: 'Meta', icon: InfoIcon },
  { key: 'tags', label: 'Tags', icon: Brackets },
  { key: 'controls', label: 'Controls', icon: SlidersHorizontal },
] as const;

const mainTab = ref<'code' | 'meta' | 'tags' | 'controls'>('code');

const toastMessage = ref<string>('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);

const pushToast = (message: string, type: typeof toastType.value = 'info') => {
  showToast.value = false;
  toastMessage.value = message;
  toastType.value = type;
  showToast.value = true;
};

// Bridge template-action toasts into the unified toast
watch(showTemplateToast, (on) => {
  if (!on) return;
  pushToast(templateToastMessage.value, templateToastType.value);
  showTemplateToast.value = false;
});

const openExternalLink = (link: any, target: string) => window.open(link, target);

const copySnippet = (snippet: string) => {
  navigator.clipboard.writeText(snippet);
  pushToast(`${snippet} copied to clipboard!`, 'success');
};

const submitForm = () => {
  const { sanitized, removed } = stripScriptsFromFields({
    name: form.name,
    description: form.description,
    head: form.head,
    html: form.html,
    css: form.css,
  });
  Object.assign(form, sanitized);

  form.put(route('templates.update', props.template), {
    preserveScroll: true,
    onSuccess: () => {
      pushToast(
        removed > 0
          ? `Saved! Also removed ${removed} script tag${removed === 1 ? '' : 's'} — inline scripts aren't supported.`
          : 'Overlay saved successfully!',
        removed > 0 ? 'warning' : 'success',
      );
    },
    onError: () => pushToast('Failed to save overlay.', 'error'),
  });
};

watch(
  () => document.documentElement.classList.contains('dark'),
  (newDark) => {
    isDark.value = newDark;
  },
);

const { register, getAllShortcuts } = useKeyboardShortcuts();
const showKeyboardShortcuts = ref(false);

onMounted(() => {
  register('save-template', 'ctrl+s', () => submitForm(), { description: 'Save overlay' });
  register(
    'preview-live',
    'ctrl+p',
    () => {
      triggerLinkWarning(
        () => openExternalLink(`/overlay/${props.template?.slug}/public`, '_blank'),
        'Remember: DO NOT EVER show the overlay link with your personal access #hash in the URL on stream! Treat it like a password.',
      );
    },
    { description: 'Preview in new tab' },
  );
  register(
    'toggle-shortcuts',
    'ctrl+k',
    () => {
      showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
    },
    { description: 'Show keyboard shortcuts' },
  );
});

const keyboardShortcutsList = computed(() => getAllShortcuts());
</script>

<template>
  <Head :title="`Editing: ${template.name}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <!-- Header -->
      <div class="mb-6 flex items-start justify-between">
        <Heading
          :title="template.name"
          :description="template.description || 'No description set.'"
          description-class="text-sm text-muted-foreground"
        />
        <div class="flex shrink-0 items-center gap-2">
          <button @click="submitForm" :disabled="form.processing || !form.isDirty" class="btn btn-primary">
            <RefreshCcwDot v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            <Save v-else class="mr-2 h-4 w-4" />
            Save
          </button>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button class="btn btn-sm btn-secondary px-2" title="More actions">
                <MoreVertical class="h-4 w-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
              <DropdownMenuItem @click="previewTemplate">
                <ExternalLink class="mr-2 h-4 w-4" />
                Preview
              </DropdownMenuItem>
              <DropdownMenuItem v-if="!template?.is_public" class="pointer-events-none text-xs text-muted-foreground">
                Add token to URL: #YOUR_TOKEN_HERE
              </DropdownMenuItem>

              <DropdownMenuSeparator />

              <DropdownMenuItem @click="forkTemplate">
                <Split class="mr-2 h-4 w-4" />
                Fork
              </DropdownMenuItem>

              <DropdownMenuSeparator />

              <DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteTemplate">
                <Trash class="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <form @submit.prevent="submitForm">
        <!-- Tab bar -->
        <div class="rounded-sm rounded-b-none border border-b-0 border-sidebar bg-sidebar-accent">
          <div class="flex border-b border-violet-600 dark:border-violet-400">
            <button
              v-for="(tab, index) in mainTabs"
              :key="tab.key"
              type="button"
              @click="mainTab = tab.key"
              :class="[
                'flex cursor-pointer items-center gap-1.5 px-5 py-2.5 text-sm font-medium transition-colors hover:bg-background',
                index === 0 && 'rounded-tl-sm',
                mainTab === tab.key ? 'bg-violet-600 text-accent dark:bg-violet-400' : 'text-accent-foreground',
              ]"
            >
              <component :is="tab.icon" class="h-4 w-4" />
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Content box -->
        <div class="rounded-b-sm border border-t-0 border-sidebar bg-sidebar-accent p-4">
          <!-- Code Tab -->
          <TemplateCodeEditor
            v-if="mainTab === 'code'"
            v-model:head="form.head"
            v-model:html="form.html"
            v-model:css="form.css"
            :is-dark="isDark"
            @toggle-shortcuts="showKeyboardShortcuts = !showKeyboardShortcuts"
          />

          <!-- Meta Tab -->
          <div v-else-if="mainTab === 'meta'" class="max-w-5xl space-y-4">
            <div>
              <label for="name" class="mb-1 block text-sm font-medium text-accent-foreground/50">Title *</label>
              <input id="name" v-model="form.name" type="text" class="input-border w-full" required />
              <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
            </div>

            <div>
              <label for="description" class="mb-1 block text-sm font-medium text-accent-foreground/50">Description</label>
              <textarea id="description" v-model="form.description" rows="3" class="input-border w-full" />
              <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
            </div>

            <div>
              <label class="flex items-center gap-2">
                <input v-model="form.is_public" type="checkbox" />
                <span class="text-sm">Make this overlay public (others can view and fork it)</span>
              </label>
            </div>

            <div class="grid grid-cols-2 gap-4 rounded-sm bg-background p-4 text-sm">
              <div>
                <span class="text-muted-foreground">Created:</span>
                <span class="ml-2">{{ new Date(template?.created_at).toLocaleDateString() }}</span>
              </div>
              <div>
                <span class="text-muted-foreground">Last updated:</span>
                <span class="ml-2">{{ new Date(template?.updated_at).toLocaleDateString() }}</span>
              </div>
              <div>
                <span class="text-muted-foreground">Views:</span>
                <span class="ml-2">{{ template?.view_count }}</span>
              </div>
              <div>
                <span class="text-muted-foreground">Forks:</span>
                <span class="ml-2">{{ template?.fork_count }}</span>
              </div>
            </div>

            <div v-if="template?.template_tags && template.template_tags.length > 0" class="rounded-sm bg-background p-4 text-sm">
              <p class="mb-2 text-muted-foreground">Template Tags Used</p>
              <div class="flex flex-wrap gap-1">
                <code v-for="tag in template.template_tags" :key="tag.display_tag" class="btn btn-chill btn-xs btn-dead">
                  {{ tag }}
                </code>
              </div>
            </div>
          </div>

          <!-- Tags Tab -->
          <div v-else-if="mainTab === 'tags'">
            <TemplateTagsList />
          </div>

          <!-- Controls Tab -->
          <div v-else-if="mainTab === 'controls'">
            <div v-if="!controls || controls.length === 0" class="py-8 text-center text-sm text-muted-foreground">
              No controls defined for this overlay yet.
              <a :href="route('templates.show', template)" class="ml-1 text-violet-400 hover:underline">Go to the overlay page</a>
              to add some.
            </div>
            <template v-else>
              <p class="mb-3 text-sm text-muted-foreground">Reference-only. Use these snippets in your HTML or CSS — click any snippet to copy it.</p>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead class="w-32">Key</TableHead>
                    <TableHead>Label</TableHead>
                    <TableHead class="w-28">Type</TableHead>
                    <TableHead>Snippet</TableHead>
                    <TableHead>Value</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="control in controls" :key="control.id">
                    <TableCell class="font-mono text-xs">{{ control.key }}</TableCell>
                    <TableCell class="text-sm">{{ control.label ?? '—' }}</TableCell>
                    <TableCell>
                      <Badge variant="outline" class="font-mono text-xs capitalize">{{ control.type }}</Badge>
                    </TableCell>
                    <TableCell>
                      <button
                        type="button"
                        @click="copySnippet(`[[[c:${control.key}]]]`)"
                        class="group flex items-center gap-1.5 rounded bg-sidebar px-2 py-1 font-mono text-xs transition-colors hover:bg-violet-600 hover:text-accent dark:hover:bg-violet-400"
                        title="Click to copy"
                      >
                        [[[c:{{ control.key }}]]]
                        <CopyIcon class="h-3 w-3 opacity-40 group-hover:opacity-100" />
                      </button>
                    </TableCell>
                    <TableCell class="text-sm text-muted-foreground">
                      {{ control.value }}
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </template>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="mt-6 flex justify-between">
          <Link :href="route('templates.show', template)" class="btn btn-cancel">← Back to Overlay</Link>
          <button type="submit" :disabled="form.processing || !form.isDirty" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>

    <KeyboardShortcutsDialog :show="showKeyboardShortcuts" :shortcuts="keyboardShortcutsList" @close="showKeyboardShortcuts = false" />

    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />
  </AppLayout>
</template>
