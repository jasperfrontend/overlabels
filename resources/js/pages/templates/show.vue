<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import TooltipBase from '@/components/TooltipBase.vue';
import ControlsManager from '@/components/ControlsManager.vue';
import ControlPanel from '@/components/ControlPanel.vue';
import ForkImportWizard from '@/components/ForkImportWizard.vue';
import type { BreadcrumbItem, OverlayControl } from '@/types/index.js';
import {
  GitForkIcon,
  EyeIcon,
  SplitIcon,
  ExternalLinkIcon,
  PencilIcon,
  TrashIcon,
  CircleAlertIcon,
  SlidersHorizontalIcon,
  LightbulbIcon,
  SquarePenIcon,
} from 'lucide-vue-next';
import { useTemplateActions } from '@/composables/useTemplateActions';

const props = defineProps<{
  template: any;
  canEdit: boolean;
  controls?: OverlayControl[];
}>();

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

          <div class="flex space-x-2">
            <a v-if="$page.props.template?.is_public" @click.prevent="previewTemplate" href="#" class="btn btn-cancel">
              Preview
              <ExternalLinkIcon class="ml-2 h-4 w-4" />
            </a>

            <TooltipBase v-else tt-content-class="tooltip-base tooltip-content" align="start" side="left">
              <template #trigger>
                <a @click.prevent="previewTemplate" href="#" class="btn btn-private">
                  Preview
                  <ExternalLinkIcon class="ml-2 h-4 w-4" />
                </a>
              </template>
              <template #content>
                <div class="space-y-1 text-sm">
                  <div class="flex items-center space-x-2">
                    <CircleAlertIcon class="mr-2 h-6 w-6 text-purple-400" />
                    <h3 class="text-xl font-bold">Don't forget</h3>
                  </div>
                  Add your token to the end of the URL like this:<br />
                  <code class="text-purple-400/80">/overlay/your-template-slug/#YOUR_TOKEN_HERE</code>
                </div>
              </template>
            </TooltipBase>

            <a v-if="canEdit" :href="route('templates.edit', template)" class="btn btn-secondary">
              Edit
              <PencilIcon class="ml-2 h-4 w-4" />
            </a>
            <button @click="forkTemplate" class="btn btn-warning">
              Fork
              <SplitIcon class="ml-2 h-4 w-4" />
            </button>
            <button v-if="canEdit" @click="deleteTemplate" class="btn btn-danger">
              Delete
              <TrashIcon class="ml-2 h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- URLs Section -->
      <div class="mb-6 rounded-sm border border-sidebar bg-sidebar-accent p-4">
        <div class="space-y-3">
          <div v-if="props.template?.is_public">
            <label for="public-url">Overlay URL</label>
            <small class="text-xs cursor-pointer ml-2 bg-background p-1 rounded" @click="copyToClipboard(publicUrl, 'Public URL')">(Click to copy)</small>
            <div class="mt-4 flex items-center">
              <input
                :value="publicUrl"
                id="public-url"
                readonly
                class="peer flex-1 rounded-l-none border border-border p-1.5 text-sm text-muted-foreground transition outline-none focus:border-1 focus:border-gray-400 focus:text-accent-foreground"
              />
              <button
                @click="copyToClipboard(publicUrl, 'Public URL')"
                class="btn btn-sm rounded-none rounded-r-none border-1 border-l-0 border-sidebar p-1.5 px-4 text-sm peer-focus:border-gray-400 peer-focus:bg-gray-400/20 hover:bg-gray-400/40 hover:ring-0"
              >
                Copy
              </button>
            </div>
          </div>
          <p class="mt-3 text-xs text-muted-foreground">
            Replace <code class="rounded-sm bg-accent p-0.5 px-1">/public</code> at the end of this link with your own
            <a :href="route('tokens.index')" target="_blank" class="text-violet-400 hover:underline">access token</a> to enable the overlay.
          </p>
        </div>
      </div>

      <!-- Main Tabs (owner only) -->
      <div v-if="canEdit" class="mb-0 rounded-sm rounded-b-none border border-b-0 border-sidebar bg-sidebar-accent p-0 pb-0">
        <div class="flex border-b border-purple-600 dark:border-purple-400">
          <button
            @click="mainTab = 'overview'"
            :class="[
              'flex cursor-pointer items-center gap-1.5 px-4 py-2 text-sm font-medium transition-colors',
              mainTab === 'overview' ? 'bg-purple-600 text-accent dark:bg-purple-400' : 'text-accent-foreground',
            ]"
          >
            <LightbulbIcon class="h-4 w-4" />
            Details
          </button>
          <button
            @click="mainTab = 'controls'"
            :class="[
              'flex cursor-pointer items-center gap-1.5 px-5 py-2.5 text-sm font-medium transition-colors',
              mainTab === 'controls' ? 'bg-purple-600 text-accent dark:bg-purple-400' : 'text-accent-foreground',
            ]"
          >
            <SlidersHorizontalIcon class="h-4 w-4" />
            Controls
          </button>
          <button
            @click="mainTab = 'panel'"
            :class="[
              'flex cursor-pointer items-center gap-1.5 px-5 py-2.5 text-sm font-medium transition-colors',
              mainTab === 'panel' ? 'bg-purple-600 text-accent dark:bg-purple-400' : 'text-accent-foreground',
            ]"
          >
            <SquarePenIcon class="h-4 w-4" />
            Values
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
              <Link :href="route('templates.show', props.template?.fork_parent)" class="m-1">
                {{ props.template?.fork_parent.name }}
              </Link>
            </div>
            <div>
              <TooltipBase tt-content-class="tooltip-base tooltip-content" align="center" side="top">
                <template #trigger>
                  <span class="font-medium">
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
                v-for="tab in ['head', 'html', 'css']"
                :key="tab"
                @click="activeTab = tab"
                :class="[
                  'cursor-pointer px-5 py-3 text-left text-xs font-semibold tracking-widest uppercase transition-colors',
                  activeTab === tab
                    ? 'bg-background text-accent-foreground'
                    : 'text-sidebar-foreground/60 hover:bg-background/40 hover:text-sidebar-foreground',
                ]"
              >
                {{ tab }}
              </button>
            </div>
            <!-- Code panel -->
            <div class="relative flex-1 bg-background text-gray-700 dark:text-accent-foreground">
              <pre class="max-h-[50vh] overflow-auto p-4"><code class="text-sm">{{ props.template?.[activeTab] || 'No content' }}</code></pre>
              <button
                @click="copyToClipboard(props.template?.[activeTab], activeTab.toUpperCase())"
                class="btn btn-sm btn-primary absolute top-3 right-3"
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
