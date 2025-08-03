<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import LinkWarningModal from '@/components/LinkWarningModal.vue';
import type { BreadcrumbItemType } from '@/types';

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
    <slot />
  </AppLayout>
</template>
