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
  last_received_at: string | null;
  settings: Record<string, unknown>;
  has_jwt: boolean;
  donations_seed_set: boolean;
  donations_seed_value: number | null;
}

const props = defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'StreamElements', href: '/settings/integrations/streamelements' }
];

const form = useForm({
  jwt_token: '',
  enabled: props.integration.connected ? props.integration.enabled : true
});

const testMode = ref(props.integration.test_mode ?? false);
const testModeLoading = ref(false);

const seedCount = ref<number | null>(null);
const seedLoading = ref(false);
const seedError = ref<string | null>(null);
const donationsSeedSet = ref(props.integration.donations_seed_set);
const donationsSeedValue = ref(props.integration.donations_seed_value);

function save() {
  form.post('/settings/integrations/streamelements', {
    preserveScroll: true,
    onSuccess: () => {
      form.jwt_token = '';
    }
  });
}

async function setSeedCount() {
  if (seedCount.value === null || seedCount.value < 0) return;
  seedLoading.value = true;
  seedError.value = null;
  try {
    const { data } = await axios.post('/settings/integrations/streamelements/seed-count', {
      initial_count: seedCount.value
    });
    donationsSeedSet.value = data.donations_seed_set;
    donationsSeedValue.value = data.donations_seed_value;
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } };
    seedError.value = err.response?.data?.error ?? 'Something went wrong.';
  } finally {
    seedLoading.value = false;
  }
}

async function toggleTestMode() {
  testModeLoading.value = true;
  try {
    const { data } = await axios.patch('/settings/integrations/streamelements/test-mode', {
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
  if (confirm('Disconnect StreamElements? This will remove all StreamElements-managed controls from your overlays.')) {
    useForm({}).delete('/settings/integrations/streamelements');
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
    <Head title="StreamElements Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <HeadingSmall
            title="StreamElements"
            description="Receive tip alerts and update overlay controls from StreamElements."
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
              to configure which alert template fires for StreamElements donations.
            </li>
            <li>
              Open any <strong>static</strong> overlay template &rarr; <strong>Controls</strong> tab &rarr; <strong>Add
              control</strong>
              to add StreamElements data controls (donation count, latest donor name, etc.) that update live.
            </li>
            <li>
              Enable test mode below, then visit <a href="https://streamelements.com/dashboard/activity"
                                                    class="text-violet-400 hover:underline" target="_blank">your
              StreamElements dashboard</a>
              to fire a few test tips.
            </li>
          </ol>
        </div>

        <form class="space-y-6" @submit.prevent="save">
          <!-- JWT Token -->
          <div class="space-y-2">
            <Label for="jwt_token">StreamElements JWT Token</Label>
            <p class="text-muted-foreground text-sm">
              Find this in <a href="https://streamelements.com/dashboard/account/channels"
                              target="_blank" class="text-violet-400 hover:underline">StreamElements Dashboard</a>
              &rarr; Account &rarr; Channels &rarr; Show secrets &rarr; JWT Token.
            </p>
            <input
              id="jwt_token"
              v-model="form.jwt_token"
              type="password"
              :placeholder="integration.has_jwt ? '(JWT saved - enter new to replace)' : 'Paste your JWT token'"
              autocomplete="off"
              class="input-border w-full rounded-sm"
            />
            <p v-if="form.errors.jwt_token" class="text-destructive text-sm">
              {{ form.errors.jwt_token }}
            </p>
            <p class="text-muted-foreground text-xs">
              Your JWT is stored encrypted and used only to subscribe to your StreamElements event stream.
              Regenerate or revoke it at any time from the StreamElements dashboard.
            </p>
          </div>

          <!-- Last received -->
          <p v-if="integration.connected" class="text-muted-foreground text-sm">
            Last event received: {{ formatDate(integration.last_received_at) }}
          </p>

          <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing || !form.jwt_token">
              {{ integration.connected ? 'Replace JWT' : 'Connect StreamElements' }}
            </Button>
            <Button variant="outline" as-child>
              <Link href="/settings/integrations">Cancel</Link>
            </Button>
          </div>
        </form>

        <!-- Test mode -->
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
              Disables duplicate event detection. Fire the same tip as many times as you like.
              <span v-if="testMode" class="text-yellow-500 font-bold">
                Turn this off before going live - your donation count will reset to {{ donationsSeedValue ?? 0 }}.
              </span>
            </p>
            <div v-if="testMode"
                 class="rounded-sm border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-amber-600 dark:text-amber-400 text-sm">
              Test mode is on. Every incoming event fires an alert regardless of duplicate transaction IDs.
            </div>
          </div>
        </template>

        <!-- Starting donation count (one-time seed) -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="font-medium text-sm">Starting donation count</p>

            <template v-if="donationsSeedSet">
              <p class="text-muted-foreground text-sm">
                Starting count set to <strong>{{ donationsSeedValue?.toLocaleString(userLocale) }}</strong>.
                Your <code
                class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:streamelements:donations_received]]]</code>
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
                Had StreamElements donations before joining? Set your starting count so your overlay doesn't begin at
                zero. This can only be set once. All your
                <code class="rounded bg-black/10 px-1 dark:bg-white/10">donations_received</code>
                controls update immediately.
              </p>
              <div class="flex gap-2 items-start">
                <div class="flex-1 space-y-1">
                  <input
                    v-model.number="seedCount"
                    type="number"
                    min="0"
                    placeholder="e.g. 1256"
                    :disabled="seedLoading"
                  />
                  <p v-if="seedError" class="text-destructive text-xs">{{ seedError }}</p>
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
            </template>
          </div>
        </template>

        <!-- Danger zone -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="font-medium text-sm">Danger zone</p>
            <p class="text-muted-foreground text-sm">
              Disconnecting StreamElements will remove all StreamElements-managed controls (tip counts, latest tipper,
              etc.) from your overlays.
            </p>
            <Button variant="destructive" size="sm" type="button" @click="disconnect">
              Disconnect StreamElements
            </Button>
          </div>
        </template>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
