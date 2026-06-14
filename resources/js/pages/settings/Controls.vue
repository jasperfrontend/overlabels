<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';

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

        <div v-if="groups.length === 0" class="rounded-md border border-sidebar p-6 text-sm text-foreground">
          You have no controls yet. Add them from an overlay's Controls tab.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="group in groups"
            :key="group.key"
            class="rounded-md border border-sidebar p-4"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <code class="font-mono text-sm text-foreground">[[[c:{{ group.key }}]]]</code>
                <Badge variant="secondary">{{ group.type }}</Badge>
                <Badge v-if="group.source" variant="secondary">{{ group.source }}</Badge>
              </div>
              <span class="text-xs text-muted-foreground">value: {{ group.value === '' ? '(empty)' : group.value }}</span>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
              <Badge v-if="group.user_scoped" variant="success">All overlays</Badge>
              <template v-for="overlay in group.overlays" :key="overlay.slug ?? overlay.name">
                <Badge variant="outline">{{ overlay.name }}</Badge>
              </template>

              <span
                v-if="group.instances > 1"
                class="text-xs text-amber-500 dark:text-amber-400"
                title="The same control exists on multiple overlays - each copy broadcasts separately."
              >
                duplicated across {{ group.instances }} controls
              </span>
            </div>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
