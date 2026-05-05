<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
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
    const { data } = await axios.post('/settings/integrations/fourthwall/seed-count', {
      initial_count: seedCount.value,
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
    const { data } = await axios.patch('/settings/integrations/fourthwall/test-mode', {
      test_mode: testMode.value,
    });
    testMode.value = data.test_mode;
  } catch {
    testMode.value = !testMode.value;
  } finally {
    testModeLoading.value = false;
  }
}

function disconnect() {
  if (confirm('Disconnect Fourthwall? This will remove all Fourthwall-managed controls from your overlays.')) {
    useForm({}).delete('/settings/integrations/fourthwall');
  }
}

const page = usePage();
const userLocale = computed<string | undefined>(() => {
  const user = (page.props as { auth?: { user?: { locale?: string } } })?.auth?.user;
  return user?.locale || undefined;
});

const flashSuccess = computed<string | null>(() => {
  return (page.props as { flash?: { success?: string } })?.flash?.success ?? null;
});
const flashError = computed<string | null>(() => {
  return (page.props as { flash?: { error?: string } })?.flash?.error ?? null;
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
        <div class="flex items-center justify-between">
          <HeadingSmall
            title="Fourthwall"
            description="Receive donation alerts and update overlay controls from your Fourthwall shop."
          />
          <Badge v-if="integration.connected" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <div
          v-if="flashError"
          class="border border-destructive/40 bg-destructive/10 px-3 py-2 text-destructive text-sm"
        >
          {{ flashError }}
        </div>
        <div
          v-if="flashSuccess"
          class="border border-green-500/40 bg-green-500/10 px-3 py-2 text-green-600 dark:text-green-400 text-sm"
        >
          {{ flashSuccess }}
        </div>

        <!-- Not connected state -->
        <template v-if="!integration.connected">
          <div class="space-y-4">
            <p class="text-foreground text-sm">
              Connect your Fourthwall shop to receive donation alerts and live-updating controls in your overlays.
              Overlabels will register a webhook on your shop automatically - no manual setup needed.
            </p>
            <Button as-child class="cursor-pointer">
              <a href="/settings/integrations/fourthwall/redirect">Authenticate with Fourthwall</a>
            </Button>
          </div>
        </template>

        <!-- Connected state -->
        <template v-if="integration.connected">
          <div class="border border-sidebar-border bg-sidebar-accent p-4 mb-6 space-y-2 text-sm text-foreground">
            <p class="font-medium">What to do next</p>
            <ol class="list-decimal pl-4 space-y-1 text-muted-foreground">
              <li>
                Go to <a href="/alerts" class="text-violet-400 hover:underline font-medium">Alerts Builder</a>
                to configure which alert template fires for Fourthwall donations.
              </li>
              <li>
                Open any <strong>static</strong> overlay template &rarr; <strong>Controls</strong> tab &rarr;
                <strong>Add control</strong> to add Fourthwall data controls (donation count, latest donor, etc.)
                that update live.
              </li>
              <li>
                Enable test mode below, then fire a test donation from your Fourthwall shop's dashboard to check
                your setup.
              </li>
            </ol>
          </div>

          <p class="text-muted-foreground text-sm">
            Last event received: {{ formatDate(integration.last_received_at) }}
          </p>
        </template>

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
              Disables duplicate event detection. Fire the same donation as many times as you like.
              <span v-if="testMode" class="text-yellow-500 font-bold">
                Turn this off before going live - your donation count will reset to {{ donationsSeedValue ?? 0 }}.
              </span>
            </p>
            <div
              v-if="testMode"
              class="border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-amber-600 dark:text-amber-400 text-sm"
            >
              Test mode is on. Every incoming event fires an alert regardless of duplicate transaction IDs.
            </div>
          </div>
        </template>

        <!-- Starting donation count -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="font-medium text-sm">Starting donation count</p>

            <template v-if="donationsSeedSet">
              <p class="text-foreground text-sm">
                Starting count set to <strong>{{ donationsSeedValue?.toLocaleString(userLocale) }}</strong>.
                Your <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:fourthwall:donations_received]]]</code>
                controls started from this value.
              </p>
              <div class="flex gap-2 items-start">
                <div class="flex-1 space-y-1">
                  <input
                    v-model.number="seedCount"
                    type="number"
                    min="0"
                    placeholder="e.g. 1256"
                    :disabled="seedLoading"
                    class="input-border"
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

            <template v-else>
              <p class="text-foreground text-sm">
                Had Fourthwall donations before joining? Set your starting count so your overlay doesn't begin at zero.
                This can only be set once. All your
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
                    class="input-border"
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

        <!-- Danger zone -->
        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="font-medium text-sm">Danger zone</p>
            <p class="text-muted-foreground text-sm">
              Disconnecting Fourthwall will remove the webhook from your Fourthwall shop and delete all
              Fourthwall-managed controls (donation counts, latest donor, etc.) from your overlays.
            </p>
            <Button variant="destructive" size="sm" type="button" class="cursor-pointer" @click="disconnect">
              Disconnect Fourthwall
            </Button>
          </div>
        </template>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
