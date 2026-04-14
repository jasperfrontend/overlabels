<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import katex from 'katex';
import 'katex/dist/katex.min.css';

const props = defineProps<{
  tex: string;
  display?: boolean;
}>();

const el = ref<HTMLElement | null>(null);

function render() {
  if (!el.value) return;
  try {
    katex.render(props.tex, el.value, {
      displayMode: !!props.display,
      throwOnError: false,
      output: 'html',
    });
  } catch (e) {
    if (el.value) el.value.textContent = props.tex;
    console.warn('[MathEquation] render error', e);
  }
}

onMounted(render);
watch(() => props.tex, render);
</script>

<template>
  <span v-if="!display" ref="el" class="katex-inline" />
  <div v-else ref="el" class="my-4 overflow-x-auto" />
</template>
