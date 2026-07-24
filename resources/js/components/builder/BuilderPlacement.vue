<script setup lang="ts">
import { computed } from 'vue';
import type { BuilderPlacement } from '@/types';

const props = defineProps<{
  placement: BuilderPlacement;
  sampleData: Record<string, string>;
  selected: boolean;
}>();

const emit = defineEmits<{ select: [id: string] }>();

// Same preview approach as the template create page: substitute sample data
// into the block's code, strip leftover tag/conditional markers for visual
// cleanliness, render in a sandboxed iframe (free style isolation).
const previewDoc = computed(() => {
  let html = props.placement.snapshot.html;
  let css = props.placement.snapshot.css;

  Object.entries(props.sampleData).forEach(([tag, value]) => {
    const pattern = new RegExp(`\\[\\[\\[${tag}]]]`, 'g');
    html = html.replace(pattern, value);
    css = css.replace(pattern, value);
  });
  html = html.replace(/\[\[\[[^\]]*]]]/g, '');
  css = css.replace(/\[\[\[[^\]]*]]]/g, '');

  return `<!DOCTYPE html>
<html lang="en">
  <head><style>html,body{margin:0;padding:0;background:transparent;overflow:hidden;}</style><style>${css}</style>${props.placement.snapshot.head}</head>
  <body>${html}</body>
</html>`;
});

const gridArea = computed(
  () => `${props.placement.y} / ${props.placement.x} / span ${props.placement.h} / span ${props.placement.w}`,
);
</script>

<template>
  <div
    :style="{ gridArea }"
    :class="[
      'relative min-h-0 min-w-0 cursor-pointer overflow-hidden transition-shadow',
      selected ? 'ring-2 ring-violet-500 z-10' : 'ring-1 ring-sidebar-border/60 hover:ring-violet-400/60',
    ]"
    @click.stop="emit('select', placement.instance_id)"
  >
    <iframe
      :srcdoc="previewDoc"
      class="pointer-events-none h-full w-full border-0"
      sandbox=""
      tabindex="-1"
      :title="placement.block_name"
    />
    <div class="pointer-events-none absolute top-0 left-0 max-w-full truncate bg-sidebar-accent/90 px-2 py-0.5 font-mono text-xs text-foreground">
      {{ placement.block_name }}
    </div>
  </div>
</template>
