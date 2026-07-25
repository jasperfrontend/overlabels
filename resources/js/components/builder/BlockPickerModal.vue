<script setup lang="ts">
import { computed, ref } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Blocks } from '@lucide/vue';

export interface LibraryBlock {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  screenshot_url: string | null;
  metadata?: { block?: { default_span?: { w: number; h: number } } } | null;
  owner_id: number;
}

const props = defineProps<{
  open: boolean;
  blocks: LibraryBlock[];
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  pick: [block: LibraryBlock];
}>();

const search = ref('');

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return props.blocks;
  return props.blocks.filter(
    (b) => b.name.toLowerCase().includes(q) || (b.description ?? '').toLowerCase().includes(q),
  );
});
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-w-3xl">
      <DialogHeader>
        <DialogTitle>Pick a block</DialogTitle>
      </DialogHeader>

      <input
        v-model="search"
        type="text"
        class="input-border w-full"
        placeholder="Search blocks..."
        autofocus
      />

      <div class="max-h-[50vh] overflow-y-auto">
        <div v-if="filtered.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <button
            v-for="block in filtered"
            :key="block.id"
            type="button"
            class="flex cursor-pointer flex-col overflow-hidden rounded-sm border border-sidebar-border text-left transition-colors hover:border-violet-400 hover:bg-violet-400/5"
            @click="emit('pick', block)"
          >
            <img
              v-if="block.screenshot_url"
              :src="block.screenshot_url"
              alt=""
              class="h-28 w-full object-cover"
              loading="lazy"
            />
            <div v-else class="flex h-28 w-full items-center justify-center bg-sidebar-accent">
              <Blocks class="size-8 text-muted-foreground/50" />
            </div>
            <div class="space-y-1 p-3">
              <div class="text-sm font-medium">{{ block.name }}</div>
              <p v-if="block.description" class="line-clamp-2 text-xs text-muted-foreground">{{ block.description }}</p>
              <p class="font-mono text-xs text-muted-foreground/70">
                {{ block.metadata?.block?.default_span?.w ?? 4 }} x {{ block.metadata?.block?.default_span?.h ?? 2 }} cells
              </p>
            </div>
          </button>
        </div>
        <div v-else class="py-10 text-center text-sm text-foreground">
          <template v-if="blocks.length">Nothing matched your search.</template>
          <template v-else>
            No blocks yet. Create one via
            <a :href="route('templates.create')" class="cursor-pointer text-violet-400 hover:underline">New Overlay</a>
            and choose the Block type.
          </template>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
