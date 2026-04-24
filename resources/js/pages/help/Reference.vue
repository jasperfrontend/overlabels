<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import { useHelpReference, renderHelpMarkdown, type HelpEntry } from '@/composables/useHelpReference';
import { BookOpen, Check, Copy, Search, X } from 'lucide-vue-next';

interface Props {
  category?: string | null;
  slug?: string | null;
}
const props = defineProps<Props>();

const { allEntries, search, get, grouped } = useHelpReference();

const selected = computed<HelpEntry | null>(() => {
  if (props.category && props.slug) return get(props.category, props.slug) ?? null;
  return null;
});

const query = ref('');
const searchResults = computed<HelpEntry[]>(() => {
  const q = query.value.trim();
  if (!q) return [];
  return search(q, 50);
});

const isSearching = computed(() => query.value.trim().length > 0);

const renderedBody = computed(() => (selected.value ? renderHelpMarkdown(selected.value.body) : ''));

// Derive the literal tag snippet from filename + category. Skip aggregate
// index files (all-*) and categories where slug-to-tag isn't 1:1.
interface TagSnippet {
  label: string;
  code: string;
  language: 'tag' | 'block';
}
const tagSnippet = computed<TagSnippet | null>(() => {
  if (!selected.value) return null;
  const { category, slug } = selected.value;
  if (slug.startsWith('all-')) return null;

  if (category === 'template-tags') {
    return { label: 'Tag', code: `[[[${slug}]]]`, language: 'tag' };
  }
  if (category === 'foreach-loops') {
    const alias = slug.split('.').pop()?.replace(/s$/, '') || 'item';
    return {
      label: 'Loop',
      code: `[[[foreach:${slug} as ${alias}]]]\n  [[[${alias}.id]]]\n[[[endforeach]]]`,
      language: 'block',
    };
  }
  return null;
});

const copied = ref(false);
async function copyTag() {
  if (!tagSnippet.value) return;
  try {
    await navigator.clipboard.writeText(tagSnippet.value.code);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1400);
  } catch {
    // clipboard blocked; ignore
  }
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
  const crumbs: BreadcrumbItem[] = [
    { title: 'Help', href: '/help' },
    { title: 'Reference', href: '/help/reference' },
  ];
  if (selected.value) {
    crumbs.push({
      title: selected.value.categoryLabel,
      href: `/help/reference/${selected.value.category}`,
    });
    crumbs.push({
      title: selected.value.title,
      href: `/help/reference/${selected.value.category}/${selected.value.slug}`,
    });
  }
  return crumbs;
});

const pageTitle = computed(() =>
  selected.value ? `${selected.value.title} - Reference` : 'Reference - Overlabels'
);

const pageDescription = computed(() =>
  selected.value
    ? (selected.value.body.replace(/\s+/g, ' ').slice(0, 180))
    : 'Searchable reference for every Overlabels template tag, EventSub event, and foreach loop field.'
);

const categoryGroups = computed(() => grouped());

// When the user clicks a search result, we navigate with Inertia.
function openEntry(entry: HelpEntry) {
  router.visit(`/help/reference/${entry.category}/${entry.slug}`);
}

// Rewrite anchor clicks inside rendered markdown so internal /help/reference/...
// links stay SPA-navigated (and external ones open in a new tab).
function onBodyClick(e: MouseEvent) {
  const target = (e.target as HTMLElement).closest('a') as HTMLAnchorElement | null;
  if (!target) return;
  const href = target.getAttribute('href');
  if (!href) return;
  if (href.startsWith('/help/reference/')) {
    e.preventDefault();
    router.visit(href);
  } else if (/^https?:\/\//.test(href)) {
    target.setAttribute('target', '_blank');
    target.setAttribute('rel', 'noopener noreferrer');
  }
}

// Reset search when we open a new entry so the sidebar shows full tree again.
watch(
  () => selected.value?.slug,
  () => {
    query.value = '';
  }
);

// Stats for landing view.
const totalCount = computed(() => allEntries.value.length);
</script>

