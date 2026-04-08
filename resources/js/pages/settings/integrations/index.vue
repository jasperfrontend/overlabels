<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { ref } from 'vue';

interface ServiceInfo {
  key: string;
  name: string;
  connected: boolean;
  enabled: boolean;
  test_mode: boolean;
  last_received_at: string | null;
}

interface EventSubInfo {
  connected: boolean;
  connected_at: string | null;
  subscription_count: number;
  active_count: number;
}

const props = defineProps<{
  services: ServiceInfo[];
  eventsub: EventSubInfo;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Settings',
    href: '/settings',
  },
  {
    title: 'Integrations',
    href: '/settings/integrations',
  },
];

const eventsubLoading = ref(false);
const eventsubMessage = ref('');

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

async function refreshEventSub() {
  eventsubLoading.value = true;
  eventsubMessage.value = '';

  try {
    const response = await fetch('/eventsub/refresh', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      },
    });

    const data = await response.json();
    eventsubMessage.value = data.message;

    if (data.success) {
      setTimeout(() => router.reload(), 2000);
    }
  } catch {
    eventsubMessage.value = 'Failed to refresh. Please try again.';
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
          <HeadingSmall title="Twitch EventSub" description="Real-time event subscriptions from Twitch for alerts, per-stream counters, and live detection." />

          <div class="mt-4 rounded-lg border p-4">
            <div class="flex items-center justify-between">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-medium">EventSub Subscriptions</span>
                  <Badge v-if="eventsub.active_count > 0" variant="default" class="bg-green-400 hover:bg-green-400">
                    {{ eventsub.active_count }} active
                  </Badge>
                  <Badge v-else-if="eventsub.connected" variant="secondary" class="bg-yellow-400 hover:bg-yellow-400">
                    No active subscriptions
                  </Badge>
                  <Badge v-else variant="secondary" class="bg-accent hover:bg-accent">Not connected</Badge>
                </div>
                <p v-if="eventsub.connected_at" class="text-muted-foreground text-sm">
                  Connected since {{ formatDate(eventsub.connected_at) }}
                </p>
                <p v-if="eventsub.connected && eventsub.active_count === 0" class="text-sm text-yellow-600 dark:text-yellow-400">
                  Subscriptions may need to be reconnected. Click "Reconnect" below.
                </p>
              </div>

              <div class="flex gap-2">
                <Button v-if="eventsub.active_count > 0" variant="outline" :disabled="eventsubLoading" @click="refreshEventSub">
                  Refresh
                </Button>
                <Button variant="default" :disabled="eventsubLoading" @click="connectEventSub">
                  {{ eventsub.active_count > 0 ? 'Reconnect' : 'Connect' }}
                </Button>
              </div>
            </div>

            <p v-if="eventsubMessage" class="text-muted-foreground mt-2 text-sm">
              {{ eventsubMessage }}
            </p>
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

              <Button v-if="['kofi', 'gpslogger', 'streamlabs'].includes(service.key)" variant="outline" as-child>
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
