<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import QRCode from 'qrcode';
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
const qrDataUrl = ref<string | null>(null);

onMounted(async () => {
    if (props.integration.webhook_url) {
        qrDataUrl.value = await QRCode.toDataURL(props.integration.webhook_url, {
            width: 200,
            margin: 2,
            color: { dark: '#000000', light: '#ffffff' },
        });
    }
});

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
                    <p class="font-medium text-foreground">Set up GPSLogger on your phone</p>
                    <ol class="list-decimal pl-4 space-y-2">
                        <li>
                            Open GPSLogger on your Android device and go to
                            <strong>Logging Details</strong> &rarr; <strong>Log to custom URL</strong>.
                        </li>
                        <li>
                            Set the <strong>URL</strong> to the webhook URL shown below (copy it with the button).
                        </li>
                        <li>
                            Set <strong>HTTP Method</strong> to <strong>POST</strong>.
                        </li>
                        <li>
                            Set <strong>HTTP Body</strong> to the following (copy-paste it exactly):
                            <code class="mt-1 block rounded bg-black/10 px-2 py-1 text-xs dark:bg-white/10 select-all break-all">lat=%LAT&amp;lon=%LON&amp;spd=%SPD&amp;alt=%ALT&amp;acc=%ACC&amp;timestamp=%TIMESTAMP&amp;ser=%SER</code>
                        </li>
                        <li>
                            Under <strong>HTTP Headers</strong>, add:<br />
                            <code class="rounded bg-black/10 px-1 dark:bg-white/10">X-GPSLogger-Token: <em>your token</em></code>
                            (the same token you entered on this page).
                        </li>
                        <li>
                            Start logging. Your overlays now have live GPS controls:
                            <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:gpslogger:gps_speed]]]</code>,
                            <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:gpslogger:gps_lat]]]</code>,
                            <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:gpslogger:gps_lng]]]</code>,
                            <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:gpslogger:gps_distance]]]</code>.
                        </li>
                    </ol>
                </div>

                <div v-if="!integration.connected" class="rounded-sm border border-border bg-sidebar-accent p-4 space-y-2 text-sm text-muted-foreground">
                    <p class="font-medium text-foreground">How it works</p>
                    <ol class="list-decimal pl-4 space-y-1">
                        <li>Choose a shared secret token below and hit <strong>Connect GPSLogger</strong>.</li>
                        <li>Install <a href="https://play.google.com/store/apps/details?id=com.mendhak.gpslogger" target="_blank" rel="noopener" class="text-violet-400 hover:underline font-medium">GPSLogger</a> on your Android phone (free, open source).</li>
                        <li>In the app, set up <strong>Log to custom URL</strong> with the webhook URL and token you chose here.</li>
                        <li>Start logging - your overlay controls update live with speed, coordinates, and distance.</li>
                    </ol>
                </div>

                <form class="space-y-6" @submit.prevent="save">
                    <!-- Shared Secret Token -->
                    <div class="space-y-2">
                        <Label for="token">Shared Secret Token <span class="text-violet-400 dark:text-violet-300">(required)</span></Label>
                        <p class="text-muted-foreground text-sm">
                            Make up any password-like string (e.g. <code class="rounded bg-black/10 px-1 dark:bg-white/10">my-stream-gps-2026</code>).
                            You will enter this same string in the GPSLogger app on your phone so
                            Overlabels can verify the data is coming from you. Do this first before proceeding with setup and save this token somewhere safe.<br>
                            It's adviced to write a dash-separated, lowercase string like
                          <code class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-sm text-foreground">my-stream-gps-2026</code>.
                        </p>
                        <Input
                            id="token"
                            v-model="form.token"
                            type="text"
                            :placeholder="integration.has_token ? '(token saved - enter new to replace)' : 'e.g. my-stream-gps-2026'"
                            autocomplete="off"
                        />
                        <p v-if="form.errors.token" class="text-destructive text-sm">
                            {{ form.errors.token }}
                        </p>
                    </div>

                    <!-- Webhook URL (read-only) -->
                    <div v-if="integration.connected && integration.webhook_url" class="space-y-2">
                        <Label for="webhook-url">Your Webhook URL</Label>
                        <p class="text-muted-foreground text-sm">
                            Scan the QR code below on the phone where you have GPSLogger installed to continue setup there.
                        </p>
                        <div class="flex gap-2">
                            <Input
                                id="webhook-url"
                                :model-value="integration.webhook_url ?? ''"
                                readonly
                                class="font-mono text-sm"
                            />
                            <Button type="button" variant="outline" @click="copyWebhookUrl">
                                {{ copied ? 'Copied!' : 'Copy' }}
                            </Button>
                        </div>
                        <div v-if="qrDataUrl" class="pt-2">
                            <img :src="qrDataUrl" alt="Webhook URL QR code" class="rounded-sm border border-border" width="200" height="200" />
                            <p class="text-xs text-muted-foreground pt-1">Scan this with your phone to open the setup instructions.</p>
                        </div>
                    </div>

                    <!-- Speed Unit -->
                    <div class="space-y-2">
                        <Label for="speed_unit">Speed Unit</Label>
                        <p class="text-muted-foreground text-sm">
                            How speed is displayed in your overlays.
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
