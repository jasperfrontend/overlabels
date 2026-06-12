<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { marked } from 'marked';
import AppLayout from '@/layouts/AppLayout.vue';
import { ArrowLeft, PencilIcon } from '@lucide/vue';
import type { BreadcrumbItem, Update, AppPageProps } from '@/types';

const props = defineProps<{
  update: Update;
}>();

marked.setOptions({ breaks: true, gfm: true });

const page = usePage<AppPageProps>();
const isAdmin = computed(() => page.props.isAdmin);

const renderedBody = computed(() => marked.parse(props.update.body) as string);
const renderedExcerpt = computed(() =>
  props.update.excerpt ? (marked.parse(props.update.excerpt) as string) : ''
);

// Tailwind v4 only ships utilities that appear in source files. Anything
// authors write inside markdown (e.g. `bg-yellow-400/10`) is invisible to it,
// so the compiler in the admin form pre-generates the missing utilities via
// UnoCSS preset-wind3 and stores them on the row. We inject that CSS into
// <head> while the post is mounted, then strip it on unmount so it doesn't
// leak utilities into other pages on client-side navigation.
const STYLE_ID = 'updates-post-style';
function injectPostStyle(css: string) {
  document.getElementById(STYLE_ID)?.remove();
  if (!css) return;
  const style = document.createElement('style');
  style.id = STYLE_ID;
  style.textContent = css;
  document.head.appendChild(style);
}
watch(
  () => props.update.compiled_css,
  (css) => injectPostStyle(css ?? ''),
  { immediate: true },
);
onBeforeUnmount(() => {
  document.getElementById(STYLE_ID)?.remove();
});

const formattedDate = computed(() =>
  new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(new Date(props.update.published_at))
);

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Updates', href: '/updates' },
  { title: props.update.title, href: `/updates/${props.update.slug}` },
];
</script>

<template>
  <Head>
    <title>{{ props.update.title }} - Overlabels</title>
    <meta v-if="props.update.excerpt" name="description" :content="props.update.excerpt" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <div class="mb-6 flex items-center justify-between gap-4">
          <Link href="/updates" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground cursor-pointer">
            <ArrowLeft class="h-4 w-4" />
            All updates
          </Link>
          <Link
            v-if="isAdmin"
            :href="`/admin/updates/${props.update.id}/edit`"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground cursor-pointer"
          >
            <PencilIcon class="h-4 w-4" />
            Edit
          </Link>
        </div>

        <div class="mb-8">
          <h1 class="text-4xl font-extrabold tracking-tight">{{ props.update.title }}</h1>
          <p class="mt-3 text-sm text-muted-foreground">{{ formattedDate }}</p>

          <div v-if="props.update.tags && props.update.tags.length" class="mt-3 flex flex-wrap gap-1">
            <Link
              v-for="tag in props.update.tags"
              :key="tag"
              :href="`/updates?tag=${encodeURIComponent(tag)}`"
              class="inline-flex items-center rounded-sm bg-sidebar px-2 py-0.5 text-xs text-foreground hover:bg-sidebar-accent cursor-pointer"
            >
              {{ tag }}
            </Link>
          </div>

          <div
            v-if="renderedExcerpt"
            class="mt-6 prose prose-invert max-w-none text-lg text-foreground"
            v-html="renderedExcerpt"
          />
        </div>

        <article class="prose prose-invert max-w-none text-foreground" v-html="renderedBody" />
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
:deep(.prose) {
  color: var(--foreground);
}
:deep(.prose h1),
:deep(.prose h2),
:deep(.prose h3),
:deep(.prose h4) {
  color: var(--foreground);
  font-weight: 700;
  margin-top: 1.5em;
  margin-bottom: 0.5em;
}
:deep(.prose h1) { font-size: 2rem; }
:deep(.prose h2) { font-size: 1.5rem; }
:deep(.prose h3) { font-size: 1.25rem; }
:deep(.prose p) {
  margin-top: 1em;
  margin-bottom: 1em;
  line-height: 1.7;
}
:deep(.prose a) {
  color: var(--primary);
  text-decoration: underline;
}
:deep(.prose ul),
:deep(.prose ol) {
  padding-left: 1.5em;
  margin-top: 1em;
  margin-bottom: 1em;
}
:deep(.prose ul) { list-style: disc; }
:deep(.prose ol) { list-style: decimal; }
:deep(.prose li) { margin-top: 0.25em; margin-bottom: 0.25em; }
:deep(.prose code) {
  background-color: var(--sidebar);
  padding: 0.1em 0.3em;
  border-radius: 0.25em;
  font-size: 0.9em;
}
:deep(.prose pre) {
  background-color: var(--sidebar);
  padding: 1em;
  border-radius: 0.375em;
  overflow-x: auto;
  margin-top: 1em;
  margin-bottom: 1em;
}
:deep(.prose pre code) {
  background-color: transparent;
  padding: 0;
}
:deep(.prose blockquote) {
  border-left: 4px solid var(--sidebar-border);
  padding-left: 1em;
  font-style: italic;
  color: var(--muted-foreground);
}
:deep(.prose img) {
  max-width: 100%;
  height: auto;
  border-radius: 0.375em;
  margin-top: 1em;
  margin-bottom: 1em;
}
</style>
