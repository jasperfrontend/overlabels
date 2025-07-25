<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { usePage } from '@inertiajs/vue3'
const user = usePage().props.auth.user

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

          <div class="p-2 overflow-y-scroll h-[300px]">
            <h1>Welcome, {{ user.name }}</h1>
            <img :src="user.avatar" width="64" />
            <a href="/auth/redirect/twitchextended">Give everything</a>
            <pre>{{ user.twitch_data }}</pre>
          </div>

          <p class="p-2">I really just set up a full Dashboard running on Vue3 with Laravel as backend in 1 click. Impressive.</p>
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
