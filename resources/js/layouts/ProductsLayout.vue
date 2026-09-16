<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { BellRing, Blocks, Gamepad2, LayoutGrid, PackageCheck } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import ProductBadge from '@/components/ProductBadge.vue';
import { NavigationMenu, NavigationMenuItem, NavigationMenuLink, NavigationMenuList } from '@/components/ui/navigation-menu';
import type { AppPageProps } from '@/types';

export interface ProductCategory {
  key: string;
  label: string;
  lead: string;
  count: number;
}

// The shell every /products page sits in: brand bar, the Products heading,
// a breadcrumb that always starts at Products, and the category sidebar.
// Renders outside AppLayout so a visitor without an account can read it.
// The sidebar links always lead to the listing, like a shop's category
// links do; a product page marks its own category as the place it belongs.
const props = defineProps<{
  categories: ProductCategory[];
  // The active sidebar entry: a category key, 'installed', or null for Show all.
  category: string | null;
  // Null for a visitor, who has no Installed link.
  installedCount: number | null;
  // The page after Products in the breadcrumb: a product's name. Omitted on the listing.
  crumb?: string;
}>();

const page = usePage<AppPageProps>();
const isAuthed = computed(() => !!page.props.auth?.user);
const loginHref = computed(() => `/login?redirect_to=${encodeURIComponent(page.url)}`);

const breadcrumbs = computed(() => [{ title: 'Products', href: '/products' }, ...(props.crumb ? [{ title: props.crumb }] : [])]);

// One icon per shelf, keyed like CATEGORIES on the server. A shelf added
// there without one here gets the generic blocks, not a blank.
const CATEGORY_ICONS: Record<string, Component> = {
  product: Gamepad2,
  alert: BellRing,
};

interface MenuEntry {
  key: string;
  label: string;
  count: number;
  href: string;
  icon: Component;
  // The Installed entry is the account's, not the catalogue's, and sits apart.
  separated?: boolean;
}

const entries = computed<MenuEntry[]>(() => {
  const items: MenuEntry[] = props.categories.map((category) => ({
    key: category.key,
    label: category.label,
    count: category.count,
    href: `/products?category=${category.key}`,
    icon: CATEGORY_ICONS[category.key] ?? Blocks,
  }));

  items.push({
    key: 'all',
    label: 'Show all',
    count: props.categories.reduce((sum, c) => sum + c.count, 0),
    href: '/products',
    icon: LayoutGrid,
  });

  if (props.installedCount !== null) {
    items.push({
      key: 'installed',
      label: 'Installed',
      count: props.installedCount,
      href: '/products?category=installed',
      icon: PackageCheck,
      separated: true,
    });
  }

  return items;
});

const isActive = (key: string) => (props.category === null ? key === 'all' : key === props.category);
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <div class="mx-auto max-w-7xl p-4 lg:p-6">
      <div class="mb-6 flex items-center justify-between">
        <a href="/" class="flex cursor-pointer items-center gap-2 text-sm font-bold tracking-tight text-foreground hover:text-violet-400">
          <img src="/favicon-light.svg" alt="" class="h-6 w-6 dark:hidden" />
          <img src="/favicon.png" alt="" class="hidden h-6 w-6 dark:block" />
          Overlabels
        </a>
        <div class="flex items-center gap-2">
          <Link v-if="isAuthed" :href="route('dashboard.index')" class="text-sm text-violet-400 hover:underline">Dashboard</Link>
          <a v-else :href="loginHref" class="text-sm text-violet-400 hover:underline">Log in</a>
          <DarkModeToggle />
        </div>
      </div>

      <header class="mb-6 flex flex-col gap-2">
        <div class="flex items-center gap-2">
          <ProductBadge label="Official Overlabels products" class="size-6 text-violet-400" />
          <h1 class="text-2xl font-semibold text-foreground">
            <Link href="/products" class="hover:text-violet-400">Products</Link>
          </h1>
        </div>
        <p class="w-full text-foreground lg:max-w-2/3">
          Everything here is free, made by Overlabels, and installed with one click. Pick something for your chat to play, or connect a service you
          already use so its support lands on your stream.
        </p>
      </header>

      <!-- Always starts at Products, on an alert's page too: every listed
           thing is a product, whichever shelf it sits on. -->
      <Breadcrumbs :breadcrumbs="breadcrumbs" class="mb-6" />

      <div class="lg:flex lg:gap-8">
        <!-- A row of links on a phone, a column from lg up, with the divider
             turning from a rule under the row into a rule beside the column.
             Real links with the filter in the URL, so a category can be
             shared and comes back on the back button. -->
        <aside class="mb-6 border-b border-border pb-4 lg:mb-0 lg:w-52 lg:shrink-0 lg:border-r lg:border-b-0 lg:pr-6 lg:pb-0">
          <NavigationMenu
            orientation="vertical"
            :viewport="false"
            aria-label="Product categories"
            class="w-full max-w-none items-stretch justify-start lg:sticky lg:top-6 [&>div]:w-full"
          >
            <NavigationMenuList class="-mx-4 justify-start gap-1 overflow-x-auto px-4 lg:mx-0 lg:w-full lg:flex-col lg:items-stretch lg:px-0">
              <NavigationMenuItem
                v-for="entry in entries"
                :key="entry.key"
                class="shrink-0"
                :class="{ 'lg:mt-2 lg:border-t lg:border-border lg:pt-2': entry.separated }"
              >
                <NavigationMenuLink
                  as-child
                  :active="isActive(entry.key)"
                  class="group/entry w-full cursor-pointer flex-row items-center gap-3 rounded-none border-l-2 border-transparent px-3 py-2 text-sm font-medium text-foreground transition-colors hover:border-sidebar-accent hover:bg-sidebar-accent/5 data-[active]:border-violet-500 data-[active]:bg-sidebar-accent data-[active]:text-violet-500 dark:data-[active]:text-violet-400"
                >
                  <Link :href="entry.href" :aria-current="isActive(entry.key) ? 'page' : undefined">
                    <component
                      :is="entry.icon"
                      class="size-4 shrink-0 text-muted-foreground transition-colors group-hover/entry:text-foreground group-data-[active]/entry:text-violet-500 dark:group-data-[active]/entry:text-violet-400"
                    />
                    <span>{{ entry.label }}</span>
                    <span
                      class="ml-auto inline-flex h-5 min-w-5 items-center justify-center border border-border px-1.5 text-xs text-muted-foreground tabular-nums transition-colors group-data-[active]/entry:border-violet-500/60 group-data-[active]/entry:text-violet-500 dark:group-data-[active]/entry:text-violet-400"
                    >
                      {{ entry.count }}
                    </span>
                  </Link>
                </NavigationMenuLink>
              </NavigationMenuItem>
            </NavigationMenuList>
          </NavigationMenu>
        </aside>

        <main class="min-w-0 flex-1">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
