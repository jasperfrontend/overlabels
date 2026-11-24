<script setup lang="ts">
import TwitchIcon from '@/components/TwitchIcon.vue';
import { Loader } from '@lucide/vue';
import { ref } from 'vue';

// green: the login page's install banner is showing, so the button takes its colour.
defineProps<{ green?: boolean }>();
const loading = ref(false);
</script>
<template>
  <a class="btn btn-xl mt-4 w-full" :class="green ? 'btn-green' : 'btn-primary'" href="/auth/redirect/twitch" @click="loading = true">
    <span class="flex items-center gap-2">
      <Loader v-if="loading" class="size-4 animate-spin" />
      <TwitchIcon v-else class="size-5" />
      <span v-if="loading">Connecting&hellip;</span>
      <span v-else>Connect with Twitch</span>
    </span>
  </a>
</template>
