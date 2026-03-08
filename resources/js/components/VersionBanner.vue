<script setup lang="ts">
import { useVersionCheck } from '@/composables/useVersionCheck';
import { RefreshCw } from 'lucide-vue-next';

const { hasNewVersion, refresh } = useVersionCheck();
</script>

<template>
  <div
    v-if="hasNewVersion"
    class="flex items-center justify-between bg-blue-600 px-4 py-2 text-sm font-medium text-white"
  >
    <span>
      A new version of the app is available. Fresher than your Twitch stats.
    </span>
    <button
      class="ml-4 inline-flex items-center gap-1.5 rounded border border-blue-300 bg-blue-700 px-3 py-1 text-xs font-semibold hover:bg-blue-800 cursor-pointer"
      @click="refresh"
    >
      <RefreshCw class="size-3" />
      Refresh
    </button>
  </div>
</template>
