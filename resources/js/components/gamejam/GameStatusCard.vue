<script setup lang="ts">
const props = defineProps({
  title: String,
  description: [String, Number],
})
</script>

<template>
  <div class="flex flex-col gap-0 p-2 justify-between">
    <span class="text-olive-400">{{ props.title }}</span>
    <span class="py-0.5 px-2 capitalize font-bold text-4xl">{{ props.description }}</span>
  </div>
</template>
