<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check } from '@lucide/vue';
import ProductBadge from '@/components/ProductBadge.vue';
import type { AppPageProps } from '@/types';

// Shown on every app page while a product install is mid-way. It exists to
// reel the person back to the product page after the step they left it for,
// so it never renders on the product page itself (those pages are outside
// AppLayout, which is where this mounts). Sibling of VersionBanner, not a
// mode of it.
const page = usePage<AppPageProps>();
const setup = computed(() => page.props.productSetup);

const summary = computed(() => {
  const s = setup.value;
  if (!s) return '';
  if (s.ready) return `${s.name} is ready.`;
  const left = s.remaining === 1 ? 'one thing left' : `${s.remaining} things left`;
  return s.next ? `Setting up ${s.name}, ${left}. Next: ${s.next.label.toLowerCase()}.` : `Setting up ${s.name}, ${left}.`;
});

function dismiss(): void {
  router.post(route('products.setup.dismiss'), {}, { preserveScroll: true });
}
</script>

<template>
  <div
    v-if="setup"
    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2 text-sm font-medium text-white"
    :class="setup.ready ? 'bg-green-700' : 'bg-fuchsia-700'"
    role="status"
  >
    <span class="inline-flex items-center gap-2">
      <Check v-if="setup.ready" class="size-4 shrink-0" />
      <ProductBadge v-else class="size-4 shrink-0" />
      {{ summary }}
    </span>
    <span class="flex items-center gap-2">
      <Link
        :href="setup.url"
        class="inline-flex cursor-pointer items-center gap-1.5 rounded border px-3 py-1 text-xs font-semibold"
        :class="setup.ready ? 'border-green-300 bg-green-800 hover:bg-green-900' : 'border-fuchsia-300 bg-fuchsia-800 hover:bg-fuchsia-900'"
      >
        {{ setup.ready ? 'Go to installed product' : `Back to ${setup.name}` }}
        <ArrowRight class="size-3" />
      </Link>
      <button v-if="!setup.ready" type="button" class="cursor-pointer text-xs text-white/80 underline-offset-2 hover:underline" @click="dismiss">
        Not now
      </button>
    </span>
  </div>
</template>
