<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import RekaToast from '@/components/RekaToast.vue';
import AlertTargetOverlaySelector from '@/components/AlertTargetOverlaySelector.vue';
import ControlsManager from '@/components/ControlsManager.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import ControlPanel from '@/components/ControlPanel.vue';
import ForkImportWizard from '@/components/ForkImportWizard.vue';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem, OverlayControl } from '@/types/index.js';
import {
  SplitIcon,
  ExternalLinkIcon,
  PencilIcon,
  TrashIcon,
  MoreVertical,
  SlidersHorizontalIcon,
  LightbulbIcon,
  SquarePenIcon,
  FileCode2Icon,
  CodeIcon,
  PaletteIcon,
  CopyIcon,
  TargetIcon,
  ImageIcon,
  Loader2,
  CheckIcon
} from 'lucide-vue-next';
import TemplateMeta from '@/components/TemplateMeta.vue';
import { useTemplateActions } from '@/composables/useTemplateActions';
import { useLinkWarning } from '@/composables/useLinkWarning';
import { VisuallyHidden } from 'reka-ui';

const showPreview = ref(false);
const showObsScreenshot = ref(false);

const { triggerLinkWarning } = useLinkWarning();

interface OverlayOption {
  id: number;
  name: string;
  slug: string;
}

const props = defineProps<{
  template: any;
  canEdit: boolean;
  controls?: OverlayControl[];
  connectedServices?: string[];
  isLive?: boolean;
  staticOverlays?: OverlayOption[];
  targetStaticOverlayIds?: number[];
  userScopedControls?: OverlayControl[];
}>();

const editorTabs = [
  { key: 'head', label: 'HEAD', icon: FileCode2Icon, color: 'text-pink-500 dark:text-pink-400' },
  { key: 'html', label: 'BODY', icon: CodeIcon, color: 'text-cyan-500 dark:text-cyan-400' },
  { key: 'css', label: 'CSS', icon: PaletteIcon, color: 'text-lime-500 dark:text-lime-400' }
];

const mainTabs = computed(() => {
  const tabs: Array<{ key: string; label: string; icon: any }> = [
    { key: 'overview', label: 'Details', icon: LightbulbIcon },
    { key: 'controls', label: 'Controls', icon: SlidersHorizontalIcon },
    { key: 'panel', label: 'Values', icon: SquarePenIcon }
  ];
  if (props.template?.screenshot_url) {
    tabs.push({ key: 'screenshot', label: 'Screenshot', icon: ImageIcon });
  }
  if (props.canEdit && props.template?.type === 'alert') {
    tabs.push({ key: 'targeting', label: 'Targeting', icon: TargetIcon });
  }
  return tabs;
});

const activeTab = ref('html');
const mainTab = ref<string>('overview');

const localTargetOverlayIds = ref<number[]>([...(props.targetStaticOverlayIds ?? [])]);
const obsIconSVG = '<svg role="img" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12,24C5.383,24,0,18.617,0,12S5.383,0,12,0s12,5.383,12,12S18.617,24,12,24z M12,1.109 C5.995,1.109,1.11,5.995,1.11,12C1.11,18.005,5.995,22.89,12,22.89S22.89,18.005,22.89,12C22.89,5.995,18.005,1.109,12,1.109z M6.182,5.99c0.352-1.698,1.503-3.229,3.05-3.996c-0.269,0.273-0.595,0.483-0.844,0.78c-1.02,1.1-1.48,2.692-1.199,4.156 c0.355,2.235,2.455,4.06,4.732,4.028c1.765,0.079,3.485-0.937,4.348-2.468c1.848,0.063,3.645,1.017,4.7,2.548 c0.54,0.799,0.962,1.736,0.991,2.711c-0.342-1.295-1.202-2.446-2.375-3.095c-1.135-0.639-2.529-0.802-3.772-0.425 c-1.56,0.448-2.849,1.723-3.293,3.293c-0.377,1.25-0.216,2.628,0.377,3.772c-0.825,1.429-2.315,2.449-3.932,2.756 c-1.244,0.261-2.551,0.059-3.709-0.464c1.036,0.302,2.161,0.355,3.191-0.011c1.381-0.457,2.522-1.567,3.024-2.935 c0.556-1.49,0.345-3.261-0.591-4.54c-0.7-1.007-1.803-1.717-3.002-1.969c-0.38-0.068-0.764-0.098-1.148-0.134 c-0.611-1.231-0.834-2.66-0.528-3.996L6.182,5.99z"/></svg>';

