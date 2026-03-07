<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ImageDropZone from '@/components/ImageDropZone.vue';

const props = defineProps<{
  screenshotUrl: string | null;
  templateId: number;
}>();

const emit = defineEmits<{
  saved: [];
  removed: [];
  error: [message: string];
}>();

const localUrl = ref<string | null>(props.screenshotUrl);

function onUrlChange(url: string | null) {
  localUrl.value = url;
  router.put(
    route('templates.screenshot', props.templateId),
    { screenshot_url: url },
    {
      preserveScroll: true,
      onSuccess: () => emit(url ? 'saved' : 'removed'),
      onError: () => {
        localUrl.value = props.screenshotUrl;
        emit('error', url ? 'Failed to save screenshot' : 'Failed to remove screenshot');
      },
    },
  );
}
</script>

<template>
  <ImageDropZone
    :model-value="localUrl"
    upload-preset="overlabels-overlay-screenshots"
    folder="overlays/screenshots"
    @update:model-value="onUrlChange"
    @error="(msg: string) => emit('error', msg)"
  />
</template>
