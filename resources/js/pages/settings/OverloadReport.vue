<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import HeadingSmall from '@/components/HeadingSmall.vue';
import SettingSwitch from '@/components/SettingSwitch.vue';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Save } from '@lucide/vue';

interface ReportSettings {
  enabled: boolean;
  threshold: number;
  quiet_seconds: number;
  events: Record<string, boolean>;
  test_mode: boolean;
  /** ISO time test mode switches itself off, while it is on. */
  test_until: string | null;
}

const props = defineProps<{
  settings: ReportSettings;
  kinds: { key: string; label: string }[];
  bot: 'off' | 'modded' | 'not_modded' | 'unknown';
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Overload Report', href: '/settings/overload-report' },
];

const enabled = ref(props.settings.enabled);
const threshold = ref(props.settings.threshold);
const quietSeconds = ref(props.settings.quiet_seconds);
const events = ref<Record<string, boolean>>({ ...props.settings.events });
const testMode = ref(props.settings.test_mode);

watch(
  () => props.settings,
  (saved) => {
    enabled.value = saved.enabled;
    threshold.value = saved.threshold;
    quietSeconds.value = saved.quiet_seconds;
    events.value = { ...saved.events };
    testMode.value = saved.test_mode;
  },
);

const saving = ref(false);
const errors = ref<Record<string, string>>({});

function testUntilLabel(iso: string | null): string {
  if (!iso) return '';
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Success is the session flash the route sets, shown by AppLayout's toast.
function save() {
  saving.value = true;
  errors.value = {};

  router.patch(
    route('settings.overload-report.update'),
    {
      enabled: enabled.value,
      threshold: threshold.value,
      quiet_seconds: quietSeconds.value,
      events: events.value,
      test_mode: testMode.value,
    },
    {
      preserveScroll: true,
      onError: (fieldErrors) => {
        errors.value = fieldErrors;
      },
      onFinish: () => {
        saving.value = false;
      },
    },
  );
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Overload Report settings" />

    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Overlabels Overload Report"
          description="When your stream gets busy, Overlabels makes a page with everything that happened, in order. Numbers and times only, never a viewer's name."
        />

        <label class="flex cursor-pointer items-start gap-3">
          <SettingSwitch v-model="enabled" class="mt-0.5" />
          <span class="text-sm">
            <span class="font-medium">Make Overload Reports</span>
            <span class="block text-muted-foreground">
              Only while you are live. Each report is kept for 90 days and listed on your dashboard. A moderator can switch them off from chat with
              <code class="font-mono">!ol report stop</code>.
            </span>
          </span>
        </label>

        <div class="grid gap-6 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="threshold">Events for a report</Label>
            <input id="threshold" v-model.number="threshold" type="number" min="5" max="100000" class="input-border w-full max-w-40 tabular-nums" />
            <p class="text-sm text-muted-foreground">
              A busy stretch with at least this many events becomes a report. A gift bomb of 10 counts as 10.
            </p>
            <p v-if="errors.threshold" class="text-sm text-destructive">{{ errors.threshold }}</p>
          </div>
          <div class="space-y-2">
            <Label for="quiet">Quiet spell (seconds)</Label>
            <input id="quiet" v-model.number="quietSeconds" type="number" min="10" max="600" class="input-border w-full max-w-40 tabular-nums" />
            <p class="text-sm text-muted-foreground">A busy stretch ends after this long without a counted event.</p>
            <p v-if="errors.quiet_seconds" class="text-sm text-destructive">{{ errors.quiet_seconds }}</p>
          </div>
        </div>

        <fieldset class="space-y-3">
          <legend class="mb-2 text-sm font-medium">What counts</legend>
          <label v-for="kind in props.kinds" :key="kind.key" class="flex cursor-pointer items-center gap-3 text-sm">
            <SettingSwitch v-model="events[kind.key]" />
            {{ kind.label }}
          </label>
          <p class="text-sm text-muted-foreground">A hype train counts once; the subs and bits inside it count as themselves.</p>
        </fieldset>

        <div class="space-y-2">
          <h3 class="text-sm font-medium">The link in chat</h3>
          <p v-if="props.bot === 'modded'" class="text-sm text-foreground">
            @overlabels is a moderator in your channel, so it posts each report's link in chat.
          </p>
          <p v-else-if="props.bot === 'not_modded'" class="text-sm text-foreground">
            Type <code class="font-mono">/mod overlabels</code> in your chat so the bot can post each report's link. Until then, reports are on your
            dashboard only.
          </p>
          <p v-else-if="props.bot === 'unknown'" class="text-sm text-foreground">
            We cannot check right now whether @overlabels is a moderator in your channel. If it is, it posts each report's link in chat.
          </p>
          <p v-else class="text-sm text-foreground">The Overlabels bot is off, so reports are on your dashboard only.</p>
        </div>

        <label class="flex cursor-pointer items-start gap-3">
          <SettingSwitch v-model="testMode" class="mt-0.5" />
          <span class="text-sm">
            <span class="font-medium">Test mode</span>
            <span class="block text-muted-foreground">
              Makes reports while you are offline too, for testing with the Twitch CLI. Switches itself off after an hour.
              <template v-if="props.settings.test_mode && props.settings.test_until"
                >On until {{ testUntilLabel(props.settings.test_until) }}.</template
              >
            </span>
          </span>
        </label>

        <button type="button" :disabled="saving" class="btn btn-primary cursor-pointer disabled:cursor-not-allowed disabled:opacity-60" @click="save">
          <Save class="mr-2 size-4" />
          {{ saving ? 'Saving...' : 'Save Overload Report settings' }}
        </button>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
