<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import RekaToast from '@/components/RekaToast.vue';
import TestModeToggle from '@/components/TestModeToggle.vue';
import { Separator } from '@/components/ui/separator';
import { parseAmountInput } from '@/utils/amountInput';
import { type BreadcrumbItem } from '@/types';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();
interface IntegrationData {
  connected: boolean;
  enabled: boolean;
  test_mode: boolean;
  last_received_at: string | null;
  settings: Record<string, any>;
  donations_seed_set: boolean;
  donations_seed_value: number | null;
}

const props = defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'StreamLabs', href: '/settings/integrations/streamlabs' },
];

// Starting donation total - one-time seed, locked after setting.
// This is money, not a tally, so it is a free-text field: the streamer types
// "65,35" or "65.35" depending on where they live and parseAmountInput settles
// it into the single number the server expects.
const seedInput = ref('');
const seedLoading = ref(false);
const toastMessage = ref<string | null>(null);
const seedError = ref<string | null>(null);
const donationsSeedSet = ref(props.integration.donations_seed_set);
const donationsSeedValue = ref(props.integration.donations_seed_value);

const seedAmount = computed(() => parseAmountInput(seedInput.value, userLocale.value));
const seedExample = computed(() => (1256.5).toLocaleString(userLocale.value, { minimumFractionDigits: 2 }));
const seedUnreadable = computed(() => seedInput.value.trim() !== '' && seedAmount.value === null);
const seedPreview = computed(() =>
  seedAmount.value === null ? null : seedAmount.value.toLocaleString(userLocale.value, { maximumFractionDigits: 2 }),
);

async function setSeedCount() {
  const amount = seedAmount.value;
  if (amount === null || amount < 0) return;
  seedLoading.value = true;
  seedError.value = null;
  try {
    const { data } = await axios.post('/settings/integrations/streamlabs/seed-count', {
      initial_count: amount,
    });
    donationsSeedSet.value = data.donations_seed_set;
    donationsSeedValue.value = data.donations_seed_value;
    toastMessage.value = `Starting total set to ${Number(data.donations_seed_value).toLocaleString(userLocale.value)}.`;
  } catch (e: any) {
    seedError.value = e.response?.data?.errors?.initial_count?.[0] ?? e.response?.data?.error ?? 'Something went wrong.';
  } finally {
    seedLoading.value = false;
  }
}

