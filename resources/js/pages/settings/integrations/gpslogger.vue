<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

interface IntegrationData {
  connected: boolean;
  enabled: boolean;
  webhook_url: string | null;
  last_received_at: string | null;
  speed_unit: string;
  has_token: boolean;
}

defineProps<{
  integration: IntegrationData;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'GPSLogger (deprecated)', href: '/settings/integrations/gpslogger' }
];

</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="GPSLogger Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <HeadingSmall
            title="GPSLogger (deprecated)"
            description="Receive live GPS data from GPSLogger for Android and display speed, coordinates, and distance in your overlays."
          />
        </div>
        <div class="flex items-center gap-2 bg-amber-500/10 border border-amber-300/50 p-4">
          <HeadingSmall
            title="This integration has been deprecated"
            description="You can no longer integrate gpslogger.app into Overlabels. Use Overlabels GPS instead. It's a vastly more polished experience exactly tailored to the needs of IRL streamers. Out on Android soon!"
            class="text-amber-400"
          />
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
