<template>
  <div class="flex items-center justify-between">
    <div class="flex flex-1 justify-between sm:hidden">
      <Link
        v-if="links[0].url"
        :href="links[0].url"
        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-foreground bg-background border border-border rounded-md hover:bg-muted dark:text-foreground dark:bg-background dark:border-border dark:hover:bg-muted"
      >
        Previous
      </Link>
      <Link
        v-if="links[links.length - 1].url"
        :href="links[links.length - 1].url"
        class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-foreground bg-background border border-border rounded-md hover:bg-muted dark:text-foreground dark:bg-background dark:border-border dark:hover:bg-muted"
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
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
          <template v-for="(link, index) in links" :key="index">
            <Link
              v-if="link.url"
              :href="link.url"
              :class="[
                link.active
                  ? 'z-10 bg-primary/10 border-primary text-primary dark:bg-primary/10 dark:border-primary dark:text-primary'
                  : 'bg-background border-border text-muted-foreground hover:bg-muted dark:bg-background dark:border-border dark:text-muted-foreground dark:hover:bg-muted',
                index === 0 ? 'rounded-l-md' : '',
                index === links.length - 1 ? 'rounded-r-md' : '',
                'relative inline-flex items-center px-4 py-2 border text-sm font-medium transition-colors'
              ]"
              v-html="link.label"
            />
            <span
              v-else
              :class="[
                'relative inline-flex items-center px-4 py-2 border text-sm font-medium cursor-not-allowed opacity-50',
                link.active
                  ? 'z-10 bg-primary/10 border-primary text-primary dark:bg-primary/10 dark:border-primary dark:text-primary'
                  : 'bg-background border-border text-muted-foreground dark:bg-background dark:border-border dark:text-muted-foreground',
                index === 0 ? 'rounded-l-md' : '',
                index === links.length - 1 ? 'rounded-r-md' : ''
              ]"
              v-html="link.label"
            />
          </template>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  links: Array,
  from: Number,
  to: Number,
  total: Number,
});
</script>
