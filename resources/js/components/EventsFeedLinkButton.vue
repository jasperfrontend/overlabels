<script setup lang="ts">
import { ref } from 'vue';
import TokenUrlDialog from '@/components/TokenUrlDialog.vue';
import { Smartphone } from '@lucide/vue';

// Mints a read+write scoped token and hands out the /events/feed#<token> URL,
// QR-first: the whole point of the feed is opening it on a phone without a
// Twitch login. Reuses the same dialog machinery as AddToObsButton.
const dialogRef = ref<InstanceType<typeof TokenUrlDialog> | null>(null);

const feedWarning =
  'The next screen shows a link containing your personal token and you should NOT show it on stream.\n\nIf you are currently streaming, move this screen away from your stream before continuing.';

function generateFeedUrl() {
  dialogRef.value?.generate();
}

defineExpose({ generateFeedUrl });
</script>

<template>
  <button
    @click="generateFeedUrl()"
    class="btn btn-primary cursor-pointer"
    title="Open your events feed on your phone"
  >
    <Smartphone class="mr-2 h-4 w-4" />
    Events feed
  </button>

  <TokenUrlDialog
    ref="dialogRef"
    title="Your events feed, on your phone"
    token-name="Events feed"
    :abilities="['read', 'write']"
    url-base="/events/feed"
    :warning="feedWarning"
    copy-hint="Scan the QR code below with your phone, or copy the link."
    qr-hint="Scan with your phone to open your events feed. This code contains your secret token, so do not show it on stream."
    qr-default-open
  >
    <template #instructions>
      <p class="text-foreground">
        The link opens your live events feed with the mute-all-alerts button - no login needed.
        Anyone with the link can read your event history and mute or unmute your alerts, so treat
        it like a password. If it ever leaks, revoke the "Events feed" token on the Tokens page
        and generate a new link here.
      </p>
    </template>

    <template #footer="{ close }">
      <div class="flex justify-end">
        <button type="button" class="btn btn-chill" @click="close()">Close</button>
      </div>
    </template>
  </TokenUrlDialog>
</template>
