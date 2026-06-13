<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import QRCode from 'qrcode';
import { GPS_PRESETS } from '@/components/controls/controlPresets';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { type BreadcrumbItem } from '@/types';

interface IntegrationData {
  connected: boolean;
  enabled: boolean;
  webhook_url: string | null;
  deep_link: string | null;
  last_received_at: string | null;
  speed_unit: string;
  has_token: boolean;
  map_sharing_enabled: boolean;
  map_delay_seconds: number;
  map_url: string | null;
  safe_zones: Array<{ id: string; lat: number; lng: number; radius: number }>;
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
  map_sharing_enabled: props.integration.map_sharing_enabled ?? false,
  map_delay_seconds: props.integration.map_delay_seconds ?? 0,
});

const copied = ref(false);
const copiedUrl = ref(false);
const copiedMap = ref(false);
const resetting = ref(false);
const regenerating = ref(false);
const qrDataUrl = ref<string | null>(null);
const toastMessage = ref<string | null>(null);
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');

watch(
  () => props.integration.deep_link,
  async (deepLink) => {
    if (!deepLink) {
      qrDataUrl.value = null;
      return;
    }
    qrDataUrl.value = await QRCode.toDataURL(deepLink, {
      width: 240,
      margin: 2,
      color: { dark: '#000000', light: '#ffffff' },
    });
  },
  { immediate: true },
);

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

function copyMapUrl() {
  if (!props.integration.map_url) return;
  navigator.clipboard.writeText(props.integration.map_url).then(() => {
    copiedMap.value = true;
    setTimeout(() => (copiedMap.value = false), 2000);
  });
}

function save() {
  form.post('/settings/integrations/overlabels-mobile', {
    preserveScroll: true,
    onSuccess: () => {
      toastType.value = 'success';
      toastMessage.value = props.integration.connected
        ? 'Settings saved.'
        : 'Overlabels GPS connected.';
    },
    onError: () => {
      toastType.value = 'error';
      toastMessage.value = 'Failed to save settings. Please try again.';
    },
  });
}

async function resetSession() {
  if (!confirm('Reset the current session distance and stats to 0? Your lifetime total is not affected.')) return;
  resetting.value = true;
  try {
    await axios.post('/settings/integrations/overlabels-mobile/reset-session');
    toastType.value = 'success';
    toastMessage.value = 'Session distance reset.';
  } finally {
    resetting.value = false;
  }
}

const lifetimeDialogOpen = ref(false);
const lifetimeConfirmText = ref('');
const resettingLifetime = ref(false);
const lifetimeConfirmed = computed(() => lifetimeConfirmText.value.trim().toUpperCase() === 'RESET');

