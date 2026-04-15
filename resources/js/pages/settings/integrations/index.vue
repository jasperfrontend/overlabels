<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { type BreadcrumbItem } from '@/types';
import { ref, onBeforeUnmount } from 'vue';

interface ServiceInfo {
  key: string;
  name: string;
  connected: boolean;
  enabled: boolean;
  test_mode: boolean;
  last_received_at: string | null;
}

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

interface BotInfo {
  enabled: boolean;
}

const props = defineProps<{
  services: ServiceInfo[];
  eventsub: EventSubInfo;
  bot: BotInfo;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Integrations',
    href: '/settings/integrations',
  },
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

onBeforeUnmount(() => {
  if (testCheerInterval) clearInterval(testCheerInterval);
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
        'Accept': 'application/json',
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

const botLoading = ref(false);

function toggleBot() {
  botLoading.value = true;
  router.patch(
    '/settings/integrations/bot',
    { enabled: !props.bot.enabled },
    {
      preserveScroll: true,
      onFinish: () => {
        botLoading.value = false;
      },
    },
  );
}

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

    if (data.success) {
      router.reload();
    }
  } catch {
    eventsubMessage.value = 'Failed to connect. Please try again.';
  } finally {
    eventsubLoading.value = false;
  }
}


function formatDate(iso: string | null): string {
  if (!iso) return 'Never';
  return new Date(iso).toLocaleString();
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Integrations" />

    <SettingsLayout>
      <div class="space-y-6">
        <!-- Twitch EventSub -->
        <div>
          <HeadingSmall title="Twitch" description="Real-time events from Twitch for alerts, per-stream counters, and live detection." />

          <div class="mt-4 rounded-lg border p-4">
            <div class="flex items-center justify-between">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-medium">Twitch Alerts</span>

                  <Badge v-if="eventsub.active_count > 0" variant="default" class="bg-green-400 hover:bg-green-400">
                    Connected
                  </Badge>

                  <Badge v-else-if="eventsub.connected" variant="secondary" class="bg-yellow-400 hover:bg-yellow-400 text-primary-foreground">
                    Not listening to any events
                  </Badge>

                  <Badge v-else variant="secondary" class="bg-accent hover:bg-accent">
                    Not connected to Twitch
                  </Badge>
                </div>

                <Dialog>
                  <p v-if="eventsub.connected && eventsub.active_count > 0" class="text-muted-foreground text-sm">
                    Listening to
                    <DialogTrigger as-child>
                      <button class="text-foreground underline underline-offset-2 hover:no-underline cursor-pointer">
                        {{ eventsub.active_count }} events
                      </button>
                    </DialogTrigger>
                  </p>

                  <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                      <DialogTitle>Active events ({{ eventsub.active_count }})</DialogTitle>
                      <DialogDescription>
                        These are the Twitch events your overlays can respond to.
                      </DialogDescription>
                    </DialogHeader>

                    <ul class="space-y-2">
                      <li
                        v-for="event in eventsub.supported_events"
                        :key="event.key"
                        class="flex items-center gap-2 text-sm"
                      >
                        <span v-if="event.active" class="text-green-500">&#10003;</span>
                        <span v-else class="text-muted-foreground">&#10005;</span>
                        <span :class="{ 'text-muted-foreground': !event.active }">{{ event.label }}</span>
                      </li>
                    </ul>
                  </DialogContent>
                </Dialog>

                <p v-if="eventsub.connected && eventsub.active_count === 0" class="text-sm text-yellow-600 dark:text-yellow-400">
                  Not receiving Twitch events. Click "(Re)connect".
                </p>
              </div>

              <div class="flex gap-2">
                <Button
                  v-if="eventsub.active_count > 0"
                  variant="outline"
                  :disabled="testCheerLoading || testCheerCooldown > 0"
                  @click="sendTestCheer"
                >
                  <template v-if="testCheerLoading">Firing...</template>
                  <template v-else-if="testCheerCooldown > 0">Wait {{ testCheerCooldown }}s</template>
                  <template v-else>Send test cheer</template>
                </Button>
                <Button variant="default" :disabled="eventsubLoading" @click="connectEventSub">
                  {{ eventsub.active_count > 0 ? 'Reconnect' : 'Connect' }}
                </Button>
              </div>
            </div>

            <p v-if="eventsubMessage" class="text-muted-foreground mt-2 text-sm">
              {{ eventsubMessage }}
            </p>
            <p
              v-if="testCheerMessage"
              class="mt-2 text-sm"
              :class="testCheerIsWarning ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
            >
              {{ testCheerMessage }}
            </p>
          </div>
        </div>

        <!-- Overlabels Bot -->
        <div>
          <HeadingSmall
            title="Overlabels Bot"
            description="Let the shared @overlabels Twitch account join your chat so you can use it to manage your overlay controls."
          />
          <div class="mt-4 rounded-lg border p-4">
            <div class="flex items-center justify-between">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-medium">Chat bot</span>
                  <Badge v-if="props.bot.enabled" variant="default" class="bg-green-400 hover:bg-green-400">Enabled</Badge>
                  <Badge v-else variant="secondary" class="bg-accent hover:bg-accent">Disabled</Badge>
                </div>
                <p v-if="props.bot.enabled" class="text-sm">
                  Run <code class="rounded bg-muted px-1 py-0.5 text-xs">/mod overlabels</code> in your Twitch chat so the bot can post without rate limits, then try <code class="rounded bg-muted px-1 py-0.5 text-xs">!ping</code> - it should reply with pong.
                </p>
                <p v-else class="text-sm text-muted-foreground">
                  Enable to have the bot join your channel. Default <a :href="route('help.bot.commands')" target="_blank" class="underline hover:text-foreground">bot commands</a> are enabled automatically the first time you enable it.
                </p>
              </div>

              <Button variant="default" :disabled="botLoading" @click="toggleBot">
                {{ props.bot.enabled ? 'Disable' : 'Enable' }}
              </Button>
            </div>
          </div>
        </div>

        <!-- External Integrations -->
        <div>
          <HeadingSmall
            title="External Integrations"
            description="Connect external donation and support platforms to power your overlays."
          />

          <div class="mt-4 space-y-4">
            <div
              v-for="service in props.services"
              :key="service.key"
              class="flex items-center justify-between rounded-lg border p-4"
            >
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-medium">{{ service.name }}</span>
                  <Badge v-if="service.connected" variant="default" class="bg-green-400 hover:bg-green-400">Connected
                  </Badge>
                  <Badge v-else variant="secondary" class="bg-accent hover:bg-accent">Not connected</Badge>
                  <Badge v-if="service.connected && service.test_mode" variant="default"
                         class="bg-yellow-400 hover:bg-yellow-400">Test mode enabled
                  </Badge>
                </div>
                <p v-if="service.connected" class="text-muted-foreground text-sm">
                  Last event: {{ formatDate(service.last_received_at) }}
                </p>
              </div>

              <Button v-if="['kofi', 'gpslogger', 'streamlabs', 'streamelements'].includes(service.key)" variant="outline" as-child>
                <Link :href="`/settings/integrations/${service.key}`">
                  {{ service.connected ? 'Manage' : 'Connect' }}
                </Link>
              </Button>
              <span v-else class="text-muted-foreground text-sm">Coming soon</span>
            </div>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
