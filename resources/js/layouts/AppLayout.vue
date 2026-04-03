<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import KeyboardShortcutsDialog from '@/components/KeyboardShortcutsDialog.vue';
import LinkWarningModal from '@/components/LinkWarningModal.vue';
import type { BreadcrumbItemType } from '@/types';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { ref, computed, onMounted } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const showKeyboardShortcuts = ref(false);
const { register, getAllShortcuts } = useKeyboardShortcuts();
const keyboardShortcutsList = computed(() => getAllShortcuts());

onMounted(() => {
  register('toggle-shortcuts', 'ctrl+k', () => {
    showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
  }, { description: 'Show keyboard shortcuts' });
});
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <LinkWarningModal />
    <CommandPalette />
    <KeyboardShortcutsDialog :show="showKeyboardShortcuts" :shortcuts="keyboardShortcutsList" @close="showKeyboardShortcuts = false" />
    <TooltipProvider>
      <slot />
    </TooltipProvider>
  </AppLayout>
</template>
