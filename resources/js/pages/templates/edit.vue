<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import type { OverlayControl } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import TemplateTagsList from '@/components/TemplateTagsList.vue';
import TemplateCodeEditor from '@/components/templates/TemplateCodeEditor.vue';
import AlertTargetOverlaySelector from '@/components/AlertTargetOverlaySelector.vue';
import TemplateScreenshot from '@/components/templates/TemplateScreenshot.vue';
import ControlsManager from '@/components/ControlsManager.vue';
import ControlPanel from '@/components/ControlPanel.vue';
import ForkImportWizard from '@/components/ForkImportWizard.vue';
import IntegrationSuggestionModal from '@/components/IntegrationSuggestionModal.vue';
import TemplateMeta from '@/components/TemplateMeta.vue';
import TriggerManager, { type TriggerData } from '@/components/TriggerManager.vue';
import BrowseFreesoundModal from '@/components/BrowseFreesoundModal.vue';
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
  Volume2,
  Zap,
  Search,
  Play,
  Pause,
  Trash2,
  ExternalLink as ExternalLinkIcon,
} from '@lucide/vue';
import axios from 'axios';
import PublicToggle from '@/components/PublicToggle.vue';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { sanitizeHtmlFields } from '@/utils/sanitize';
import { compileTailwindCss } from '@/utils/compileTailwind';
import { useLinkWarning } from '@/composables/useLinkWarning';
import { useTemplateActions } from '@/composables/useTemplateActions';
import { captureListContext, deriveListContext } from '@/composables/useListContext';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';

interface OverlayOption {
  id: number;
  name: string;
  slug: string;
}

interface FreesoundLibraryRow {
  id: number;
  freesound_id: number;
  name: string;
  author: string;
  license: string;
  duration: number | null;
  preview_url: string;
  freesound_url: string | null;
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
    compiled_css: string | null;
    is_public: boolean;
    slug: string;
    type: 'static' | 'alert';
    screenshot_url: string | null;
    created_at: string;
    updated_at: string;
    view_count: number;
    fork_count: number;
    template_tags: string[] | null | undefined;
    tts_expression: string | null;
    bot_message_expression: string | null;
    tts_delay_ms: number | null;
    alert_sound_url: string | null;
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
  userLists?: Array<{ id: number; slug: string; label?: string | null; items_count: number; disabled: boolean }>;
  triggers?: TriggerData | null;
  freesoundLibrary?: FreesoundLibraryRow[];
}

const FREESOUND_LIBRARY_CAP = 100;

const props = withDefaults(defineProps<Props>(), {
  template: Object
});

const { triggerLinkWarning } = useLinkWarning();

const {
  canDelete,
  previewTemplate,
  forkTemplate,
  deleteTemplate,
  toastMessage: templateToastMessage,
  toastType: templateToastType,
  showToast: showTemplateToast,
  forkWizardOpen,
  forkWizardTemplateId,
  forkWizardTemplateSlug,
  forkWizardSourceControls,
  forkWizardRequiredServices,
  forkWizardConnectedServices
} = useTemplateActions(props.template, { redirectAfterDelete: () => listContext.href });

const form = useForm({
  name: props?.template?.name,
  description: props?.template?.description || '',
  head: props?.template?.head || '',
  html: props?.template?.html || '',
  css: props?.template?.css || '',
  compiled_css: props?.template?.compiled_css || '',
  is_public: props?.template?.is_public,
  tts_expression: props?.template?.tts_expression || '',
  bot_message_expression: props?.template?.bot_message_expression || '',
  tts_delay_ms: props?.template?.tts_delay_ms ?? 0,
  alert_sound_url: props?.template?.alert_sound_url || '',
});

// Freeze the list we came from for this template, so the breadcrumb and the
// post-delete redirect (see useTemplateActions) always agree, even after the
// index is re-filtered or restored via browser back/forward. When there's no
// recorded navigation (direct URL, fresh tab), fall back to a crumb derived from
// the template itself. The edit page is owner-only, so ownership is always "My".
const listContext = captureListContext(
  props.template?.id,
  deriveListContext({ type: props.template?.type, ownedByMe: true }),
);

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: listContext.title,
    href: listContext.href
  },
  {
    title: props.template?.name || 'Template',
    href: `/templates/${props.template?.id}`
  },
  {
    title: 'Edit',
    href: route('templates.edit', props.template)
  }
];

