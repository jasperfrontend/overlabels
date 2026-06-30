<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';

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

// Test mode is independent of the main form - toggled instantly via its own endpoint
const testMode = ref(props.integration.test_mode ?? false);
const testModeLoading = ref(false);

// Starting donation count - one-time seed
const seedCount = ref<number | null>(null);
const seedLoading = ref(false);
const seedError = ref<string | null>(null);
const donationsSeedSet = ref(props.integration.donations_seed_set);
const donationsSeedValue = ref(props.integration.donations_seed_value);

async function setSeedCount() {
  if (seedCount.value === null || seedCount.value < 0) return;
  seedLoading.value = true;
  seedError.value = null;
  try {
    const { data } = await axios.post('/settings/integrations/throne/seed-count', {
      initial_count: seedCount.value,
    });
    donationsSeedSet.value = data.donations_seed_set;
    donationsSeedValue.value = data.donations_seed_value;
  } catch (e: any) {
    seedError.value = e.response?.data?.error ?? 'Something went wrong.';
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

async function toggleTestMode() {
  testModeLoading.value = true;
  try {
    const { data } = await axios.patch('/settings/integrations/throne/test-mode', {
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

function disconnect() {
  if (confirm('Disconnect Throne? This will remove all Throne controls from your overlays.')) {
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
        <div class="flex items-center justify-between">
          <HeadingSmall
            title="Throne"
            description="Receive gift and contribution alerts and update overlay controls from Throne."
          />
          <Badge v-if="integration.connected" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Not connected: one-click connect (no credentials needed) -->
        <div v-if="!integration.connected" class="space-y-4">
          <div class="border border-sidebar-border bg-sidebar-accent p-4 space-y-2 text-sm text-muted-foreground">
            <p class="font-medium text-foreground">Connect to Throne</p>
            <p>
              Click the button below to connect to Throne and generate a unique Webhook URL.
              You'll need to add this URL into your Throne Webhook settings.
            </p>
          </div>
          <Button :disabled="connectForm.processing" @click="connect">
            {{ connectForm.processing ? 'Connecting...' : 'Connect Throne' }}
          </Button>
        </div>

        <template v-else>
          <!-- What to do next -->
          <div class="border border-sidebar-border bg-sidebar-accent p-4 space-y-2 text-sm text-muted-foreground">
            <p class="font-medium text-foreground">What to do next</p>
            <ol class="list-decimal space-y-1 pl-4">
              <li>
                Copy the webhook URL below into your Throne webhook settings and save (there's a button for it right there).
              </li>
              <li>
                Go to <a href="/alerts" class="font-medium text-violet-400 hover:underline">Alerts Builder</a>
                to choose which alert template fires for Throne gifts.
              </li>
              <li>
                Open any <strong>static</strong> overlay template -&gt; <strong>Controls</strong> tab -&gt;
                <strong>Add control</strong> to add Throne data controls (gift count, latest gifter, item name, etc.).
              </li>
            </ol>
          </div>

          <!-- Webhook URL (read-only) -->
          <div v-if="integration.webhook_url" class="group space-y-2">
            <Label>Your Webhook URL</Label>
            <p class="text-sm text-muted-foreground">
              Paste this into the Webhook URL field on your Throne webhook settings page.
            </p>
            <div class="flex">
              <input
                :value="integration.webhook_url ?? ''"
                readonly
                class="peer mr-0 w-full input-border font-mono text-sm"
              />
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
              <a
                href="https://throne.com/profile/integrations/webhook"
                target="_blank"
                rel="noopener"
                class="btn btn-primary cursor-pointer"
              >
                Open Throne webhook settings -&gt;
              </a>
              <p class="text-xs" :class="copied ? 'font-medium text-violet-400' : 'text-muted-foreground'">
                <template v-if="copied">Copied. Now open Throne and paste it into the Webhook URL field.</template>
                <template v-else>Manual step: paste the URL above into the Webhook URL field there, then save.</template>
              </p>
            </div>
          </div>

          <!-- Last received -->
          <p class="text-sm text-muted-foreground">
            Last event received: {{ formatDate(integration.last_received_at) }}
          </p>

          <!-- Test mode - independent toggle, saves instantly -->
          <Separator />
          <div class="space-y-2">
            <div class="flex items-center gap-3">
              <button
                type="button"
                role="switch"
                :aria-checked="testMode"
                :disabled="testModeLoading"
                class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                :class="testMode ? 'bg-yellow-500' : 'bg-muted-foreground/30'"
                @click="testMode = !testMode; toggleTestMode()"
              >
                <span
                  class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-sm ring-0 transition-transform"
                  :class="testMode ? 'translate-x-4.5' : 'translate-x-0.5'"
                />
              </button>
              <Label class="cursor-pointer" @click="testMode = !testMode; toggleTestMode()">
                Test mode <span v-if="testMode" class="ml-1 text-yellow-500">enabled</span>
                <span v-if="testModeLoading" class="ml-1 text-xs text-yellow-500">saving...</span>
              </Label>
            </div>
            <p class="text-sm text-muted-foreground">
              Disables duplicate event detection. Fire Throne's "Test webhook" as many times as you like.
              <span v-if="testMode" class="font-bold text-yellow-500">
                Turn this off before going live - your gift count will reset to {{ donationsSeedValue ?? 0 }}.
              </span>
            </p>
            <div
              v-if="testMode"
              class="border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-600 dark:text-amber-400"
            >
              Test mode is on. Every incoming webhook fires an alert regardless of duplicate event IDs.
            </div>
          </div>

          <!-- Starting gift count (one-time seed) -->
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Starting gift count</p>
            <p v-if="donationsSeedSet" class="text-sm text-muted-foreground">
              Starting count set to <strong>{{ donationsSeedValue?.toLocaleString(userLocale) }}</strong>.
              Your <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:throne:donations_received]]]</code>
              controls started from this value.
            </p>
            <p v-else class="text-sm text-muted-foreground">
              Had Throne gifts before joining? Set your starting count so your overlay doesn't begin at zero. All your
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">donations_received</code> controls update immediately.
            </p>
            <div class="flex items-start gap-2">
              <div class="flex-1 space-y-1">
                <input
                  v-model.number="seedCount"
                  type="number"
                  min="0"
                  placeholder="e.g. 1256"
                  :disabled="seedLoading"
                  class="input-border"
                />
                <p v-if="seedError" class="text-xs text-destructive">{{ seedError }}</p>
              </div>
              <Button
                type="button"
                variant="outline"
                :disabled="seedLoading || seedCount === null"
                @click="setSeedCount"
              >
                {{ seedLoading ? 'Saving...' : 'Set starting count' }}
              </Button>
            </div>
          </div>

          <!-- Danger zone -->
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Danger zone</p>
            <p class="text-sm text-muted-foreground">
              Disconnecting Throne will remove all Throne-managed controls (gift counts, latest gifter, etc.) from
              your overlays.
            </p>
            <Button variant="destructive" size="sm" type="button" @click="disconnect">
              Disconnect Throne
            </Button>
          </div>
        </template>

        <div v-if="!integration.connected" class="pt-2">
          <Button variant="outline" as-child>
            <Link href="/settings/integrations">Cancel</Link>
          </Button>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
