<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

interface HelpCard {
  title: string;
  description: string;
  href: string;
  icon: Component;
}

defineProps<{ pages: HelpCard[] }>();
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <Link
      v-for="page in pages"
      :key="page.href"
      :href="page.href"
      class="group flex gap-4 rounded-lg border border-border p-5 transition-colors hover:border-violet-400/50 hover:bg-sidebar-accent"
    >
      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-violet-500/10 text-violet-400"
      >
        <component :is="page.icon" class="h-5 w-5" />
      </div>
      <div>
        <h2 class="font-semibold text-foreground group-hover:text-violet-400">{{ page.title }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">{{ page.description }}</p>
      </div>
    </Link>
  </div>
</template>
