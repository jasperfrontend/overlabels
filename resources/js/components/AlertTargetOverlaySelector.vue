<script setup lang="ts">
import { computed } from 'vue';
import { Target } from 'lucide-vue-next';
import FilterableGroupedList, { type FilterableGroup } from '@/components/FilterableGroupedList.vue';

interface OverlayOption {
  id: number;
  name: string;
  slug: string;
}

const props = defineProps<{
  staticOverlays: OverlayOption[];
  modelValue: number[];
  disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [ids: number[]] }>();

function toggle(id: number) {
  if (props.disabled) return;
  emit(
    'update:modelValue',
    props.modelValue.includes(id) ? props.modelValue.filter((x) => x !== id) : [...props.modelValue, id],
  );
}

const groups = computed<FilterableGroup<OverlayOption>[]>(() => [
  {
    key: 'all',
    label: 'Static overlays',
    items: props.staticOverlays,
  },
]);

function overlaySearchText(overlay: OverlayOption): string {
  return `${overlay.name} ${overlay.slug}`;
}
</script>

<template>
  <FilterableGroupedList
    :groups="groups"
    :item-search-text="overlaySearchText"
    :items-label="{ singular: 'overlay', plural: 'overlays' }"
    placeholder="Filter overlays..."
    flat
  >
    <template #description>
      Leave all unchecked to show this alert on <strong>all</strong> static overlays. Select one or more to restrict
      where this alert fires.
    </template>

    <template #empty>
      <div class="rounded-lg border-2 border-dashed border-muted-foreground/25 p-8 text-center text-sm text-muted-foreground">
        You have no static overlays yet.
      </div>
    </template>

    <template #item="{ item }">
      <div
        class="input-border flex cursor-pointer items-center gap-3 rounded-lg p-3 transition-colors hover:bg-background"
        :class="{
          'border-green-400 bg-green-400/5 hover:bg-green-400/10': modelValue.includes(item.id),
          'cursor-not-allowed opacity-60': disabled,
        }"
        @click="toggle(item.id)"
      >
        <input
          :id="`target-overlay-${item.id}`"
          type="checkbox"
          :checked="modelValue.includes(item.id)"
          :disabled="disabled"
          class="pointer-events-none"
          @click.prevent
        />
        <div class="flex flex-1 items-center justify-between gap-3">
          <span class="font-medium text-foreground">{{ item.name }}</span>
          <span class="font-mono text-xs text-muted-foreground">{{ item.slug }}</span>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex items-center gap-2 text-xs text-muted-foreground">
        <Target :size="12" class="text-violet-400" />
        <span v-if="modelValue.length > 0">
          {{ modelValue.length }} overlay{{ modelValue.length !== 1 ? 's' : '' }} selected
        </span>
        <span v-else>This alert will fire on all overlays</span>
      </div>
    </template>
  </FilterableGroupedList>
</template>
