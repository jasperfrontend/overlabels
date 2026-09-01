<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import HelpBeacon from '@/components/HelpBeacon.vue';
import ReferencePalette from '@/components/ReferencePalette.vue';
import KeyboardShortcutsDialog from '@/components/KeyboardShortcutsDialog.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import RekaToast from '@/components/RekaToast.vue';
import type { AppPageProps, BreadcrumbItemType } from '@/types';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';

interface Props {
  breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
});

// The one place a session flash becomes a toast. Any controller that returns
// `back()->with('message', ...)` (or `success` / `error`, see
// HandleInertiaRequests) shows up here, on whichever page the redirect lands.
// Keyed on the flash object rather than the message string so the same text
// flashed twice in a row still produces a second toast.
const page = usePage<AppPageProps>();
const flashMessage = ref<string | null>(null);
const flashType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const flashKey = ref(0);

watch(
  () => page.props.flash,
  (flash) => {
    if (!flash?.message) return;
    flashMessage.value = flash.message;
    flashType.value = flash.type || 'info';
    flashKey.value++;
  },
  { immediate: true },
);

const showKeyboardShortcuts = ref(false);
const { register, getAllShortcuts } = useKeyboardShortcuts();
const keyboardShortcutsList = computed(() => getAllShortcuts());

onMounted(() => {
  register(
    'toggle-shortcuts',
    'ctrl+k',
    () => {
      showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
    },
    { description: 'Show keyboard shortcuts' },
  );
});
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <ConfirmDialog />
    <CommandPalette />
    <ReferencePalette />
    <KeyboardShortcutsDialog :show="showKeyboardShortcuts" :shortcuts="keyboardShortcutsList" @close="showKeyboardShortcuts = false" />
    <HelpBeacon />
    <RekaToast v-if="flashMessage" :key="flashKey" :message="flashMessage" :type="flashType" @dismiss="flashMessage = null" />
    <TooltipProvider>
      <slot />
    </TooltipProvider>
  </AppLayout>
</template>
