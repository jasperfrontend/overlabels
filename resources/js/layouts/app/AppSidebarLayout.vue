<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import LockdownBanner from '@/components/LockdownBanner.vue';
import ProductFlowFrame from '@/components/ProductFlowFrame.vue';
import ProductSetupBanner from '@/components/ProductSetupBanner.vue';
import ScopeUpdateBanner from '@/components/ScopeUpdateBanner.vue';
import VersionBanner from '@/components/VersionBanner.vue';
import { focusStart, MAIN_CONTENT_ID } from '@/composables/useFocusStart';
import type { BreadcrumbItemType } from '@/types';

interface Props {
  breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
});
</script>

<template>
  <AppShell variant="sidebar">
    <!-- The first Tab stop on every page: invisible until it has focus, then a
         pill in the top-left corner. Lands where a rendered page starts Tab
         from anyway (useFocusStart), scrolling there for this one caller. -->
    <a
      :href="`#${MAIN_CONTENT_ID}`"
      class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:rounded-sm focus:bg-violet-500 focus:px-3 focus:py-2 focus:text-sm focus:font-semibold focus:text-white focus:shadow-lg focus:outline-none"
      @click.prevent="focusStart({ scroll: true })"
    >
      Skip to content
    </a>
    <ProductFlowFrame />
    <AppSidebar />
    <AppContent :id="MAIN_CONTENT_ID" variant="sidebar" class="overflow-x-clip">
      <VersionBanner />
      <ProductSetupBanner />
      <LockdownBanner />
      <ImpersonationBanner />
      <ScopeUpdateBanner />
      <AppSidebarHeader :breadcrumbs="breadcrumbs" />
      <slot />
    </AppContent>
  </AppShell>
</template>
