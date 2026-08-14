<script setup lang="ts">
import TwitchIcon from '@/components/TwitchIcon.vue';
</script>
<template>
  <a
    class="mt-4 flex flex-col items-center rounded-lg bg-[#9146FF] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#7c3aed] active:scale-[0.98]"
    href="/auth/redirect/twitch"
  >
    <span class="flex items-center gap-2">
      <TwitchIcon class="size-5" />
      Connect
    </span>
  </a>
</template>
