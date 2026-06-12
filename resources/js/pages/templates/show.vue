<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import RekaToast from '@/components/RekaToast.vue';
import AlertTargetOverlaySelector from '@/components/AlertTargetOverlaySelector.vue';
import AddToObsButton from '@/components/AddToObsButton.vue';
import ControlsManager from '@/components/ControlsManager.vue';
import TriggerManager from '@/components/TriggerManager.vue';
import { Dialog, DialogContent, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import ControlPanel from '@/components/ControlPanel.vue';
import ForkImportWizard from '@/components/ForkImportWizard.vue';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
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
  TargetIcon,
  ImageIcon,
  Zap,
} from '@lucide/vue';
import TemplateMeta from '@/components/TemplateMeta.vue';
import { useTemplateActions } from '@/composables/useTemplateActions';
import { captureListContext, deriveListContext } from '@/composables/useListContext';
import { VisuallyHidden } from 'reka-ui';
import { Badge } from '@/components/ui/badge';

const showPreview = ref(false);
const obsButton = ref<InstanceType<typeof AddToObsButton> | null>(null);

interface OverlayOption {
  id: number;
  name: string;
  slug: string;
}

interface TriggerData {
  eventTypes: Record<string, string>;
  externalEventTypes: Record<string, Record<string, string>>;
  connectedServices: string[];
  assigned: {
    twitch: Array<{ event_type: string; duration_ms: number; enabled: boolean }>;
    external: Array<{ service: string; event_type: string; duration_ms: number; enabled: boolean }>;
  };
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
  userLists?: Array<{ id: number; slug: string; label?: string | null; items_count: number; disabled: boolean }>;
  triggers?: TriggerData | null;
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
    tabs.push({ key: 'triggers', label: 'Triggers', icon: Zap });
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
const localControls = ref<OverlayControl[]>([...(props.controls ?? [])]);
const { register } = useKeyboardShortcuts();

onMounted(() => {
  register('edit-template', 'e', () => {
    if (props.template?.id) router.visit(route('templates.edit', props.template.id));
  }, { description: 'Edit this overlay' });

  for (let i = 1; i <= 6; i++) {
    register(`switch-tab-${i}`, `${i}`, () => {
      const tab = mainTabs.value[i - 1];
      if (tab) mainTab.value = tab.key;
    }, { description: `Switch to tab ${i}` });
  }

  register('add-to-obs', 'a', () => {
    obsButton.value?.generateOBSUrl();
  }, { description: 'Add to OBS' });
});

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
  forkWizardConnectedServices,
  openWizardFromPayload,
} = useTemplateActions(props.template, { redirectAfterDelete: () => listContext.href });

// Non-AJAX fork entry points (the Copy button on /overlay/{slug}/public,
// the dropdown items in TemplateCard/TemplateList/TemplateTable that use
// router.post) can't open the wizard inline because they trigger a full
// navigation to this page. The controller flashes the wizard payload onto
// the session and we pick it up here so controls still get imported.
const page = usePage();
onMounted(() => {
  const flash = (page.props as any)?.flash;
  if (flash?.fork_wizard) {
    openWizardFromPayload(flash.fork_wizard);
  }
});

// Local toast state for clipboard copy
const copyToClipboard = (url: string, shownValue: string) => {
  navigator.clipboard.writeText(url);
  showToast.value = false;
  toastMessage.value = `${shownValue} copied to clipboard!`;
  toastType.value = 'success';
  showToast.value = true;
};

