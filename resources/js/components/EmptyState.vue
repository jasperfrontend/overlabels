<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(defineProps<{
  message: string;
  colspan?: number;
  icon?: Component;
  title?: string;
  dashed?: boolean;
}>(), {
  dashed: false,
});
</script>

<template>
  <!-- Table row variant — used inside <tbody> -->
  <tr v-if="colspan !== undefined">
    <td :colspan="colspan" class="px-3 py-6 text-center text-sm text-muted-foreground">
      {{ message }}
    </td>
  </tr>

  <!-- Standalone div variant -->
  <div
    v-else
    class="flex flex-col items-center justify-center text-center"
    :class="dashed
      ? 'rounded-lg border-2 border-dashed border-muted-foreground/25 p-8 sm:p-12'
      : 'py-8 sm:py-12'"
  >
    <component v-if="icon" :is="icon" class="mb-4 h-12 w-12 text-muted-foreground/50" />
    <h3 v-if="title" class="mb-2 text-lg font-semibold">{{ title }}</h3>
    <p :class="['text-muted-foreground', title ? 'mb-6 max-w-sm text-sm' : 'text-sm']">{{ message }}</p>
    <slot name="action" />
  </div>
</template>
