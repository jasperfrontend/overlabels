<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { CHECKIN_PRESETS } from '@/components/controls/controlPresets';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

interface IntegrationData {
  connected: boolean;
  enabled: boolean;
  pin_lifetime: 'per_stream' | 'persistent';
  home_place_label: string | null;
  cooldown_seconds: number;
  last_received_at: string | null;
  total_pins: number;
}

const props = defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Chat Checkin', href: '/settings/integrations/checkin' },
];

const form = useForm({
  enabled: props.integration.connected ? props.integration.enabled : true,
  pin_lifetime: props.integration.pin_lifetime ?? 'per_stream',
  home_place: props.integration.home_place_label ?? '',
  cooldown_seconds: props.integration.cooldown_seconds ?? 30,
});

function save() {
  form.post('/settings/integrations/checkin', { preserveScroll: true });
}

async function disconnect() {
  if (
    await confirm({
      message:
        'Disconnect Chat Checkin? This removes the checkin controls from your overlays. Existing pins are kept and come back if you reconnect.',
      confirmLabel: 'Disconnect',
    })
  ) {
    useForm({}).delete('/settings/integrations/checkin');
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
    <Head title="Chat Checkin Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <HeadingSmall
            title="Chat Checkin"
            description="Viewers type !checkin Rotterdam, NL in chat and pin themselves on your overlay. City-level only - nobody can pin an address."
          />
          <Badge v-if="integration.connected" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Not connected: explain what this does -->
        <div v-if="!integration.connected" class="space-y-2 border border-sidebar-border bg-sidebar-accent p-4 text-sm text-foreground">
          <p class="font-medium">How it works</p>
          <ol class="list-decimal space-y-1 pl-4">
            <li>Click <strong>Connect Chat Checkin</strong> below. The Overlabels bot must be enabled in your channel.</li>
            <li>Viewers type <code class="rounded bg-black/10 px-1 dark:bg-white/10">!checkin City, CC</code> in chat.</li>
            <li>
              Each viewer gets one pin - checking in again moves it. Use the checkin tags and
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[foreach:checkins as pin]]]</code> in your overlays.
            </li>
          </ol>
        </div>

        <!-- Connected: available tags -->
        <div v-if="integration.connected" class="text-md flex flex-col gap-4 border border-sidebar bg-sidebar-accent p-6">
          <div>
            <p class="text-sm font-medium text-foreground">Overlay controls</p>
            <p class="mt-1 text-sm text-muted-foreground">Use these tags in your overlay templates:</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
              <div v-for="preset in CHECKIN_PRESETS" :key="preset.key" class="space-y-1">
                <p class="text-sm font-medium text-foreground">{{ preset.label }}</p>
                <p class="text-xs text-muted-foreground">
                  Type: <span class="font-mono">{{ preset.type }}</span>
                </p>
                <code class="rounded bg-black/10 px-1 text-sm dark:bg-white/10">[[[c:checkin:{{ preset.key }}]]]</code>
              </div>
            </div>
          </div>
          <p v-if="integration.total_pins > 0" class="text-sm text-muted-foreground">
            {{ integration.total_pins }} viewer{{ integration.total_pins === 1 ? '' : 's' }} have checked in so far.
          </p>
        </div>

        <!-- Settings form -->
        <form class="space-y-6" @submit.prevent="save">
          <div class="space-y-2">
            <Label for="pin_lifetime">Pin lifetime</Label>
            <p class="text-sm text-muted-foreground">
              Per stream starts every stream with a fresh map and chat checks in again. Persistent grows a world map of your community across streams.
            </p>
            <select
              id="pin_lifetime"
              v-model="form.pin_lifetime"
              class="w-full border border-sidebar-border bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none"
            >
              <option value="per_stream">Per stream (fresh map at go-live)</option>
              <option value="persistent">Persistent (pins stay forever)</option>
            </select>
          </div>

          <div class="space-y-2">
            <Label for="home_place">Home location</Label>
            <p class="text-sm text-muted-foreground">
              Your own city, so checkins get a distance. Powers
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">latest_checkin_distance</code> and
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">farthest_checkin_this_stream</code> - render them in km or miles with the
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">|distance:km</code> pipe. Leave empty to skip distances.
            </p>
            <Input id="home_place" v-model="form.home_place" placeholder="Rotterdam, NL" autocomplete="off" />
            <p v-if="form.errors.home_place" class="text-sm text-destructive">{{ form.errors.home_place }}</p>
          </div>

          <div class="space-y-2">
            <Label for="cooldown_seconds">Per-viewer cooldown (seconds)</Label>
            <p class="text-sm text-muted-foreground">
              How long a viewer waits before !checkin works for them again. Keeps a spammy chat from flooding the map.
            </p>
            <Input id="cooldown_seconds" v-model.number="form.cooldown_seconds" type="number" min="5" max="600" class="w-32" />
            <p v-if="form.errors.cooldown_seconds" class="text-sm text-destructive">{{ form.errors.cooldown_seconds }}</p>
          </div>

          <p v-if="integration.connected" class="text-sm text-muted-foreground">
            Last checkin received: {{ formatDate(integration.last_received_at) }}
          </p>

          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ integration.connected ? 'Save changes' : 'Connect Chat Checkin' }}
            </button>
            <Link href="/settings/integrations" class="btn btn-chill">Cancel</Link>
          </div>
        </form>

        <!-- Danger zone -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Danger zone</p>
            <p class="text-sm text-muted-foreground">
              Disconnecting removes the checkin controls from your overlays and disables !checkin. Pins are kept and come back if you reconnect.
            </p>
            <button type="button" @click="disconnect" class="btn btn-danger">Disconnect Chat Checkin</button>
          </div>
        </template>

        <p class="text-xs text-muted-foreground">
          Place data by <a href="https://www.geonames.org/" target="_blank" rel="noopener" class="underline">GeoNames</a> (CC BY 4.0).
        </p>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
