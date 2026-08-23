<script setup lang="ts">
withDefaults(
  defineProps<{
    label?: string;
    placeholder?: string;
    inputId?: string;
  }>(),
  { label: 'Search', placeholder: '', inputId: 'filter-search' },
);

const model = defineModel<string>({ required: true });

const emit = defineEmits<{ search: [] }>();
</script>

<template>
  <div class="flex flex-col gap-1">
    <label :for="inputId">{{ label }}</label>
    <input :id="inputId" v-model="model" type="text" :placeholder="placeholder" class="input-border h-10 w-full" @input="emit('search')" />
  </div>
</template>
