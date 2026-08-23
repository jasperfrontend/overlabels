<script setup lang="ts">
export interface FilterOption {
  value: string;
  label: string;
}

defineProps<{
  label: string;
  selectId: string;
  options: FilterOption[];
}>();

const model = defineModel<string>({ required: true });

const emit = defineEmits<{ change: [] }>();
</script>

<template>
  <div class="flex flex-col gap-1">
    <label :for="selectId">{{ label }}</label>
    <select :id="selectId" v-model="model" class="input-border h-10 w-full cursor-pointer" @change="emit('change')">
      <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>
  </div>
</template>
