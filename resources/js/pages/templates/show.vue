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
  InfoIcon,
  TargetIcon,
  ImageIcon
} from 'lucide-vue-next';
import TemplateMeta from '@/components/TemplateMeta.vue';
import { useTemplateActions } from '@/composables/useTemplateActions';
import { VisuallyHidden } from 'reka-ui';

const showPreview = ref(false);

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
const localControls = ref<OverlayControl[]>([...(props.controls ?? [])]);

// Use the template actions composable
const {
  authUrl,
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
  forkWizardSourceControls
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
    />

    <div class="p-4">
      <!-- Header -->
      <div class="mb-5 flex items-start justify-between gap-4">
        <div class="min-w-0">

          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-xl font-semibold tracking-tight">{{ template?.name }}</h2>
            <span
              class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-medium"
              :class="template?.is_public
                ? 'border-green-500/40 text-green-500 dark:text-green-400'
                : 'border-violet-500/40 text-violet-500 dark:text-violet-400'"
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

      <!-- OBS URL — compact row -->
      <div class="mb-5 flex items-center gap-2">
        <span class="shrink-0 text-xs font-medium uppercase tracking-wide text-muted-foreground">OBS URL</span>
        <div class="flex min-w-0 flex-1 items-center">
          <input
            :value="authUrl"
            readonly
            class="peer input-border min-w-0 flex-1 rounded-r-none"
          />
          <button
            @click="copyToClipboard(authUrl, 'OBS URL')"
            class="btn btn-sm rounded-none rounded-r-sm border border-l-0 border-border px-3 h-9.5 peer-focus:border-gray-400 peer-focus:bg-gray-400/20 hover:bg-gray-400/40"
            title="Copy URL"
          >
            <CopyIcon class="h-4 w-4" />
          </button>
        </div>
        <button
          @click="showOBSHelp = true"
          class="shrink-0 cursor-pointer rounded-full p-1 text-muted-foreground transition-colors hover:text-foreground"
          title="How to add this overlay to OBS"
        >
          <InfoIcon class="h-4 w-4" />
        </button>
      </div>

      <!-- OBS Setup Dialog -->
      <Dialog v-model:open="showOBSHelp">
        <DialogContent class="max-w-lg">
          <DialogHeader>
            <DialogTitle>Adding this overlay to OBS</DialogTitle>
          </DialogHeader>
          <div class="space-y-4 text-sm">
            <div
              class="rounded-sm border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-amber-600 dark:text-amber-400">
              <p class="font-semibold">Your personal access token is required</p>
              <p class="mt-1">Every overlay URL contains a <code class="rounded bg-black/10 px-1 dark:bg-white/10">#YOUR_TOKEN_HERE</code>
                placeholder. You must replace it with your real token before the overlay will work.</p>
            </div>

            <div>
              <p class="mb-1 font-medium">Where is my token?</p>
              <p class="text-muted-foreground">Your token was generated during onboarding and shown to you once — it is
                never stored in full and cannot be retrieved again. You can find the partial preview (first few
                characters) on your <a :href="route('tokens.index')" target="_blank"
                                       class="text-violet-400 hover:underline">Access Tokens page</a>. If you no longer
                have it, create a new token there.</p>
            </div>

            <div>
              <p class="mb-2 font-medium">Steps to add in OBS</p>
              <ol class="list-decimal space-y-1.5 pl-4 text-muted-foreground">
                <li>Copy the OBS URL above using the copy button.</li>
                <li>Replace <code class="rounded bg-accent px-1 text-accent-foreground">#YOUR_TOKEN_HERE</code> at the
                  end of the URL with your actual token. KEEP THE #!!
                </li>
                <li>In OBS, add a new <strong class="text-foreground">Browser Source</strong>.</li>
                <li>Paste the full URL (with your real token) into the URL field.</li>
                <li>Set <strong class="text-foreground">Width</strong> to <code
                  class="rounded bg-accent px-1 text-accent-foreground">1920</code> and <strong class="text-foreground">Height</strong>
                  to <code class="rounded bg-accent px-1 text-accent-foreground">1080</code> for full-screen coverage.
                </li>
                <li>Click <strong class="text-foreground">OK</strong>. Your overlay is now live!</li>
              </ol>
            </div>

            <div class="rounded-sm border border-red-500/30 bg-red-500/10 px-3 py-2 text-red-600 dark:text-red-400">
              <p class="font-semibold">Never share your token URL on stream</p>
              <p class="mt-1">Your token acts like a password. Anyone with it can trigger your overlays. Keep the URL
                out of screen recordings, screenshots, and live video.</p>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <!-- Main Tabs (owner only) -->
      <div v-if="canEdit" class="mb-0 rounded-sm rounded-b-none border border-b-0 border-sidebar bg-card p-0 pb-0">
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
          <!--          <button-->
          <!--            class="mb-0 flex w-full cursor-pointer items-center gap-2 rounded-sm border border-border bg-background px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-accent-foreground"-->
          <!--            :class="showCode ? 'border-b-0 rounded-b-none' : 'rounded-sm'"-->
          <!--            @click="showCode = !showCode"-->
          <!--          >-->
          <!--            <CodeIcon class="h-4 w-4 shrink-0" />-->
          <!--            <span>{{ showCode ? 'Hide source' : 'View source' }}</span>-->
          <!--            <ChevronDownIcon-->
          <!--              class="ml-auto h-4 w-4 shrink-0 transition-transform duration-200"-->
          <!--              :class="{ 'rotate-180': showCode }"-->
          <!--            />-->
          <!--          </button>-->

          <div v-show="showCode" class="flex min-h-[30vh] overflow-hidden border border-x-sidebar border-b-sidebar">
            <!-- File tabs sidebar -->
            <div class="flex flex-col border-r border-sidebar bg-sidebar text-sidebar-foreground">
              <button
                v-for="tab in editorTabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                :class="[
                  'flex cursor-pointer items-center gap-1.5 px-5 py-3 text-left text-xs uppercase transition-colors',
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
          <div class="mt-8">
            <TemplateMeta
              :created-at="template?.created_at"
              :updated-at="template?.updated_at"
              :view-count="template?.view_count"
              :fork-count="template?.fork_count"
              :template-tags="template?.template_tags"
              :fork-parent="template?.fork_parent"
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
