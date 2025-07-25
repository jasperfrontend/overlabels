<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { usePage } from '@inertiajs/vue3'
import { ref } from 'vue';
const user = usePage().props.auth.user
const showEmail = ref(false)
defineProps(['userName'])

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
];
</script>

<template>
  <Head title="Dashboard" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">

          <!-- <div class="p-2 overflow-y-scroll h-[300px]">
            <h1>Welcome, {{ user.name }}</h1>
            <img :src="user.avatar" width="64" />
            <pre>{{ user.twitch_data }}</pre>
          </div> -->

          <div class="m-5 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col items-center py-8" v-if="user">
              <img class="w-24 h-24 mb-3 rounded-full shadow-lg cursor-pointer hover:shadow-2xl transition-shadow ring-1 hover:ring-2 ring-purple-500 ring-offset-2" :src="user.avatar" :alt="user.name" @click="showEmail = !showEmail" />
              <h5 class="mb-1 text-xl font-medium text-gray-900 dark:text-white">{{ user.name }}</h5>
              <span class="text-sm text-gray-500 dark:text-gray-400" v-if="showEmail">{{ user.twitch_data.email }}</span>
            </div>
          </div>


        </div>
        <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
          <PlaceholderPattern />
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
