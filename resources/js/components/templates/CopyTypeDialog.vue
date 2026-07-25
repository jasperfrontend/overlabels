<script setup lang="ts">
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Blocks, Layers } from '@lucide/vue';

// Shown when copying a static template: the copy can stay a static overlay
// or become a Builder block. Alerts and blocks never see this dialog.
defineProps<{ open: boolean }>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  choose: [type: 'static' | 'block'];
}>();
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle>Copy this overlay</DialogTitle>
      </DialogHeader>

      <p class="text-sm text-foreground">Choose what your copy becomes.</p>

      <div class="grid gap-3 sm:grid-cols-2">
        <button
          type="button"
          class="cursor-pointer border border-sidebar-border bg-sidebar-accent p-4 text-left transition-colors hover:border-violet-400 hover:bg-violet-400/10"
          @click="emit('choose', 'static')"
        >
          <Layers class="mb-2 h-5 w-5 text-violet-400" />
          <span class="block font-medium text-accent-foreground">Static overlay</span>
          <span class="mt-1 block text-xs text-muted-foreground">An exact copy to edit and add to OBS.</span>
        </button>

        <button
          type="button"
          class="cursor-pointer border border-sidebar-border bg-sidebar-accent p-4 text-left transition-colors hover:border-violet-400 hover:bg-violet-400/10"
          @click="emit('choose', 'block')"
        >
          <Blocks class="mb-2 h-5 w-5 text-violet-400" />
          <span class="block font-medium text-accent-foreground">Block</span>
          <span class="mt-1 block text-xs text-muted-foreground">A reusable piece to place on a grid in the Builder.</span>
        </button>
      </div>

      <DialogFooter>
        <button type="button" class="btn btn-cancel cursor-pointer" @click="emit('update:open', false)">Cancel</button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
