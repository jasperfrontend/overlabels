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
  settings: Record<string, unknown>;
  donations_seed_set: boolean;
  donations_seed_value: number | null;
}

const props = defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Fourthwall', href: '/settings/integrations/fourthwall' },
];

// Starting donation total - this is money, not a tally, so it is a free-text
// field: the streamer types "65,35" or "65.35" depending on where they live and
// parseAmountInput settles it into the single number the server expects.
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
    const { data } = await axios.post('/settings/integrations/fourthwall/seed-count', {
      initial_count: amount,
    });
    donationsSeedSet.value = data.donations_seed_set;
    donationsSeedValue.value = data.donations_seed_value;
    toastMessage.value = `Starting total set to ${Number(data.donations_seed_value).toLocaleString(userLocale.value)}.`;
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string; errors?: { initial_count?: string[] } } } };
    seedError.value = err.response?.data?.errors?.initial_count?.[0] ?? err.response?.data?.error ?? 'Something went wrong.';
  } finally {
    seedLoading.value = false;
  }
}

async function disconnect() {
  if (
    await confirm({
      message: 'Disconnect Fourthwall? This will remove all Fourthwall-managed controls from your overlays.',
      confirmLabel: 'Disconnect',
    })
  ) {
    useForm({}).delete('/settings/integrations/fourthwall');
  }
}

const page = usePage();
const userLocale = computed<string | undefined>(() => {
  const user = (page.props as { auth?: { user?: { locale?: string } } })?.auth?.user;
  return user?.locale || undefined;
});

function formatDate(iso: string | null): string {
  if (!iso) return 'Never';
  return new Date(iso).toLocaleString(userLocale.value);
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Fourthwall Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <HeadingSmall title="Fourthwall" description="Receive donation alerts and update overlay controls from your Fourthwall shop." />
          <Badge v-if="integration.connected" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Not connected state -->
        <template v-if="!integration.connected">
          <div class="space-y-4">
            <p class="text-sm text-foreground">
              Connect your Fourthwall shop to receive donation alerts and live-updating controls in your overlays. Overlabels will register a webhook
              on your shop automatically - no manual setup needed.
            </p>
            <a href="/settings/integrations/fourthwall/redirect" class="btn btn-primary">Authenticate with Fourthwall</a>
          </div>
        </template>

        <!-- Connected state -->
        <template v-if="integration.connected">
          <div class="mb-6 space-y-2 border border-sidebar-border bg-sidebar-accent p-4 text-sm text-foreground">
            <p class="font-medium">What to do next</p>
            <ol class="list-decimal space-y-1 pl-4 text-muted-foreground">
              <li>
                Go to <a href="/triggers" class="font-medium text-violet-400 hover:underline">Triggers</a>
                to configure which alert template fires for Fourthwall donations.
              </li>
              <li>
                Open any <strong>static</strong> overlay template &rarr; <strong>Controls</strong> tab &rarr; <strong>Add control</strong> to add
                Fourthwall data controls (donation count, latest donor, etc.) that update live.
              </li>
              <li>Enable test mode below, then fire a test donation from your Fourthwall shop's dashboard to check your setup.</li>
            </ol>
          </div>

          <p class="text-sm text-muted-foreground">Last event received: {{ formatDate(integration.last_received_at) }}</p>
        </template>

        <template v-if="integration.connected">
          <Separator />
          <TestModeToggle
            service="fourthwall"
            service-label="Fourthwall"
            how-to-fire="send a test donation from your Fourthwall shop's dashboard"
            total-label="donation total"
            :initial="integration.test_mode"
            :seed-value="donationsSeedValue"
          />
        </template>

        <!-- Starting donation total -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Starting donation total</p>

            <template v-if="donationsSeedSet">
              <p class="text-sm text-foreground">
                Starting total set to <strong>{{ donationsSeedValue?.toLocaleString(userLocale) }}</strong
                >. Your <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:fourthwall:total_received]]]</code>
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

            <template v-else>
              <p class="text-sm text-foreground">
                Had Fourthwall donations before joining? Set the total you already raised so your overlay doesn't begin at zero. Decimals are fine, in
                whichever notation you write money. All your
                <code class="rounded bg-black/10 px-1 dark:bg-white/10">total_received</code>
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
              Disconnecting Fourthwall will remove the webhook from your Fourthwall shop and delete all Fourthwall-managed controls (donation counts,
              latest donor, etc.) from your overlays.
            </p>
            <button type="button" class="btn btn-danger" @click="disconnect">Disconnect Fourthwall</button>
          </div>
        </template>
      </div>
    </SettingsLayout>
    <RekaToast v-if="toastMessage" :message="toastMessage" type="success" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
