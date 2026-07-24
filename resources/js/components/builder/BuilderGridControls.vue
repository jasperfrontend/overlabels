<script setup lang="ts">
const props = defineProps<{
  grid: { cols: number; rows: number; gap: number };
}>();

const emit = defineEmits<{ update: [cols: number, rows: number, gap: number] }>();

const presets = [
  { label: '12 x 8', cols: 12, rows: 8 },
  { label: '6 x 4', cols: 6, rows: 4 },
  { label: '16 x 9', cols: 16, rows: 9 },
] as const;

function set(field: 'cols' | 'rows' | 'gap', value: number) {
  const next = { ...props.grid, [field]: value };
  emit('update', next.cols, next.rows, next.gap);
}
</script>

<template>
  <div class="flex flex-wrap items-end gap-4">
    <div>
      <label for="builder-cols" class="mb-1 block text-sm font-medium text-accent-foreground">Columns</label>
      <input
        id="builder-cols"
        :value="grid.cols"
        type="number"
        min="1"
        max="24"
        class="input-border w-20"
        @change="set('cols', Number(($event.target as HTMLInputElement).value))"
      />
    </div>
    <div>
      <label for="builder-rows" class="mb-1 block text-sm font-medium text-accent-foreground">Rows</label>
      <input
        id="builder-rows"
        :value="grid.rows"
        type="number"
        min="1"
        max="24"
        class="input-border w-20"
        @change="set('rows', Number(($event.target as HTMLInputElement).value))"
      />
    </div>
    <div>
      <label for="builder-gap" class="mb-1 block text-sm font-medium text-accent-foreground">Gap (px)</label>
      <input
        id="builder-gap"
        :value="grid.gap"
        type="number"
        min="0"
        max="100"
        class="input-border w-20"
        @change="set('gap', Number(($event.target as HTMLInputElement).value))"
      />
    </div>
    <div class="flex items-center gap-2">
      <button
        v-for="preset in presets"
        :key="preset.label"
        type="button"
        class="btn btn-cancel cursor-pointer text-xs"
        @click="emit('update', preset.cols, preset.rows, grid.gap)"
      >
        {{ preset.label }}
      </button>
    </div>
  </div>
</template>
