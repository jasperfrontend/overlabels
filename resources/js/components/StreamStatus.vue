<script setup lang="ts">
import { useLivingTitle } from '@/composables/useLivingTitle';
import { useStreamState } from '@/composables/useStreamState';
import { Link } from '@inertiajs/vue3';
import { Pause } from '@lucide/vue';

// Live state for the app header, on every screen size. Render it ONCE:
// useStreamState leaves the whole alerts channel when it unmounts, so a second
// copy for mobile would cut the first one off. Offline renders an empty root on
// purpose, so a margin class on it still lays the header out.
const { isLive, isTransitioning, uptime } = useStreamState();

// "Title paused" means Overlabels stopped writing your title because you
// changed it on Twitch, and nobody sits on the settings page during a stream
// to see that.
const { paused: titlePaused } = useLivingTitle();
</script>

<template>
  <div class="flex items-center gap-3 font-mono text-xs">
    <span v-if="isLive" class="flex items-center gap-2 text-green-500 dark:text-green-400">
      <span class="size-2 rounded-full bg-green-500" />
      <span class="md:hidden">LIVE {{ uptime }}</span>
      <span class="hidden md:inline">Live for {{ uptime }}</span>
    </span>
    <span v-else-if="isTransitioning" class="flex items-center gap-2 text-muted-foreground">
      <span class="size-2 animate-pulse rounded-full bg-orange-400" />
      <span class="md:hidden">Checking...</span>
      <span class="hidden md:inline">Checking stream</span>
    </span>

    <Link
      v-if="titlePaused"
      href="/settings/title"
      class="inline-flex cursor-pointer items-center gap-1 text-amber-400 hover:underline"
      title="Your title was changed on Twitch, so Overlabels stopped writing it. Click to resume."
    >
      <Pause class="size-3" />
      <span class="hidden md:inline">Title paused</span>
      <span class="sr-only md:hidden">Title paused</span>
    </Link>
  </div>
</template>
