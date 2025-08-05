<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from "@/components/RekaToast.vue"
import type { BreadcrumbItem } from '@/types/index.js';
import { GitForkIcon, EyeIcon, SplitIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps({
  template: Object,
  canEdit: Boolean,
});

const activeTab = ref('html');
// Toast state
const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
const showToast = ref(false);

const publicUrl = computed(() => {
  return `${window.location.origin}/overlay/${props.template?.slug}/public`;
});

const authUrl = computed(() => {
  return `${window.location.origin}/overlay/${props.template?.slug}#YOUR_TOKEN_HERE`;
});

const copyToClipboard = (url:string, shownValue:string) => {
  navigator.clipboard.writeText(url);
  showToast.value = false;
  toastMessage.value = `${shownValue} copied to clipboard!`;
  toastType.value = 'success';
  showToast.value = true;
};

const forkTemplate = async () => {
  if (!confirm('Fork this template?')) return;

  try {
    const response = await axios.post(route('templates.fork', props.template));
    router.visit(route('templates.show', response.data.template));
  } catch (error) {
    console.error('Failed to fork template:', error);
    showToast.value = true;
    toastMessage.value = 'Failed to fork template.';
    toastType.value = 'error';
  }
};

const deleteTemplate = () => {
  if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) return;

  router.delete(route('templates.destroy', props.template), {
    onSuccess: () => {
      // Will redirect to index
    },
  });
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
          </div>
          <div class="flex space-x-2">
            <a v-if="canEdit" :href="route('templates.edit', template)" class="btn btn-primary"> Edit </a>
            <button @click="forkTemplate" class="btn btn-warning">Fork</button>
            <button v-if="canEdit" @click="deleteTemplate" class="btn btn-danger">Delete</button>
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
            <span :class="props.template?.is_public ? 'text-green-600' : 'text-orange-500'" class="font-medium">
              {{ props.template?.is_public ? 'Public' : 'Private' }}
            </span>
          </div>
        </div>
      </div>

      <!-- URLs Section -->
      <div class="mb-6 rounded-lg border bg-accent/15 p-4">
        <h3 v-if="props.template?.is_public" class="mb-3 font-semibold">Overlay URLs</h3>
        <h3 v-else class="mb-3 font-semibold">Overlay URL</h3>
        <div class="space-y-3">
          <div v-if="props.template?.is_public">
            <label class="text-sm">Public URL (Preview without data):</label>
            <div class="mt-1 flex items-center">
              <input
                :value="publicUrl"
                readonly
                class="flex-1 rounded-l-md border px-3 py-2 text-sm text-muted-foreground outline-none focus:border-green-400 transition"
              />
              <button
                @click="copyToClipboard(publicUrl, 'Public URL')"
                class="btn btn-sm btn-primary rounded-l-md border border-l-0 px-4 py-2 text-sm hover:ring-0"
              >
                Copy
              </button>
            </div>
          </div>
          <div>
            <label class="text-xs text-muted-foreground" for="auth-url">Authenticated URL (Use with your token):</label>
            <div class="mt-1 flex items-center">
              <input
                id="auth-url"
                :value="authUrl"
                readonly
                class="flex-1 rounded-l-md border px-3 py-2 text-sm text-muted-foreground outline-none focus:border-green-400 transition"
              />
              <button
                @click="copyToClipboard(authUrl, 'Authenticated URL')"
                class="btn btn-sm btn-primary rounded-l-md border border-l-0 px-4 py-2 text-sm hover:ring-0"
              >
                Copy
              </button>
            </div>
            <p class="mt-3 text-xs text-gray-500">
              Replace YOUR_TOKEN_HERE with your
              <Link class="text-cyan-400/80 hover:text-cyan-400" :href="route('tokens.index')"> actual access token</Link>
            </p>
          </div>
        </div>
      </div>

      <!-- Code Tabs -->
      <div class="overflow-hidden rounded-lg border">
        <div class="flex border-b">
          <button
            v-for="tab in ['html', 'css']"
            :key="tab"
            @click="activeTab = tab"
            :class="[
              'cursor-pointer px-8 py-3 text-sm font-medium uppercase transition-colors',
              activeTab === tab ? 'border-b-1 border-accent-foreground/40 bg-gray-100/20 text-accent-foreground' : 'hover:text-gray-300',
            ]"
          >
            {{ tab }}
          </button>
        </div>
        <div class="bg-sidebar-accent p-4 relative">
          <pre class="overflow-auto max-h-[50vh]"><code class="text-sm">{{ props.template?.[activeTab] || 'No content' }}</code></pre>
          <button
            @click="copyToClipboard(props.template?.[activeTab], activeTab.toUpperCase())"
            class="absolute top-6 right-15 btn btn-sm btn-primary"
            >
            Copy
          </button>
        </div>
      </div>

      <!-- Template Tags Used -->
      <div v-if="props.template?.template_tags && props.template.template_tags.length > 0" class="mt-6">
        <h3 class="mb-2 font-semibold">Template Tags Used</h3>
        <div class="flex flex-wrap gap-2">
          <code v-for="tag in props.template.template_tags" :key="tag" class="rounded bg-black/20 px-2 py-1 text-sm">
            {{ tag }}
          </code>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-6 flex justify-between">
        <a :href="route('templates.index')" class="btn"> ← Back to Templates </a>
        <div class="space-x-3">
          <a :href="publicUrl" target="_blank" class="btn btn-primary">
            Preview Public
            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
              />
            </svg>
          </a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
