<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import UserIconPicker from '@/components/UserIconPicker.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

defineProps<{ userIcon: string }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Appearance settings" description="Update your account's appearance settings" />
                <AppearanceTabs />

                <div class="space-y-2">
                    <HeadingSmall title="Dashboard icon" description="The icon shown next to your name on the dashboard. Click to change it." />
                    <div class="flex items-center gap-3">
                        <UserIconPicker :user-icon="userIcon" />
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
