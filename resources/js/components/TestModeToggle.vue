<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import { ExternalLink, FlaskConical } from '@lucide/vue';

/**
 * The one test-mode switch for every donation integration page.
 *
 * Test mode does three things, and the copy here says all three in the
 * streamer's words: repeats of the same test event are accepted instead of
 * being dropped as duplicates, nothing received counts toward usage, and
 * turning it OFF puts every control the service manages back to its starting
 * value (DonationIntegrationController::setTestMode). The state is spelled out
 * next to the switch, not carried by colour alone.
 */
const props = defineProps<{
  /** Integration key, used for the endpoint: kofi, bmac, fourthwall, streamlabs, throne. */
  service: string;
  /** How the service is named in copy: "Ko-fi", "Buy Me a Coffee". */
  serviceLabel: string;
  /** Imperative clause finishing "Turn this on, then ...": 'press Send test on Ko-fi's webhook page'. */
  howToFire: string;
  /** What the running total is called: "donation total", "gift total". */
  totalLabel: string;
  initial: boolean;
  /** The starting total the reset goes back to, null when none was set. */
  seedValue: number | null;
}>();

const on = ref(props.initial);
const saving = ref(false);

async function toggle() {
  if (saving.value) return;
  const next = !on.value;
  on.value = next;
  saving.value = true;
  try {
    const { data } = await axios.patch(`/settings/integrations/${props.service}/test-mode`, { test_mode: next });
    on.value = data.test_mode;
  } catch {
    on.value = !next;
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center gap-3">
      <button
        type="button"
        role="switch"
        :aria-checked="on"
        :disabled="saving"
        class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
        :class="on ? 'bg-yellow-500' : 'bg-muted-foreground/30'"
        @click="toggle"
      >
        <span
          class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-sm ring-0 transition-transform"
          :class="on ? 'translate-x-4.5' : 'translate-x-0.5'"
        />
      </button>
      <button type="button" class="flex cursor-pointer items-center gap-1.5 text-sm font-medium text-foreground" :disabled="saving" @click="toggle">
        <FlaskConical class="size-4 shrink-0" :class="on ? 'text-yellow-500' : 'text-muted-foreground'" aria-hidden="true" />
        Test mode is {{ on ? 'on' : 'off' }}
      </button>
    </div>

    <p class="text-sm text-foreground">
      Trying out your alerts? Turn this on, then {{ howToFire }} as often as you like. Every test arrives like a real one: alerts fire and controls
      update, so you see exactly what a real {{ totalLabel.replace(' total', '') }} looks like. Nothing received while it is on counts toward your
      usage.
    </p>

    <p v-if="on" class="text-sm font-medium text-yellow-600 dark:text-yellow-400">
      Turn this off before you go live. Switching it off puts your {{ serviceLabel }} controls back to their starting values: the
      {{ totalLabel }} goes back to {{ seedValue ?? 0 }} and the latest supporter details are cleared, so nothing from testing reaches a real stream.
    </p>

    <a
      :href="route('help.integration-test-mode')"
      target="_blank"
      rel="noopener"
      class="inline-flex cursor-pointer items-center gap-1 text-sm text-violet-500 hover:underline dark:text-violet-400"
    >
      What test mode does, in full
      <ExternalLink class="size-3.5" aria-hidden="true" />
    </a>
  </div>
</template>
