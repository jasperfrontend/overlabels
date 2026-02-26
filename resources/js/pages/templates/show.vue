<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import TooltipBase from '@/components/TooltipBase.vue';
import ControlsManager from '@/components/ControlsManager.vue';
import ControlPanel from '@/components/ControlPanel.vue';
import ForkImportWizard from '@/components/ForkImportWizard.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem, OverlayControl } from '@/types/index.js';
import {
  GitForkIcon,
  EyeIcon,
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
  ShieldCheck,
} from 'lucide-vue-next';
import { useTemplateActions } from '@/composables/useTemplateActions';

const props = defineProps<{
  template: any;
  canEdit: boolean;
  controls?: OverlayControl[];
}>();

const editorTabs = [
  { key: 'head', label: 'HEAD', icon: FileCode2Icon, color: 'text-pink-500 dark:text-pink-400' },
  { key: 'html', label: 'HTML', icon: CodeIcon, color: 'text-cyan-500 dark:text-cyan-400' },
  { key: 'css', label: 'CSS', icon: PaletteIcon, color: 'text-lime-500 dark:text-lime-400' },
];

const mainTabs = [
  { key: 'overview', label: 'Details', icon: LightbulbIcon },
  { key: 'controls', label: 'Controls', icon: SlidersHorizontalIcon },
  { key: 'panel', label: 'Values', icon: SquarePenIcon },
] as const;

const activeTab = ref('html');
const mainTab = ref<'overview' | 'controls' | 'panel'>('overview');
const localControls = ref<OverlayControl[]>([...(props.controls ?? [])]);

// Use the template actions composable
const {
  publicUrl,
  authUrl,
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
} = useTemplateActions(props.template);

// Local toast state for clipboard copy
const copyToClipboard = (url: string, shownValue: string) => {
  navigator.clipboard.writeText(url);
  showToast.value = false;
  toastMessage.value = `${shownValue} copied to clipboard!`;
  toastType.value = 'success';
  showToast.value = true;
};

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: `Editing: ${props.template?.name}`,
    href: '/templates/*',
  },
];

const forkTitle = computed(() => {
  return 'This Overlay has been forked from ' + props.template?.owner.name + "'s template" + ' "' + props.template?.fork_parent.name + '"';
});
</script>

