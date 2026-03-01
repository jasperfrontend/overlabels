<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';

interface ServiceInfo {
    key: string;
    name: string;
    connected: boolean;
    enabled: boolean;
    last_received_at: string | null;
}

const props = defineProps<{
    services: ServiceInfo[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Integrations',
        href: '/settings/integrations',
    },
];

function formatDate(iso: string | null): string {
    if (!iso) return 'Never';
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Integrations" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="External Integrations"
                    description="Connect external donation and support platforms to power your overlays."
                />

                <div class="space-y-4">
                    <div
                        v-for="service in services"
                        :key="service.key"
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ service.name }}</span>
                                <Badge v-if="service.connected" variant="default">Connected</Badge>
                                <Badge v-else variant="secondary">Not connected</Badge>
                            </div>
                            <p v-if="service.connected" class="text-muted-foreground text-sm">
                                Last event: {{ formatDate(service.last_received_at) }}
                            </p>
                        </div>

                        <Button v-if="service.key === 'kofi'" variant="outline" as-child>
                            <Link :href="`/settings/integrations/${service.key}`">
                                {{ service.connected ? 'Manage' : 'Connect' }}
                            </Link>
                        </Button>
                        <span v-else class="text-muted-foreground text-sm">Coming soon</span>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