async function resetLifetime() {
  if (!lifetimeConfirmed.value) return;
  resettingLifetime.value = true;
  try {
    await axios.post('/settings/integrations/overlabels-mobile/reset-lifetime');
    toastType.value = 'success';
    toastMessage.value = 'Lifetime distance reset to 0.';
    lifetimeDialogOpen.value = false;
    lifetimeConfirmText.value = '';
  } finally {
    resettingLifetime.value = false;
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
          <Badge v-if="integration.connected" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Not connected: explain what this does -->
        <div
          v-if="!integration.connected"
          class="border border-sidebar-border bg-sidebar-accent p-4 space-y-2 text-sm text-foreground"
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
          class="border border-sidebar bg-sidebar-accent p-6 gap-4 flex flex-col text-md"
        >
          <p class="font-medium text-foreground">Connect the Overlabels GPS app</p>
          <p class="text-sm text-foreground">
            Open the Overlabels GPS app on your phone and scan this QR code. The app will be configured automatically.
          </p>
          <div class="flex flex-col items-start gap-3">
            <img :src="qrDataUrl" alt="Setup QR code" class="border border-sidebar-border" width="240" height="240" />
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
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
              <div v-for="preset in GPS_PRESETS" :key="preset.key" class="space-y-1">
                <p class="text-sm font-medium text-foreground">{{ preset.label }}</p>
                <p class="text-xs text-muted-foreground">Type: <span class="font-mono">{{ preset.type }}</span></p>
                <code class="rounded bg-black/10 px-1 text-sm dark:bg-white/10">[[[c:gps:{{ preset.key }}]]]</code>
              </div>
            </div>
          </div>
        </div>

        <!-- Settings form -->
        <form class="space-y-6" @submit.prevent="save">
          <!-- Speed Unit -->
          <div class="space-y-2">
            <Label for="speed_unit">Speed Unit</Label>
            <p class="text-muted-foreground text-sm">
              Default unit for the GPS Sessions dashboard. Overlay templates pick their own unit per tag, e.g.
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:gps:speed|speed:kmh]]]</code>
              or <code class="rounded bg-black/10 px-1 dark:bg-white/10">|speed:mph</code>.
            </p>
            <select
              id="speed_unit"
              v-model="form.speed_unit"
              class="w-full border border-sidebar-border bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
            >
              <option value="kmh">km/h</option>
              <option value="mph">mph</option>
            </select>
          </div>

          <!-- Map Sharing -->
          <template v-if="integration.connected">
            <Separator />
            <div class="space-y-4">
              <div class="space-y-2">
                <Label for="map_sharing_enabled">Public live map</Label>
                <p class="text-muted-foreground text-sm">
                  Share your live GPS location on a public map page. Anyone with the link can see where you are while tracking is active.
                </p>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    id="map_sharing_enabled"
                    v-model="form.map_sharing_enabled"
                    type="checkbox"
                    class="rounded border-sidebar"
                  />
                  <span class="text-sm text-foreground">Enable public map</span>
                </label>
              </div>

              <div v-if="form.map_sharing_enabled" class="space-y-2">
                <Label for="map_delay_seconds">Location delay</Label>
                <p class="text-muted-foreground text-sm">
                  Add a delay to your public location for safety. Viewers see where you were, not where you are.
                </p>
                <select
                  id="map_delay_seconds"
                  v-model.number="form.map_delay_seconds"
                  class="w-full border border-sidebar-border bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
                >
                  <option :value="0">No delay (real-time)</option>
                  <option :value="60">1 minute</option>
                  <option :value="120">2 minutes</option>
                  <option :value="300">5 minutes</option>
                </select>
              </div>

              <div v-if="integration.map_url" class="space-y-1">
                <Label>Your public map URL</Label>
                <div class="flex gap-2">
                  <Input :model-value="integration.map_url" readonly class="font-mono text-sm" />
                  <Button type="button" variant="outline" size="sm" @click="copyMapUrl">
                    {{ copiedMap ? 'Copied!' : 'Copy' }}
                  </Button>
                </div>
              </div>
            </div>
          </template>

          <!-- Safe zones -->
          <template v-if="integration.connected">
            <Separator />
            <div class="space-y-2">
              <Label>Safe zones</Label>
              <p class="text-muted-foreground text-sm">
                When you are inside a safe zone, the app does not send GPS data. Manage zones in the Overlabels GPS app.
              </p>
              <ul v-if="integration.safe_zones.length" class="space-y-1 text-sm">
                <li
                  v-for="zone in integration.safe_zones"
                  :key="zone.id"
                  class="text-foreground"
                >
                  {{ zone.lat.toFixed(5) }}, {{ zone.lng.toFixed(5) }} - {{ zone.radius }}m radius
                </li>
              </ul>
              <p v-else class="text-sm text-muted-foreground">
                No safe zones set. Configure them in the Overlabels GPS app.
              </p>
            </div>
          </template>

          <!-- Last received -->
          <p v-if="integration.connected" class="text-muted-foreground text-sm">
            Last event received: {{ formatDate(integration.last_received_at) }}
          </p>

          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ integration.connected ? 'Save changes' : 'Connect Overlabels GPS' }}
            </button>
            <button class="btn btn-cancel" as-child>
              <Link href="/settings/integrations">Cancel</Link>
            </button>
          </div>
        </form>

        <!-- Reset Distance -->
        <template v-if="integration.connected">
          <Separator />

          <!-- Session reset: low-stakes -->
          <div class="space-y-2">
            <p class="font-medium text-sm">Reset session distance</p>
            <p class="text-foreground text-sm">
              Zero out the current session's distance, speed and duration stats. Your lifetime total is
              untouched. Note: a new session already resets these automatically - this is for fixing a
              session mid-stream.
            </p>
            <Button
              variant="outline"
              size="sm"
              type="button"
              class="cursor-pointer"
              :disabled="resetting"
              @click="resetSession"
            >
              {{ resetting ? 'Resetting...' : 'Reset session distance' }}
            </Button>
          </div>

          <!-- Lifetime reset: destructive -->
          <div class="space-y-2 rounded-md border border-destructive/40 p-4">
            <p class="font-medium text-sm text-destructive">Reset lifetime distance</p>
            <p class="text-foreground text-sm">
              This wipes your all-time cumulative distance back to 0 km. It is permanent and cannot be
              undone - every kilometre you have ever logged is gone. This is not the same as starting a
              new trip or stream (use the session reset above, or just start a new session).
            </p>
            <Button
              variant="destructive"
              size="sm"
              type="button"
              class="cursor-pointer"
              @click="lifetimeDialogOpen = true"
            >
              Reset lifetime distance
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

    <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />

    <!-- Lifetime reset: type-to-confirm guard -->
    <Dialog v-model:open="lifetimeDialogOpen">
      <DialogContent class="max-w-md">
        <DialogHeader>
          <DialogTitle class="text-destructive">Reset lifetime distance?</DialogTitle>
          <DialogDescription class="text-foreground">
            This permanently wipes your all-time cumulative distance back to 0 km. It cannot be undone.
            Your current session distance is not affected.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-2">
          <Label for="lifetime-confirm" class="text-sm">
            Type <span class="font-mono font-semibold">RESET</span> to confirm.
          </Label>
          <Input
            id="lifetime-confirm"
            v-model="lifetimeConfirmText"
            autocomplete="off"
            placeholder="RESET"
            @keyup.enter="resetLifetime"
          />
        </div>

        <DialogFooter>
          <Button
            variant="outline"
            type="button"
            class="cursor-pointer"
            @click="lifetimeDialogOpen = false"
          >
            Cancel
          </Button>
          <Button
            variant="destructive"
            type="button"
            class="cursor-pointer"
            :disabled="!lifetimeConfirmed || resettingLifetime"
            @click="resetLifetime"
          >
            {{ resettingLifetime ? 'Resetting...' : 'Reset lifetime distance' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
