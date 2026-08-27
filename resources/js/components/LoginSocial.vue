<script setup lang="ts">
import TwitchIcon from '@/components/TwitchIcon.vue';
import { Loader } from '@lucide/vue';
import { ref } from 'vue';
const loading = ref(false);
</script>
<template>
  <a class="btn btn-xl btn-primary mt-4 w-full" href="/auth/redirect/twitch" @click="loading = true">
    <span class="flex items-center gap-2">
      <Loader v-if="loading" class="size-4 animate-spin" />
      <TwitchIcon v-else class="size-5" />
      <span v-if="loading">Connecting&hellip;</span>
      <span v-else>Connect</span>
    </span>
  </a>
</template>