async function disconnect() {
  if (
    await confirm({
      message: 'Disconnect StreamLabs? This will remove all StreamLabs-managed controls from your overlays.',
      confirmLabel: 'Disconnect',
    })
  ) {
    useForm({}).delete('/settings/integrations/streamlabs');
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
    <Head title="StreamLabs Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <HeadingSmall title="StreamLabs" description="Receive donation alerts and update overlay controls from StreamLabs." />
          <Badge v-if="integration.connected" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Closed beta banner -->
        <div class="space-y-2 border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
          <p class="font-medium text-amber-600 dark:text-amber-400">Closed beta</p>
          <p class="text-muted-foreground">
            The StreamLabs integration is in closed beta while our application is under review by StreamLabs. During this period, only whitelisted
            StreamLabs accounts can connect. If you'd like early access, reach out to
            <a href="mailto:jasper@emailjasper.com" class="text-violet-400 hover:underline">jasper@emailjasper.com</a>.
          </p>
        </div>

        <!-- Not connected state -->
        <template v-if="!integration.connected">
          <div class="space-y-4">
            <p class="text-sm text-muted-foreground">
              Connect your StreamLabs account to receive donation alerts and live-updating controls in your overlays.
            </p>
            <a href="/settings/integrations/streamlabs/redirect" class="btn btn-primary">Authenticate with StreamLabs</a>
          </div>
        </template>

        <!-- Connected state -->
        <template v-if="integration.connected">
          <div class="mb-6 space-y-2 border border-sidebar-border bg-sidebar-accent p-4 text-sm text-muted-foreground">
            <p class="font-medium text-foreground">What to do next</p>
            <ol class="list-decimal space-y-1 pl-4">
              <li>
                Go to <a href="/triggers" class="font-medium text-violet-400 hover:underline">Triggers</a>
                to configure which alert template fires for StreamLabs donations.
              </li>
              <li>
                Open any <strong>static</strong> overlay template &rarr; <strong>Controls</strong> tab &rarr; <strong>Add control</strong>
                to add StreamLabs data controls (donation count, latest donor name, etc.) that update live.
              </li>
              <li>
                Enable test mode below, then visit
                <a href="https://streamlabs.com/dashboard#/alertbox/general/tipping" class="text-violet-400 hover:underline" target="_blank"
                  >the Streamlabs dashboard</a
                >
                to fire a few test events (Click Test > Streamlabs > Tipping).
              </li>
            </ol>
          </div>

          <!-- Last received -->
          <p class="text-sm text-muted-foreground">Last event received: {{ formatDate(integration.last_received_at) }}</p>
        </template>

        <template v-if="integration.connected">
          <Separator />
          <TestModeToggle
            service="streamlabs"
            service-label="StreamLabs"
            how-to-fire="send a test donation from StreamLabs"
            total-label="donation total"
            :initial="integration.test_mode"
            :seed-value="donationsSeedValue"
          />
        </template>

        <!-- Starting donation total (one-time seed) -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Starting donation total</p>

            <!-- Already seeded — locked -->
            <template v-if="donationsSeedSet">
              <p class="text-sm text-muted-foreground">
                Starting total set to <strong>{{ donationsSeedValue?.toLocaleString(userLocale) }}</strong
                >. Your <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:streamlabs:total_received]]]</code>
                controls started from this value.
              </p>
              <div class="flex items-start gap-2">
                <div class="flex-1 space-y-1">
                  <input
                    v-model="seedInput"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    :placeholder="'e.g. ' + seedExample"
                    :disabled="seedLoading"
                    class="input-border"
                  />
                  <p v-if="seedUnreadable" class="text-xs text-destructive">That doesn't look like an amount. Try {{ seedExample }}.</p>
                  <p v-else-if="seedPreview" class="text-xs text-muted-foreground">Saving as {{ seedPreview }}</p>
                  <p v-if="seedError" class="text-xs text-destructive">{{ seedError }}</p>
                </div>
                <button type="button" class="btn btn-primary" :disabled="seedLoading || seedAmount === null" @click="setSeedCount">
                  {{ seedLoading ? 'Saving...' : 'Set starting total' }}
                </button>
              </div>
            </template>

            <!-- Not seeded yet -->
            <template v-else>
              <p class="text-sm text-muted-foreground">
                Had StreamLabs donations before joining? Set the total you already raised so your overlay doesn't begin at zero. Decimals are fine, in
                whichever notation you write money. All your <code class="rounded bg-black/10 px-1 dark:bg-white/10">total_received</code>
                controls update immediately.
              </p>
              <div class="flex items-start gap-2">
                <div class="flex-1 space-y-1">
                  <input
                    v-model="seedInput"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    :placeholder="'e.g. ' + seedExample"
                    :disabled="seedLoading"
                    class="input-border"
                  />
                  <p v-if="seedUnreadable" class="text-xs text-destructive">That doesn't look like an amount. Try {{ seedExample }}.</p>
                  <p v-else-if="seedPreview" class="text-xs text-muted-foreground">Saving as {{ seedPreview }}</p>
                  <p v-if="seedError" class="text-xs text-destructive">{{ seedError }}</p>
                </div>
                <button type="button" class="btn btn-primary" :disabled="seedLoading || seedAmount === null" @click="setSeedCount">
                  {{ seedLoading ? 'Saving...' : 'Set starting total' }}
                </button>
              </div>
            </template>
          </div>
        </template>

        <!-- Danger zone -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Danger zone</p>
            <p class="text-sm text-muted-foreground">
              Disconnecting StreamLabs will remove all StreamLabs-managed controls (donation counts, latest donor, etc.) from your overlays.
            </p>
            <button type="button" @click="disconnect" class="btn btn-danger">Disconnect StreamLabs</button>
          </div>
        </template>
      </div>
    </SettingsLayout>
    <RekaToast v-if="toastMessage" :message="toastMessage" type="success" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
