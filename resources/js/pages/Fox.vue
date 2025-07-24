<script setup lang="ts">
import RandomFox from '@/components/RandomFox.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3'

const newFox = () => {
  router.visit('/fox', {
    replace: true,
  })
}
const props = defineProps({
  foxPic: { type: String, required: true },
})

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'A random fox',
    href: '/fox',
  },
];

</script>

<template>
  <Head title="Dashboard" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
          
        <RandomFox :fox-pic="props.foxPic" />

        </div>
        <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border grid place-content-center">
          <p class="p-2">
            <a @click.prevent="newFox" href="/fox" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-100 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-500">New fox!</a>
          </p>
        </div>
        <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
          <PlaceholderPattern />
        </div>
      </div>
      <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
        <PlaceholderPattern />
      </div>
    </div>
  </AppLayout>
</template>