<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { BreadcrumbItem } from '@/types'
import TemplateBuilderEditor from '@/components/TemplateBuilderEditor.vue'
import Heading from '@/components/Heading.vue';

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
    <div class="p-4">
      <!-- Header -->
      <Heading title="Template Builder" description="Create custom HTML/CSS templates for your overlays using our CodePen-style editor." />


      <!-- Overlay Hash Selector -->
      <div v-if="!overlayHash && userOverlayHashes.length > 0"
           class="mt-6">
        <h2 class="text-lg font-medium mb-4">Select an overlay to edit</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <a v-for="hash in userOverlayHashes"
             :key="hash.slug"
             :href="route('template.builder', hash.slug)"
             class="block text-left cursor-pointer rounded-2xl border bg-accent/20 p-4 shadow backdrop-blur-sm transition hover:bg-accent/50 hover:ring-2 hover:ring-gray-300 active:bg-accent dark:hover:ring-gray-700">
            <div class="font-medium">{{ hash.overlay_name }}</div>
            <div class="text-sm pt-3 text-gray-500 dark:text-gray-400">{{ hash.slug }}</div>
            <div class="text-xs text-gray-400/60 pt-2">{{ hash.created_at }}</div>
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
