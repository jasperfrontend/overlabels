<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import ProductBadge from '@/components/ProductBadge.vue';

// Exists only in the product UI mode (see useUiMode): the callout under Add
// to OBS that says this is the last step of an install, with the way back.
defineProps<{ product: string | null }>();
</script>

<template>
  <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border border-green-500 bg-green-600 px-4 py-3 text-white" role="status">
    <span class="inline-flex items-center gap-2 text-sm font-medium">
      <ProductBadge class="size-5 shrink-0" />
      This is the last step. Add this overlay as a browser source in OBS and your product is live on stream.
    </span>
    <Link
      v-if="product"
      :href="`/products/${product}`"
      class="inline-flex cursor-pointer items-center gap-1.5 border border-white/60 bg-white px-3 py-1 text-xs font-semibold text-green-700 hover:bg-green-50"
    >
      <ArrowLeft class="size-3" />
      Back to the product
    </Link>
  </div>
</template>