const mainTabs = computed(() => {
  const tabs: Array<{ key: string; label: string; icon: any }> = [
    { key: 'code', label: 'Code', icon: Code },
    { key: 'meta', label: 'Meta', icon: InfoIcon },
    { key: 'tags', label: 'Tags', icon: Brackets },
    { key: 'controls', label: 'Controls', icon: SlidersHorizontal },
    { key: 'panel', label: 'Values', icon: SquarePenIcon },
    { key: 'screenshot', label: 'Screenshot', icon: ImageIcon }
  ];
  if (props.template.type === 'alert') {
    tabs.push({ key: 'triggers', label: 'Triggers', icon: Zap });
    tabs.push({ key: 'targeting', label: 'Targeting', icon: Target });
    tabs.push({ key: 'tts', label: 'Effects', icon: Volume2 });
  }
  return tabs;
});

const mainTab = ref<string>('code');
const localControls = ref<OverlayControl[]>([...(props.controls ?? [])]);

const localTargetOverlayIds = ref<number[]>([...(props.targetStaticOverlayIds ?? [])]);

const freesoundLibrary = ref<FreesoundLibraryRow[]>([...(props.freesoundLibrary ?? [])]);
const freesoundModalOpen = ref(false);
const libraryAuditioningId = ref<number | null>(null);
let libraryAuditionPlayer: HTMLAudioElement | null = null;

const currentLibrarySound = computed(() =>
  freesoundLibrary.value.find((s) => s.preview_url === form.alert_sound_url) ?? null
);

function toggleLibraryAudition(sound: FreesoundLibraryRow) {
  if (libraryAuditioningId.value === sound.id) {
    stopLibraryAudition();
    return;
  }
  stopLibraryAudition();
  libraryAuditionPlayer = new Audio(sound.preview_url);
  libraryAuditionPlayer.addEventListener('ended', stopLibraryAudition);
  libraryAuditionPlayer.play().catch(() => stopLibraryAudition());
  libraryAuditioningId.value = sound.id;
}

function stopLibraryAudition() {
  if (libraryAuditionPlayer) {
    libraryAuditionPlayer.pause();
    libraryAuditionPlayer.removeEventListener('ended', stopLibraryAudition);
    libraryAuditionPlayer = null;
  }
  libraryAuditioningId.value = null;
}

function useLibrarySound(sound: FreesoundLibraryRow) {
  form.alert_sound_url = sound.preview_url;
  applySoundDurationToTtsDelay(sound.duration);
  stopLibraryAudition();
}

/**
 * When a sound is picked, set the TTS delay to the sound's duration so the
 * voice starts right after the SFX finishes. Always overwrites - the assumption
 * is the user is actively choosing this sound for this alert, so its length is
 * the right reference for the delay. Users can still hand-tune after.
 */
function applySoundDurationToTtsDelay(durationSeconds: number | null | undefined): void {
  if (durationSeconds === null || durationSeconds === undefined) return;
  form.tts_delay_ms = Math.ceil(durationSeconds * 1000);
}

async function removeLibrarySound(sound: FreesoundLibraryRow) {
  try {
    await axios.delete(route('freesound.destroy', { sound: sound.id }));
    freesoundLibrary.value = freesoundLibrary.value.filter((s) => s.id !== sound.id);
    if (form.alert_sound_url === sound.preview_url) {
      form.alert_sound_url = '';
      form.tts_delay_ms = 0;
    }
    if (libraryAuditioningId.value === sound.id) stopLibraryAudition();
    pushToast('Sound removed from your library.', 'success');
  } catch (e: any) {
    pushToast(e.response?.data?.message ?? 'Could not remove sound.', 'error');
  }
}

