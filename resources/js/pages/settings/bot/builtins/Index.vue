<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import CollectionFilter from '@/components/CollectionFilter.vue';
import ProviderIcon from '@/components/ProviderIcon.vue';
import { Switch } from '@/components/ui/switch';
import { useCollectionFilter } from '@/composables/useCollectionFilter';
import { serviceLabel } from '@/utils/services';
import { type BreadcrumbItem } from '@/types';
import { Lock, Terminal } from '@lucide/vue';

interface BotBuiltin {
  id: number;
  command: string;
  permission_level: string;
  enabled: boolean;
  owner: 'user' | 'product' | 'overlabels';
  service: string | null;
  editable: boolean;
}

const props = defineProps<{
  builtins: BotBuiltin[];
  permissionLevels: string[];
  botEnabled: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Built-in commands', href: '/settings/bot/builtins' },
];

// The bang is matched rather than stripped, so both `age` and `!age` hit.
const { query, filtered } = useCollectionFilter<BotBuiltin>(
  () => props.builtins,
  (builtin, q) => `!${builtin.command}`.toLowerCase().includes(q),
);

const yours = computed(() => filtered.value.filter((b) => b.owner === 'user'));
const productOwned = computed(() => filtered.value.filter((b) => b.owner === 'product'));
const platformOwned = computed(() => filtered.value.filter((b) => b.owner === 'overlabels'));

// No local copy of the rows: the switch and the tier read straight off the
// props, and the PATCH redirects back so Inertia hands them back updated.
// Nothing to keep in step, and nothing that can disagree with the server.
function save(builtin: BotBuiltin, patch: Partial<Pick<BotBuiltin, 'enabled' | 'permission_level'>>) {
  router.patch(
    `/settings/bot/builtins/${builtin.id}`,
    {
      enabled: builtin.enabled,
      permission_level: builtin.permission_level,
      ...patch,
    },
    { preserveScroll: true },
  );
}

function onTier(builtin: BotBuiltin, event: Event) {
  save(builtin, { permission_level: (event.target as HTMLSelectElement).value });
}
</script>

<template>
  <Head>
    <title>Built-in commands</title>
  </Head>

  <AppLayout :breadcrumbs="breadcrumbItems">
    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Built-in commands"
          description="The commands the bot already knows. Switch off the ones you don't want in your chat, and set who is allowed to use the rest."
        />

        <div v-if="!props.botEnabled" class="border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
          <p class="text-foreground">The Overlabels bot isn't enabled yet. These settings are saved, but nothing fires until the bot is on.</p>
          <Link href="/settings/integrations/bot" class="mt-2 inline-block cursor-pointer underline hover:text-amber-400">
            Switch the bot on -&gt;
          </Link>
        </div>

        <p class="text-sm text-muted-foreground">
          Not sure what one of these does?
          <a href="/help/bot/commands" target="_blank" class="cursor-pointer underline hover:text-foreground">Read what each command does -&gt;</a>
        </p>

        <CollectionFilter v-if="props.builtins.length > 0" v-model="query" noun="command" placeholder="Filter commands..." />

        <div v-if="props.builtins.length === 0" class="border border-sidebar-border p-8 text-center">
          <Terminal class="mx-auto size-10 text-foreground/40" />
          <p class="mt-4 text-foreground">No built-in commands are registered for your channel yet.</p>
          <p class="mt-1 text-sm text-foreground/70">They are set up the first time you enable the bot.</p>
        </div>

        <p v-else-if="filtered.length === 0" class="py-8 text-center text-sm text-muted-foreground">No commands match "{{ query }}"</p>

        <template v-else>
          <!-- Yours: the whole point of the page. -->
          <div v-if="yours.length > 0" class="space-y-3">
            <p class="text-sm font-medium text-foreground">Yours to set</p>
            <div
              v-for="builtin in yours"
              :key="builtin.id"
              class="flex flex-col gap-3 border border-sidebar-border p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <code class="bg-muted px-2 py-0.5 font-mono text-sm">!{{ builtin.command }}</code>

              <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-foreground/70">
                  <span>Who can use it</span>
                  <select
                    :value="builtin.permission_level"
                    class="cursor-pointer border border-sidebar-border bg-background px-2 py-1 text-sm text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none"
                    :aria-label="`Permission level for !${builtin.command}`"
                    @change="onTier(builtin, $event)"
                  >
                    <option v-for="level in props.permissionLevels" :key="level" :value="level">{{ level }}</option>
                  </select>
                </label>

                <Switch :checked="builtin.enabled" :aria-label="`Enable !${builtin.command}`" @update:checked="save(builtin, { enabled: $event })" />
              </div>
            </div>
          </div>

          <!-- Installed by a product. Frozen: the product reads these itself. -->
          <div v-if="productOwned.length > 0" class="space-y-3">
            <p class="text-sm font-medium text-foreground">Installed by a product</p>
            <p class="text-sm text-muted-foreground">
              These belong to the product that brought them. Their settings, including their cooldown, live on the product's own page.
            </p>
            <div
              v-for="builtin in productOwned"
              :key="builtin.id"
              class="flex flex-col gap-3 border border-l-2 border-sidebar-border border-l-primary p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <div class="flex flex-wrap items-center gap-2">
                <code class="bg-muted px-2 py-0.5 font-mono text-sm">!{{ builtin.command }}</code>
                <span v-if="builtin.service" class="flex items-center gap-1.5 text-xs tracking-wide text-primary uppercase">
                  <ProviderIcon :source="builtin.service" class="size-3.5" />
                  {{ serviceLabel(builtin.service) }}
                </span>
              </div>

              <div class="flex items-center gap-4 text-sm text-foreground/70">
                <span>{{ builtin.permission_level }}</span>
                <Link
                  v-if="builtin.service"
                  :href="`/settings/integrations/${builtin.service}`"
                  class="cursor-pointer underline hover:text-foreground"
                >
                  Open its settings -&gt;
                </Link>
              </div>
            </div>
          </div>

          <!-- The platform's own escape hatches. -->
          <div v-if="platformOwned.length > 0" class="space-y-3">
            <p class="text-sm font-medium text-foreground">Kept on by Overlabels</p>
            <p class="text-sm text-muted-foreground">
              These two switch chat control commands on and off. Turning them off would leave you no way to switch controls back on from chat, so they
              stay.
            </p>
            <div
              v-for="builtin in platformOwned"
              :key="builtin.id"
              class="flex flex-col gap-3 border border-sidebar-border p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <code class="bg-muted px-2 py-0.5 font-mono text-sm">!{{ builtin.command }}</code>
              <div class="flex items-center gap-4 text-sm text-foreground/70">
                <span>{{ builtin.permission_level }}</span>
                <span class="flex items-center gap-1.5 text-xs tracking-wide uppercase">
                  <Lock class="size-3.5" />
                  always on
                </span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
