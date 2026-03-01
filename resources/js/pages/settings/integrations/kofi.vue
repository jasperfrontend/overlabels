<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
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
    settings: { enabled_events?: string[] };
    has_token: boolean;
}

const props = defineProps<{
    integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Integrations', href: '/settings/integrations' },
    { title: 'Ko-fi', href: '/settings/integrations/kofi' },
];

const EVENT_TYPES = [
    { value: 'donation', label: 'Donations' },
    { value: 'subscription', label: 'Subscriptions' },
    { value: 'shop_order', label: 'Shop Orders' },
    { value: 'commission', label: 'Commissions' },
];

const form = useForm({
    verification_token: '',
    enabled_events: props.integration.settings?.enabled_events ?? ['donation', 'subscription', 'shop_order'],
    enabled: props.integration.connected ? props.integration.enabled : true,
});

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
    form.post('/settings/integrations/kofi', {
        preserveScroll: true,
    });
}

function disconnect() {
    if (confirm('Disconnect Ko-fi? This will remove all Ko-fi controls from your overlays.')) {
        useForm({}).delete('/settings/integrations/kofi');
    }
}

function formatDate(iso: string | null): string {
    if (!iso) return 'Never';
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Ko-fi Integration" />

        <SettingsLayout>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        title="Ko-fi"
                        description="Receive donation alerts and update overlay controls from Ko-fi."
                    />
                    <Badge v-if="integration.connected" variant="default">Connected</Badge>
                    <Badge v-else variant="secondary">Not connected</Badge>
                </div>

                <form class="space-y-6" @submit.prevent="save">
                    <!-- Verification Token -->
                    <div class="space-y-2">
                        <Label for="verification_token">Ko-fi Verification Token</Label>
                        <p class="text-muted-foreground text-sm">
                            Find this in Ko-fi → My Page → API → Verification Token.
                        </p>
                        <Input
                            id="verification_token"
                            v-model="form.verification_token"
                            type="text"
                            :placeholder="integration.has_token ? '(token saved — enter new to replace)' : 'Paste your verification token'"
                            autocomplete="off"
                        />
                        <p v-if="form.errors.verification_token" class="text-destructive text-sm">
                            {{ form.errors.verification_token }}
                        </p>
                    </div>

                    <!-- Webhook URL (read-only) -->
                    <div v-if="integration.connected && integration.webhook_url" class="space-y-2">
                        <Label>Your Webhook URL</Label>
                        <p class="text-muted-foreground text-sm">
                            Paste this URL into Ko-fi → My Page → API → Webhook URL.
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

                    <!-- Enabled Event Types -->
                    <div class="space-y-2">
                        <Label>Alert on</Label>
                        <p class="text-muted-foreground text-sm">
                            Which Ko-fi event types should trigger alerts and update controls.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="et in EVENT_TYPES"
                                :key="et.value"
                                type="button"
                                :variant="form.enabled_events.includes(et.value) ? 'default' : 'outline'"
                                size="sm"
                                @click="toggleEvent(et.value)"
                            >
                                {{ et.label }}
                            </Button>
                        </div>
                    </div>

                    <!-- Last received -->
                    <p v-if="integration.connected" class="text-muted-foreground text-sm">
                        Last event received: {{ formatDate(integration.last_received_at) }}
                    </p>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ integration.connected ? 'Save changes' : 'Connect Ko-fi' }}
                        </Button>
                        <Button variant="outline" as-child>
                            <Link href="/settings/integrations">Cancel</Link>
                        </Button>
                    </div>
                </form>

                <!-- Danger zone -->
                <template v-if="integration.connected">
                    <Separator />
                    <div class="space-y-2">
                        <p class="font-medium text-sm">Danger zone</p>
                        <p class="text-muted-foreground text-sm">
                            Disconnecting Ko-fi will remove all Ko-fi-managed controls (donation counts, latest donor,
                            etc.) from your overlays.
                        </p>
                        <Button variant="destructive" size="sm" type="button" @click="disconnect">
                            Disconnect Ko-fi
                        </Button>
                    </div>
                </template>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
