<script setup lang="ts">
import { ref } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { VisuallyHidden } from 'reka-ui';
import { useLinkWarning } from '@/composables/useLinkWarning';
import { CheckIcon, CopyIcon, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
  template: { id: number; name: string; slug: string };
}>();

const { triggerLinkWarning } = useLinkWarning();

const showOBSHelp = ref(false);
const showObsScreenshot = ref(false);
const obsGenerating = ref(false);
const obsGeneratedUrl = ref<string | null>(null);
const obsUrlCopied = ref(false);
const obsConfirmedCopied = ref(false);
const obsError = ref('');

const obsIconSVG = '<svg role="img" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12,24C5.383,24,0,18.617,0,12S5.383,0,12,0s12,5.383,12,12S18.617,24,12,24z M12,1.109 C5.995,1.109,1.11,5.995,1.11,12C1.11,18.005,5.995,22.89,12,22.89S22.89,18.005,22.89,12C22.89,5.995,18.005,1.109,12,1.109z M6.182,5.99c0.352-1.698,1.503-3.229,3.05-3.996c-0.269,0.273-0.595,0.483-0.844,0.78c-1.02,1.1-1.48,2.692-1.199,4.156 c0.355,2.235,2.455,4.06,4.732,4.028c1.765,0.079,3.485-0.937,4.348-2.468c1.848,0.063,3.645,1.017,4.7,2.548 c0.54,0.799,0.962,1.736,0.991,2.711c-0.342-1.295-1.202-2.446-2.375-3.095c-1.135-0.639-2.529-0.802-3.772-0.425 c-1.56,0.448-2.849,1.723-3.293,3.293c-0.377,1.25-0.216,2.628,0.377,3.772c-0.825,1.429-2.315,2.449-3.932,2.756 c-1.244,0.261-2.551,0.059-3.709-0.464c1.036,0.302,2.161,0.355,3.191-0.011c1.381-0.457,2.522-1.567,3.024-2.935 c0.556-1.49,0.345-3.261-0.591-4.54c-0.7-1.007-1.803-1.717-3.002-1.969c-0.38-0.068-0.764-0.098-1.148-0.134 c-0.611-1.231-0.834-2.66-0.528-3.996L6.182,5.99z"/></svg>';

function generateOBSUrl() {
  triggerLinkWarning(async () => {
    showOBSHelp.value = true;
    obsGenerating.value = true;
    obsGeneratedUrl.value = null;
    obsUrlCopied.value = false;
    obsConfirmedCopied.value = false;
    obsError.value = '';
    try {
      const url = route('tokens.store');
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ name: `OBS - ${props.template?.name ?? 'Overlay'}` }),
      });

      if (!response.ok) {
        const errorBody = await response.text();
        console.error('[OBS URL] Token generation failed', { status: response.status, body: errorBody, url });
        obsError.value = 'Failed to generate a token. Please try again.';
        return;
      }

      const data = await response.json();
      obsGeneratedUrl.value = `${window.location.origin}/overlay/${props.template?.slug}/#${data.plain_token}`;
    } catch (e) {
      console.error('[OBS URL]', e);
      obsError.value = 'Failed to generate a token. Please try again.';
    } finally {
      obsGenerating.value = false;
    }
  }, 'The next screen shows your personal token and you should NOT show this on stream.\n\nIf you are currently streaming, move this screen away from your stream before continuing.');
}

function copyOBSUrl() {
  if (!obsGeneratedUrl.value) return;
  navigator.clipboard.writeText(obsGeneratedUrl.value);
  obsUrlCopied.value = true;
  setTimeout(() => {
    obsUrlCopied.value = false;
  }, 5000);
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
  <Dialog :open="showOBSHelp">
    <DialogContent class="max-w-lg" @escape-key-down.prevent @pointer-down-outside.prevent @interact-outside.prevent>
      <DialogHeader>
        <DialogTitle>Add this overlay to OBS</DialogTitle>
      </DialogHeader>

      <!-- Loading state -->
      <div v-if="obsGenerating" class="flex items-center justify-center gap-3 py-8">
        <Loader2 class="h-5 w-5 animate-spin text-violet-400" />
        <span class="text-sm">Generating your secure URL...</span>
      </div>

      <!-- Error state -->
      <div v-else-if="obsError" class="space-y-4 text-sm">
        <div class="rounded-sm border border-red-500/30 bg-red-500/10 px-3 py-2 text-red-600 dark:text-red-400">
          <p>{{ obsError }}</p>
        </div>
      </div>

      <!-- URL generated -->
      <div v-else-if="obsGeneratedUrl" class="space-y-4 text-sm">
        <p class="text-green-400" v-if="obsUrlCopied">URL Copied to clipboard</p>
        <p class="text-foreground" v-else>Easy: Just drag the box below in your OBS and click "Yes" to confirm.</p>

        <a
          class="flex items-center cursor-pointer gap-2 rounded-lg border border-green-500/20 bg-green-400/10 dark:bg-green-950/10 p-3 font-mono text-xs break-all transition-colors hover:bg-green-400/20 active:ring active:ring-green-500 select-all"
          :class="obsUrlCopied ? 'border-green-500/40 ring ring-green-500/80' : 'border-green-500/20'"
          :href="obsGeneratedUrl"
          @click.prevent="copyOBSUrl"
        >
          <span class="flex-1 text-green-600 dark:text-green-300/80">{{ obsGeneratedUrl }}</span>
          <span class="shrink-0 rounded-md p-2 transition hover:bg-green-500/10" title="Copy URL">
            <CheckIcon v-if="obsUrlCopied" class="h-4 w-4 text-green-400" />
            <CopyIcon v-else class="h-4 w-4 text-green-400" />
          </span>
        </a>

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

        <div class="h-px bg-violet-300"></div>

        <div class="flex flex-col bg-violet-600/10 p-2 border rounded-sm border-violet-500/50 gap-2">
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="obsConfirmedCopied" type="checkbox" />
            <span class="text-sm text-foreground">I have copied this overlay to my OBS as a Browser Source</span>
          </label>

          <button
            v-if="obsConfirmedCopied"
            type="button"
            class="btn btn-primary mt-2"
            @click="showOBSHelp = false"
          >
            Close this screen and continue
          </button>
        </div>
      </div>
    </DialogContent>
  </Dialog>

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
