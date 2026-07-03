<script setup lang="ts">
import { ref, watch } from 'vue';
import QRCode from 'qrcode';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useLinkWarning } from '@/composables/useLinkWarning';
import { CheckIcon, CopyIcon, Loader2, QrCodeIcon } from '@lucide/vue';

// Shared "generate a tokened URL" dialog: mints a fresh OverlayAccessToken via
// tokens.store, appends it as a URL fragment, and offers copy-to-clipboard and
// a QR code. Wrapped by AddToObsButton (overlay browser-source URLs) and
// EventsFeedLinkButton (phone events feed); the wrapper renders its own
// trigger button, provides the surrounding copy via slots, and calls the
// exposed generate().
const props = defineProps<{
  title: string;
  // Name stored on the minted token, so it's recognizable on the Tokens page.
  tokenName: string;
  // Path the fragment is appended to, e.g. `/overlay/my-slug/` or `/events/feed`.
  urlBase: string;
  // Abilities for the minted token; omitted = unrestricted (legacy behavior).
  abilities?: string[];
  // Shown by the pre-open link warning (the "don't show this on stream" gate).
  warning: string;
  // Line above the URL box before/after copying.
  copyHint: string;
  copiedHint?: string;
  // Line under the QR image.
  qrHint: string;
  // Open the QR section as soon as the URL exists (phone-first flows).
  qrDefaultOpen?: boolean;
}>();

defineSlots<{
  instructions?: () => unknown;
  footer?: (props: { close: () => void }) => unknown;
}>();

const { triggerLinkWarning } = useLinkWarning();

const showDialog = ref(false);
const generating = ref(false);
const generatedUrl = ref<string | null>(null);
const urlCopied = ref(false);
const error = ref('');
const showQrCode = ref(false);
const qrDataUrl = ref<string | null>(null);

// Render the QR only after a URL is confirmed; clear it when the URL is reset.
watch(generatedUrl, async (url) => {
  if (!url) {
    qrDataUrl.value = null;
    return;
  }
  qrDataUrl.value = await QRCode.toDataURL(url, {
    width: 240,
    margin: 2,
    color: { dark: '#000000', light: '#ffffff' },
  });
});

function generate() {
  triggerLinkWarning(async () => {
    showDialog.value = true;
    generating.value = true;
    generatedUrl.value = null;
    urlCopied.value = false;
    error.value = '';
    showQrCode.value = props.qrDefaultOpen ?? false;
    try {
      const url = route('tokens.store');
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({
          name: props.tokenName,
          ...(props.abilities ? { abilities: props.abilities } : {}),
        }),
      });

      if (!response.ok) {
        const errorBody = await response.text();
        console.error('[Token URL] Token generation failed', { status: response.status, body: errorBody, url });
        error.value = 'Failed to generate a token. Please try again.';
        return;
      }

      const data = await response.json();
      generatedUrl.value = `${window.location.origin}${props.urlBase}#${data.plain_token}`;
    } catch (e) {
      console.error('[Token URL]', e);
      error.value = 'Failed to generate a token. Please try again.';
    } finally {
      generating.value = false;
    }
  }, props.warning);
}

function copyUrl() {
  if (!generatedUrl.value) return;
  navigator.clipboard.writeText(generatedUrl.value);
  urlCopied.value = true;
  setTimeout(() => {
    urlCopied.value = false;
  }, 5000);
}

function close() {
  showDialog.value = false;
}

defineExpose({ generate });
</script>

<template>
  <Dialog :open="showDialog">
    <DialogContent class="max-w-lg" @escape-key-down.prevent @pointer-down-outside.prevent @interact-outside.prevent>
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
      </DialogHeader>

      <!-- Loading state -->
      <div v-if="generating" class="flex items-center justify-center gap-3 py-8">
        <Loader2 class="h-5 w-5 animate-spin text-violet-400" />
        <span class="text-sm">Generating your secure URL...</span>
      </div>

      <!-- Error state -->
      <div v-else-if="error" class="space-y-4 text-sm">
        <div class="rounded-sm border border-red-500/30 bg-red-500/10 px-3 py-2 text-red-600 dark:text-red-400">
          <p>{{ error }}</p>
        </div>
      </div>

      <!-- URL generated -->
      <div v-else-if="generatedUrl" class="space-y-4 text-sm">
        <p class="text-green-400" v-if="urlCopied">{{ copiedHint ?? 'URL Copied to clipboard' }}</p>
        <p class="text-foreground" v-else>{{ copyHint }}</p>

        <a
          class="flex items-center cursor-pointer gap-2 rounded-lg border border-green-500/20 bg-green-400/10 dark:bg-green-950/10 p-3 font-mono text-xs break-all transition-colors hover:bg-green-400/20 active:ring active:ring-green-500 select-all"
          :class="urlCopied ? 'border-green-500/40 ring ring-green-500/80' : 'border-green-500/20'"
          :href="generatedUrl"
          @click.prevent="copyUrl"
        >
          <span class="flex-1 text-green-600 dark:text-green-300/80">{{ generatedUrl }}</span>
          <span class="shrink-0 rounded-md p-2 transition hover:bg-green-500/10" title="Copy URL">
            <CheckIcon v-if="urlCopied" class="h-4 w-4 text-green-400" />
            <CopyIcon v-else class="h-4 w-4 text-green-400" />
          </span>
        </a>

        <slot name="instructions" />

        <!-- QR code for getting the URL onto a phone -->
        <div class="rounded-lg border border-violet-500/30 bg-violet-600/5">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left cursor-pointer"
            @click="showQrCode = !showQrCode"
          >
            <span class="flex items-center gap-2 text-sm text-foreground">
              <QrCodeIcon class="h-4 w-4 text-violet-400" />
              {{ showQrCode ? 'QR code to open on your phone' : 'Show QR code to open on your phone' }}
            </span>
            <span class="text-xs text-violet-400">{{ showQrCode ? 'Hide' : 'Show' }}</span>
          </button>

          <div v-if="showQrCode && qrDataUrl" class="flex flex-col items-center gap-2 px-3 pb-3">
            <img :src="qrDataUrl" alt="QR code for this URL" class="rounded bg-white p-2" width="240" height="240" />
            <p class="text-center text-xs text-foreground">{{ qrHint }}</p>
          </div>
        </div>

        <div class="h-px bg-violet-300"></div>

        <slot name="footer" :close="close" />
      </div>
    </DialogContent>
  </Dialog>
</template>
