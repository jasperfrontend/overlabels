<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';

interface EventSubEvent {
  key: string;
  label: string;
  active: boolean;
}

interface EventSubInfo {
  connected: boolean;
  connected_at: string | null;
  subscription_count: number;
  active_count: number;
  supported_events: EventSubEvent[];
}

const props = defineProps<{
  eventsub: EventSubInfo;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Twitch Alerts', href: '/settings/integrations/twitch' },
];

const eventsubLoading = ref(false);
const eventsubMessage = ref('');

const testCheerLoading = ref(false);
const testCheerMessage = ref('');
const testCheerIsWarning = ref(false);
const testCheerCooldown = ref(0);
let testCheerInterval: ReturnType<typeof setInterval> | null = null;

const TEST_CHEER_COOLDOWN_SECONDS = 60;

function startTestCheerCooldown() {
  testCheerCooldown.value = TEST_CHEER_COOLDOWN_SECONDS;
  if (testCheerInterval) clearInterval(testCheerInterval);
  testCheerInterval = setInterval(() => {
    testCheerCooldown.value--;
    if (testCheerCooldown.value <= 0 && testCheerInterval) {
      clearInterval(testCheerInterval);
      testCheerInterval = null;
    }
  }, 1000);
}

interface EventSubSetupPayload {
  created: string[];
  failed: Record<string, string> | string[];
  existing: string[];
  skipped_missing_scope: string[];
  success: boolean;
}

interface EventSubSetupProgressPayload {
  phase: 'connecting' | 'verifying';
  processed: number;
  total: number;
  connected: number;
}

type EchoChannel = {
  listen: <T>(event: string, cb: (payload: T) => void) => EchoChannel;
  stopListening: (event: string) => EchoChannel;
};

let eventsubChannel: EchoChannel | null = null;

onBeforeUnmount(() => {
  if (testCheerInterval) clearInterval(testCheerInterval);
  eventsubChannel?.stopListening('.eventsub.setup-completed');
  eventsubChannel?.stopListening('.eventsub.setup-progress');
  eventsubChannel = null;
});

async function sendTestCheer() {
  testCheerLoading.value = true;
  testCheerMessage.value = '';
  testCheerIsWarning.value = false;

  try {
    const response = await fetch('/twitch/test-cheer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      },
    });

    const data = await response.json();

    if (!response.ok || !data.ok) {
      testCheerMessage.value = data.error ?? 'Failed to fire test cheer.';
      testCheerIsWarning.value = true;
      return;
    }

    startTestCheerCooldown();

    const parts = [
      `Thanks for testing! Fired ${data.bits} bits from ${data.cheerer_name}.`,
      'This event will disappear from your logs in ~60 seconds, and you can only fire one test cheer per minute to keep things tidy.',
    ];
    if (!data.alert_fired) {
      parts.push('Heads up: no alert is mapped to channel.cheer, so nothing will appear on your overlays.');
      testCheerIsWarning.value = true;
    }
    if (!data.controls_updated) {
      parts.push('Controls did not update because the stream is not live. Use php artisan stream:fake-live {twitch_id} to bypass.');
      testCheerIsWarning.value = true;
    }
    testCheerMessage.value = parts.join(' ');
  } catch {
    testCheerMessage.value = 'Failed to fire test cheer. Please try again.';
    testCheerIsWarning.value = true;
  } finally {
    testCheerLoading.value = false;
  }
}

const page = usePage();
const userLocale = computed<string | undefined>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.locale || undefined;
});
const twitchId = computed<string | undefined>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.twitch_id ? String(user.twitch_id) : undefined;
});

onMounted(() => {
  const echo = (window as any).Echo;
  if (!echo || !twitchId.value) return;
  eventsubChannel = echo.private(`alerts.${twitchId.value}`);
  eventsubChannel?.listen('.eventsub.setup-progress', (payload: EventSubSetupProgressPayload) => {
    // Also covers an F5 mid-sequence: a fresh page picks the progress back up
    // and re-freezes the button until the completion event lands.
    eventsubLoading.value = true;
    if (payload.phase === 'verifying') {
      eventsubMessage.value = `All ${payload.total} events requested. Twitch is verifying them now - about 15 more seconds...`;
    } else {
      eventsubMessage.value = `Connecting Twitch events: ${payload.connected} connected, ${payload.total - payload.processed} to go...`;
    }
  });
  eventsubChannel?.listen('.eventsub.setup-completed', (payload: EventSubSetupPayload) => {
    const createdCount = payload.created?.length ?? 0;
    const existingCount = payload.existing?.length ?? 0;
    const failedCount = Array.isArray(payload.failed) ? payload.failed.length : Object.keys(payload.failed ?? {}).length;
    const skippedCount = payload.skipped_missing_scope?.length ?? 0;

    if (payload.success) {
      const parts = [`Connected: ${createdCount} created, ${existingCount} existing, ${failedCount} failed`];
      if (skippedCount > 0) parts.push(`${skippedCount} skipped (missing scope)`);
      eventsubMessage.value = parts.join(', ') + '.';
    } else {
      const reason = Array.isArray(payload.failed) ? payload.failed.join('; ') : Object.values(payload.failed ?? {}).join('; ');
      eventsubMessage.value = `Setup failed: ${reason || 'unknown error'}`;
    }

    eventsubLoading.value = false;
    router.reload({ only: ['eventsub'] });
  });
});

