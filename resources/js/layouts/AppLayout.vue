<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import LinkWarningModal from '@/components/LinkWarningModal.vue';
import type { BreadcrumbItemType } from '@/types';
import { TooltipProvider } from '@/components/ui/tooltip';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <LinkWarningModal />
    <TooltipProvider>
      <slot />
    </TooltipProvider>
  </AppLayout>
</template>