<template>
  <Head :title="`Editing: ${props.template?.name}`" />
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
      <div class="mb-6">
        <div class="flex items-start justify-between">
          <div>
            <Heading :title="props.template?.name" :description="props.template?.description" description-class="text-sm text-muted-foreground" />
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
                <DropdownMenuItem v-if="!template?.is_public" class="pointer-events-none text-xs text-muted-foreground">
                  Add token to URL: #YOUR_TOKEN_HERE
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem @click="forkTemplate">
                  <SplitIcon class="mr-2 h-4 w-4" />
                  Fork
                </DropdownMenuItem>

                <DropdownMenuSeparator v-if="canEdit" />

                <DropdownMenuItem v-if="canEdit" class="text-destructive focus:text-destructive" @click="deleteTemplate">
                  <TrashIcon class="mr-2 h-4 w-4" />
                  Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>

      <!-- URLs Section -->
      <div class="mb-6 rounded-sm border border-sidebar bg-sidebar-accent p-4">
        <div class="space-y-3">
          <div v-if="props.template?.is_public">
            <label for="public-url">OBS Overlay URL</label>
            <small
              class="relative -top-0.5 ml-2 cursor-pointer rounded-full bg-background p-1 px-2 text-xs transition-colors hover:bg-violet-600 hover:text-accent dark:hover:bg-violet-400"
              @click="copyToClipboard(publicUrl, 'Public URL')"
              >Click to copy</small
            >
            <div class="mt-4 flex items-center">
              <input :value="publicUrl" id="public-url" readonly class="peer input-border" />
              <button
                @click="copyToClipboard(publicUrl, 'Public URL')"
                class="btn btn-sm rounded-none rounded-r-none border border-l-0 border-border p-2 px-4 text-sm peer-focus:border-gray-400 peer-focus:bg-gray-400/20 hover:bg-gray-400/40 hover:ring-0"
              >
                Copy
              </button>
            </div>
          </div>

          <div v-else>
            <div class="flex flex-row">
              <div>
                <label for="public-url"> OBS Overlay URL</label>
                <small
                  class="relative -top-0.5 ml-2 cursor-pointer rounded-full bg-background p-1 px-2 text-xs transition-colors hover:bg-violet-600 hover:text-accent dark:hover:bg-violet-400"
                  @click="copyToClipboard(authUrl, 'Public URL')"
                  >Click to copy</small
                >
              </div>
              <div class="ml-auto flex flex-row gap-2 text-sm text-violet-400"><ShieldCheck class="mt-0.5 h-4 w-4" /> Private Overlay</div>
            </div>
            <div class="mt-4 flex items-center">
              <input :value="authUrl" id="public-url" readonly class="peer input-border" />
              <button
                @click="copyToClipboard(authUrl, 'Public URL')"
                class="btn btn-sm rounded-none rounded-r-none border border-l-0 border-border p-2 px-4 text-sm peer-focus:border-gray-400 peer-focus:bg-gray-400/20 hover:bg-gray-400/40 hover:ring-0"
              >
                Copy
              </button>
            </div>
          </div>

          <p class="mt-4 text-sm text-muted-foreground">
            Before use in OBS, replace
            <code class="rounded-sm bg-accent p-0.5 px-1 text-accent-foreground" v-if="props.template?.is_public">public</code
            ><code v-else class="rounded-sm bg-accent p-0.5 px-1 text-accent-foreground">#YOUR_TOKEN_HERE</code> in this Private OBS Overlay URL with
            your own <a :href="route('tokens.index')" target="_blank" class="text-violet-400 hover:underline">access token</a> to activate the
            overlay:
            <TooltipBase tt-content-class="tooltip-base tooltip-content" align="top" side="top">
              <template #trigger>
                <small
                  class="relative cursor-help rounded-full bg-background p-1 px-2 text-xs text-accent-foreground transition-colors hover:bg-background/50"
                  >Example</small
                >
              </template>
              <template #content>
                <p class="text-sm">
                  <code class="rounded-sm bg-accent p-0.5 px-1 text-accent-foreground">{{ authUrl }}</code>
                </p>
              </template>
            </TooltipBase>
          </p>
        </div>
      </div>

      <!-- Main Tabs (owner only) -->
      <div v-if="canEdit" class="mb-0 rounded-sm rounded-b-none border border-b-0 border-sidebar bg-sidebar-accent p-0 pb-0">
        <div class="flex border-b border-violet-600 dark:border-violet-400">
          <button
            v-for="(tab, index) in mainTabs"
            :key="tab.key"
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

      <div class="mb-6 rounded-b-sm border border-t-0 border-sidebar bg-sidebar-accent p-4">
        <!-- Controls Manager tab -->
        <div v-if="canEdit && mainTab === 'controls'" class="mb-6">
          <ControlsManager :template="template" :initial-controls="localControls" @change="localControls = $event" />
        </div>

        <!-- Control Panel tab -->
        <div v-if="canEdit && mainTab === 'panel'" class="mb-6">
          <ControlPanel :template="template" :controls="localControls" />
        </div>

        <!-- Code Tabs (overview only) -->
        <div v-if="!canEdit || mainTab === 'overview'" class="overflow-hidden">
          <!-- Meta Information -->
          <div class="mt-2 mb-5.5 flex items-center space-x-6 text-sm text-muted-foreground">
            <div class="flex items-center">
              <img :src="props.template?.owner.avatar" :alt="props.template?.owner.name" class="mr-2 size-5 rounded-full" />
              <span>by {{ props.template?.owner.name }}</span>
            </div>
            <div>
              <EyeIcon class="mr-1 inline-block h-4 w-4 text-sidebar-foreground" />
              <span class="font-medium">{{ props.template?.view_count }}</span>
              <span class="ml-1">{{ props.template?.view_count === 1 ? 'view' : 'views' }}</span>
            </div>
            <div>
              <GitForkIcon class="mr-1 inline-block h-4 w-4 text-sidebar-foreground" />
              <span class="font-medium">{{ props.template?.forks_count }}</span> <span class="ml-1">forks</span>
            </div>
            <div v-if="props.template?.fork_parent">
              <SplitIcon class="mr-1 inline-block h-4 w-4 text-sidebar-foreground" />
              Forked from
              <Link :href="route('templates.show', props.template?.fork_parent)" class="m-1 text-foreground/70 hover:underline" :title="forkTitle">
                {{ props.template?.fork_parent.name }}
              </Link>
            </div>
            <div class="ml-auto">
              <TooltipBase tt-content-class="tooltip-base tooltip-content" align="center" side="right">
                <template #trigger>
                  <span class="font-medium">
                    <ShieldCheck class="inline-block h-4 w-4 text-sidebar-foreground" />
                    {{ props.template?.is_public ? 'Public' : 'Private' }}
                  </span>
                </template>
                <template #content>
                  <span class="text-xl font-bold">{{ props.template?.is_public ? 'Public' : 'Private' }} template</span><br />
                  Private templates can only be viewed by you.<br />Public templates can be viewed by anyone.
                </template>
              </TooltipBase>
            </div>
          </div>

          <div class="flex min-h-[30vh] overflow-hidden border border-border">
            <!-- File tabs sidebar -->
            <div class="flex flex-col border-r border-border bg-sidebar text-sidebar-foreground">
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
                {{ tab.label }}
              </button>
            </div>
            <!-- Code panel -->
            <div class="relative flex-1 bg-background text-gray-700 dark:text-accent-foreground">
              <pre class="h-[50vh] overflow-auto p-4"><code class="text-sm">{{ props.template?.[activeTab] || 'No content' }}</code></pre>
              <button
                @click="copyToClipboard(props.template?.[activeTab], activeTab.toUpperCase())"
                class="btn btn-sm btn-primary absolute top-4 right-8 w-30"
              >
                Copy {{ activeTab.toUpperCase() }}
              </button>
            </div>
          </div>

          <!-- Template Tags Used -->
          <div v-if="props.template?.template_tags && props.template.template_tags.length > 0" class="mt-8 mb-0">
            <h3 class="mb-4">Template Tags Used</h3>
            <div class="flex flex-wrap gap-2">
              <code v-for="tag in props.template.template_tags" :key="tag" class="btn btn-chill btn-xs btn-dead">
                {{ tag }}
              </code>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-6 flex justify-between">
        <a :href="route('templates.index')" class="btn btn-cancel"> ← Back to Templates </a>
      </div>
    </div>
  </AppLayout>
</template>
