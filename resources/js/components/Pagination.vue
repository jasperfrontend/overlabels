<template>
  <div class="flex items-center justify-between">
    <div class="flex flex-1 justify-between sm:hidden">
      <Link
        v-if="mergedLinks[0].url"
        :href="mergedLinks[0].url"
        class="relative inline-flex items-center rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted dark:border-border dark:bg-background dark:text-foreground dark:hover:bg-muted"
      >
        Previous
      </Link>
      <Link
        v-if="mergedLinks[mergedLinks.length - 1].url"
        :href="mergedLinks[mergedLinks.length - 1].url"
        class="relative ml-3 inline-flex items-center rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted dark:border-border dark:bg-background dark:text-foreground dark:hover:bg-muted"
      >
        Next
      </Link>
    </div>
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-muted-foreground dark:text-muted-foreground">
          Showing
          <span class="font-medium text-foreground dark:text-foreground">{{ from }}</span>
          to
          <span class="font-medium text-foreground dark:text-foreground">{{ to }}</span>
          of
          <span class="font-medium text-foreground dark:text-foreground">{{ total }}</span>
          results
        </p>
      </div>
      <div>
        <nav class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
          <template v-for="(link, index) in mergedLinks" :key="index">
            <Link
              v-if="link.url"
              :href="link.url"
              :class="[
                link.active
                  ? 'z-10 border-primary bg-primary/10 text-primary dark:border-primary dark:bg-primary/10 dark:text-primary'
                  : 'border-border bg-background text-muted-foreground hover:bg-muted dark:border-border dark:bg-background dark:text-muted-foreground dark:hover:bg-muted',
                index === 0 ? 'rounded-l-md' : '',
                index === mergedLinks.length - 1 ? 'rounded-r-md' : '',
                'relative inline-flex items-center border px-4 py-2 text-sm font-medium transition-colors',
              ]"
              ><span v-html="link.label"
            /></Link>
            <span
              v-else
              :class="[
                'relative inline-flex cursor-not-allowed items-center border px-4 py-2 text-sm font-medium opacity-50',
                link.active
                  ? 'z-10 border-primary bg-primary/10 text-primary dark:border-primary dark:bg-primary/10 dark:text-primary'
                  : 'border-border bg-background text-muted-foreground dark:border-border dark:bg-background dark:text-muted-foreground',
                index === 0 ? 'rounded-l-md' : '',
                index === mergedLinks.length - 1 ? 'rounded-r-md' : '',
              ]"
              v-html="link.label"
            >
            </span>
          </template>
        </nav>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

interface PaginationLink {
  url: string | null;
  label: string;
  page: number | null;
  active: boolean;
}

const props = defineProps<{
  links: PaginationLink[];
  from: number;
  to: number;
  total: number;
}>();

// Merge the page number from each pagination link with the current URL's
// query params so that filters/search/sort are preserved when paginating.
function mergedUrl(linkUrl: string): string {
  const current = new URL(window.location.href);
  const linked = new URL(linkUrl, window.location.href);
  const page = linked.searchParams.get('page');
  if (page !== null) {
    current.searchParams.set('page', page);
  } else {
    current.searchParams.delete('page');
  }
  return current.pathname + current.search;
}

const mergedLinks = computed(() =>
  props.links.map((link) => ({
    ...link,
    url: link.url ? mergedUrl(link.url) : '#',
  })),
);
</script>
