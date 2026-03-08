<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ImageDropZone from '@/components/ImageDropZone.vue';
import { Dialog, DialogContent, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { VisuallyHidden } from 'reka-ui';

const props = defineProps<{
  screenshotUrl: string | null;
  templateId: number;
  name: string
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
      <DialogFooter>
        <div class="flex w-full items-center justify-between gap-2">
          <div class="text-sm text-muted-foreground">
            Screenshot: {{props.name}}
          </div>
          <button type="button" class="ml-auto btn btn-chill" @click="showPreview = false">Close</button>
        </div>
      </DialogFooter>
    </DialogContent>

  </Dialog>
</template>
