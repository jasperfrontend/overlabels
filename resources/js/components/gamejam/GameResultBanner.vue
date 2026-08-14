<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
const props = defineProps<{
  title?: string;
  description?: string;
  footnote?: string;
  status?: 'won' | 'lost' | 'neutral';
}>();
const titleClass = computed(
  () =>
    ({
      won: 'text-green-400',
      lost: 'text-red-400',
      neutral: 'text-yellow-400',
    })[props.status ?? 'neutral'],
);

const page = usePage();
</script>

<template>
  <div class="medievalsharp-regular mx-4 w-full bg-olive-900 py-20 text-center text-foreground shadow-2xl">
    <h2 :class="['mb-4 text-8xl font-bold', titleClass]">{{ title }}</h2>
    <p class="text-3xl">{{ props.description }}</p>
    <p class="mt-8 text-xl text-olive-400">{{ props.footnote }}</p>
    <a :href="page.url">OK</a>
  </div>
</template>

<style scoped></style>