function saveTargeting() {
  router.put(
    route('templates.target-overlays', props.template),
    { overlay_ids: localTargetOverlayIds.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        showToast.value = false;
        toastMessage.value = 'Targeting settings saved.';
        toastType.value = 'success';
        showToast.value = true;
      },
      onError: () => {
        showToast.value = false;
        toastMessage.value = 'Failed to save targeting settings.';
        toastType.value = 'error';
        showToast.value = true;
      }
    }
  );
}

const showCode = ref(true);
const showOBSHelp = ref(false);
const obsGenerating = ref(false);
const obsGeneratedUrl = ref<string | null>(null);
const obsUrlCopied = ref(false);
const obsConfirmedCopied = ref(false);
const obsError = ref('');
const localControls = ref<OverlayControl[]>([...(props.controls ?? [])]);

function generateOBSUrl() {
  triggerLinkWarning(async () => {
    showOBSHelp.value = true;
    obsGenerating.value = true;
    obsGeneratedUrl.value = null;
    obsUrlCopied.value = false;
    obsConfirmedCopied.value = false;
    obsError.value = '';
    try {
      const url = route('tokens.store');
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
        },
        body: JSON.stringify({ name: `OBS - ${props.template?.name ?? 'Overlay'}` })
      });

      if (!response.ok) {
        const errorBody = await response.text();
        console.error('[OBS URL] Token generation failed', { status: response.status, body: errorBody, url });
        obsError.value = 'Failed to generate a token. Please try again.';
        return;
      }

      const data = await response.json();
      obsGeneratedUrl.value = `${window.location.origin}/overlay/${props.template?.slug}/#${data.plain_token}`;
    } catch (e) {
      console.error('[OBS URL]', e);
      obsError.value = 'Failed to generate a token. Please try again.';
    } finally {
      obsGenerating.value = false;
    }
  }, 'The next screen shows your personal token and you should NOT show this on stream.\n\nIf you are currently streaming, move this screen away from your stream before continuing.');
}

function copyOBSUrl() {
  if (!obsGeneratedUrl.value) return;
  navigator.clipboard.writeText(obsGeneratedUrl.value);
  obsUrlCopied.value = true;
  setTimeout(() => {
    obsUrlCopied.value = false;
  }, 5000);
}

// Use the template actions composable
const {
  canDelete,
  previewTemplate,
  forkTemplate,
  deleteTemplate,
  toastMessage,
  toastType,
  showToast,
  forkWizardOpen,
  forkWizardTemplateId,
  forkWizardTemplateSlug,
  forkWizardSourceControls,
  forkWizardRequiredServices,
  forkWizardConnectedServices
} = useTemplateActions(props.template);

// Local toast state for clipboard copy
const copyToClipboard = (url: string, shownValue: string) => {
  navigator.clipboard.writeText(url);
  showToast.value = false;
  toastMessage.value = `${shownValue} copied to clipboard!`;
  toastType.value = 'success';
  showToast.value = true;
};

function getListContext(): { title: string; href: string } {
  try {
    const stored = sessionStorage.getItem('templates_list_context');
    if (stored) return JSON.parse(stored);
  } catch { /* ignore */
  }
  return { title: 'My overlays', href: route('templates.index') };
}

const listContext = getListContext();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: listContext.title,
    href: listContext.href
  },
  {
    title: props.template?.name || 'Template',
    href: `/templates/${props.template?.id}`
  }
];

</script>