<template>
  <HelpLayout
    :breadcrumbs="breadcrumbs"
    :title="pageTitle"
    :description="pageDescription"
    :canonical-url="`https://overlabels.com/help/reference${selected ? `/${selected.category}/${selected.slug}` : ''}`"
  >
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <BookOpen class="size-6 text-muted-foreground" />
      <h1 class="text-2xl font-bold">Reference</h1>
      <span class="text-sm text-muted-foreground">{{ totalCount }} entries</span>
      <span class="ml-auto text-xs text-muted-foreground hidden sm:inline">
        Tip: press <kbd class="border rounded px-1">Ctrl</kbd>+<kbd class="border rounded px-1">/</kbd> anywhere to search
      </span>
    </div>

    <div class="relative mb-4">
      <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
      <input
        v-model="query"
        type="text"
        placeholder="Search everything (e.g. follower, raid, hype train)..."
        class="w-full rounded-md border bg-background py-2 pl-9 pr-9 text-sm outline-none focus:ring-2 focus:ring-ring"
      />
      <button
        v-if="query"
        class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer rounded p-1 text-muted-foreground hover:bg-accent"
        type="button"
        aria-label="Clear search"
        @click="query = ''"
      >
        <X class="size-4" />
      </button>
    </div>

    <div class="grid gap-6 md:grid-cols-[280px_minmax(0,1fr)]">
      <aside class="max-h-[calc(100vh-16rem)] overflow-y-auto rounded-md border p-2">
        <div v-if="isSearching">
          <div class="px-2 pt-1 pb-2 text-[11px] font-medium text-muted-foreground/70 uppercase tracking-wide">
            {{ searchResults.length }} results
          </div>
          <div v-if="searchResults.length === 0" class="p-4 text-center text-xs text-muted-foreground">
            Nothing matched.
          </div>
          <button
            v-for="entry in searchResults"
            :key="`s-${entry.category}/${entry.slug}`"
            class="flex w-full flex-col items-start gap-0.5 rounded-md px-2 py-1.5 text-left text-sm cursor-pointer hover:bg-accent"
            :class="selected && selected.slug === entry.slug && selected.category === entry.category ? 'bg-accent' : ''"
            @click="openEntry(entry)"
          >
            <span class="font-mono text-xs truncate w-full">{{ entry.title }}</span>
            <span class="text-[10px] uppercase tracking-wide text-muted-foreground/70">{{ entry.categoryLabel }}</span>
          </button>
        </div>

        <div v-else>
          <template v-for="group in categoryGroups" :key="group.category">
            <div class="px-2 pt-2 pb-1 text-[11px] font-medium text-muted-foreground/70 uppercase tracking-wide">
              {{ group.categoryLabel }}
              <span class="ml-1 normal-case font-normal text-muted-foreground/50">({{ group.items.length }})</span>
            </div>
            <Link
              v-for="entry in group.items"
              :key="`t-${entry.category}/${entry.slug}`"
              :href="`/help/reference/${entry.category}/${entry.slug}`"
              class="block rounded-md px-2 py-1 font-mono text-xs cursor-pointer hover:bg-accent"
              :class="selected && selected.slug === entry.slug && selected.category === entry.category ? 'bg-accent text-accent-foreground' : 'text-foreground'"
            >
              {{ entry.title }}
            </Link>
          </template>
        </div>
      </aside>

      <article class="min-w-0">
        <div v-if="!selected" class="rounded-md border p-6">
          <h2 class="mb-2 text-lg font-semibold">Pick an entry from the sidebar</h2>
          <p class="text-sm text-foreground">
            Or start typing above. The search looks through titles, slugs, and body text - so "followe" finds every
            follower-related tag, event, and loop field at once.
          </p>
          <p class="mt-3 text-sm text-muted-foreground">
            You can also open this from anywhere in the app with
            <kbd class="border rounded px-1 text-xs">Ctrl</kbd>+<kbd class="border rounded px-1 text-xs">/</kbd>.
          </p>
          <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <div
              v-for="group in categoryGroups"
              :key="`c-${group.category}`"
              class="rounded-md border p-3"
            >
              <div class="font-medium text-sm">{{ group.categoryLabel }}</div>
              <div class="text-xs text-muted-foreground">{{ group.items.length }} entries</div>
            </div>
          </div>
        </div>

        <div v-else class="rounded-md border p-6">
          <div class="mb-4 flex items-center gap-3">
            <span class="text-[10px] uppercase tracking-wide text-muted-foreground/70">
              {{ selected.categoryLabel }}
            </span>
          </div>
          <h2 class="mb-4 font-mono text-2xl font-semibold break-all">{{ selected.title }}</h2>

          <div
            v-if="tagSnippet"
            class="mb-5 rounded-md border bg-muted/40"
          >
            <div class="flex items-center justify-between border-b px-3 py-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">
              <span>{{ tagSnippet.label }}</span>
              <button
                type="button"
                class="flex items-center gap-1 rounded px-1.5 py-0.5 text-foreground cursor-pointer hover:bg-accent"
                @click="copyTag"
              >
                <Check v-if="copied" class="size-3" />
                <Copy v-else class="size-3" />
                <span class="text-[10px]">{{ copied ? 'Copied' : 'Copy' }}</span>
              </button>
            </div>
            <pre class="overflow-x-auto px-3 py-2 font-mono text-sm text-foreground whitespace-pre-wrap break-all">{{ tagSnippet.code }}</pre>
          </div>

          <div
            class="help-prose text-sm text-foreground"
            @click="onBodyClick"
            v-html="renderedBody"
          />
        </div>
      </article>
    </div>
  </HelpLayout>
