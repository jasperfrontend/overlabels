<script setup lang="ts">
import { reactive, ref } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem, type ForeachCaps } from '@/types';
import type { AppPageProps } from '@/types';
import { getDefaultCurrency } from '@/utils/formatters';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard'
  },
  {
    title: 'Appearance settings',
    href: '/settings/appearance'
  }
];

const FOREACH_CAP_MAX = 50;
const FOREACH_CAP_DEFAULTS: ForeachCaps = {
  subscribers: 10,
  goals: 3,
  followers: 5,
  followed: 5,
};

const page = usePage<AppPageProps>();
const locale = ref(page.props.auth.user.locale ?? 'en-US');
const foreachCaps = reactive<ForeachCaps>({
  ...FOREACH_CAP_DEFAULTS,
  ...(page.props.auth.user.foreach_caps ?? {}),
});
const showConfirmation = ref(false);
const confirmationTitle = ref('');
const foreachSaving = ref(false);
const foreachConfirmation = ref('');

const LOCALES = [
  { value: 'en-US', label: 'English (US)' },
  { value: 'en-GB', label: 'English (UK)' },
  { value: 'nl-NL', label: 'Nederlands' },
  { value: 'nl-BE', label: 'Nederlands (België)' },
  { value: 'de-DE', label: 'Deutsch' },
  { value: 'fr-FR', label: 'Français' },
  { value: 'es-ES', label: 'Español' },
  { value: 'pt-BR', label: 'Português (Brasil)' },
  { value: 'ja-JP', label: 'Japanese' },
  { value: 'ko-KR', label: 'Korean' }
] as const;

function updateLocale(newLocale: string) {
  locale.value = newLocale;
  showConfirmation.value = false; // close any previous confirmation first
  try {
    router.patch(route('settings.locale'), { locale: newLocale }, {
      preserveScroll: true
    });
  } catch (error) {
    showConfirmation.value = true;
    confirmationTitle.value = 'Locale updated failed. Please try again. If the error persists, log out and back in again.';
  } finally {
    showConfirmation.value = true;
    confirmationTitle.value = `locale updated to ${newLocale}`;
    setTimeout(() => {
      showConfirmation.value = false;
    }, 5000)
  }
}

// Format examples based on current locale
function exampleNumber(): string {
  try {
    return new Intl.NumberFormat(locale.value).format(1234567.89);
  } catch {
    return '1,234,567.89';
  }
}

function exampleCurrency(): string {
  try {
    return new Intl.NumberFormat(locale.value, {
      style: 'currency',
      currency: getDefaultCurrency(locale.value)
    }).format(42.5);
  } catch {
    return '$42.50';
  }
}

function exampleDate(): string {
  try {
    return new Intl.DateTimeFormat(locale.value, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    }).format(new Date());
  } catch {
    return 'Apr 5, 2026';
  }
}

const FOREACH_CAP_FIELDS: { key: keyof ForeachCaps; label: string; hint: string }[] = [
  { key: 'subscribers', label: 'Subscribers', hint: 'Items available in [[[foreach:subscribers as s]]]' },
  { key: 'goals', label: 'Goals', hint: 'Items available in [[[foreach:goals as g]]]' },
  { key: 'followers', label: 'Followers', hint: 'Items available in [[[foreach:channel_followers as f]]]' },
  { key: 'followed', label: 'Followed channels', hint: 'Items available in [[[foreach:followed_channels as f]]]' },
];

function clampCap(value: number | string): number {
  const n = typeof value === 'string' ? parseInt(value, 10) : value;
  if (!Number.isFinite(n)) return 1;
  return Math.max(1, Math.min(FOREACH_CAP_MAX, Math.trunc(n)));
}

function saveForeachCaps() {
  for (const field of FOREACH_CAP_FIELDS) {
    foreachCaps[field.key] = clampCap(foreachCaps[field.key]);
  }

  foreachSaving.value = true;
  foreachConfirmation.value = '';

  router.patch(route('settings.foreach-caps'), { ...foreachCaps }, {
    preserveScroll: true,
    onSuccess: () => {
      foreachConfirmation.value = 'Foreach loop limits updated.';
      setTimeout(() => { foreachConfirmation.value = ''; }, 5000);
    },
    onError: () => {
      foreachConfirmation.value = 'Saving failed. Values must be 1 to 50.';
    },
    onFinish: () => {
      foreachSaving.value = false;
    },
  });
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Appearance settings" />

    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall title="Appearance settings" description="Update your account's appearance settings" />
        <AppearanceTabs />
      </div>

      <div class="space-y-6">
        <HeadingSmall title="Formatting locale" description="Controls how numbers, currencies, and dates are formatted in your overlays." />

        <div class="space-y-3">
          <select
            :value="locale"
            @change="updateLocale(($event.target as HTMLSelectElement).value)"
            class="input-border h-10 w-full max-w-xs rounded-sm"
          >
            <option v-for="loc in LOCALES" :key="loc.value" :value="loc.value">
              {{ loc.label }}
            </option>
          </select>
          <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted-foreground">
            <span>Number: <strong class="text-foreground">{{ exampleNumber() }}</strong></span>
            <span>Currency: <strong class="text-foreground">{{ exampleCurrency() }}</strong></span>
            <span>Date: <strong class="text-foreground">{{ exampleDate() }}</strong></span>
          </div>
        </div>
        <div
          v-if="showConfirmation"
          class="w-auto inline-flex p-1 gap-2 items-center rounded-sm"
        >
          <p class="text-sm text-green-600 dark:text-green-300">{{ confirmationTitle }}</p>
        </div>
      </div>

      <div class="space-y-6">
        <HeadingSmall
          title="Foreach loop limits"
          description="How many items each [[[foreach:...]]] loop expands to in your overlays. Hard maximum is 50 per loop."
        />

        <div class="grid gap-4 sm:grid-cols-2 max-w-xl">
          <div v-for="field in FOREACH_CAP_FIELDS" :key="field.key" class="space-y-1">
            <label :for="`cap-${field.key}`" class="text-sm text-foreground">
              {{ field.label }}
            </label>
            <input
              :id="`cap-${field.key}`"
              v-model.number="foreachCaps[field.key]"
              type="number"
              min="1"
              :max="FOREACH_CAP_MAX"
              :placeholder="String(FOREACH_CAP_DEFAULTS[field.key])"
              class="input-border h-10 w-full rounded-sm px-3"
            />
            <p class="text-xs text-muted-foreground">{{ field.hint }}</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button
            type="button"
            :disabled="foreachSaving"
            class="cursor-pointer rounded-sm border border-border bg-primary px-4 h-10 text-sm text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
            @click="saveForeachCaps"
          >
            {{ foreachSaving ? 'Saving...' : 'Save loop limits' }}
          </button>
          <p v-if="foreachConfirmation" class="text-sm text-green-600 dark:text-green-300">
            {{ foreachConfirmation }}
          </p>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
