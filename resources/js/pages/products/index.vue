<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bot, Check, Package, Sparkles } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import type { AppPageProps } from '@/types';

interface ProductSummary {
  slug: string;
  name: string;
  description: string;
  requires_bot: boolean;
  hero: string | null;
  installed: boolean;
}

const props = defineProps<{
  products: ProductSummary[];
}>();

const page = usePage<AppPageProps>();
const isAuthed = computed(() => !!page.props.auth?.user);
</script>

<template>
  <Head>
    <title>Products</title>
    <meta name="description" content="Things viewers can do in your chat and see on your stream. Installed in one click, set up from one page." />
  </Head>

  <!-- Public page: renders outside AppLayout like the public overlay preview,
       so a visitor without an account can read it. -->
  <div class="min-h-screen bg-background text-foreground">
    <div class="mx-auto max-w-7xl p-4 lg:p-6">
      <div class="mb-6 flex items-center justify-between">
        <a href="/" class="flex cursor-pointer items-center gap-2 text-sm font-bold tracking-tight text-foreground hover:text-violet-400">
          <img src="/favicon-light.svg" alt="" class="h-6 w-6 dark:hidden" />
          <img src="/favicon.png" alt="" class="hidden h-6 w-6 dark:block" />
          Overlabels
        </a>
        <Link v-if="isAuthed" :href="route('dashboard.index')" class="text-sm text-violet-400 hover:underline">Dashboard</Link>
        <a v-else href="/login?redirect_to=/products" class="text-sm text-violet-400 hover:underline">Log in</a>
      </div>

      <header class="mb-8 flex flex-col gap-2">
        <div class="flex items-center gap-2">
          <Package class="size-5 text-violet-400" />
          <h1 class="text-2xl font-semibold text-foreground">Products</h1>
        </div>
        <p class="max-w-prose text-foreground">
          Things viewers can do in your chat and see on your stream. Each one installs in one click and tells you the few things left to do on the
          same page. No code, and nothing to configure that you do not want to.
        </p>
      </header>

      <ul class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <li v-for="product in props.products" :key="product.slug" class="collection-row relative border border-border p-4">
          <Link :href="`/products/${product.slug}`" class="absolute inset-0 z-0 cursor-pointer" :aria-label="product.name" />
          <!-- A hero is the product's own artwork, 16:9, sitting above the copy
               inside the same card so the whole thing stays one link. -->
          <img v-if="product.hero" :src="product.hero" alt="" class="mb-4 block aspect-video w-full object-cover" />
          <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <h2 class="text-lg font-medium text-foreground">{{ product.name }}</h2>
            <span v-if="product.installed" class="inline-flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
              <Check class="size-4" />
              Installed
            </span>
          </div>
          <p class="mt-1 max-w-prose text-sm text-foreground">{{ product.description }}</p>
          <p v-if="product.requires_bot" class="mt-2 inline-flex items-center gap-1.5 text-xs text-muted-foreground">
            <Bot class="size-3.5" />
            Works through the Overlabels bot in your chat
          </p>
        </li>
        <!-- The third slot is empty on purpose. A grid with two cards and a
             hole reads as unfinished; a grid with two cards and a labelled
             placeholder reads as a shelf with room on it. -->
        <li class="flex">
          <EmptyState dashed class="w-full" :icon="Sparkles" message="More to come!" />
        </li>
      </ul>
    </div>
  </div>
</template>
