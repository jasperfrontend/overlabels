<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  foxes: {
    type: Object,
    required: true,
  }
})

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Foxes Gallery',
    href: '/foxes',
  },
];

// Helper function to get the best image URL
const getImageUrl = (fox: { cloudinary_url: any; api_url: any; local_file: any; }) => {
  return fox.cloudinary_url || fox.api_url || `/storage/${fox.local_file}`;
};
</script>

<template>
  <Head title="Foxes Gallery" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="mx-auto max-w-3xl py-8">
        <h1 class="text-2xl font-bold mb-6">Fox Gallery</h1>
        <!-- Pagination controls -->
        <div v-if="props.foxes.first_page_url || props.foxes.last_page_url" class="flex justify-center mt-6 mb-6 gap-2">
          <template v-for="(link, idx) in props.foxes.links">
            <button
              v-if="link.url"
              :key="idx"
              :disabled="link.active"
              @click="$inertia.visit(link.url)"
              v-html="link.label"
              class="px-3 cursor-pointer py-1 border rounded hover:bg-gray-100 dark:hover:bg-zinc-800"
              :class="{ 'font-bold text-blue-700': link.active, 'opacity-50 cursor-not-allowed': !link.url }"
            />
            <button v-else :key="`sep-${idx}`" class="px-2" v-html="link.label" />
          </template>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div
            v-for="fox in props.foxes.data"
            :key="fox.id"
            class="rounded-lg border p-2 bg-white dark:bg-zinc-900 shadow-sm flex flex-col items-center"
          >
            <div class="text-center">
            <a
              :href="getImageUrl(fox)"
              target="_blank"
              rel="noopener"
              class="text-xs text-blue-300 hover:underline break-all"
            >
            <img
              :src="getImageUrl(fox)"
              :alt="`Fox ${fox.id}`"
              class="aspect-video object-cover w-full rounded-md mb-2"
            />
            View image</a>
            </div>
            <div class="text-xs text-gray-500 mt-1">
              {{ (new Date(fox.created_at)).toLocaleString() }}
            </div>
          </div>
        </div>

        <!-- Pagination controls -->
        <div v-if="props.foxes.first_page_url || props.foxes.last_page_url" class="flex justify-center mt-6 gap-2">
          <template v-for="(link, idx) in props.foxes.links">
            <button
              v-if="link.url"
              :key="idx"
              :disabled="link.active"
              @click="$inertia.visit(link.url)"
              v-html="link.label"
              class="px-3 py-1 cursor-pointer border rounded hover:bg-gray-100 dark:hover:bg-zinc-800"
              :class="{ 'font-bold text-blue-700': link.active, 'opacity-50 cursor-not-allowed': !link.url }"
            />
            <span v-else :key="`sep-${idx}`" class="px-2" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>  
  </AppLayout>
</template>