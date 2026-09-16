<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Bot, Check, Ellipsis, Plug } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import ProductBadge from '@/components/ProductBadge.vue';
import ProviderIcon from '@/components/ProviderIcon.vue';
import ServiceLogo from '@/components/ServiceLogo.vue';
import ProductsLayout, { type ProductCategory } from '@/layouts/ProductsLayout.vue';
import { useEventColors } from '@/composables/useEventColors';
import { serviceLabel } from '@/utils/services';

interface ProductSummary {
  slug: string;
  name: string;
  description: string;
  category: string | null;
  requires_bot: boolean;
  hero: string | null;
  service: string | null;
  installs: string[];
  installed: boolean;
}

interface Shelf {
  key: string;
  label: string;
  lead: string;
  products: ProductSummary[];
}

const props = defineProps<{
  products: ProductSummary[];
  categories: ProductCategory[];
  // The active filter: a category key, 'installed', or null for Show all.
  category: string | null;
  // The heading and lead for that filter, null for Show all.
  shelf: { label: string; lead: string } | null;
  // Null for a visitor, who has no Installed link.
  installed_count: number | null;
}>();

const { eventTypeDotClass } = useEventColors();

// The service's brand colour, for the icon that stands in for a hero. The
// event-type argument is only there to miss EVENT_STYLES (those are Twitch
// event names) so the lookup falls through to the source colour.
const serviceColor = (service: string) => eventTypeDotClass('product', service);

// Show all is one shelf per category, in the order the server sorted them,
// plus one for anything uncategorised. A filter is a single shelf with the
// server's heading. Either way the page renders shelves, never a bare grid.
const shelves = computed<Shelf[]>(() => {
  if (props.category !== null && props.shelf) {
    return [{ key: props.category, label: props.shelf.label, lead: props.shelf.lead, products: props.products }];
  }

  const byCategory = props.categories
    .map((category) => ({
      key: category.key,
      label: category.label,
      lead: category.lead,
      products: props.products.filter((product) => product.category === category.key),
    }))
    .filter((shelf) => shelf.products.length > 0 || shelf.key === 'product');

  const uncategorised = props.products.filter((product) => product.category === null);
  if (uncategorised.length > 0) {
    byCategory.push({ key: 'other', label: 'More', lead: 'Everything else you can install in one click.', products: uncategorised });
  }

  return byCategory;
});

const title = computed(() => (props.shelf ? `${props.shelf.label} - Products` : 'Products'));
const description = computed(
  () =>
    props.shelf?.lead ??
    'Everything here is free, made by Overlabels, and installed with one click. Pick something for your chat to play, or connect a service you already use so its support lands on your stream.',
);
</script>

