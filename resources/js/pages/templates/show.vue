<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from "@/components/RekaToast.vue"
import TooltipBase from '@/components/TooltipBase.vue';
import type { BreadcrumbItem } from '@/types/index.js';
import { GitForkIcon, EyeIcon, SplitIcon, ExternalLinkIcon, PencilIcon, TrashIcon, CircleAlertIcon } from 'lucide-vue-next';
import { useTemplateActions } from '@/composables/useTemplateActions';

const props = defineProps({
  template: Object,
  canEdit: Boolean,
});

const activeTab = ref('html');

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
} = useTemplateActions(props.template);

// Local toast state for clipboard copy
const copyToClipboard = (url:string, shownValue:string) => {
  navigator.clipboard.writeText(url);
  showToast.value = false;
  toastMessage.value = `${shownValue} copied to clipboard!`;
  toastType.value = 'success';
  showToast.value = true;
};

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: `Overlabels Overlay Editor: ${props.template?.name}`,
    href: '/templates/*',
  },
];
</script>

<template>
  <Head :title="`Overlabels Overlay Editor: ${props.template?.name}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" />
    <div class="p-4">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-start justify-between">
          <div>
            <Heading :title="props.template?.name" :description="props.template?.description" />
            <!-- <pre>{{$page.props.template}}</pre>-->
          </div>
          <div class="flex space-x-2">
            <a
              v-if="$page.props.template?.is_public"
              @click.prevent="previewTemplate"
              href="#" class="btn btn-cancel">
              Preview
              <ExternalLinkIcon class="w-4 h-4 ml-2" />
            </a>

            <TooltipBase
              v-else
              tt-content-class="tooltip-base tooltip-content"

              align="start"
              side="left"
            >
              <template #trigger>
                <a
                  @click.prevent="previewTemplate"
                  href="#" class="btn btn-private">
                  Preview
                  <ExternalLinkIcon class="w-4 h-4 ml-2" />
                </a>
              </template>
              <template #content>
                <div class="space-y-1 text-sm">
                  <div class="flex items-center space-x-2">
                  <CircleAlertIcon class="w-6 h-6 mr-2 text-purple-400" />
                  <h3 class="text-xl font-bold">Don't forget</h3>
                  </div>
                  Add your token to the end of the URL like this:<br />
                  <code class="text-purple-400/80">/overlay/your-template-slug/#YOUR_TOKEN_HERE</code>
                </div>
              </template>
            </TooltipBase>

            <a v-if="canEdit" :href="route('templates.edit', template)" class="btn btn-secondary">
              Edit
              <PencilIcon class="w-4 h-4 ml-2" />
            </a>
            <button @click="forkTemplate" class="btn btn-warning">
              Fork
              <SplitIcon class="w-4 h-4 ml-2" />
            </button>
            <button v-if="canEdit" @click="deleteTemplate" class="btn btn-danger">
              Delete
              <TrashIcon class="w-4 h-4 ml-2" />
            </button>
          </div>
        </div>

        <!-- Meta Information -->
        <div class="mt-4 flex items-center space-x-6 text-sm text-muted-foreground">
          <div class="flex items-center">
            <img :src="props.template?.owner.avatar" :alt="props.template?.owner.name" class="mr-2 h-6 w-6 rounded-full" />
            <span>{{ props.template?.owner.name }}</span>
          </div>
          <div>
            <EyeIcon class="mr-1 inline-block h-4 w-4 text-white/50" />
            <span class="font-medium">{{ props.template?.view_count }}</span>
            <span class="ml-1">{{ props.template?.view_count === 1 ? 'view' : 'views' }}</span>
          </div>
          <div>
            <GitForkIcon class="mr-1 inline-block h-4 w-4 text-white/50" />
            <span class="font-medium">{{ props.template?.forks_count }}</span> <span class="ml-1">forks</span>
          </div>
          <div v-if="props.template?.fork_parent">
            <SplitIcon class="mr-1 inline-block h-4 w-4 text-white/50" />
            Forked from
            <Link
              :href="route('templates.show', props.template?.fork_parent)"
              class="ml-1 rounded-full border border-dotted border-cyan-300/50 p-1 px-2 text-gray-400/80 hover:text-gray-400"
            >
              {{ props.template?.fork_parent.name }}
            </Link>
          </div>
          <div>
            <TooltipBase
              tt-content-class="tooltip-base tooltip-content"
              align="center"
              side="top"
            >
              <template #trigger>
                <span :class="props.template?.is_public ? 'text-green-400' : 'text-violet-400'" class="font-medium">
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
      </div>

      <!-- URLs Section -->
      <div class="mb-6 rounded-sm border border-sidebar bg-sidebar-accent p-4">
        <h3 v-if="props.template?.is_public" class="mb-3 font-semibold">Overlay URLs</h3>
        <h3 v-else class="mb-3 font-semibold">Overlay URL</h3>
        <div class="space-y-3">
          <div v-if="props.template?.is_public">
            <label class="text-sm" for="public-url">Public URL</label>
            <div class="mt-1 flex items-center">
              <input
                :value="publicUrl"
                id="public-url"
                readonly
                class="peer flex-1 rounded-l-md border border-sidebar px-3 py-2 text-sm text-muted-foreground outline-none focus:border-gray-400 focus:text-accent-foreground transition"
              />
              <button
                @click="copyToClipboard(publicUrl, 'Public URL')"
                class="btn btn-sm peer-focus:bg-gray-400/20 hover:bg-gray-400/40 peer-focus:border-gray-400 rounded-r-md border border-sidebar border-l-0 px-4 py-2 text-sm hover:ring-0"
              >
                Copy
              </button>
            </div>
          </div>
          <div>
            <label class="text-sm" for="auth-url">Authenticated URL</label>
            <div class="mt-1 flex items-center">
              <input
                id="auth-url"
                :value="authUrl"
                readonly
                class="peer flex-1 rounded-l-md border border-sidebar px-3 py-2 text-sm text-muted-foreground outline-none focus:border-gray-400 focus:text-accent-foreground transition"
              />
              <button
                @click="copyToClipboard(authUrl, 'Authenticated URL')"
                class="btn btn-sm peer-focus:bg-gray-400/20 hover:bg-gray-400/40 peer-focus:border-gray-400 rounded-r-md border border-sidebar border-l-0 px-4 py-2 text-sm hover:ring-0"
              >
                Copy
              </button>
            </div>
            <p class="mt-3 text-xs text-accent-foreground">
              Replace <span class="text-accent-foreground"><code>YOUR_TOKEN_HERE</code></span> at the end of the URL with your own
              <Link :href="route('tokens.index')"
              class="text-violet-400 underline hover:no-underline"
              >access token</Link>
            </p>
          </div>
        </div>
      </div>

      <!-- Code Tabs -->
      <div class="overflow-hidden rounded-sm border border-sidebar">
        <div class="flex border-b">
          <button
            v-for="tab in ['head', 'html', 'css']"
            :key="tab"
            @click="activeTab = tab"
            :class="[
              'cursor-pointer px-8 py-2.5 text-sm font-medium transition-colors',
              activeTab === tab ? 'border-accent-foreground/40 bg-sidebar text-accent-foreground' : 'hover:text-gray-300',
            ]"
          >
            {{ tab }}
          </button>
        </div>
        <div class="bg-accent/20 text-gray-700 dark:text-accent-foreground p-4 relative">
          <pre class="overflow-auto max-h-[50vh]"><code class="text-sm">{{ props.template?.[activeTab] || 'No content' }}</code></pre>
          <button
            @click="copyToClipboard(props.template?.[activeTab], activeTab.toUpperCase())"
            class="absolute top-6 right-15 btn btn-sm btn-primary"
            >
            Copy {{activeTab.toUpperCase()}}
          </button>
        </div>
      </div>

      <!-- Template Tags Used -->
      <div v-if="props.template?.template_tags && props.template.template_tags.length > 0" class="mt-6">
        <h3 class="mb-2 font-semibold">Template Tags Used</h3>
        <div class="flex flex-wrap gap-2">
          <code v-for="tag in props.template.template_tags" :key="tag" class="btn btn-primary btn-sm btn-square btn-dead">
            {{ tag }}
          </code>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-6 flex justify-between">
        <a :href="route('templates.index')" class="btn btn-cancel"> ← Back to Templates </a>
      </div>
    </div>
  </AppLayout>
</template>
