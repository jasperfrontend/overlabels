<script setup lang="ts">
/**
 * A product's icon looked up by slug, for lists that only have the slug.
 * Takes the same props as the icons themselves; renders nothing for a
 * product without one (check with hasProductIcon() to pick a fallback).
 */
import { computed } from 'vue';
import { PRODUCT_ICONS } from './registry';
import type { ProductIconProps } from './types';

const props = defineProps<ProductIconProps & { slug: string }>();

const icon = computed(() => PRODUCT_ICONS[props.slug] ?? null);

const iconProps = computed<ProductIconProps>(() => ({
  size: props.size,
  color: props.color,
  background: props.background,
  padding: props.padding,
  title: props.title,
}));
</script>

<template>
  <component :is="icon" v-if="icon" v-bind="iconProps" />
</template>
