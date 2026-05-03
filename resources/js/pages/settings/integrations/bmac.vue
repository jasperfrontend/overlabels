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
  settings: { enabled_events?: string[] };
  has_secret: boolean;
  donations_seed_set: boolean;
  donations_seed_value: number | null;
}

const props = defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Buy Me a Coffee', href: '/settings/integrations/bmac' }
];

const EVENT_TYPES = [
  { value: 'donation', label: 'Donations' },
  { value: 'commission', label: 'Commission orders' },
  { value: 'extra', label: 'Extras' },
  { value: 'membership', label: 'Memberships' },
  { value: 'recurring', label: 'Monthly support' },
  { value: 'wishlist', label: 'Wishlist payments' }
];

const DEFAULT_EVENTS = EVENT_TYPES.map((e) => e.value);

const form = useForm({
  webhook_secret: '',
  enabled_events: props.integration.settings?.enabled_events ?? DEFAULT_EVENTS,
  enabled: props.integration.connected ? props.integration.enabled : true
});

const testMode = ref(props.integration.test_mode ?? false);
const testModeLoading = ref(false);

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
    const { data } = await axios.post('/settings/integrations/bmac/seed-count', {
      initial_count: seedCount.value
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

function toggleEvent(eventType: string) {
  const idx = form.enabled_events.indexOf(eventType);
  if (idx >= 0) {
    form.enabled_events.splice(idx, 1);
  } else {
    form.enabled_events.push(eventType);
  }
}

function save() {
  form.post('/settings/integrations/bmac', {
    preserveScroll: true,
    // Clear the secret field so the placeholder ("(secret saved - enter new
    // to replace)") shows on the next render. Without this, the typed value
    // sticks across the round-trip because Inertia preserves component state.
    onSuccess: () => form.reset('webhook_secret'),
  });
}

const submitLabel = computed(() => {
  if (!props.integration.connected) return 'Generate webhook URL';
  if (!props.integration.has_secret) return 'Save webhook secret';
  return 'Save changes';
});

async function toggleTestMode() {
  testModeLoading.value = true;
  try {
    const { data } = await axios.patch('/settings/integrations/bmac/test-mode', {
      test_mode: testMode.value
    });
    testMode.value = data.test_mode;
  } catch {
    testMode.value = !testMode.value;
  } finally {
    testModeLoading.value = false;
  }
}

function disconnect() {
  if (confirm('Disconnect Buy Me a Coffee? This will remove all BMAC controls from your overlays.')) {
    useForm({}).delete('/settings/integrations/bmac');
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
    <Head title="Buy Me a Coffee Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <HeadingSmall
            title="Buy Me a Coffee"
            description="Receive support, commission, extras, membership, and wishlist alerts from Buy Me a Coffee."
          />

          <Badge v-if="integration.connected" variant="default" class="bg-green-400 hover:bg-green-400">Connected
          </Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <div v-if="integration.connected"
             class="rounded-sm border border-border bg-sidebar-accent p-4 mb-6 space-y-2 text-sm text-muted-foreground">
          <p class="font-medium text-foreground">What to do next</p>
          <ol class="list-decimal pl-4 space-y-1">
            <li>
              Go to <a href="/alerts" class="text-violet-400 hover:underline font-medium">Alerts Builder</a>
              to configure which alert template fires for each BMAC event type (Donation, Commission, Membership, etc.).
            </li>
            <li>
              Open any <strong>static</strong> overlay template -> <strong>Controls</strong> tab -> <strong>Add
              control</strong>
              to add BMAC data controls (donation count, latest donor name, etc.) that update live.
            </li>
          </ol>
        </div>

        <form class="space-y-6" @submit.prevent="save">
          <!-- Setup steps - only shown until the integration is fully wired up -->
          <div v-if="!integration.connected || !integration.has_secret" class="rounded-sm border border-violet-500/30 bg-violet-500/5 p-4 space-y-2 text-sm text-muted-foreground">
            <p class="font-medium text-foreground">How to set up BMAC webhooks</p>
            <ol class="list-decimal pl-4 space-y-1">
              <li v-if="!integration.connected">
                Click <strong>Generate webhook URL</strong> at the bottom of this page. Overlabels will generate
                a unique URL for you, then this page will reload showing the URL.
              </li>
              <li v-if="!integration.connected">
                Open <a href="https://studio.buymeacoffee.com/webhooks/" target="_blank" rel="noopener" class="cursor-pointer text-violet-400 hover:underline">studio.buymeacoffee.com/webhooks</a> and click <strong>Create new webhook</strong>. Paste the Overlabels URL into the <strong>Webhook URL</strong> field.
              </li>
              <li v-else>
                Open <a href="https://studio.buymeacoffee.com/webhooks/" target="_blank" rel="noopener" class="cursor-pointer text-violet-400 hover:underline">studio.buymeacoffee.com/webhooks</a> and click <strong>Create new webhook</strong>. Paste the URL above into BMAC's <strong>Webhook URL</strong> field.
              </li>
              <li>Pick the BMAC events you want Overlabels to receive (the same ones you check below).</li>
              <li>BMAC will reveal a <strong>Secret</strong>. Click it to copy, paste it into the field below, then save.</li>
              <li>Use BMAC's <strong>Send Test</strong> button to confirm everything works - the event will appear in <a href="/dashboard/recents" class="cursor-pointer text-violet-400 hover:underline">Recent Events</a>.</li>
            </ol>
          </div>

          <!-- Webhook URL (read-only) - shown as soon as the integration row exists -->
          <div v-if="integration.connected && integration.webhook_url" class="group space-y-2">
            <Label>Your Webhook URL</Label>
            <p class="text-muted-foreground text-sm">
              Paste this URL into the "Webhook URL" field on your BMAC webhook.
            </p>
            <div class="flex">
              <input
                :value="integration.webhook_url ?? ''"
                readonly
                class="peer font-mono text-sm input-border w-full mr-0"
              />
              <button type="button" class="btn btn-sm rounded-none bg-background rounded-r-sm border border-l-0 border-border dark:border-violet-300/30 p-2 px-4 text-sm peer-focus:border-violet-400 peer-focus:bg-background hover:bg-violet-400/40 dark:peer-focus:border-violet-400 hover:ring-0" @click="copyWebhookUrl">
                {{ copied ? 'Copied!' : 'Copy' }}
              </button>
            </div>
          </div>

          <!-- Webhook Secret - only shown once the URL is generated -->
          <div v-if="integration.connected" class="space-y-2">
            <Label for="webhook_secret">Webhook Secret</Label>
            <p class="text-muted-foreground text-sm">
              BMAC reveals this on the webhook page after you create the webhook. It's used to verify
              that incoming webhooks actually came from Buy Me a Coffee.
            </p>
            <input
              id="webhook_secret"
              v-model="form.webhook_secret"
              type="text"
              :placeholder="integration.has_secret ? '(secret saved - enter new to replace)' : 'Paste your webhook secret'"
              autocomplete="off"
              class="input-border w-full rounded-sm"
            />
            <p v-if="form.errors.webhook_secret" class="text-destructive text-sm">
              {{ form.errors.webhook_secret }}
            </p>
          </div>

          <!-- Enabled Event Types -->
          <div class="space-y-2">
            <Label>Alert on</Label>
            <p class="text-muted-foreground text-sm">
              Which BMAC event types should trigger alerts and update controls. Match this with the events
              you selected when creating the webhook on BMAC.
            </p>
            <div class="flex flex-wrap gap-2">
              <Button
                v-for="et in EVENT_TYPES"
                :key="et.value"
                type="button"
                :variant="form.enabled_events.includes(et.value) ? 'default' : 'outline'"
                size="sm"
                class="cursor-pointer"
                @click="toggleEvent(et.value)"
              >
                {{ et.label }}
              </Button>
            </div>
          </div>

          <p v-if="integration.connected" class="text-muted-foreground text-sm">
            Last event received: {{ formatDate(integration.last_received_at) }}
          </p>

          <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing" class="cursor-pointer">
              {{ submitLabel }}
            </Button>
            <Button variant="outline" as-child>
              <Link href="/settings/integrations">Cancel</Link>
            </Button>
          </div>
        </form>

        <template v-if="integration.connected">
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
            <p class="text-muted-foreground text-sm">
              Disables duplicate event detection. Fire the same BMAC test webhook as many times as you like.
              <span v-if="testMode" class="text-yellow-500 font-bold">
                Turn this off before going live - your donation count will reset to {{ donationsSeedValue ?? 0 }}.
              </span>
            </p>
            <div v-if="testMode"
                 class="rounded-sm border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-amber-600 dark:text-amber-400 text-sm">
              Test mode is on. Every incoming webhook fires an alert regardless of duplicate transaction IDs.
            </div>
          </div>
        </template>

        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="font-medium text-sm">Starting donation count</p>

            <template v-if="donationsSeedSet">
              <p class="text-muted-foreground text-sm">
                Starting count set to <strong>{{ donationsSeedValue?.toLocaleString(userLocale) }}</strong>.
                Your <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:bmac:donations_received]]]</code>
                controls started from this value.
              </p>
              <p class="text-muted-foreground text-sm">
                Need to correct it? Email
                <a href="mailto:jasper@emailjasper.com"
                   class="text-violet-400 hover:underline">jasper@emailjasper.com</a>.
              </p>
            </template>

            <template v-else>
              <p class="text-muted-foreground text-sm">
                Had BMAC supporters before joining? Set your starting count so your overlay doesn't begin at zero.
                This can only be set once. All your <code class="rounded bg-black/10 px-1 dark:bg-white/10">donations_received</code>
                controls update immediately.
              </p>
              <div class="flex gap-2 items-start">
                <div class="flex-1 space-y-1">
                  <input
                    v-model.number="seedCount"
                    type="number"
                    min="0"
                    placeholder="e.g. 256"
                    :disabled="seedLoading"
                    class="input-border w-full rounded-sm"
                  />
                  <p v-if="seedError" class="text-destructive text-xs">{{ seedError }}</p>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  class="cursor-pointer"
                  :disabled="seedLoading || seedCount === null"
                  @click="setSeedCount"
                >
                  {{ seedLoading ? 'Saving...' : 'Set starting count' }}
                </Button>
              </div>
            </template>
          </div>
        </template>

        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="font-medium text-sm">Danger zone</p>
            <p class="text-muted-foreground text-sm">
              Disconnecting Buy Me a Coffee will remove all BMAC-managed controls (donation counts, latest donor,
              etc.) from your overlays.
            </p>
            <Button variant="destructive" size="sm" type="button" class="cursor-pointer" @click="disconnect">
              Disconnect Buy Me a Coffee
            </Button>
          </div>
        </template>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
