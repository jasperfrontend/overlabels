<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import ProviderIcon from '@/components/ProviderIcon.vue';
import { Badge } from '@/components/ui/badge';
import { serviceLabel } from '@/utils/services';
import { Globe, Layers, Lock } from '@lucide/vue';

interface ControlGroup {
  key: string;
  source: string | null;
  type: string;
  source_managed: boolean;
  user_scoped: boolean;
  overlays: Array<{ name: string; slug: string | null }>;
  instances: number;
  value: string;
}

defineProps<{ groups: ControlGroup[] }>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Settings', href: '/settings/account' },
  { title: 'Controls', href: '/settings/controls' },
];

/**
 * A control living on eight overlays printed eight chips and pushed the rest of
 * the row off screen. Three is enough to recognise which group you are looking
 * at; the remainder is one click away, per group.
 */
const VISIBLE_OVERLAYS = 3;
const expandedOverlays = ref<Record<string, boolean>>({});

function visibleOverlays(group: ControlGroup) {
  return expandedOverlays.value[group.key] ? group.overlays : group.overlays.slice(0, VISIBLE_OVERLAYS);
}

function toggleOverlays(key: string) {
  expandedOverlays.value[key] = !expandedOverlays.value[key];
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Controls" />

    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Controls"
          description="Every control you own and where it lives. Service controls (GPS, donations) are user-scoped - one value shared across all your overlays. Controls bound to specific overlays are listed per overlay."
        />

        <div v-if="groups.length === 0" class="border border-sidebar p-6 text-sm text-foreground">
          You have no controls yet. Add them from an overlay's Controls tab.
        </div>

        <div v-else class="space-y-1.5">
          <div v-for="group in groups" :key="group.key" class="border border-l-2 border-sidebar border-l-accent p-4">
            <!-- Identity. The tag is what you scan the page for, so nothing competes with it. -->
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
              <code class="font-mono text-sm text-foreground">[[[c:{{ group.key }}]]]</code>
              <Badge variant="outline" class="text-[10px] font-normal capitalize">{{ group.type }}</Badge>
              <span
                v-if="group.source"
                class="inline-flex items-center gap-1.5 border border-border px-2 py-0.5 text-[10px] text-muted-foreground"
                :title="
                  group.source_managed
                    ? `Managed by ${serviceLabel(group.source)} - its value cannot be set by hand`
                    : `Provisioned by ${serviceLabel(group.source)}`
                "
              >
                <ProviderIcon :source="group.source" class="h-3 w-3 shrink-0" />
                {{ serviceLabel(group.source) }}
                <Lock v-if="group.source_managed" class="h-2.5 w-2.5 shrink-0" />
              </span>
            </div>

            <!-- Value. Long ones truncate to one line; the whole string is on hover. -->
            <p v-if="group.value !== ''" class="mt-2 truncate font-mono text-xs text-muted-foreground" :title="group.value">
              {{ group.value }}
            </p>

            <!-- Scope. Neither state is better than the other, so both read the same weight. -->
            <div
              v-if="group.user_scoped || group.overlays.length"
              class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs text-muted-foreground"
            >
              <span v-if="group.user_scoped" class="inline-flex items-center gap-1.5" title="User-scoped: one value shared by every overlay you own">
                <Globe class="h-3.5 w-3.5 shrink-0" />
                All overlays
              </span>

              <template v-if="group.overlays.length">
                <span
                  class="inline-flex items-center gap-1.5"
                  :title="
                    group.instances > 1 ? `The same key exists on ${group.instances} separate controls - each copy broadcasts on its own` : undefined
                  "
                >
                  <Layers class="h-3.5 w-3.5 shrink-0" />
                  {{ group.overlays.length }} overlay{{ group.overlays.length === 1 ? '' : 's' }}
                </span>

                <Badge v-for="overlay in visibleOverlays(group)" :key="overlay.slug ?? overlay.name" variant="outline" class="font-normal">
                  {{ overlay.name }}
                </Badge>

                <button v-if="group.overlays.length > VISIBLE_OVERLAYS" type="button" class="btn btn-xs btn-plain" @click="toggleOverlays(group.key)">
                  {{ expandedOverlays[group.key] ? 'Show less' : `+${group.overlays.length - VISIBLE_OVERLAYS} more` }}
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
