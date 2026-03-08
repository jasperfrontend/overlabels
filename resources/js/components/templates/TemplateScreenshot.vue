<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ImageDropZone from '@/components/ImageDropZone.vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { VisuallyHidden } from 'reka-ui';

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
const showPreview = ref(false);

function onUrlChange(url: string | null) {
  localUrl.value = url;
  router.put(
    route('templates.screenshot', props.templateId),
    { screenshot_url: url },
    {
      preserveScroll: true,
      onSuccess: () => {
        if (url) emit('saved');
        else emit('removed');
      },
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
    @click-image="showPreview = true"
  />

  <Dialog :open="showPreview" @update:open="showPreview = $event">
    <DialogContent class="max-w-[90vw] max-h-[90vh] w-auto p-2 sm:max-w-[90vw]">
      <VisuallyHidden>
        <DialogTitle>Screenshot preview</DialogTitle>
      </VisuallyHidden>
      <img
        v-if="localUrl"
        :src="localUrl"
        alt="Screenshot preview"
        class="max-h-[85vh] w-auto rounded object-contain"
      />
    </DialogContent>
  </Dialog>
</template>