function onFreesoundSaved(sound: FreesoundLibraryRow) {
  // Replace existing entry if user re-saved a sound; otherwise prepend new.
  const existing = freesoundLibrary.value.findIndex((s) => s.freesound_id === sound.freesound_id);
  if (existing >= 0) {
    freesoundLibrary.value.splice(existing, 1, sound);
  } else {
    freesoundLibrary.value = [sound, ...freesoundLibrary.value];
  }
  form.alert_sound_url = sound.preview_url;
  applySoundDurationToTtsDelay(sound.duration);
  pushToast(`Added "${sound.name}" to your library.`, 'success');
}

function clearAlertSoundUrl() {
  form.alert_sound_url = '';
  form.tts_delay_ms = 0;
}

function licenseShort(license: string): string {
  if (!license) return '';
  const l = license.toLowerCase();
  // URL forms (what the Freesound API actually returns)
  if (l.includes('publicdomain/zero') || l.includes('cc0')) return 'CC0';
  if (l.includes('/licenses/by-nc')) return 'CC-BY-NC';
  if (l.includes('/licenses/by-sa')) return 'CC-BY-SA';
  if (l.includes('/licenses/by-nd')) return 'CC-BY-ND';
  if (l.includes('sampling')) return 'Sampling+';
  if (l.includes('/licenses/by/')) return 'CC-BY';
  // Name-string forms (older Freesound docs claimed these)
  if (l.includes('creative commons 0')) return 'CC0';
  if (l === 'attribution') return 'CC-BY';
  if (l.includes('noncommercial')) return 'CC-BY-NC';
  return l.startsWith('http') ? 'CC' : license;
}

function formatDuration(d: number | null | undefined): string {
  if (d === null || d === undefined) return '';
  if (d < 1) return `${Math.round(d * 1000)}ms`;
  return `${d.toFixed(1)}s`;
}

