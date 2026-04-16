<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import QRCode from 'qrcode';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';

interface IntegrationData {
  connected: boolean;
  enabled: boolean;
  webhook_url: string | null;
  deep_link: string | null;
  last_received_at: string | null;
  speed_unit: string;
  has_token: boolean;
}

const props = defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Overlabels GPS', href: '/settings/integrations/overlabels-mobile' },
];

const form = useForm({
  speed_unit: props.integration.speed_unit ?? 'kmh',
  enabled: props.integration.connected ? props.integration.enabled : true,
});

const copied = ref(false);
const copiedUrl = ref(false);
const resetting = ref(false);
const regenerating = ref(false);
const qrDataUrl = ref<string | null>(null);

onMounted(async () => {
  if (props.integration.deep_link) {
    qrDataUrl.value = await QRCode.toDataURL(props.integration.deep_link, {
      width: 240,
      margin: 2,
      color: { dark: '#000000', light: '#ffffff' },
    });
  }
});

function copyDeepLink() {
  if (!props.integration.deep_link) return;
  navigator.clipboard.writeText(props.integration.deep_link).then(() => {
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
  });
}

function copyWebhookUrl() {
  if (!props.integration.webhook_url) return;
  navigator.clipboard.writeText(props.integration.webhook_url).then(() => {
    copiedUrl.value = true;
    setTimeout(() => (copiedUrl.value = false), 2000);
  });
}

function save() {
  form.post('/settings/integrations/overlabels-mobile', {
    preserveScroll: true,
  });
}

async function resetDistance() {
  if (!confirm('Reset distance to 0? This cannot be undone.')) return;
  resetting.value = true;
  try {
    await axios.post('/settings/integrations/overlabels-mobile/reset-distance');
  } finally {
    resetting.value = false;
  }
}

function regenerateToken() {
  if (!confirm('Regenerate the token? You will need to scan the new QR code in the app again.')) return;
  regenerating.value = true;
  useForm({}).post('/settings/integrations/overlabels-mobile/regenerate-token', {
    preserveScroll: true,
    onFinish: () => {
      regenerating.value = false;
    },
  });
}

function disconnect() {
  if (confirm('Disconnect Overlabels GPS? This will remove all GPS controls from your overlays.')) {
    useForm({}).delete('/settings/integrations/overlabels-mobile');
  }
}

const page = usePage();
const userLocale = computed<string | undefined>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.locale || undefined;
});

