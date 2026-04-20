<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  title?: string
  description?: string
  footnote?: string
  status?: 'won' | 'lost' | 'neutral'
}>()
const titleClass = computed(() => ({
  won: 'text-green-400',
  lost: 'text-red-400',
  neutral: 'text-yellow-400',
}[props.status ?? 'neutral']))
</script>

<template>
  <div class="bg-olive-900 py-20 text-foreground shadow-2xl medievalsharp-regular text-center w-full mx-4">
    <h2 :class="['text-8xl font-bold mb-4', titleClass]">{{ title }}</h2>
    <p class="text-3xl">{{ props.description }}</p>
    <p class="text-xl mt-8 text-olive-400">{{ props.footnote }}</p>
  </div>
</template>

<style scoped>

</style>
