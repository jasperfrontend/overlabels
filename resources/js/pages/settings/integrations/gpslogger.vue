<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';

interface IntegrationData {
    connected: boolean;
    enabled: boolean;
    webhook_url: string | null;
    last_received_at: string | null;
    speed_unit: string;
    has_token: boolean;
}

const props = defineProps<{
    integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Integrations', href: '/settings/integrations' },
    { title: 'GPSLogger', href: '/settings/integrations/gpslogger' },
];

const form = useForm({
    token: '',
    speed_unit: props.integration.speed_unit ?? 'kmh',
    enabled: props.integration.connected ? props.integration.enabled : true,
});

const copied = ref(false);
const resetting = ref(false);

function copyWebhookUrl() {
    if (!props.integration.webhook_url) return;
    navigator.clipboard.writeText(props.integration.webhook_url).then(() => {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    });
}

function save() {
    form.post('/settings/integrations/gpslogger', {
        preserveScroll: true,
    });
}

async function resetDistance() {
    if (!confirm('Reset distance to 0? This cannot be undone.')) return;
    resetting.value = true;
    try {
        await axios.post('/settings/integrations/gpslogger/reset-distance');
    } finally {
        resetting.value = false;
    }
}

function disconnect() {
    if (confirm('Disconnect GPSLogger? This will remove all GPS controls from your overlays.')) {
        useForm({}).delete('/settings/integrations/gpslogger');
    }
}

function formatDate(iso: string | null): string {
    if (!iso) return 'Never';
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="GPSLogger Integration" />

        <SettingsLayout>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        title="GPSLogger"
                        description="Receive live GPS data from GPSLogger for Android and display speed, coordinates, and distance in your overlays."
                    />

                    <Badge v-if="integration.connected" variant="default" class="bg-green-400 hover:bg-green-400">Connected</Badge>
                    <Badge v-else variant="secondary">Not connected</Badge>
                </div>

                <div v-if="integration.connected" class="rounded-sm border border-border bg-sidebar-accent p-4 mb-6 space-y-2 text-sm text-muted-foreground">
                    <p class="font-medium text-foreground">What to do next</p>
                    <ol class="list-decimal pl-4 space-y-1">
                        <li>Copy the webhook URL below and paste it into GPSLogger's custom URL logging settings.</li>
                        <li>
                            Add the header <code class="rounded bg-black/10 px-1 dark:bg-white/10">X-GPSLogger-Token</code>
                            with the same token you entered here.
                        </li>
                        <li>
                            Your overlays now have GPS controls:
                            <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:gpslogger:gps_speed]]]</code>,
                            <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:gpslogger:gps_distance]]]</code>, etc.
                        </li>
                    </ol>
                </div>

                <form class="space-y-6" @submit.prevent="save">
                    <!-- Shared Secret Token -->
                    <div class="space-y-2">
                        <Label for="token">Shared Secret Token</Label>
                        <p class="text-muted-foreground text-sm">
                            Choose a secret token. You will add this same token as the
                            <code class="rounded bg-black/10 px-1 dark:bg-white/10">X-GPSLogger-Token</code>
                            header in GPSLogger's custom URL settings.
                        </p>
                        <Input
                            id="token"
                            v-model="form.token"
                            type="text"
                            :placeholder="integration.has_token ? '(token saved - enter new to replace)' : 'Enter a shared secret token'"
                            autocomplete="off"
                        />
                        <p v-if="form.errors.token" class="text-destructive text-sm">
                            {{ form.errors.token }}
                        </p>
                    </div>

                    <!-- Webhook URL (read-only) -->
                    <div v-if="integration.connected && integration.webhook_url" class="space-y-2">
                        <Label>Your Webhook URL</Label>
                        <p class="text-muted-foreground text-sm">
                            Paste this URL into GPSLogger's custom URL logging target.
                        </p>
                        <div class="flex gap-2">
                            <Input
                                :model-value="integration.webhook_url ?? ''"
                                readonly
                                class="font-mono text-sm"
                            />
                            <Button type="button" variant="outline" @click="copyWebhookUrl">
                                {{ copied ? 'Copied!' : 'Copy' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Speed Unit -->
                    <div class="space-y-2">
                        <Label for="speed_unit">Speed Unit</Label>
                        <p class="text-muted-foreground text-sm">
                            GPSLogger sends speed in m/s. Choose your preferred display unit.
                        </p>
                        <select
                            id="speed_unit"
                            v-model="form.speed_unit"
                            class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
                        >
                            <option value="kmh">km/h</option>
                            <option value="mph">mph</option>
                        </select>
                    </div>

                    <!-- Last received -->
                    <p v-if="integration.connected" class="text-muted-foreground text-sm">
                        Last event received: {{ formatDate(integration.last_received_at) }}
                    </p>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ integration.connected ? 'Save changes' : 'Connect GPSLogger' }}
                        </Button>
                        <Button variant="outline" as-child>
                            <Link href="/settings/integrations">Cancel</Link>
                        </Button>
                    </div>
                </form>

                <!-- Reset Distance -->
                <template v-if="integration.connected">
                    <Separator />
                    <div class="space-y-2">
                        <p class="font-medium text-sm">Reset distance</p>
                        <p class="text-muted-foreground text-sm">
                            Reset the accumulated GPS distance back to 0 km. Useful at the start of a new trip or stream.
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            :disabled="resetting"
                            @click="resetDistance"
                        >
                            {{ resetting ? 'Resetting...' : 'Reset distance to 0' }}
                        </Button>
                    </div>
                </template>

                <!-- Danger zone -->
                <template v-if="integration.connected">
                    <Separator />
                    <div class="space-y-2">
                        <p class="font-medium text-sm">Danger zone</p>
                        <p class="text-muted-foreground text-sm">
                            Disconnecting GPSLogger will remove all GPS controls (speed, coordinates, distance)
                            from your overlays.
                        </p>
                        <Button variant="destructive" size="sm" type="button" @click="disconnect">
                            Disconnect GPSLogger
                        </Button>
                    </div>
                </template>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
