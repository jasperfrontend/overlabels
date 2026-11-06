<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Trophy } from '@lucide/vue';
import ProductIcon, { hasProductIcon } from '@/components/ProductIcon.vue';
import ServiceLogo from '@/components/ServiceLogo.vue';
import { useEventColors } from '@/composables/useEventColors';
import type { InstalledProduct } from '@/types';

const props = defineProps<{
  products: InstalledProduct[];
  /** Listed games not installed yet. */
  gamesLeft: number;
}>();

const { eventTypeDotClass } = useEventColors();

// Same lookup the /products Alerts tiles use: the event-type argument only
// exists to miss the Twitch styles so it falls through to the brand colour.
const serviceColor = (service: string) => eventTypeDotClass('product', service);

type Filter = 'product' | 'alert';

// The two /products shelves. Anything uncategorised sits with Products, the
// same way /products files it under "More" rather than under Alerts.
const kindOf = (product: InstalledProduct): Filter => (product.category === 'alert' ? 'alert' : 'product');

const counts = computed(() => ({
  product: props.products.filter((p) => kindOf(p) === 'product').length,
  alert: props.products.filter((p) => kindOf(p) === 'alert').length,
}));

// Products first; an account with only alerts installed opens on those.
const filter = ref<Filter>(counts.value.product > 0 ? 'product' : 'alert');

const filters: { key: Filter; label: string }[] = [
  { key: 'product', label: 'Products' },
  { key: 'alert', label: 'Alerts' },
];

const visible = computed(() => props.products.filter((p) => kindOf(p) === filter.value));
</script>

<template>
  <!-- Single root, so the dashboard's spacing class lands on the whole thing. -->
  <div class="flex flex-col gap-3">
    <section v-if="products.length > 0" class="border border-border p-4">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <h2 class="text-base font-semibold text-foreground">Your installed products</h2>

        <!-- Only worth a control when there is something on both sides. -->
        <div v-if="counts.product > 0 && counts.alert > 0" class="flex border border-border" role="group" aria-label="Filter installed products">
          <button
            v-for="f in filters"
            :key="f.key"
            type="button"
            class="cursor-pointer px-2.5 py-1 text-xs font-medium transition-colors"
            :class="filter === f.key ? 'bg-violet-500/20 text-foreground' : 'text-muted-foreground hover:text-foreground'"
            :aria-pressed="filter === f.key"
            @click="filter = f.key"
          >
            {{ f.label }} ({{ counts[f.key] }})
          </button>
        </div>
      </div>

      <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <li v-for="product in visible" :key="product.slug" class="collection-row relative flex items-center gap-3 p-2">
          <!-- Stretched link: the whole tile manages the product. -->
          <Link :href="route('products.manage', product.slug)" class="absolute inset-0 cursor-pointer" :aria-label="`Manage ${product.name}`" />

          <!-- The product's own mark, or the service's for an alert. The hero
           screenshot is the last resort: at this size it is noise. -->
          <div
            v-if="hasProductIcon(product.slug) || product.service"
            class="flex size-12 shrink-0 items-center justify-center"
            :class="hasProductIcon(product.slug) ? 'bg-violet-500 text-sidebar dark:bg-violet-400' : ['bg-muted/30', serviceColor(product.service!)]"
          >
            <ProductIcon v-if="hasProductIcon(product.slug)" :slug="product.slug" class="size-7" />
            <ServiceLogo v-else :source="product.service!" class="size-6" />
          </div>
          <img v-else-if="product.hero" :src="product.hero" alt="" class="block size-12 shrink-0 object-cover" />
          <div v-else class="size-12 shrink-0 bg-muted/30" />

          <div class="min-w-0">
            <p class="truncate font-medium text-foreground">{{ product.name }}</p>
            <span class="inline-flex items-center gap-1 text-xs text-violet-600 dark:text-violet-400">
              Manage
              <ArrowRight class="size-3" />
            </span>
          </div>
        </li>
      </ul>
    </section>

    <!-- The pitch for the next install. Below the block, not beside the
       filter: the two do not weigh the same. Names no product, so it stays
       true whatever is already installed. -->
    <section v-if="gamesLeft > 0" class="flex flex-col gap-3 border border-border p-4">
      <h2 class="text-base font-semibold text-foreground">We got more things for your chat to do!</h2>
      <p class="max-w-prose text-sm text-foreground">
        Overlabels offers small games for your chat that are ideal for downtime, BRB scenes or if you're just looking for a small break! Yes, all
        games are free forever.
      </p>
      <Link :href="route('products.index')" class="btn btn-md btn-primary self-start">Browse all</Link>
    </section>

    <!-- Every game installed: nothing left to pitch, so say so. A plain <a>:
       the help pages are Blade, not Inertia. -->
    <section v-else class="flex flex-col gap-3 bg-sidebar-accent p-4">
      <h2 class="flex items-center gap-2 text-base font-semibold text-foreground">
        <!-- Amber on purpose: the one exception to the no-yellow rule. A trophy is gold. -->
        <Trophy class="size-5 text-amber-500 dark:text-amber-400" />
        You beat Overlabels!
      </h2>
      <p class="max-w-prose text-sm text-foreground">
        All Overlabels games are installed on your account. We literally have nothing more to offer, unless you want to build it yourself?
      </p>
      <a href="/help/how-overlabels-works" class="btn btn-md btn-primary self-start">View the docs</a>
    </section>
  </div>
</template>