<template>
  <Head>
    <title>{{ title }}</title>
    <meta name="description" :content="description" />
  </Head>

  <ProductsLayout :categories="categories" :category="category" :installed-count="installed_count">
    <section v-for="shelf in shelves" :key="shelf.key" class="mb-12 last:mb-0">
      <h2 class="text-lg font-semibold text-foreground">{{ shelf.label }}</h2>
      <p class="mt-1 mb-4 max-w-prose text-sm text-foreground">{{ shelf.lead }}</p>

      <!-- The Alerts shelf is the quieter one on purpose. An alert is a
           plain thing (connect a service, its support lands on stream), so
           its tile is the service icon, the name and one line, five to a
           row, and the full description waits on the product page. Giving
           it the Products card would tell the reader both shelves weigh
           the same, and they do not. -->
      <ul v-if="shelf.key === 'alert' && shelf.products.length > 0" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <li v-for="product in shelf.products" :key="product.slug" class="collection-row relative flex flex-col gap-2 border border-border p-3">
          <Link :href="`/products/${product.slug}`" class="absolute inset-0 z-0 cursor-pointer" :aria-label="product.name" />
          <div class="flex items-center justify-between gap-2">
            <ServiceLogo v-if="product.service" :source="product.service" class="size-7 shrink-0" :class="serviceColor(product.service)" />
            <span
              v-if="product.installed"
              class="inline-flex items-center gap-1 border border-green-500/60 px-1.5 py-0.5 text-xs font-medium text-green-600 dark:text-green-400"
            >
              <Check class="size-3.5" />
              Installed
            </span>
          </div>
          <h3 class="text-base font-medium text-foreground">{{ product.name }}</h3>
          <p v-if="product.service" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
            <Plug class="size-3.5" />
            Connects {{ serviceLabel(product.service) }}
          </p>
        </li>
      </ul>

      <ul v-else-if="shelf.products.length > 0 || shelf.key === 'product'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <li v-for="product in shelf.products" :key="product.slug" class="collection-row relative flex flex-col border border-border p-4">
          <Link :href="`/products/${product.slug}`" class="absolute inset-0 z-0 cursor-pointer" :aria-label="product.name" />
          <!-- A hero is the product's own artwork, 16:9, sitting above the
               copy inside the same card so the whole thing stays one link.
               Without one, the service's icon in its brand colour fills the
               same box, so the two shelves line up. -->
          <!-- Not positioned on purpose: a positioned box later in the DOM
               would paint over the card's stretched link and swallow clicks
               on the artwork. The tag anchors to the card instead. -->
          <div class="mb-4">
            <img v-if="product.hero" :src="product.hero" alt="" class="block aspect-video w-full object-cover" />
            <div
              v-else-if="product.service"
              class="flex aspect-video w-full items-center justify-center border border-border bg-muted/30"
              :class="serviceColor(product.service)"
            >
              <ProviderIcon :source="product.service" class="size-16" />
            </div>
            <!-- The installed state lives here and nowhere else on the card:
                 one tag on the artwork, so the title row never has to make
                 room for it and the badge below stays what it is. -->
            <span
              v-if="product.installed"
              class="pointer-events-none absolute top-6 right-6 z-10 inline-flex items-center gap-1 border border-green-500/60 bg-background/90 px-1.5 py-0.5 text-xs font-medium text-green-600 dark:text-green-400"
            >
              <Check class="size-3.5" />
              Installed
            </span>
          </div>
          <h3 class="inline-flex items-center gap-2 text-lg font-medium text-foreground">
            <!-- The badge means "an official Overlabels product", so only the
                 Products shelf gets it, and it is always the same colour. An
                 alert is a service's API inside Overlabels, not one of ours. -->
            <ProductBadge
              v-if="product.category === 'product'"
              label="An official Overlabels product"
              class="relative z-10 size-5 shrink-0 text-violet-400"
            />
            {{ product.name }}
          </h3>
          <p class="mt-1 max-w-prose text-sm text-foreground">{{ product.description }}</p>
          <ul v-if="product.installs.length > 0" class="mt-3 flex flex-wrap gap-1.5" aria-label="What it installs">
            <li v-for="chip in product.installs" :key="chip" class="border border-border px-1.5 py-0.5 text-xs text-muted-foreground">
              {{ chip }}
            </li>
          </ul>
          <div class="mt-auto flex flex-col gap-1 pt-2">
            <p v-if="product.category === 'alert' && product.service" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
              <Plug class="size-3.5" />
              Connects {{ serviceLabel(product.service) }}
            </p>
            <p v-if="product.requires_bot" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
              <Bot class="size-3.5" />
              Works through the Overlabels bot in your chat
            </p>
          </div>
        </li>
        <!-- The Products shelf always ends with a labelled placeholder. A
             grid with a hole reads as unfinished; a shelf with room on it
             reads as a shelf. Alerts get none: five is a full row. -->
        <li v-if="shelf.key === 'product'" class="flex">
          <EmptyState dashed class="w-full" :icon="Ellipsis" message="More to come!" />
        </li>
      </ul>

      <EmptyState
        v-else
        dashed
        :message="shelf.key === 'installed' ? 'Nothing installed yet. Everything here installs in one click.' : 'Nothing here yet.'"
      >
        <template #action>
          <Link href="/products" class="text-sm text-violet-400 hover:underline">Show all</Link>
        </template>
      </EmptyState>
    </section>
  </ProductsLayout>
</template>
