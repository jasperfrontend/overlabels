<script setup lang="ts">
import { ref } from 'vue';
import TokenUrlDialog from '@/components/TokenUrlDialog.vue';
import { Dialog, DialogContent, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { VisuallyHidden } from 'reka-ui';

const props = defineProps<{
  template: { id: number; name: string; slug: string };
}>();

const dialogRef = ref<InstanceType<typeof TokenUrlDialog> | null>(null);
const showObsScreenshot = ref(false);
const obsConfirmedCopied = ref(false);

const obsWarning =
  'The next screen shows your personal token and you should NOT show this on stream.\n\nIf you are currently streaming, move this screen away from your stream before continuing.';

const obsIconSVG = '<svg role="img" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12,24C5.383,24,0,18.617,0,12S5.383,0,12,0s12,5.383,12,12S18.617,24,12,24z M12,1.109 C5.995,1.109,1.11,5.995,1.11,12C1.11,18.005,5.995,22.89,12,22.89S22.89,18.005,22.89,12C22.89,5.995,18.005,1.109,12,1.109z M6.182,5.99c0.352-1.698,1.503-3.229,3.05-3.996c-0.269,0.273-0.595,0.483-0.844,0.78c-1.02,1.1-1.48,2.692-1.199,4.156 c0.355,2.235,2.455,4.06,4.732,4.028c1.765,0.079,3.485-0.937,4.348-2.468c1.848,0.063,3.645,1.017,4.7,2.548 c0.54,0.799,0.962,1.736,0.991,2.711c-0.342-1.295-1.202-2.446-2.375-3.095c-1.135-0.639-2.529-0.802-3.772-0.425 c-1.56,0.448-2.849,1.723-3.293,3.293c-0.377,1.25-0.216,2.628,0.377,3.772c-0.825,1.429-2.315,2.449-3.932,2.756 c-1.244,0.261-2.551,0.059-3.709-0.464c1.036,0.302,2.161,0.355,3.191-0.011c1.381-0.457,2.522-1.567,3.024-2.935 c0.556-1.49,0.345-3.261-0.591-4.54c-0.7-1.007-1.803-1.717-3.002-1.969c-0.38-0.068-0.764-0.098-1.148-0.134 c-0.611-1.231-0.834-2.66-0.528-3.996L6.182,5.99z"/></svg>';

function generateOBSUrl() {
  obsConfirmedCopied.value = false;
  dialogRef.value?.generate();
}

defineExpose({ generateOBSUrl });
</script>

<template>
  <button
    @click="generateOBSUrl()"
    class="flex gap-2 py-4 btn btn-secondary w-full"
    title="Add this overlay to your OBS"
  >
    <span class="shrink-0 text-sm font-medium uppercase tracking-wide flex items-center gap-1.5">
      <span v-html="obsIconSVG" class="size-4 inline-block" />
      Add to OBS
    </span>
  </button>

  <!-- OBS URL Dialog -->
  <TokenUrlDialog
    ref="dialogRef"
    title="Add this overlay to OBS"
    :token-name="`OBS - ${props.template?.name ?? 'Overlay'}`"
    :url-base="`/overlay/${props.template?.slug}/`"
    :warning="obsWarning"
    copy-hint='Easy: Just drag the box below in your OBS and click "Yes" to confirm.'
    qr-hint="Scan with your phone to open this overlay. This code contains your secret token, so do not show it on stream."
  >
    <template #instructions>
      <div>
        <ol class="list-decimal space-y-1.5 pl-4 text-foreground">
          <li><strong>Drag the box above</strong> directly on to OBS.</li>
          <li>In OBS, this will automatically create a new <strong>Browser Source</strong>.</li>
          <li>Alternatively, you can click the green box above to copy the link and create the browser source manually. Be sure to set it to fullscreen (right click > transform > Fit to screen)</li>
          <li>Your OBS Browser Source settings should look like
            <button type="button" class="underline text-violet-400 hover:text-violet-300 cursor-pointer" @click="showObsScreenshot = true">this example</button>.
          </li>
        </ol>
      </div>
    </template>

    <template #footer="{ close }">
      <div class="flex flex-col bg-violet-600/10 p-2 border rounded-sm border-violet-500/50 gap-2">
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="obsConfirmedCopied" type="checkbox" />
          <span class="text-sm text-foreground">I have copied this overlay to my OBS as a Browser Source</span>
        </label>

        <button
          v-if="obsConfirmedCopied"
          type="button"
          class="btn btn-primary mt-2"
          @click="close()"
        >
          Close this screen and continue
        </button>
      </div>
    </template>
  </TokenUrlDialog>

  <!-- OBS Settings Screenshot Modal -->
  <Dialog :open="showObsScreenshot" @update:open="showObsScreenshot = $event">
    <DialogContent class="max-w-[90vw] max-h-[90vh] w-auto p-2 sm:max-w-[90vw]">
      <VisuallyHidden>
        <DialogTitle>OBS Browser Source settings example</DialogTitle>
      </VisuallyHidden>
      <img
        src="/obs-brower-source-settings.png"
        alt="OBS Browser Source settings example"
        class="max-w-full max-h-[80vh] rounded object-contain"
      />
      <DialogFooter>
        <button type="button" class="ml-auto btn btn-chill" @click="showObsScreenshot = false">Close</button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
