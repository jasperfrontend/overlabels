<script setup lang="ts">
import { ref } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';
import type { AppPageProps } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
];

const page = usePage<AppPageProps>();
const locale = ref(page.props.auth.user.locale ?? 'en-US');

const LOCALES = [
    { value: 'en-US', label: 'English (US)' },
    { value: 'en-GB', label: 'English (UK)' },
    { value: 'nl-NL', label: 'Nederlands' },
    { value: 'nl-BE', label: 'Nederlands (Belgie)' },
    { value: 'de-DE', label: 'Deutsch' },
    { value: 'fr-FR', label: 'Francais' },
    { value: 'es-ES', label: 'Espanol' },
    { value: 'pt-BR', label: 'Portugues (Brasil)' },
    { value: 'ja-JP', label: 'Japanese' },
    { value: 'ko-KR', label: 'Korean' },
] as const;

function updateLocale(newLocale: string) {
    locale.value = newLocale;
    router.patch(route('settings.locale'), { locale: newLocale }, {
        preserveScroll: true,
    });
}

// Format examples based on current locale
function exampleNumber(): string {
    try { return new Intl.NumberFormat(locale.value).format(1234567.89); } catch { return '1,234,567.89'; }
}
function exampleCurrency(): string {
    try { return new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'USD' }).format(42.5); } catch { return '$42.50'; }
}
function exampleDate(): string {
    try { return new Intl.DateTimeFormat(locale.value, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date()); } catch { return 'Apr 5, 2026'; }
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
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