// Freeze the list we came from for this template, so the breadcrumb and the
// post-delete redirect (see useTemplateActions) always agree, even after the
// index is re-filtered or restored via browser back/forward. When there's no
// recorded navigation (direct URL, fresh tab, straight after create), fall back
// to a crumb derived from the template's own type + ownership.
const listContext = captureListContext(
  props.template?.id,
  deriveListContext({ type: props.template?.type, ownedByMe: props.canEdit }),
);

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

            <Badge variant="default">
              {{ template?.is_public ? 'Public' : 'Private' }}
            </Badge>
          </div>
          <p v-if="template?.description" class="mt-1 text-sm text-muted-foreground">{{ template?.description }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-2">

          <a v-if="canEdit" :href="route('templates.edit', template)" class="btn btn-sm btn-primary" title="Edit this overlay (keyboard shortcut: 'e')">
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


      <!-- Main Tabs (owner only) -->
      <div v-if="canEdit" class=" bg-violet-300/20 dark:bg-violet-900/20">
        <div
          class="flex max-w-full touch-pan-x lg:touch-none overflow-auto">
          <button
            v-for="(tab, index) in mainTabs"
            :key="tab.key"
            type="button"
            @click="mainTab = tab.key"
            :class="[
                'flex cursor-pointer items-center gap-1.5 px-5 py-2.5 text-sm font-medium transition-colors hover:bg-background',
                mainTab === tab.key ? ' border-t-2 border-t-violet-400 bg-white dark:bg-violet-500/30 dark:hover-bg-violet-500 text-black dark:text-violet-300' : 'text-accent-foreground',
              ]"
          >
            <component :is="tab.icon" class="h-4 w-4" />
            {{ tab.label }}
          </button>
        </div>
      </div>

      <div class="mb-6 border border-sidebar-border bg-card">
        <!-- Controls Manager tab -->
        <div v-if="canEdit && mainTab === 'controls'" class="mb-6 p-4">
          <ControlsManager :template="template" :initial-controls="localControls"
                           :connected-services="connectedServices" :user-scoped-controls="userScopedControls"
                           :user-lists="userLists"
                           @change="localControls = $event" />
        </div>

        <!-- Control Panel tab -->
        <div v-if="canEdit && mainTab === 'panel'" class="mb-6 p-4">
          <ControlPanel :template="template" :controls="localControls" :is-live="isLive" />
        </div>

        <!-- Screenshot tab -->
        <div v-if="mainTab === 'screenshot'" class="mb-6 p-4">
          <div class="mt-1 pb-5 flex items-center text-sm gap-2">
            {{ props.template?.name }} - screenshot
          </div>
          <img
            :src="template.screenshot_url"
            alt="Overlay screenshot"
            class="max-h-[70vh] hover:opacity-70 transition-all border border-sidebar-border cursor-pointer"
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
              class="max-w-[50vw] object-contain"
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

        <!-- Triggers tab (alert templates, owner only) -->
        <div v-if="canEdit && mainTab === 'triggers'" class="mb-6 p-4">
          <TriggerManager
            v-if="triggers"
            :template-id="template.id"
            :triggers="triggers"
            @saved="() => { showToast = false; toastMessage = 'Triggers saved.'; toastType = 'success'; showToast = true; }"
            @error="(msg: string) => { showToast = false; toastMessage = msg; toastType = 'error'; showToast = true; }"
          />
        </div>

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
          <div v-show="showCode" class="flex min-h-[30vh] overflow-hidden">
            <!-- File tabs sidebar -->
            <div class="flex flex-col bg-sidebar text-sidebar-foreground">
              <button
                v-for="tab in editorTabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                :class="[
                    'flex cursor-pointer items-center gap-1.5 px-6 py-3 text-left text-xs uppercase',
                  activeTab === tab.key
                    ? 'bg-[#f8f8f8] dark:bg-[#160e21] text-accent-foreground'
                    : 'text-sidebar-foreground hover:bg-background/40 hover:text-foreground',
                ]"
              >
                <component :is="tab.icon" :class="tab.color" class="size-3.5" />
                <span>{{ tab.label }}</span>
              </button>
            </div>
            <!-- Code panel -->
            <div class="relative flex-1  text-gray-700 dark:text-accent-foreground">
              <pre class="h-[50vh] overflow-auto p-4 bg-white dark:bg-[#160e21]"><code
                class="text-sm text-muted-foreground">{{ props.template?.[activeTab] || 'No content' }}</code></pre>
              <button
                @click="copyToClipboard(props.template?.[activeTab], activeTab.toUpperCase())"
                class="btn btn-sm btn-chill absolute top-4 right-8 w-30"
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
