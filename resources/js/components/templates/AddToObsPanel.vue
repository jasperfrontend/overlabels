<script setup lang="ts">
import AddToObsButton from '@/components/AddToObsButton.vue';
import { VideoIcon } from '@lucide/vue';

// The "Add to OBS" tab body, shared by templates/show and templates/edit. It
// used to live inside the code editor's vertical tabs, but Builder-composed
// overlays replace that editor entirely, so it moved up to the main tabs.
const props = defineProps<{
  template: { id: number; name: string; slug: string; type?: string };
}>();
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-4 text-sm text-foreground">
    <div class="flex items-center gap-3">
      <VideoIcon class="size-6 text-violet-500 dark:text-violet-400" />
      <h3 class="text-lg font-semibold">Add this overlay to OBS</h3>
    </div>
    <p>Add this overlay to OBS by clicking the button below and copying the exact URL to your OBS as a browser source.</p>

    <div v-if="props.template?.type === 'alert'" class="space-y-2 rounded border-l-4 border-violet-500 bg-violet-500/10 p-3 text-foreground/90">
      <p class="font-medium text-violet-700 dark:text-violet-300">Heads up: you're adding an alert directly to OBS</p>
      <p>
        That works fine, but alerts are usually far more powerful rendered inside a static overlay, where they inherit its structure and styling.
        <a
          :href="route('help.overlays-vs-alerts')"
          target="_blank"
          rel="noopener noreferrer"
          class="cursor-pointer font-medium text-violet-600 underline hover:text-violet-500 dark:text-violet-300"
          >Read "Overlays vs Alerts"</a
        >
        so you know what you're doing.
      </p>
    </div>

    <div>
      <AddToObsButton :template="props.template" />
    </div>
  </div>
</template>
