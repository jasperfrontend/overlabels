<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import type { OverlayControl } from '@/types';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import TemplateTagsList from '@/components/TemplateTagsList.vue';
import TemplateCodeEditor from '@/components/templates/TemplateCodeEditor.vue';
import AlertTargetOverlaySelector from '@/components/AlertTargetOverlaySelector.vue';
import TemplateScreenshot from '@/components/templates/TemplateScreenshot.vue';
import ControlsManager from '@/components/ControlsManager.vue';
import ControlPanel from '@/components/ControlPanel.vue';
import TemplateMeta from '@/components/TemplateMeta.vue';
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
  SquarePenIcon,
  Target,
  ImageIcon,
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

interface OverlayOption {
  id: number
  name: string
  slug: string
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
    type: 'static' | 'alert';
    screenshot_url: string | null;
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
  connectedServices?: string[];
  isLive?: boolean;
  staticOverlays?: OverlayOption[];
  targetStaticOverlayIds?: number[];
  userScopedControls?: OverlayControl[];
}

const props = withDefaults(defineProps<Props>(), {
  existingTemplate: () => ({ head: '', html: '', css: '' }),
  availableTags: () => [],
  template: Object,
});

const isDark = ref(document.documentElement.classList.contains('dark'));
const { triggerLinkWarning } = useLinkWarning();

const {
  canDelete,
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

const mainTabs = computed(() => {
  const tabs: Array<{ key: string; label: string; icon: any }> = [
    { key: 'code', label: 'Code', icon: Code },
    { key: 'meta', label: 'Meta', icon: InfoIcon },
    { key: 'tags', label: 'Tags', icon: Brackets },
    { key: 'controls', label: 'Controls', icon: SlidersHorizontal },
    { key: 'panel', label: 'Values', icon: SquarePenIcon },
    { key: 'screenshot', label: 'Screenshot', icon: ImageIcon },
  ];
  if (props.template.type === 'alert') {
    tabs.push({ key: 'targeting', label: 'Targeting', icon: Target });
  }
  return tabs;
});

const mainTab = ref<string>('code');
const localControls = ref<OverlayControl[]>([...(props.controls ?? [])]);

const localTargetOverlayIds = ref<number[]>([...(props.targetStaticOverlayIds ?? [])]);

function saveTargeting() {
  router.put(
    route('templates.target-overlays', props.template),
    { overlay_ids: localTargetOverlayIds.value },
    {
      preserveScroll: true,
      onSuccess: () => pushToast('Targeting settings saved.', 'success'),
      onError: () => pushToast('Failed to save targeting settings.', 'error'),
    },
  );
}

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

const { register } = useKeyboardShortcuts();

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
});

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
                Copy
              </DropdownMenuItem>

              <DropdownMenuSeparator />

              <DropdownMenuItem v-if="canDelete" class="text-destructive focus:text-destructive" @click="deleteTemplate">
                <Trash class="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
              <DropdownMenuItem v-else disabled class="text-muted-foreground text-xs">
                Part of a kit - cannot delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <form @submit.prevent="submitForm">
        <!-- Tab bar -->
        <div class="rounded-sm rounded-b-none border border-b-0 border-sidebar bg-card">
          <div class="flex border-b border-violet-600 dark:border-violet-400">
            <button
              v-for="(tab, index) in mainTabs"
              :key="tab.key"
              type="button"
              @click="mainTab = tab.key"
              :class="[
                'flex cursor-pointer items-center gap-1.5 px-5 py-2.5 text-sm font-medium transition-colors hover:bg-background',
                index === 0 && 'rounded-tl-sm',
                mainTab === tab.key ? 'bg-violet-400 hover:bg-violet-500 text-black' : 'text-accent-foreground',
              ]"
            >
              <component :is="tab.icon" class="h-4 w-4" />
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Content box -->
        <div class="rounded-b-sm border border-t-0 border-sidebar bg-card p-4">
          <!-- Code Tab -->
          <TemplateCodeEditor
            v-show="mainTab === 'code'"
            v-model:head="form.head"
            v-model:body="form.html"
            v-model:css="form.css"
            :is-dark="isDark"
          />

          <!-- Meta Tab -->
          <div v-if="mainTab === 'meta'" class="max-w-5xl space-y-4">
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

            <TemplateMeta
              :created-at="template?.created_at"
              :updated-at="template?.updated_at"
              :view-count="template?.view_count"
              :fork-count="template?.fork_count"
              :template-tags="template?.template_tags"
            />
          </div>

          <!-- Tags Tab -->
          <div v-if="mainTab === 'tags'">
            <TemplateTagsList />
          </div>

          <!-- Controls Tab -->
          <div v-if="mainTab === 'controls'">
            <ControlsManager :template="template" :initial-controls="localControls" :connected-services="connectedServices" :user-scoped-controls="userScopedControls" @change="localControls = $event" />
          </div>

          <!-- Values Tab -->
          <div v-if="mainTab === 'panel'">
            <ControlPanel :template="template" :controls="localControls" :is-live="isLive" />
          </div>

          <!-- Screenshot Tab -->
          <div v-if="mainTab === 'screenshot'">
            <TemplateScreenshot
              :screenshot-url="template.screenshot_url"
              :template-id="template.id"
              :name="template.name"
              @saved="pushToast('Screenshot saved.', 'success')"
              @removed="pushToast('Screenshot removed.', 'success')"
              @error="(msg: string) => pushToast(msg, 'error')"
            />
          </div>

          <!-- Targeting Tab (alert templates only) -->
          <div v-if="mainTab === 'targeting'" class="max-w-2xl">
            <AlertTargetOverlaySelector
              v-model="localTargetOverlayIds"
              :static-overlays="staticOverlays ?? []"
            />
            <button type="button" @click="saveTargeting" class="btn btn-primary mt-4">Save targeting</button>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="mt-6 flex justify-between">
          <Link :href="route('templates.show', template)" class="btn btn-cancel">← Back to Overlay</Link>
          <button type="submit" :disabled="form.processing || !form.isDirty" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>


    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />
  </AppLayout>
</template>
