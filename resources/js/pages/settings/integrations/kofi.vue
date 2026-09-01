<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import EventTypeToggleList from '@/components/EventTypeToggleList.vue';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import RekaToast from '@/components/RekaToast.vue';
import { Separator } from '@/components/ui/separator';
import { parseAmountInput } from '@/utils/amountInput';
import { type BreadcrumbItem } from '@/types';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();
interface IntegrationData {
  connected: boolean;
  enabled: boolean;
  test_mode: boolean;
  webhook_url: string | null;
  last_received_at: string | null;
  settings: { enabled_events?: string[] };
  has_token: boolean;
  donations_seed_set: boolean;
  donations_seed_value: number | null;
}

const props = defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Ko-fi', href: '/settings/integrations/kofi' },
];

const EVENT_TYPES = [
  { value: 'donation', label: 'Donations' },
  { value: 'subscription', label: 'Subscriptions' },
  { value: 'shop_order', label: 'Shop Orders' },
  { value: 'commission', label: 'Commissions' },
];

const form = useForm({
  verification_token: '',
  enabled_events: props.integration.settings?.enabled_events ?? ['donation', 'subscription', 'shop_order'],
  enabled: props.integration.connected ? props.integration.enabled : true,
});

// Test mode is independent of the main form — toggled instantly via its own endpoint
const testMode = ref(props.integration.test_mode ?? false);
const testModeLoading = ref(false);

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
    const { data } = await axios.post('/settings/integrations/kofi/seed-count', {
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

const copied = ref(false);

function copyWebhookUrl() {
  if (!props.integration.webhook_url) return;
  navigator.clipboard.writeText(props.integration.webhook_url).then(() => {
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
  });
}

function save() {
  form.post('/settings/integrations/kofi', {
    preserveScroll: true,
  });
}

async function toggleTestMode() {
  testModeLoading.value = true;
  try {
    const { data } = await axios.patch('/settings/integrations/kofi/test-mode', {
      test_mode: testMode.value,
    });
    testMode.value = data.test_mode;
  } catch {
    // revert on failure
    testMode.value = !testMode.value;
  } finally {
    testModeLoading.value = false;
  }
}

async function disconnect() {
  if (await confirm({ message: 'Disconnect Ko-fi? This will remove all Ko-fi controls from your overlays.', confirmLabel: 'Disconnect' })) {
    useForm({}).delete('/settings/integrations/kofi');
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
    <Head title="Ko-fi Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <HeadingSmall title="Ko-fi" description="Receive donation alerts and update overlay controls from Ko-fi." />
          <Badge v-if="integration.connected" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <div v-if="!integration.connected" class="space-y-2 border border-violet-500/30 bg-violet-500/5 p-4">
          <HeadingSmall
            title="Why Ko-fi?"
            description="Learn why Ko-fi is the best way to receive donations as a streamer - and why Overlabels chose it as our first integration."
          />
          <Link href="/help/why-kofi" class="btn btn-plain">Read more</Link>
        </div>

        <div v-if="integration.connected" class="mb-6 space-y-2 border border-sidebar-border bg-sidebar-accent p-4 text-sm text-muted-foreground">
          <p class="font-medium text-foreground">What to do next</p>
          <ol class="list-decimal space-y-1 pl-4">
            <li>
              Go to <a href="/triggers" class="font-medium text-violet-400 hover:underline">Triggers</a>
              to configure which alert template fires for each Ko-fi event type (Donation, Subscription, etc.).
            </li>
            <li>
              Open any <strong>static</strong> overlay template → <strong>Controls</strong> tab → <strong>Add control</strong>
              to add Ko-fi data controls (donation count, latest donor name, etc.) that update live.
            </li>
          </ol>
        </div>

        <form class="space-y-6" @submit.prevent="save">
          <!-- Verification Token -->
          <div class="space-y-2">
            <Label for="verification_token">Ko-fi Verification Token</Label>
            <p class="text-sm text-muted-foreground">Find this in Ko-fi → My Page → API → Verification Token.</p>
            <input
              id="verification_token"
              v-model="form.verification_token"
              type="text"
              :placeholder="integration.has_token ? '(token saved - enter new to replace)' : 'Paste your verification token'"
              autocomplete="off"
              class="input-border w-full"
            />
            <p v-if="form.errors.verification_token" class="text-sm text-destructive">
              {{ form.errors.verification_token }}
            </p>
          </div>

          <!-- Webhook URL (read-only) -->
          <div v-if="integration.connected && integration.webhook_url" class="group space-y-2">
            <Label>Your Webhook URL</Label>
            <p class="text-sm text-muted-foreground">Paste this URL into Ko-fi → My Page → API → Webhook URL.</p>
            <div class="flex">
              <input :value="integration.webhook_url ?? ''" readonly class="peer input-border mr-0 w-full font-mono text-sm" />
              <button
                type="button"
                class="btn btn-chill btn-sm rounded-none rounded-r-sm border border-l-0 border-sidebar-border p-2 px-4 text-sm hover:ring-0"
                @click="copyWebhookUrl"
              >
                {{ copied ? 'Copied!' : 'Copy' }}
              </button>
            </div>
          </div>

          <!-- Enabled Event Types -->
          <div class="space-y-2">
            <Label>Alert on</Label>
            <p class="text-sm text-muted-foreground">Which Ko-fi event types should trigger alerts and update controls.</p>
            <EventTypeToggleList v-model="form.enabled_events" :event-types="EVENT_TYPES" />
          </div>

          <!-- Last received -->
          <p v-if="integration.connected" class="text-sm text-muted-foreground">
            Last event received: {{ formatDate(integration.last_received_at) }}
          </p>

          <div class="flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary" :disabled="form.processing">
              {{ integration.connected ? 'Save changes' : 'Connect Ko-fi' }}
            </button>
            <Link href="/settings/integrations" class="btn btn-sm btn-chill">Cancel</Link>
          </div>
        </form>

        <!-- Test mode — independent toggle, saves instantly -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <div class="flex items-center gap-3">
              <button
                type="button"
                role="switch"
                :aria-checked="testMode"
                :disabled="testModeLoading"
                class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                :class="testMode ? 'bg-yellow-500' : 'bg-muted-foreground/30'"
                @click="
                  testMode = !testMode;
                  toggleTestMode();
                "
              >
                <span
                  class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-sm ring-0 transition-transform"
                  :class="testMode ? 'translate-x-4.5' : 'translate-x-0.5'"
                />
              </button>
              <Label
                class="cursor-pointer"
                @click="
                  testMode = !testMode;
                  toggleTestMode();
                "
              >
                Test mode
              </Label>
            </div>
            <p class="text-sm text-muted-foreground">
              Disables duplicate event detection. Fire the same Ko-fi webhook as many times as you like.
              <span v-if="testMode" class="font-bold text-yellow-500">
                Turn this off before going live - your donation total will reset to {{ donationsSeedValue ?? 0 }}.
              </span>
            </p>
          </div>
        </template>

        <!-- Starting donation total (one-time seed) -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Starting donation total</p>

            <!-- Already seeded - locked -->
            <template v-if="donationsSeedSet">
              <p class="text-sm text-muted-foreground">
                Starting total set to <strong>{{ donationsSeedValue?.toLocaleString(userLocale) }}</strong
                >. Your <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:kofi:total_received]]]</code>
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
                Had Ko-fi donations before joining? Set the total you already raised so your overlay doesn't begin at zero. Decimals are fine, in
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
              Disconnecting Ko-fi will remove all Ko-fi-managed controls (donation counts, latest donor, etc.) from your overlays.
            </p>
            <button type="button" @click="disconnect" class="btn btn-danger">Disconnect Ko-fi</button>
          </div>
        </template>
      </div>
    </SettingsLayout>
    <RekaToast v-if="toastMessage" :message="toastMessage" type="success" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