</template>

<style scoped>
.help-prose :deep(p) {
  margin: 0.5rem 0;
  line-height: 1.65;
}
.help-prose :deep(h1),
.help-prose :deep(h2),
.help-prose :deep(h3),
.help-prose :deep(h4) {
  font-weight: 600;
  margin: 1.25rem 0 0.5rem;
  line-height: 1.3;
}
.help-prose :deep(h1) { font-size: 1.5rem; }
.help-prose :deep(h2) { font-size: 1.25rem; }
.help-prose :deep(h3) { font-size: 1.1rem; }
.help-prose :deep(ul),
.help-prose :deep(ol) {
  margin: 0.5rem 0;
  padding-left: 1.5rem;
}
.help-prose :deep(ul) { list-style: disc; }
.help-prose :deep(ol) { list-style: decimal; }
.help-prose :deep(li) { margin: 0.25rem 0; }
.help-prose :deep(code) {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.85em;
  background: hsl(var(--accent) / 0.6);
  padding: 0.1em 0.35em;
  border-radius: 0.25rem;
}
.help-prose :deep(pre) {
  background: hsl(var(--muted));
  padding: 0.75rem 1rem;
  border-radius: 0.375rem;
  overflow-x: auto;
  margin: 0.75rem 0;
}
.help-prose :deep(pre code) {
  background: transparent;
  padding: 0;
  font-size: 0.85em;
}
.help-prose :deep(a) {
  color: hsl(var(--primary));
  text-decoration: underline;
  text-underline-offset: 2px;
  cursor: pointer;
}
.help-prose :deep(a:hover) { text-decoration-thickness: 2px; }
.help-prose :deep(blockquote) {
  border-left: 3px solid hsl(var(--border));
  padding-left: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin: 0.75rem 0;
}
.help-prose :deep(hr) {
  border: 0;
  border-top: 1px solid hsl(var(--border));
  margin: 1.25rem 0;
}
.help-prose :deep(table) {
  border-collapse: collapse;
  margin: 0.75rem 0;
  width: 100%;
}
.help-prose :deep(th),
.help-prose :deep(td) {
  border: 1px solid hsl(var(--border));
  padding: 0.4rem 0.6rem;
  text-align: left;
}
.help-prose :deep(th) {
  background: hsl(var(--accent) / 0.4);
  font-weight: 600;
}
</style>