<template>
  <Head :title="`Viewing: ${props.template?.name}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />
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
      <div class="mb-5 flex items-start justify-between gap-4">
        <div class="min-w-0">

          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-xl font-semibold tracking-tight">{{ template?.name }}</h2>
            <span
              class="shrink-0 rounded-sm px-2 py-0.5 text-xs font-medium"
              :class="template?.is_public
                ? 'bg-green-500/10 text-green-500 dark:text-green-400'
                : 'bg-violet-500/10 text-violet-500 dark:text-violet-400'"
            >
              {{ template?.is_public ? 'Public' : 'Private' }}
            </span>
          </div>
          <p v-if="template?.description" class="mt-1 text-sm text-muted-foreground">{{ template?.description }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
          <a v-if="canEdit" :href="route('templates.edit', template)" class="btn btn-sm btn-primary">
            <PencilIcon class="mr-2 h-4 w-4" />
            Edit
          </a>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button class="btn btn-sm btn-secondary px-2" title="More actions">
                <MoreVertical class="h-4 w-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
              <DropdownMenuItem @click="previewTemplate">
                <ExternalLinkIcon class="mr-2 h-4 w-4" />
                Preview
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="forkTemplate">
                <SplitIcon class="mr-2 h-4 w-4" />
                Copy
              </DropdownMenuItem>
              <DropdownMenuSeparator v-if="canEdit" />
              <DropdownMenuItem v-if="canEdit && canDelete" class="text-destructive focus:text-destructive"
                                @click="deleteTemplate">
                <TrashIcon class="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
              <DropdownMenuItem v-else-if="canEdit" disabled class="text-muted-foreground text-xs">
                Part of a kit - cannot delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <!-- Add to OBS -->
      <div class="mb-5">
        <button
          @click="generateOBSUrl()"
          class="h-9.5 flex gap-2 shrink-0 w-50 btn btn-xl border-0 bg-teal-400 hover:bg-teal-500 text-black"
          title="Add this overlay to your OBS"
        >
          <span class="shrink-0 text-sm font-medium uppercase tracking-wide flex items-center gap-1.5">
            <span v-html="obsIconSVG" class="size-4 inline-block" />
            Add to OBS
          </span>
        </button>
      </div>

      <!-- OBS URL Dialog -->
      <Dialog :open="showOBSHelp">
        <DialogContent class="max-w-lg" @escape-key-down.prevent @pointer-down-outside.prevent @interact-outside.prevent>
          <DialogHeader>
            <DialogTitle>Add this overlay to OBS</DialogTitle>
          </DialogHeader>

          <!-- Loading state -->
          <div v-if="obsGenerating" class="flex items-center justify-center gap-3 py-8">
            <Loader2 class="h-5 w-5 animate-spin text-violet-400" />
            <span class="text-sm">Generating your secure URL...</span>
          </div>

          <!-- Error state -->
          <div v-else-if="obsError" class="space-y-4 text-sm">
            <div class="rounded-sm border border-red-500/30 bg-red-500/10 px-3 py-2 text-red-600 dark:text-red-400">
              <p>{{ obsError }}</p>
            </div>
          </div>

          <!-- URL generated -->
          <div v-else-if="obsGeneratedUrl" class="space-y-4 text-sm">
            <p class="text-green-400" v-if="obsUrlCopied">URL Copied to clipboard</p>
            <p class="text-foreground" v-else>Click to copy this URL, then paste it into a Browser Source in OBS.</p>

            <button
              class="flex items-center cursor-pointer gap-2 rounded-lg border
              border-green-500/20 bg-green-400/10 dark:bg-green-950/10 p-3
              font-mono text-xs break-all transition-colors hover:bg-green-400/20
              active:ring active:ring-green-500"
              :class="obsUrlCopied ? 'border-green-500/40 ring ring-green-500/80' : 'border-green-500/20'"
              @click="copyOBSUrl"
            >
              <span class="flex-1 text-green-600 dark:text-green-300/80">{{ obsGeneratedUrl }}</span>
              <span class="shrink-0 rounded-md p-2 transition hover:bg-green-500/10" title="Copy URL">
                <CheckIcon v-if="obsUrlCopied" class="h-4 w-4 text-green-400" />
                <CopyIcon v-else class="h-4 w-4 text-green-400" />
              </span>
            </button>

            <div>
              <p class="mb-2 font-medium">Steps</p>
              <ol class="list-decimal space-y-1.5 pl-4 text-foreground">
                <li><strong>Click the box above</strong> to copy the overlay URL.</li>
                <li>In OBS, add a new <strong>Browser Source</strong>.</li>
                <li>Paste the URL into the URL field.</li>
                <li>Set <strong>Width</strong> to <code
                  class="rounded bg-accent px-1 text-accent-foreground">1920</code>
                  and <strong>Height</strong> to <code class="rounded bg-accent px-1 text-accent-foreground">1080</code>.
                </li>
                <li>Leave "Shutdown source when not visible" and "Refresh browser source when scene becomes active" both <strong>unchecked</strong>.</li>
                <li>Click <strong>OK</strong>. Right-click the source and choose <strong>Transform &gt; Fit to
                  screen</strong>
                  (or press <code class="rounded bg-accent px-1 text-accent-foreground">Ctrl+F</code>) to make it
                  full-screen.
                </li>
                <li>Your overlay is live!</li>
              </ol>
              <p class="mt-2 text-foreground">
                Your OBS Browser Source settings should look like
                <button type="button" class="underline text-violet-400 hover:text-violet-300 cursor-pointer" @click="showObsScreenshot = true">this example</button>.
              </p>
            </div>

            <div class="h-px bg-violet-300"></div>

            <div class="flex flex-col bg-violet-600/10 p-2 border rounded-sm border-violet-500/50 gap-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="obsConfirmedCopied" type="checkbox" />
                <span class="text-sm text-foreground">I have copied this overlay to my OBS as a Browser Source</span>
              </label>

              <button
                v-if="obsConfirmedCopied"
                type="button"
                class="btn btn-primary mt-2"
                @click="showOBSHelp = false"
              >
                Close this screen and continue
              </button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <!-- OBS Settings Screenshot Modal -->
      <Dialog :open="showObsScreenshot" @update:open="showObsScreenshot = $event">
        <DialogContent class="max-w-[90vw] max-h-[90vh] w-auto p-2 sm:max-w-[90vw]">
          <VisuallyHidden>
            <DialogTitle>OBS Browser Source settings example</DialogTitle>
          </VisuallyHidden>
          <img
            src="/obs-brower-source-settings.png"
            alt="OBS Browser Source settings example"
            class="max-w-full max-h-[80vh] rounded object-contain"
          />
          <DialogFooter>
            <button type="button" class="ml-auto btn btn-chill" @click="showObsScreenshot = false">Close</button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Main Tabs (owner only) -->
      <div v-if="canEdit" class="mb-0 rounded-sm rounded-b-none border border-b-0 border-sidebar bg-sidebar-accent p-0 pb-0">
        <div
          class="flex border-b border-violet-600 dark:border-violet-400 max-w-full touch-pan-x lg:touch-none overflow-auto">
          <button
            v-for="(tab, index) in mainTabs"
            :key="tab.key"
            @click="mainTab = tab.key"
            :class="[
              'flex cursor-pointer items-center gap-1.5 px-3 py-2 lg:px-5 lg:py-2.5 text-sm font-medium transition-colors hover:bg-background',
              index === 0 && 'rounded-tl-sm',
              mainTab === tab.key ? 'bg-violet-400 hover:bg-violet-500 text-black' : 'text-accent-foreground',
            ]"
          >
            <component :is="tab.icon" class="h-4 w-4" />
            {{ tab.label }}
          </button>
        </div>
      </div>

      <div class="mb-6 rounded-b-sm border border-t-0 border-sidebar bg-card">
        <!-- Controls Manager tab -->
        <div v-if="canEdit && mainTab === 'controls'" class="mb-6 p-4">
          <ControlsManager :template="template" :initial-controls="localControls"
                           :connected-services="connectedServices" :user-scoped-controls="userScopedControls"
                           @change="localControls = $event" />
        </div>

        <!-- Control Panel tab -->
        <div v-if="canEdit && mainTab === 'panel'" class="mb-6 p-4">
          <ControlPanel :template="template" :controls="localControls" :is-live="isLive" />
        </div>

        <!-- Screenshot tab -->
        <div v-if="mainTab === 'screenshot'" class="mb-6 p-4">
          <img
            :src="template.screenshot_url"
            alt="Overlay screenshot"
            class="max-h-[70vh] hover:opacity-70 transition-all rounded border border-sidebar cursor-pointer"
            @click="showPreview = true"
          />
        </div>

        <Dialog :open="showPreview" @update:open="showPreview = $event">
          <DialogContent class="max-w-[90vw] max-h-[90vh] w-auto p-2 sm:max-w-[90vw]">
            <VisuallyHidden>
              <DialogTitle>Screenshot preview</DialogTitle>
            </VisuallyHidden>
            <img
              v-if="template.screenshot_url"
              :src="template.screenshot_url"
              alt="Screenshot preview"
              class="max-w-[50vw] rounded object-contain"
            />
            <DialogFooter>
              <div class="flex w-full items-center justify-between gap-2">
                <div class="text-sm text-muted-foreground">
                  Screenshot: {{ props.template?.name }}
                </div>
                <button type="button" class="ml-auto btn btn-chill" @click="showPreview = false">Close</button>
              </div>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- Targeting tab (alert templates, owner only) -->
        <div v-if="canEdit && mainTab === 'targeting'" class="mb-6 lg:max-w-3xl p-4">
          <AlertTargetOverlaySelector
            v-model="localTargetOverlayIds"
            :static-overlays="staticOverlays ?? []"
          />
          <button type="button" @click="saveTargeting" class="btn btn-primary mt-4">Save targeting</button>
        </div>

        <!-- Code Tabs (overview only) -->
        <div v-if="!canEdit || mainTab === 'overview'" class="overflow-hidden">
          <div v-show="showCode" class="flex min-h-[30vh] overflow-hidden border border-x-sidebar border-b-sidebar">
            <!-- File tabs sidebar -->
            <div class="flex flex-col border-r border-sidebar bg-sidebar text-sidebar-foreground">
              <button
                v-for="tab in editorTabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                :class="[
                    'flex cursor-pointer items-center gap-1.5 px-6 py-3 text-left text-xs uppercase transition-colors',
                  activeTab === tab.key
                    ? 'bg-background text-accent-foreground'
                    : 'text-sidebar-foreground/60 hover:bg-background/40 hover:text-sidebar-foreground',
                ]"
              >
                <component :is="tab.icon" :class="tab.color" class="size-3.5" />
                <span>{{ tab.label }}</span>
              </button>
            </div>
            <!-- Code panel -->
            <div class="relative flex-1 bg-background text-gray-700 dark:text-accent-foreground">
              <pre class="h-[50vh] overflow-auto p-4"><code
                class="text-sm">{{ props.template?.[activeTab] || 'No content' }}</code></pre>
              <button
                @click="copyToClipboard(props.template?.[activeTab], activeTab.toUpperCase())"
                class="btn btn-sm btn-primary absolute top-4 right-8 w-30"
              >
                Copy {{ activeTab.toUpperCase() }}
              </button>
            </div>
          </div>

          <!-- Meta + Template Tags -->
          <div>
            <TemplateMeta
              :created-at="template?.created_at"
              :updated-at="template?.updated_at"
              :view-count="template?.view_count"
              :fork-count="template?.fork_count"
              :template-tags="template?.template_tags"
              :fork-parent="template?.fork_parent"
              :slug="template?.slug"
              :owner="template?.owner.name"
            />
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-6 flex justify-between">
        <a :href="route('templates.index')" class="btn btn-cancel"> ← All Overlays </a>
      </div>
    </div>
  </AppLayout>
</template>
