<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { BreadcrumbItem } from '@/types'
import TemplateBuilderEditor from '@/components/TemplateBuilderEditor.vue'

// Props from Inertia controller
interface Props {
  overlayHash?: {
    hash_key: string
    overlay_name: string
    slug: string
    id: number
  }
  existingTemplate: {
    html: string
    css: string
  }
  availableTags: Array<{
    tag_name: string
    display_name: string
    description: string
    data_type: string
    category: string
    sample_data?: string
  }>
  userOverlayHashes: Array<{
    id: number
    hash_key: string
    slug: string
    overlay_name: string
    created_at: string
  }>
}

const props = withDefaults(defineProps<Props>(), {
  existingTemplate: () => ({ html: '', css: '' }),
  availableTags: () => [],
  userOverlayHashes: () => []
})

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Template Builder',
    href: route('template.builder'),
  }
]

if (props.overlayHash) {
  breadcrumbs.push({
    title: props.overlayHash.overlay_name,
    href: route('template.builder', props.overlayHash.slug), // Now uses slug!
  })
}
</script>

<template>
  <Head title="Template Builder" />
  
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
          Template Builder
        </h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Create custom HTML/CSS templates for your overlays using our CodePen-style editor.
        </p>
      </div>

      <!-- Overlay Hash Selector -->
      <div v-if="!overlayHash && userOverlayHashes.length > 0" 
           class="bg-white dark:bg-gray-800 rounded-lg border p-6">
        <h2 class="text-lg font-medium mb-4">Select an Overlay to Edit</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <a v-for="hash in userOverlayHashes" 
             :key="hash.slug"
             :href="route('template.builder', hash.slug)"
             class="block p-4 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="font-medium">{{ hash.overlay_name }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ hash.slug }}</div>
            <div class="text-xs text-gray-400 mt-1">{{ hash.created_at }}</div>
          </a>
        </div>
      </div>

      <!-- Template Builder Editor -->
      <TemplateBuilderEditor
        v-if="overlayHash"
        :overlay-hash="overlayHash"
        :existing-template="existingTemplate"
        :available-tags="availableTags"
      />

      <!-- No overlays message -->
      <div v-if="!overlayHash && userOverlayHashes.length === 0"
           class="bg-white dark:bg-gray-800 rounded-lg border p-8 text-center">
        <h2 class="text-lg font-medium mb-2">No Overlay Hashes Found</h2>
        <p class="text-gray-600 dark:text-gray-400 mb-4">
          You need to create an overlay hash first before you can build templates.
        </p>
        <a :href="route('overlay.hashes.index')" 
           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          Create Overlay Hash
        </a>
      </div>
    </div>
  </AppLayout>
</template>