function formatDate(iso: string | null): string {
  if (!iso) return 'Never';
  return new Date(iso).toLocaleString(userLocale.value);
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Overlabels GPS Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <HeadingSmall
            title="Overlabels GPS"
            description="Stream your live GPS location from the Overlabels GPS Android app. Display speed, coordinates, and distance in your overlays."
          />

          <Badge v-if="integration.connected" variant="default" class="bg-green-400 hover:bg-green-400">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Not connected: explain what this does -->
        <div
          v-if="!integration.connected"
          class="rounded-sm border border-border bg-sidebar-accent p-4 space-y-2 text-sm text-foreground"
        >
          <p class="font-medium">How it works</p>
          <ol class="list-decimal pl-4 space-y-1">
            <li>Click <strong>Connect Overlabels GPS</strong> below.</li>
            <li>A QR code will appear. Open the Overlabels GPS app on your phone and scan it.</li>
            <li>Start tracking - your overlay controls update live with speed, coordinates, and distance.</li>
          </ol>
        </div>

        <!-- Connected: QR code + setup instructions -->
        <div
          v-if="integration.connected && qrDataUrl"
          class="rounded-sm border border-sidebar bg-sidebar-accent p-6 gap-4 flex flex-col text-md"
        >
          <p class="font-medium text-foreground">Connect the Overlabels GPS app</p>
          <p class="text-sm text-foreground">
            Open the Overlabels GPS app on your phone and scan this QR code. The app will be configured automatically.
          </p>
          <div class="flex flex-col items-start gap-3">
            <img :src="qrDataUrl" alt="Setup QR code" class="rounded-sm border border-border" width="240" height="240" />
            <p class="text-xs text-muted-foreground">
              This QR code contains your endpoint URL and authentication token. Do not share it.
            </p>
          </div>

          <Separator />

          <details class="text-sm">
            <summary class="cursor-pointer font-medium text-foreground">Manual setup (if you can't scan the QR code)</summary>
            <div class="mt-3 space-y-3">
              <div class="space-y-1">
                <Label>Endpoint URL</Label>
                <div class="flex gap-2">
                  <Input :model-value="integration.webhook_url ?? ''" readonly class="font-mono text-sm" />
                  <Button type="button" variant="outline" size="sm" @click="copyWebhookUrl">
                    {{ copiedUrl ? 'Copied!' : 'Copy' }}
                  </Button>
                </div>
              </div>
              <div class="space-y-1">
                <Label>Deep link</Label>
                <div class="flex gap-2">
                  <Input :model-value="integration.deep_link ?? ''" readonly class="font-mono text-sm" />
                  <Button type="button" variant="outline" size="sm" @click="copyDeepLink">
                    {{ copied ? 'Copied!' : 'Copy' }}
                  </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                  Open this link on your phone to configure the app automatically.
                </p>
              </div>
            </div>
          </details>

          <Separator />

          <div>
            <p class="font-medium text-sm text-foreground">Overlay controls</p>
            <p class="text-sm text-muted-foreground mt-1">
              Use these tags in your overlay templates:
            </p>
            <div class="flex flex-wrap gap-2 mt-2">
              <code class="rounded bg-black/10 px-1 dark:bg-white/10 text-sm">[[[c:overlabels-mobile:gps_speed]]]</code>
              <code class="rounded bg-black/10 px-1 dark:bg-white/10 text-sm">[[[c:overlabels-mobile:gps_lat]]]</code>
              <code class="rounded bg-black/10 px-1 dark:bg-white/10 text-sm">[[[c:overlabels-mobile:gps_lng]]]</code>
              <code class="rounded bg-black/10 px-1 dark:bg-white/10 text-sm">[[[c:overlabels-mobile:gps_distance]]]</code>
              <code class="rounded bg-black/10 px-1 dark:bg-white/10 text-sm">[[[c:overlabels-mobile:gps_bearing]]]</code>
              <code class="rounded bg-black/10 px-1 dark:bg-white/10 text-sm">[[[c:overlabels-mobile:gps_battery]]]</code>
              <code class="rounded bg-black/10 px-1 dark:bg-white/10 text-sm">[[[c:overlabels-mobile:gps_charging]]]</code>
            </div>
          </div>
        </div>

        <!-- Settings form -->
        <form class="space-y-6" @submit.prevent="save">
          <!-- Speed Unit -->
          <div class="space-y-2">
            <Label for="speed_unit">Speed Unit</Label>
            <p class="text-muted-foreground text-sm">
              How speed is displayed in your overlays.
            </p>
            <select
              id="speed_unit"
              v-model="form.speed_unit"
              class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
            >
              <option value="kmh">km/h</option>
              <option value="mph">mph</option>
            </select>
          </div>

          <!-- Last received -->
          <p v-if="integration.connected" class="text-muted-foreground text-sm">
            Last event received: {{ formatDate(integration.last_received_at) }}
          </p>

          <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">
              {{ integration.connected ? 'Save changes' : 'Connect Overlabels GPS' }}
            </Button>
            <Button variant="outline" as-child>
              <Link href="/settings/integrations">Cancel</Link>
            </Button>
          </div>
        </form>

        <!-- Reset Distance -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="font-medium text-sm">Reset distance</p>
            <p class="text-muted-foreground text-sm">
              Reset the accumulated GPS distance back to 0 km. Useful at the start of a new trip or stream.
            </p>
            <Button
              variant="outline"
              size="sm"
              type="button"
              :disabled="resetting"
              @click="resetDistance"
            >
              {{ resetting ? 'Resetting...' : 'Reset distance to 0' }}
            </Button>
          </div>
        </template>

        <!-- Regenerate Token -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="font-medium text-sm">Regenerate token</p>
            <p class="text-muted-foreground text-sm">
              Generate a new authentication token. You will need to scan the QR code in the app again.
            </p>
            <Button
              variant="outline"
              size="sm"
              type="button"
              :disabled="regenerating"
              @click="regenerateToken"
            >
              {{ regenerating ? 'Regenerating...' : 'Regenerate token' }}
            </Button>
          </div>
        </template>

        <!-- Danger zone -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="font-medium text-sm">Danger zone</p>
            <p class="text-muted-foreground text-sm">
              Disconnecting Overlabels GPS will remove all GPS controls (speed, coordinates, distance)
              from your overlays.
            </p>
            <Button variant="destructive" size="sm" type="button" @click="disconnect">
              Disconnect Overlabels GPS
            </Button>
          </div>
        </template>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
