<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import CollectionFilter from '@/components/CollectionFilter.vue';
import ProductBadge from '@/components/ProductBadge.vue';
import { useCollectionFilter } from '@/composables/useCollectionFilter';
import { type BreadcrumbItem } from '@/types';
import { FlaskConical, Power, PowerOff, ZapOff } from '@lucide/vue';
import {
  buildIntegrationRows,
  matchesIntegration,
  type BotSummary,
  type EventSubSummary,
  type IntegrationRow,
  type ServiceInfo,
} from '@/utils/integrationRows';

const props = defineProps<{
  services: ServiceInfo[];
  eventsub: EventSubSummary;
  bot: BotSummary;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Integrations',
    href: '/settings/integrations',
  },
];

const page = usePage();
const userLocale = computed<string | undefined>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.locale || undefined;
});

function formatDate(iso: string | null): string {
  if (!iso) return 'Never';
  return new Date(iso).toLocaleString(userLocale.value);
}

// Row shape and band order both live in the util, so they are unit-tested
// rather than buried in a template.
const rows = computed<IntegrationRow[]>(() => buildIntegrationRows(props.services, props.eventsub, props.bot, formatDate));

const { query, filtering, filtered: filteredRows } = useCollectionFilter<IntegrationRow>(() => rows.value, matchesIntegration);
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Integrations" />

    <SettingsLayout>
      <div class="space-y-6">
        <div>
          <HeadingSmall
            title="Integrations"
            description="Everything that feeds your overlays. Open one to connect it or change how it behaves."
            description-class="text-sm text-muted-foreground"
          />

          <CollectionFilter v-model="query" noun="integration" placeholder="Filter integrations..." class="mt-4" />

          <p v-if="filtering && filteredRows.length === 0" class="py-8 text-center text-sm text-muted-foreground">
            No integrations match "{{ query }}"
          </p>

          <div class="mt-4 space-y-4">
            <div v-for="row in filteredRows" :key="row.key" class="flex items-center justify-between gap-4 border border-sidebar-border p-4">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <ProductBadge
                    v-if="row.product"
                    :label="row.connected ? 'Overlabels product, connected' : 'Overlabels product, not connected'"
                    class="my-1 size-5 shrink-0"
                    :class="row.connected ? 'text-green-400' : 'text-orange-400'"
                  />
                  <span v-else-if="row.stalled" title="Connected, but listening to nothing"><ZapOff class="my-1 size-5 text-fuchsia-400" /></span>
                  <span v-else-if="row.connected" title="Connected"><Power class="my-1 size-5 text-green-400" /></span>
                  <span v-else title="Not connected"><PowerOff class="my-1 size-5 text-orange-400" /></span>
                  <span v-if="row.testMode" title="Test mode enabled"><FlaskConical class="my-1 size-5 text-yellow-400" /></span>
                  <span class="font-medium">{{ row.name }}</span>
                </div>
                <!-- Fuchsia is the colour of something being wrong on this page. -->
                <p v-if="row.status" class="text-sm" :class="row.statusAlert ? 'text-fuchsia-400' : 'text-muted-foreground'">
                  {{ row.status }}
                </p>
                <p v-else-if="row.product" class="text-sm text-muted-foreground">
                  Not connected.
                  <Link :href="`/products/${row.product}`" class="underline underline-offset-2 hover:text-foreground">Install the product</Link>
                  to connect it.
                </p>
              </div>

              <Link class="btn btn-sm shrink-0" :class="row.connected ? 'btn-plain' : 'btn-primary'" :href="row.href">
                {{ row.connected ? 'Manage' : 'Connect' }}
              </Link>
            </div>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
