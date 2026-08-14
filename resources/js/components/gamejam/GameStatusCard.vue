<script setup lang="ts">
const props = defineProps({
  title: String,
  description: [String, Number],
});
</script>

<template>
  <div class="flex flex-col justify-between gap-0 p-2">
    <span class="text-olive-400">{{ props.title }}</span>
    <span class="px-2 py-0.5 text-4xl font-bold capitalize">{{ props.description }}</span>
  </div>
</template>
