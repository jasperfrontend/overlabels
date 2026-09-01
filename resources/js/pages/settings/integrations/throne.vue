<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
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
  webhook_url: string | null;
  last_received_at: string | null;
  donations_seed_set: boolean;
  donations_seed_value: number | null;
}

const props = defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Throne', href: '/settings/integrations/throne' },
];

// Connecting takes no input: Throne signs with its own global key, so we just
// create the integration and reveal the webhook URL.
const connectForm = useForm({});

function connect() {
  connectForm.post('/settings/integrations/throne', { preserveScroll: true });
}

// Starting gift total - one-time seed.
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
    const { data } = await axios.post('/settings/integrations/throne/seed-count', {
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
    setTimeout(() => (copied.value = false), 5000);
  });
}

async function disconnect() {
  if (await confirm({ message: 'Disconnect Throne? This will remove all Throne controls from your overlays.', confirmLabel: 'Disconnect' })) {
    useForm({}).delete('/settings/integrations/throne');
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
    <Head title="Throne Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <HeadingSmall title="Throne" description="Receive gift and contribution alerts and update overlay controls from Throne." />
          <Badge v-if="integration.connected" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Not connected: one-click connect (no credentials needed) -->
        <div v-if="!integration.connected" class="space-y-4">
          <div class="space-y-2 border border-sidebar-border bg-sidebar-accent p-4 text-sm text-muted-foreground">
            <p class="font-medium text-foreground">Connect to Throne</p>
            <p>
              Click the button below to connect to Throne and generate a unique Webhook URL. You'll need to add this URL into your Throne Webhook
              settings.
            </p>
          </div>
          <button :disabled="connectForm.processing" @click="connect" class="btn btn-primary">
            {{ connectForm.processing ? 'Connecting...' : 'Connect Throne' }}
          </button>
        </div>

        <template v-else>
          <!-- What to do next -->
          <div class="space-y-2 border border-sidebar-border bg-sidebar-accent p-4 text-sm text-muted-foreground">
            <p class="font-medium text-foreground">What to do next</p>
            <ol class="list-decimal space-y-1 pl-4">
              <li>Copy the webhook URL below into your Throne webhook settings and save (there's a button for it right there).</li>
              <li>
                Go to <a href="/triggers" class="font-medium text-violet-400 hover:underline">Triggers</a>
                to choose which alert template fires for Throne gifts.
              </li>
              <li>
                Open any <strong>static</strong> overlay template -&gt; <strong>Controls</strong> tab -&gt; <strong>Add control</strong> to add Throne
                data controls (gift count, latest gifter, item name, etc.).
              </li>
            </ol>
          </div>

          <!-- Webhook URL (read-only) -->
          <div v-if="integration.webhook_url" class="group space-y-2">
            <Label>Your Webhook URL</Label>
            <p class="text-sm text-muted-foreground">Paste this into the Webhook URL field on your Throne webhook settings page.</p>
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

            <!-- Manual step: send them straight to Throne's webhook settings page -->
            <div class="mt-2 flex flex-wrap items-center gap-3">
              <a href="https://throne.com/profile/integrations/webhook" target="_blank" rel="noopener" class="btn btn-sm btn-primary">
                Open Throne webhook settings -&gt;
              </a>
              <p class="text-xs" :class="copied ? 'font-medium text-violet-400' : 'text-muted-foreground'">
                <template v-if="copied">Copied. Now open Throne and paste it into the Webhook URL field.</template>
                <template v-else>Manual step: paste the URL above into the Webhook URL field there, then save.</template>
              </p>
            </div>
          </div>

          <!-- Last received -->
          <p class="text-sm text-muted-foreground">Last event received: {{ formatDate(integration.last_received_at) }}</p>

          <Separator />
          <TestModeToggle
            service="throne"
            service-label="Throne"
            how-to-fire='press "Test webhook" in Throne'
            total-label="gift total"
            :initial="integration.test_mode"
            :seed-value="donationsSeedValue"
          />

          <!-- Starting gift total (one-time seed) -->
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Starting gift total</p>
            <p v-if="donationsSeedSet" class="text-sm text-muted-foreground">
              Starting total set to <strong>{{ donationsSeedValue?.toLocaleString(userLocale) }}</strong
              >. Your <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:throne:total_received]]]</code>
              controls started from this value.
            </p>
            <p v-else class="text-sm text-muted-foreground">
              Had Throne gifts before joining? Set the total you already received so your overlay doesn't begin at zero. Decimals are fine, in
              whichever notation you write money. All your
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">total_received</code> controls update immediately.
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
              <button class="btn btn-primary" :disabled="seedLoading || seedAmount === null" @click="setSeedCount">
                {{ seedLoading ? 'Saving...' : 'Set starting total' }}
              </button>
            </div>
          </div>

          <!-- Danger zone -->
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Danger zone</p>
            <p class="text-sm text-muted-foreground">
              Disconnecting Throne will remove all Throne-managed controls (gift counts, latest gifter, etc.) from your overlays.
            </p>
            <button type="button" @click="disconnect" class="btn btn-danger">Disconnect Throne</button>
          </div>
        </template>

        <div v-if="!integration.connected" class="pt-2">
          <Link href="/settings/integrations" class="btn btn-sm btn-chill">Cancel</Link>
        </div>
      </div>
    </SettingsLayout>
    <RekaToast v-if="toastMessage" :message="toastMessage" type="success" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
