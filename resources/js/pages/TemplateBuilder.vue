<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { BreadcrumbItem } from '@/types'
import Heading from '@/components/Heading.vue';
import TemplateBuilderEditor from '@/components/TemplateBuilderEditor.vue'

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

withDefaults(defineProps<Props>(), {
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

</script>

<template>
  <Head title="Template Builder" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <!-- Header -->
      <Heading title="Template Builder" description="Create custom HTML/CSS templates for your overlays using our CodePen-style editor." />

      <div class="mt-4">
        <!-- Template Builder Editor -->
        <TemplateBuilderEditor
          :overlay-hash="overlayHash"
          :existing-template="existingTemplate"
          :available-tags="availableTags"
        />
      </div>

    </div>

  </AppLayout>
</template>
