<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Package } from '@lucide/vue';

// The dashboard's push to /products for an account with nothing installed.
// The whole banner is one link, so there is no wrong place to click. Cyan is
// a fixed palette value rather than a theme token on purpose: it is foreign to
// the app's violet in every theme, which is the point.
defineProps<{
  name: string;
  avatar?: string | null;
}>();
</script>

<template>
  <Link
    :href="route('products.index')"
    class="welcome-banner group block cursor-pointer border-2 border-neutral-950 bg-cyan-300 p-5 text-neutral-950 transition-colors hover:bg-cyan-200 focus-visible:ring-4 focus-visible:ring-cyan-300/50 focus-visible:outline-none sm:p-6"
  >
    <div class="flex items-center gap-4">
      <img v-if="avatar" :src="avatar" alt="" class="h-14 w-14 shrink-0 border-2 border-neutral-950 object-cover" />
      <div v-else class="grid h-14 w-14 shrink-0 place-items-center border-2 border-neutral-950 bg-neutral-950 text-cyan-300">
        <Package class="h-7 w-7" />
      </div>

      <div class="min-w-0">
        <div class="text-xs font-semibold tracking-widest uppercase">Start here</div>
        <h2 class="truncate text-2xl font-bold sm:text-3xl">Welcome {{ name }}</h2>
      </div>
    </div>

    <p class="mt-4 max-w-[60ch] text-base leading-relaxed">Discover free stream upgrades and get them live in minutes!</p>

    <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2">
      <span class="inline-flex items-center gap-2 bg-neutral-950 px-5 py-3 text-lg font-semibold text-cyan-300">
        Give your chat something to do
        <ArrowRight class="h-5 w-5 transition-transform group-hover:translate-x-1" />
      </span>
      <span class="text-sm font-medium">Free chat games and overlays. Ready in minutes.</span>
    </div>
  </Link>
</template>
