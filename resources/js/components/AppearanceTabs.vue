<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Coffee, Monitor, Moon, Sun } from '@lucide/vue';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
  { value: 'light', Icon: Sun, label: 'Light' },
  { value: 'dark', Icon: Moon, label: 'Dark' },
  { value: 'sepia', Icon: Coffee, label: 'Sepia' },
  { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
  <div class="inline-flex gap-1 bg-card p-1">
    <button
      v-for="{ value, Icon, label } in tabs"
      :key="value"
      @click="updateAppearance(value)"
      :class="[
        'flex cursor-pointer items-center px-3.5 py-1.5 transition-colors',
        appearance === value ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-chill',
      ]"
    >
      <component :is="Icon" class="-ml-1 h-4 w-4" />
      <span class="ml-1.5 text-sm">{{ label }}</span>
    </button>
  </div>
</template>