async function connectEventSub() {
  eventsubLoading.value = true;
  eventsubMessage.value = '';

  try {
    const response = await fetch('/eventsub/connect', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      },
    });

    const data = await response.json();
    eventsubMessage.value = data.message;

    if (!response.ok) {
      eventsubLoading.value = false;
    }
  } catch {
    eventsubMessage.value = 'Failed to connect. Please try again.';
    eventsubLoading.value = false;
  }
}

const listening = computed(() => props.eventsub.active_count > 0);
const inactiveEvents = computed(() => props.eventsub.supported_events.filter((event) => !event.active));
const complete = computed(() => listening.value && inactiveEvents.value.length === 0);

function formatDate(iso: string | null): string {
  if (!iso) return 'Never';
  return new Date(iso).toLocaleString(userLocale.value);
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Twitch Alerts Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <HeadingSmall title="Twitch Alerts" description="Real-time events from Twitch for alerts, per-stream counters, and live detection." />
          <Badge v-if="listening" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Not connected: explain what this does -->
        <div v-if="!listening" class="space-y-2 border border-sidebar-border bg-sidebar-accent p-4 text-sm text-foreground">
          <p class="font-medium">How it works</p>
          <ol class="list-decimal space-y-1 pl-4">
            <li>Click <strong>Connect</strong> below. Overlabels asks Twitch to start sending your channel's events.</li>
            <li>Twitch verifies each subscription. The whole run takes about 30 seconds and this page follows along.</li>
            <li>
              Your alerts, per-stream counters and the live dot all read from those events. Without them, nothing on your overlays reacts to your
              channel.
            </li>
          </ol>
        </div>

        <div class="space-y-3">
          <!--
            Not wrong until it is: every event subscribed says one number, and
            the fraction only shows up when part of the list is missing.
            Fuchsia is the colour of something being wrong here.
          -->
          <p v-if="complete" class="text-sm text-muted-foreground">
            Listening to {{ eventsub.active_count }} events. Connected {{ formatDate(eventsub.connected_at) }}.
          </p>
          <p v-else-if="listening" class="text-sm text-fuchsia-400">
            Listening to {{ eventsub.active_count }} of {{ eventsub.supported_events.length }} events. Connected
            {{ formatDate(eventsub.connected_at) }}.
          </p>
          <p v-else-if="eventsub.connected" class="text-sm text-fuchsia-400">
            Connected to Twitch, but not receiving any events. Reconnecting fixes this.
          </p>

          <div class="flex flex-wrap gap-2">
            <button class="btn btn-primary" :disabled="eventsubLoading" @click="connectEventSub">
              {{ listening ? 'Reconnect' : 'Connect' }}
            </button>
            <button v-if="listening" :disabled="testCheerLoading || testCheerCooldown > 0" @click="sendTestCheer" class="btn btn-chill">
              <template v-if="testCheerLoading">Firing...</template>
              <template v-else-if="testCheerCooldown > 0">Wait {{ testCheerCooldown }}s</template>
              <template v-else>Send test cheer</template>
            </button>
            <Link href="/settings/integrations" class="btn btn-chill">Back to integrations</Link>
          </div>

          <p v-if="eventsubMessage" class="text-sm text-muted-foreground">{{ eventsubMessage }}</p>
          <p v-if="testCheerMessage" class="text-sm" :class="testCheerIsWarning ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">
            {{ testCheerMessage }}
          </p>
        </div>

        <!--
          The event list is the whole point of this page, so it is laid out
          rather than hidden behind the dialog the integrations list used to
          open. Inactive events are called out under the active ones: a gap
          there is the reason an alert never fires.
        -->
        <Separator />
        <div class="space-y-3">
          <div>
            <p class="text-sm font-medium">Events</p>
            <p class="text-sm text-muted-foreground">These are the Twitch events your overlays can respond to.</p>
          </div>

          <ul class="grid gap-2 sm:grid-cols-2">
            <li v-for="event in eventsub.supported_events" :key="event.key" class="flex items-center gap-2 text-sm">
              <span v-if="event.active" class="text-green-500">&#10003;</span>
              <span v-else class="text-muted-foreground">&#10005;</span>
              <span :class="{ 'text-muted-foreground': !event.active }">{{ event.label }}</span>
            </li>
          </ul>

          <p v-if="listening && inactiveEvents.length > 0" class="text-sm text-muted-foreground">
            {{ inactiveEvents.length }} event{{ inactiveEvents.length === 1 ? '' : 's' }} are not subscribed. Alerts that listen for those never fire.
            Reconnecting retries them; one that keeps failing usually needs a Twitch scope your account has not granted.
          </p>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
