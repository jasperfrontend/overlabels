
<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import type { BreadcrumbItem } from '@/types/index.js';
import Heading from '@/components/Heading.vue';

import { GitForkIcon, EyeIcon, SplitIcon } from 'lucide-vue-next';

const props = defineProps({
  template: Object,
  canEdit: Boolean,
});

const activeTab = ref('html');

const publicUrl = computed(() => {
  return `${window.location.origin}/overlay/${props.template.slug}/public`;
});

const authUrl = computed(() => {
  return `${window.location.origin}/overlay/${props.template.slug}#YOUR_TOKEN_HERE`;
});

const copyUrl = (url) => {
  navigator.clipboard.writeText(url);
  alert('URL copied to clipboard!');
};

const forkTemplate = async () => {
  if (!confirm('Fork this template?')) return;

  try {
    const response = await axios.post(route('templates.fork', props.template));
    router.visit(route('templates.show', response.data.template));
  } catch (error) {
    console.error('Failed to fork template:', error);
    alert('Failed to fork template');
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
    title: `Overlabels Overlay Editor: ${props.template.name}`,
    href: '/templates/*',
  },
];


</script>

<template>
  <Head :title="`Overlabels Overlay Editor: ${template.name}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex justify-between items-start">
          <div>
            <Heading :title="`${template.name}`" :description="`${template.description}`" />
          </div>
          <div class="flex space-x-2">
            <Link
              v-if="canEdit"
              :href="route('templates.edit', template)"
              class="px-4 py-2 bg-yellow-500/50 border border-yellow-500 text-accent-foreground rounded-md hover:bg-yellow-600/50 cursor-pointer"
            >
              Edit
            </Link>
            <button
              @click="forkTemplate"
              class="px-4 py-2 bg-green-500/50 border border-green-500 text-accent-foreground rounded-md hover:bg-green-600/50 cursor-pointer"
            >
              Fork
            </button>
            <button
              v-if="canEdit"
              @click="deleteTemplate"
              class="px-4 py-2 bg-red-500/50 border border-red-500 text-accent-foreground rounded-md hover:bg-red-600/50 cursor-pointer"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Meta Information -->
        <div class="mt-4 flex items-center space-x-6 text-sm ">
          <div class="flex items-center">
            <img
              :src="template.owner.avatar"
              :alt="template.owner.name"
              class="w-6 h-6 rounded-full mr-2"
            />
            <span>{{ template.owner.name }}</span>
          </div>
          <div>
            <EyeIcon class="w-4 h-4 mr-1 inline-block text-white/50" />
            <span class="font-medium">{{ template.view_count }}</span> <span class="ml-1">{{ template.view_count === 1 ? 'view' : 'views' }}</span>
          </div>
          <div>
            <GitForkIcon class="w-4 h-4 mr-1 inline-block text-white/50" />
            <span class="font-medium">{{ template.forks_count }}</span> <span class="ml-1">forks</span>
          </div>
          <div v-if="template.fork_parent">
            <SplitIcon class="w-4 h-4 mr-1 inline-block text-white/50" />
            Forked from
            <Link
              :href="route('templates.show', template.fork_parent)"
              class="text-gray-400/80 hover:text-gray-400 ml-1 p-1 px-2 rounded-full border border-dotted border-cyan-300/50"
            >
              {{ template.fork_parent.name }}
            </Link>
          </div>
          <div class="ml-auto">
            <span
              :class="template.is_public ? 'text-green-600' : 'text-orange-500'"
              class="font-medium"
            >
              {{ template.is_public ? 'Public' : 'Private' }}
            </span>
          </div>
        </div>
      </div>

      <!-- URLs Section -->
      <div class="rounded-lg border p-4 mb-6 bg-accent/15">
        <h3 v-if="template.is_public" class="font-semibold mb-3">Overlay URLs</h3>
        <h3 v-else class="font-semibold mb-3">Overlay URL</h3>
        <div class="space-y-3">
          <div v-if="template.is_public">
            <label class="text-sm ">Public URL (Preview without data):</label>
            <div class="flex items-center mt-1">
              <input
                :value="publicUrl"
                readonly
                class="flex-1 px-3 py-2 border rounded-l-md text-sm"
              />
              <button
                @click="copyUrl(publicUrl)"
                class="px-4 py-2 border border-l-0 bg-gray-600 text-white rounded-r-md hover:bg-gray-700 text-sm cursor-pointer"
              >
                Copy
              </button>
            </div>
          </div>
          <div>
            <label class="text-xs text-muted-foreground" for="auth-url">Authenticated URL (Use with your token):</label>
            <div class="flex items-center mt-1">
              <input
                id="auth-url"
                :value="authUrl"
                readonly
                class="flex-1 px-3 py-2 border rounded-l-md text-sm"
              />
              <button
                @click="copyUrl(authUrl)"
                class="px-4 py-2 border border-l-0 bg-gray-600 text-white rounded-r-md hover:bg-gray-700 text-sm cursor-pointer"
              >
                Copy
              </button>
            </div>
            <p class="text-xs text-gray-500 mt-3">
              Replace YOUR_TOKEN_HERE with your <Link class="text-cyan-400/80 hover:text-cyan-400" :href="route('tokens.index')"> actual access token</Link>
            </p>
          </div>
        </div>
      </div>

      <!-- Code Tabs -->
      <div class="border rounded-lg overflow-hidden">
        <div class="flex border-b">
          <button
            v-for="tab in ['html', 'css']"
            :key="tab"
            @click="activeTab = tab"
            :class="[
              'px-4 py-2 text-sm font-medium uppercase',
              activeTab === tab
                ? 'bg-gray-100 text-gray-900 border-b-2 border-blue-500'
                : ' hover:text-gray-300'
            ]"
          >
            {{ tab }}
          </button>
        </div>
        <div class="p-4 bg-sidebar-accent">
          <pre class="overflow-x-auto"><code class="text-sm">{{ template[activeTab] || 'No content' }}</code></pre>
        </div>
      </div>

      <!-- Template Tags Used -->
      <div v-if="template.template_tags && template.template_tags.length > 0" class="mt-6">
        <h3 class="font-semibold mb-2">Template Tags Used</h3>
        <div class="flex flex-wrap gap-2">
          <code
            v-for="tag in template.template_tags"
            :key="tag"
            class="px-2 py-1 bg-black/20 rounded text-sm"
          >
            {{ tag }}
          </code>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-6 flex justify-between">
        <Link
          :href="route('templates.index')"
        >
          ← Back to Templates
        </Link>
        <div class="space-x-3">
          <a
            :href="publicUrl"
            target="_blank"
            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50"
          >
            Preview Public
            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

