<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm, Link, usePage, router } from '@inertiajs/vue3';
import { TOWER_PRESETS } from '@/components/controls/controlPresets';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

interface IntegrationData {
  connected: boolean;
  enabled: boolean;
  tower_lifetime: 'per_stream' | 'persistent';
  cooldown_seconds: number;
  last_received_at: string | null;
  height: number;
  record: number;
}

const props = defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Chat Tower', href: '/settings/integrations/tower' },
];

const form = useForm({
  enabled: props.integration.connected ? props.integration.enabled : true,
  tower_lifetime: props.integration.tower_lifetime ?? 'per_stream',
  cooldown_seconds: props.integration.cooldown_seconds ?? 30,
});

function save() {
  form.post('/settings/integrations/tower', { preserveScroll: true });
}

async function resetTower() {
  if (
    await confirm({
      message: 'Knock the tower down? The blocks go, the record and the counters stay.',
      confirmLabel: 'Clear the tower',
    })
  ) {
    router.post('/settings/integrations/tower/reset', {}, { preserveScroll: true });
  }
}

async function disconnect() {
  if (
    await confirm({
      message:
        'Disconnect Chat Tower? This removes the tower controls from your overlays and switches !stack off. The standing blocks are kept and come back if you reconnect.',
      confirmLabel: 'Disconnect',
    })
  ) {
    useForm({}).delete('/settings/integrations/tower');
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
    <Head title="Chat Tower Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <HeadingSmall
            title="Chat Tower"
            description="Chat stacks one shared tower with !stack. Every block leans a little, the tower sways more the taller it gets, and when it falls the viewer who placed the last block gets named."
          />
          <Badge v-if="integration.connected" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Not connected: explain what this does -->
        <div v-if="!integration.connected" class="space-y-2 border border-sidebar-border bg-sidebar-accent p-4 text-sm text-foreground">
          <p class="font-medium">How it works</p>
          <ol class="list-decimal space-y-1 pl-4">
            <li>Click <strong>Connect Chat Tower</strong> below. The Overlabels bot must be enabled in your channel.</li>
            <li>
              Viewers type <code class="rounded bg-black/10 px-1 dark:bg-white/10">!stack</code> to put a block on top, or
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">!stack left</code> and
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">!stack right</code> to aim it.
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">!tower</code> says how it is going.
            </li>
            <li>
              Render it with the tower tags and
              <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[foreach:tower as block]]]</code> in your overlays, or install the Chat Tower
              product and get the overlay ready-made.
            </li>
          </ol>
        </div>

        <!-- Connected: available tags -->
        <div v-if="integration.connected" class="text-md flex flex-col gap-4 border border-sidebar bg-sidebar-accent p-6">
          <div>
            <p class="text-sm font-medium text-foreground">Overlay controls</p>
            <p class="mt-1 text-sm text-muted-foreground">Use these tags in your overlay templates:</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
              <div v-for="preset in TOWER_PRESETS" :key="preset.key" class="space-y-1">
                <p class="text-sm font-medium text-foreground">{{ preset.label }}</p>
                <p class="text-xs text-muted-foreground">
                  Type: <span class="font-mono">{{ preset.type }}</span>
                </p>
                <code class="rounded bg-black/10 px-1 text-sm dark:bg-white/10">[[[c:tower:{{ preset.key }}]]]</code>
              </div>
            </div>
          </div>
          <p class="text-sm text-muted-foreground">
            <template v-if="integration.height > 0">The tower stands {{ integration.height }} high right now.</template>
            <template v-else>No tower standing right now.</template>
            <template v-if="integration.record > 0"> The record is {{ integration.record }}.</template>
          </p>
        </div>

        <!-- Settings form -->
        <form class="space-y-6" @submit.prevent="save">
          <div class="space-y-2">
            <Label for="tower_lifetime">Tower lifetime</Label>
            <p class="text-sm text-muted-foreground">
              Per stream knocks the tower down when you go live, so every stream starts from the ground. Persistent leaves it standing between
              streams. The record carries over either way.
            </p>
            <select
              id="tower_lifetime"
              v-model="form.tower_lifetime"
              class="w-full border border-sidebar-border bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none"
            >
              <option value="per_stream">Per stream (fresh tower at go-live)</option>
              <option value="persistent">Persistent (the tower stays up)</option>
            </select>
          </div>

          <div class="space-y-2">
            <Label for="cooldown_seconds">Per-viewer cooldown (seconds)</Label>
            <p class="text-sm text-muted-foreground">
              How long a viewer waits before !stack works for them again. One person cannot build or topple it alone.
            </p>
            <Input id="cooldown_seconds" v-model.number="form.cooldown_seconds" type="number" min="5" max="600" class="w-32" />
            <p v-if="form.errors.cooldown_seconds" class="text-sm text-destructive">{{ form.errors.cooldown_seconds }}</p>
          </div>

          <p v-if="integration.connected" class="text-sm text-muted-foreground">
            Last block received: {{ formatDate(integration.last_received_at) }}
          </p>

          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ integration.connected ? 'Save changes' : 'Connect Chat Tower' }}
            </button>
            <Link href="/settings/integrations" class="btn btn-chill">Cancel</Link>
          </div>
        </form>

        <template v-if="integration.connected">
          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Clear the tower</p>
            <p class="text-sm text-muted-foreground">Knocks the standing tower down without naming anyone. The record and the counters stay.</p>
            <button type="button" class="btn btn-chill" @click="resetTower">Clear the tower</button>
          </div>

          <Separator />
          <div class="space-y-2">
            <p class="text-sm font-medium">Danger zone</p>
            <p class="text-sm text-muted-foreground">
              Disconnecting removes the tower controls from your overlays and switches !stack off. The standing blocks are kept and come back if you
              reconnect.
            </p>
            <button type="button" @click="disconnect" class="btn btn-danger">Disconnect Chat Tower</button>
          </div>
        </template>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