function saveTargeting() {
  router.put(
    route('templates.target-overlays', props.template),
    { overlay_ids: localTargetOverlayIds.value },
    {
      preserveScroll: true,
      onSuccess: () => pushToast('Targeting settings saved.', 'success'),
      onError: () => pushToast('Failed to save targeting settings.', 'error')
    }
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

const suggestionModalOpen = ref(false);
const showSuggestionLink = ref(false);

const submitForm = async () => {
  // Check if embeddable elements were present before sanitization
  const rawHtml = `${form.head} ${form.html} ${form.css}`;
  const hadEmbeds = /<iframe\b|<embed\b|<object\b/i.test(rawHtml);

  const { sanitized, removed } = sanitizeHtmlFields({
    name: form.name,
    description: form.description,
    head: form.head,
    html: form.html,
    css: form.css
  });
  Object.assign(form, sanitized);

  form.compiled_css = await compileTailwindCss({
    html: form.html,
    head: form.head,
    css: form.css,
  });

  form.put(route('templates.update', props.template), {
    preserveScroll: true,
    onSuccess: () => {
      if (removed > 0 && hadEmbeds) {
        showSuggestionLink.value = true;
        pushToast(
          `Saved! Removed ${removed} unsafe pattern${removed === 1 ? '' : 's'}. Embeds (iframes, objects) are not allowed - want to suggest an integration instead?`,
          'warning'
        );
      } else if (removed > 0) {
        pushToast(
          `Saved! Removed ${removed} unsafe pattern${removed === 1 ? '' : 's'} (scripts, event handlers, or javascript: URIs).`,
          'warning'
        );
      } else {
        showSuggestionLink.value = false;
        pushToast('Overlay saved successfully!', 'success');
      }
    },
    onError: () => pushToast('Failed to save overlay.', 'error')
  });
};


const { register } = useKeyboardShortcuts();

onMounted(() => {
  register('save-template', 'ctrl+s', () => submitForm(), { description: 'Save overlay' });
  register(
    'preview-live',
    'ctrl+p',
    () => {
      triggerLinkWarning(
        () => openExternalLink(`/overlay/${props.template?.slug}/public`, '_blank'),
        'Remember: DO NOT EVER show the overlay link with your personal access #hash in the URL on stream! Treat it like a password.'
      );
    },
    { description: 'Preview in new tab' }
  );

  for (let i = 1; i <= 8; i++) {
    register(`switch-tab-${i}`, `${i}`, () => {
      const tab = mainTabs.value[i - 1];
      if (tab) mainTab.value = tab.key;
    }, { description: `Switch to tab ${i}` });
  }

  register('blur-focus', 'alt+f', () => {
    const el = document.activeElement as HTMLElement | null;
    el?.blur();
  }, { description: 'Release focus from editor / input' });

  register('back-to-show', 's', () => {
    if (form.isDirty) {
      pushToast('Save your changes before leaving.', 'warning');
      return;
    }
    if (props.template?.id) router.visit(route('templates.show', props.template.id));
  }, { description: 'Back to overlay overview' });
});

</script>

<template>
  <Head :title="`Editing: ${template.name}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <IntegrationSuggestionModal v-model:open="suggestionModalOpen" />
    <ForkImportWizard
      v-model:open="forkWizardOpen"
      :forked-template-id="forkWizardTemplateId"
      :forked-template-slug="forkWizardTemplateSlug"
      :source-controls="forkWizardSourceControls"
      :required-services="forkWizardRequiredServices"
      :connected-services="forkWizardConnectedServices"
    />
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

              <DropdownMenuItem v-if="canDelete" class="text-destructive focus:text-destructive"
                                @click="deleteTemplate">
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
        <div class="bg-violet-300/20 dark:bg-violet-900/20">
          <div
            class="flex dark:border-violet-400 max-w-full touch-pan-x lg:touch-none overflow-auto">
            <button
              v-for="(tab) in mainTabs"
              :key="tab.key"
              type="button"
              @click="mainTab = tab.key"
              :class="[
                'flex cursor-pointer items-center gap-1.5 px-4 py-2.5 text-sm font-medium transition-colors hover:bg-background',
                mainTab === tab.key ? ' border-t-2 border-t-violet-400 bg-white dark:bg-violet-500/30 dark:hover-bg-violet-500 text-black dark:text-violet-300' : 'text-accent-foreground',
              ]"
            >
              <component :is="tab.icon" class="h-4 w-4" />
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Content box -->
        <div
          class="border border-t-0 border-sidebar-border bg-card h-full overflow-auto"
        >
          <!-- Code Tab -->
          <TemplateCodeEditor
            :template-type="props.template.type"
            :template="props.template"
            v-show="mainTab === 'code'"
            v-model:head="form.head"
            v-model:body="form.html"
            v-model:css="form.css"
          />

          <!-- Meta Tab -->
          <div v-if="mainTab === 'meta'" class="max-w-5xl p-4 pt-5 space-y-5">
            <div>
              <label for="name" class="mb-1 block text-sm font-medium text-accent-foreground">Title *</label>
              <input id="name" v-model="form.name" type="text" class="input-border w-full" required />
              <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
            </div>

            <div>
              <label for="description"
                     class="mb-1 block text-sm font-medium text-accent-foreground">Description</label>
              <textarea id="description" v-model="form.description" rows="3" class="input-border w-full" />
              <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
            </div>

            <PublicToggle v-model="form.is_public" label="Overlay" />

            <TemplateMeta
              :created-at="template?.created_at"
              :updated-at="template?.updated_at"
              :view-count="template?.view_count"
              :fork-count="template?.fork_count"
              :template-tags="template?.template_tags"
              :slug="template.slug"
              owner="You"
            />
          </div>

          <!-- Tags Tab -->
          <div v-if="mainTab === 'tags'" class="p-4">
            <TemplateTagsList />
          </div>

          <!-- Controls Tab -->
          <div v-if="mainTab === 'controls'" class="p-4">
            <ControlsManager :template="template" :initial-controls="localControls"
                             :connected-services="connectedServices" :user-scoped-controls="userScopedControls"
                             :user-lists="userLists"
                             @change="localControls = $event" />
          </div>

          <!-- Values Tab -->
          <div v-if="mainTab === 'panel'" class="p-4">
            <ControlPanel :template="template" :controls="localControls" :is-live="isLive" />
          </div>

          <!-- Screenshot Tab -->
          <div v-if="mainTab === 'screenshot'" class="p-4">
            <TemplateScreenshot
              :screenshot-url="template.screenshot_url"
              :template-id="template.id"
              :name="template.name"
              @saved="pushToast('Screenshot saved.', 'success')"
              @removed="pushToast('Screenshot removed.', 'success')"
              @error="(msg: string) => pushToast(msg, 'error')"
            />
          </div>

          <!-- Triggers Tab (alert templates only) -->
          <div v-if="mainTab === 'triggers'" class="p-4">
            <TriggerManager
              v-if="triggers"
              :template-id="template.id"
              :triggers="triggers"
              @saved="pushToast('Triggers saved.', 'success')"
              @error="(msg: string) => pushToast(msg, 'error')"
            />
          </div>

          <!-- Targeting Tab (alert templates only) -->
          <div v-if="mainTab === 'targeting'" class="max-w-2xl p-4">
            <AlertTargetOverlaySelector
              v-model="localTargetOverlayIds"
              :static-overlays="staticOverlays ?? []"
            />
            <button type="button" @click="saveTargeting" class="btn btn-primary mt-4">Save targeting</button>
          </div>

          <!-- Effects Tab (alert templates only): alert sound + TTS + bot chat message -->
          <div v-if="mainTab === 'tts'" class="p-4">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <!-- Left column (2/3): alert sound + TTS -->
              <div class="lg:col-span-2 space-y-6">
                <section>
                  <header class="mb-3">
                    <h3 class="text-base font-semibold text-accent-foreground">Alert sound</h3>
                    <p class="text-xs text-foreground/80">
                      Plays once when the alert fires. Preloaded on overlay mount for instant playback.
                    </p>
                  </header>

                  <div class="flex gap-2">
                    <input
                      id="alert_sound_url"
                      v-model="form.alert_sound_url"
                      type="url"
                      maxlength="2048"
                      class="input-border flex-1 font-mono text-sm"
                      placeholder="https://your-cdn.example/coin.mp3 - or browse Freesound"
                    />
                    <button
                      type="button"
                      class="btn btn-secondary cursor-pointer whitespace-nowrap"
                      @click="freesoundModalOpen = true"
                    >
                      <Search class="mr-1.5 h-4 w-4" />
                      Browse Freesound
                    </button>
                  </div>
                  <div v-if="form.errors.alert_sound_url" class="mt-1 text-sm text-red-600">{{ form.errors.alert_sound_url }}</div>

                  <!-- Attribution box, shown when the current URL matches a Freesound library entry -->
                  <div v-if="currentLibrarySound" class="mt-2 flex items-center gap-2 rounded border border-sidebar-border bg-muted/30 px-3 py-2 text-xs text-foreground">
                    <span class="font-medium">{{ currentLibrarySound.name }}</span>
                    <span>by {{ currentLibrarySound.author }}</span>
                    <span class="inline-flex items-center rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase">
                      {{ licenseShort(currentLibrarySound.license) }}
                    </span>
                    <a
                      v-if="currentLibrarySound.freesound_url"
                      :href="currentLibrarySound.freesound_url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="cursor-pointer inline-flex items-center gap-0.5 text-violet-500 hover:underline"
                    >
                      Freesound <ExternalLinkIcon class="h-3 w-3" />
                    </a>
                    <button
                      type="button"
                      class="ml-auto cursor-pointer text-foreground/70 hover:text-foreground"
                      @click="clearAlertSoundUrl"
                      title="Clear sound from this alert"
                    >Clear</button>
                  </div>

                  <p v-if="!form.alert_sound_url" class="mt-2 text-xs text-foreground/70">
                    Or host your own MP3/OGG/WAV anywhere CORS-friendly (Cloudflare R2, Backblaze B2, GitHub Pages). Raw <code class="rounded bg-muted px-1">&lt;audio&gt;</code> in the alert HTML is stripped on save - use this field instead.
                  </p>
                </section>

                <hr class="border-sidebar-border" />

                <section>
                  <header class="mb-3">
                    <h3 class="text-base font-semibold text-accent-foreground">Text-to-speech</h3>
                    <p class="text-xs text-foreground/80">
                      Spoken by Kaylin, the voice of Overlabels. Empty disables TTS for this alert.
                    </p>
                  </header>

                  <label for="tts_expression" class="mb-1 block text-xs font-medium text-accent-foreground">
                    Expression
                  </label>
                  <textarea
                    id="tts_expression"
                    v-model="form.tts_expression"
                    rows="3"
                    maxlength="2000"
                    class="input-border w-full font-mono text-sm"
                    placeholder="[[[event.user_name]]] just resubscribed for [[[event.streak_months|number]]] months!"
                  />
                  <div v-if="form.errors.tts_expression" class="mt-1 text-sm text-red-600">{{ form.errors.tts_expression }}</div>

                  <label for="tts_delay_ms" class="mt-3 mb-1 block text-xs font-medium text-accent-foreground">
                    Wait before speaking (ms)
                  </label>
                  <input
                    id="tts_delay_ms"
                    v-model.number="form.tts_delay_ms"
                    type="number"
                    min="0"
                    max="60000"
                    step="1"
                    class="input-border w-40"
                    placeholder="0"
                  />
                  <p class="mt-1 text-xs text-foreground/70">
                    Delay TTS until the alert sound finishes. 0 = speak immediately.
                  </p>
                  <div v-if="form.errors.tts_delay_ms" class="mt-1 text-sm text-red-600">{{ form.errors.tts_delay_ms }}</div>

                  <div v-if="template.template_tags && template.template_tags.length" class="mt-3">
                    <p class="mb-1.5 text-xs font-medium text-foreground/80">Tags from this alert</p>
                    <div class="flex flex-wrap gap-1.5">
                      <code
                        v-for="tag in template.template_tags"
                        :key="tag"
                        class="cursor-pointer rounded bg-muted px-1.5 py-0.5 text-xs hover:bg-muted/70"
                        @click="form.tts_expression = (form.tts_expression || '') + `[[[${tag}]]]`"
                      >[[[{{ tag }}]]]</code>
                    </div>
                  </div>

                  <details class="mt-3 text-xs text-foreground/80">
                    <summary class="cursor-pointer">How do I mute TTS?</summary>
                    <p class="mt-1 pl-4">
                      Add a boolean control with the key <code class="rounded bg-muted px-1">tts</code>
                      to any of your overlays (Controls tab). When it's off, TTS is skipped for all your
                      alerts. Turn it on or remove the control to resume.
                    </p>
                  </details>
                </section>

                <hr class="border-sidebar-border" />

                <section>
                  <header class="mb-3">
                    <h3 class="text-base font-semibold text-accent-foreground">Bot chat message</h3>
                    <p class="text-xs text-foreground/80">
                      Posted to your channel chat when this alert fires - handy when the alert sound is muted or missed.
                      Empty disables it. Requires the Overlabels bot to be enabled.
                    </p>
                  </header>

                  <label for="bot_message_expression" class="mb-1 block text-xs font-medium text-accent-foreground">
                    Message
                  </label>
                  <textarea
                    id="bot_message_expression"
                    v-model="form.bot_message_expression"
                    rows="3"
                    maxlength="500"
                    class="input-border w-full font-mono text-sm"
                    placeholder="[[[event.user_name]]] just resubscribed for [[[event.streak_months|number]]] months! Thank you!"
                  />
                  <div v-if="form.errors.bot_message_expression" class="mt-1 text-sm text-red-600">{{ form.errors.bot_message_expression }}</div>
                  <p class="mt-1 text-xs text-foreground/70">
                    Same tags as TTS. No <code class="rounded bg-muted px-1">[[[if]]]</code> logic - plain text and tags only. Capped at 500 characters (Twitch's chat limit).
                  </p>

                  <div v-if="template.template_tags && template.template_tags.length" class="mt-3">
                    <p class="mb-1.5 text-xs font-medium text-foreground/80">Tags from this alert</p>
                    <div class="flex flex-wrap gap-1.5">
                      <code
                        v-for="tag in template.template_tags"
                        :key="tag"
                        class="cursor-pointer rounded bg-muted px-1.5 py-0.5 text-xs hover:bg-muted/70"
                        @click="form.bot_message_expression = (form.bot_message_expression || '') + `[[[${tag}]]]`"
                      >[[[{{ tag }}]]]</code>
                    </div>
                  </div>
                </section>
              </div>

              <!-- Right column (1/3): saved sound library -->
              <aside class="space-y-2">
                <header class="flex items-baseline justify-between">
                  <h3 class="text-base font-semibold text-accent-foreground">Your sounds</h3>
                  <span class="text-xs text-foreground/70">
                    {{ freesoundLibrary.length }} / {{ FREESOUND_LIBRARY_CAP }}
                  </span>
                </header>

                <div v-if="freesoundLibrary.length === 0" class="rounded border border-sidebar-border bg-muted/30 p-3 text-xs text-foreground">
                  Browse Freesound to save sounds here. Saved sounds work across all your alerts.
                </div>

                <ul v-else class="space-y-1.5">
                  <li
                    v-for="sound in freesoundLibrary"
                    :key="sound.id"
                    class="rounded border border-sidebar-border bg-card p-2"
                    :class="{ 'ring-1 ring-violet-400': form.alert_sound_url === sound.preview_url }"
                  >
                    <div class="flex items-center gap-2">
                      <button
                        type="button"
                        class="cursor-pointer rounded-full bg-violet-500/20 p-1 text-violet-600 hover:bg-violet-500/30 dark:text-violet-300"
                        :title="libraryAuditioningId === sound.id ? 'Stop' : 'Play'"
                        @click="toggleLibraryAudition(sound)"
                      >
                        <Pause v-if="libraryAuditioningId === sound.id" class="h-3 w-3" />
                        <Play v-else class="h-3 w-3" />
                      </button>
                      <div class="flex-1 min-w-0">
                        <div class="truncate text-xs font-medium text-accent-foreground">{{ sound.name }}</div>
                        <div class="text-[11px] text-foreground/70 flex items-center gap-1.5">
                          <span class="truncate">{{ sound.author }}</span>
                          <span class="inline-flex items-center rounded bg-muted px-1 py-0 text-[9px] font-medium uppercase">
                            {{ licenseShort(sound.license) }}
                          </span>
                          <span v-if="sound.duration !== null">{{ formatDuration(sound.duration) }}</span>
                        </div>
                      </div>
                      <button
                        type="button"
                        class="cursor-pointer text-foreground/70 hover:text-red-500 p-1"
                        title="Remove from library"
                        @click="removeLibrarySound(sound)"
                      >
                        <Trash2 class="h-3.5 w-3.5" />
                      </button>
                    </div>
                    <button
                      v-if="form.alert_sound_url !== sound.preview_url"
                      type="button"
                      class="mt-1.5 w-full cursor-pointer rounded bg-violet-500/10 px-2 py-1 text-[11px] text-violet-700 hover:bg-violet-500/20 dark:text-violet-300"
                      @click="useLibrarySound(sound)"
                    >
                      Use for this alert
                    </button>
                    <div v-else class="mt-1.5 text-center text-[11px] text-violet-500">
                      In use for this alert
                    </div>
                  </li>
                </ul>
              </aside>
            </div>
          </div>
        </div>

        <BrowseFreesoundModal
          :show="freesoundModalOpen"
          :library-count="freesoundLibrary.length"
          :library-cap="FREESOUND_LIBRARY_CAP"
          @close="freesoundModalOpen = false"
          @saved="onFreesoundSaved"
        />

        <!-- Form Actions -->
        <div class="mt-6 flex justify-between">
          <Link
            v-if="!form.isDirty"
            :href="route('templates.show', template)"
            class="btn btn-cancel"
            title="Go back to overlay (keyboard shortcut: 's')"
          >← Back to Overlay</Link>
          <button
            v-else
            type="button"
            disabled
            class="btn btn-cancel opacity-50 cursor-not-allowed"
            title="Save your changes before leaving"
          >← Back to Overlay (unsaved changes)</button>
          <button type="submit" :disabled="form.processing || !form.isDirty" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>


    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false">
      <button
        v-if="showSuggestionLink"
        class="mt-2 btn hover:text-card"
        @click="showToast = false; suggestionModalOpen = true;"
      >
        Suggest integration
      </button>
    </RekaToast>
  </AppLayout>
</template>
