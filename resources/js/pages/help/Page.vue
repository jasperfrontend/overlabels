<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import HelpLayout from '@/layouts/HelpLayout.vue';
import Heading from '@/components/Heading.vue';
import type { BreadcrumbItem } from '@/types';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

/**
 * Generic renderer for every help page. Content comes from
 * resources/help/pages/<slug>.md, rendered to HTML server-side by
 * App\Support\HelpPage - so the prose ships inside the response instead of
 * being locked in the JS bundle the way the old hand-written pages were.
 */
interface TocEntry {
  id: string;
  text: string;
}

const props = defineProps<{
  slug: string;
  title: string;
  description: string;
  heading: string;
  lead: string;
  canonical: string;
  section: string | null;
  html: string;
  toc: TocEntry[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
  const crumbs: BreadcrumbItem[] = [{ title: 'Help', href: '/help' }];

  if (props.section) {
    crumbs.push({ title: props.section, href: '/help/bot' });
  }

  crumbs.push({ title: props.heading, href: `/help/${props.slug}` });

  return crumbs;
});

const content = ref<HTMLElement | null>(null);

/**
 * Render any TeX the server left as `.help-math` placeholders. KaTeX is
 * client-side only, so the markdown pipeline stashes the source in a data
 * attribute and we typeset it here. Loaded lazily so pages without math never
 * pay for the library.
 */
async function renderMath() {
  const nodes = content.value?.querySelectorAll<HTMLElement>('.help-math:not([data-rendered])');
  if (!nodes?.length) return;

  const [{ default: katex }] = await Promise.all([import('katex'), import('katex/dist/katex.min.css')]);

  nodes.forEach((node) => {
    const tex = node.dataset.tex ?? '';
    try {
      katex.render(tex, node, {
        displayMode: node.dataset.display === '1',
        throwOnError: false,
        output: 'html',
      });
    } catch {
      node.textContent = tex;
    }
    node.dataset.rendered = '1';
  });
}

onMounted(renderMath);
watch(
  () => props.html,
  () => nextTick(renderMath),
);

/**
 * Keep SPA navigation for in-app links. Rendered markdown produces plain
 * anchors, which would otherwise trigger a full page load and lose the
 * Inertia shell. Anchor jumps, external links and modified clicks are all
 * left to the browser.
 */
function onContentClick(event: MouseEvent) {
  if (event.defaultPrevented || event.button !== 0) return;
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

  const anchor = (event.target as HTMLElement)?.closest('a');
  if (!anchor) return;

  const href = anchor.getAttribute('href');
  if (!href || href.startsWith('#')) return;
  if (anchor.target && anchor.target !== '_self') return;
  if (!href.startsWith('/') || href.startsWith('//')) return;

  event.preventDefault();
  router.visit(href);
}
</script>

<template>
  <HelpLayout :breadcrumbs="breadcrumbs" :title="props.title" :description="props.description" :canonical-url="props.canonical">
    <div class="mb-10">
      <Heading :title="props.heading" title-class="text-4xl font-bold mb-4" :description="props.lead" />
    </div>

    <nav v-if="props.toc.length > 1" class="mb-12 border border-sidebar-border bg-card p-6">
      <h2 id="toc" class="mb-4 text-xl font-bold">Table of contents</h2>
      <ol class="list-decimal space-y-1 pl-6 text-foreground">
        <li v-for="entry in props.toc" :key="entry.id">
          <a :href="`#${entry.id}`" class="cursor-pointer text-violet-400 hover:underline">{{ entry.text }}</a>
        </li>
      </ol>
    </nav>

    <!-- Server-rendered from markdown. Safe: the source is repo-controlled
         prose written by us, never user input. -->
    <!-- eslint-disable-next-line vue/no-v-html -->
    <article ref="content" class="help-prose" @click="onContentClick" v-html="props.html" />
  </HelpLayout>
</template>
