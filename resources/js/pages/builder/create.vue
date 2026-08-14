<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import PublicToggle from '@/components/PublicToggle.vue';
import BuilderCanvas from '@/components/builder/BuilderCanvas.vue';
import BuilderGridControls from '@/components/builder/BuilderGridControls.vue';
import BlockPickerModal, { type LibraryBlock } from '@/components/builder/BlockPickerModal.vue';
import SelectedBlockPanel from '@/components/builder/SelectedBlockPanel.vue';
import BuilderStylePanel from '@/components/builder/BuilderStylePanel.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useBuilderState, type BuilderControlDef } from '@/composables/useBuilderState';
import { useBlockSourceSync } from '@/composables/useBlockSourceSync';
import { composeBuilderTemplate } from '@/utils/composeBuilderTemplate';
import { sanitizeHtmlFields } from '@/utils/sanitize';
import { stashSaveNotice } from '@/utils/saveNotice';
import { compileTailwindCss } from '@/utils/compileTailwind';
import { isTextEntryTarget } from '@/utils/isTextEntryTarget';
import { renderTemplateSource } from '@/utils/renderTemplate';
import { controlsToPreviewData } from '@/utils/controlPreview';
import { ExternalLink, Save } from '@lucide/vue';

const props = defineProps<{
  sampleData: Record<string, string>;
  blocks: LibraryBlock[];
}>();

const page = usePage<AppPageProps>();
const locale = computed(() => page.props.auth.user?.locale ?? 'en-US');

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Builder', href: '/builder' }];

const state = useBuilderState();

// Blocks placed here bring control *definitions* rather than saved rows - the
// rows are only created on save - so previews resolve `c:` tags from those
// defaults. Recomputes as blocks are added or removed.
const previewData = computed<Record<string, unknown>>(() => ({
  ...props.sampleData,
  ...controlsToPreviewData(state.controlsForImport()),
}));
const { stalePlacementIds, refreshPlacement, syncAll, noteFreshSource } = useBlockSourceSync(state);

function refreshSelectedFromSource() {
  if (state.selectedId.value && refreshPlacement(state.selectedId.value)) {
    toast('Block refreshed from its source.', 'success');
  }
}

function syncAllFromSource() {
  const refreshed = syncAll();
  if (refreshed > 0) {
    toast(`${refreshed} block${refreshed === 1 ? '' : 's'} refreshed from source.`, 'success');
  }
}

const name = ref('');
const description = ref('');
const isPublic = ref(false);
const saving = ref(false);

const pickerOpen = ref(false);
const pickerCell = ref<{ x: number; y: number } | null>(null);

const showPreview = ref(false);
const previewHtml = ref('');
const previewBox = ref<HTMLDivElement | null>(null);
const previewScale = ref(0.5);

function measurePreview() {
  nextTick(() => {
    if (previewBox.value) {
      previewScale.value = previewBox.value.clientWidth / state.canvas.value.width;
    }
  });
}

const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);

function toast(message: string, type: typeof toastType.value = 'info') {
  showToast.value = false;
  toastMessage.value = message;
  toastType.value = type;
  showToast.value = true;
}

function onCellClick(x: number, y: number) {
  pickerCell.value = { x, y };
  pickerOpen.value = true;
}

async function onPickBlock(block: LibraryBlock) {
  pickerOpen.value = false;
  const cell = pickerCell.value;
  if (!cell) return;

  try {
    const { data } = await axios.get(route('templates.blocks.snapshot', block.id));
    noteFreshSource(block.id, data);
    const span = data.default_span ?? { w: 4, h: 2 };
    const placed = state.addPlacement(
      { id: data.id, slug: data.slug, name: data.name },
      { head: data.head, html: data.html, css: data.css },
      (data.controls ?? []) as BuilderControlDef[],
      cell.x,
      cell.y,
      span.w,
      span.h,
    );
    if (!placed) {
      toast('That block does not fit there. Try an emptier spot or a bigger grid.', 'warning');
    }
  } catch {
    toast('Could not load that block. It may have been removed.', 'error');
  }
}

const composed = computed(() => composeBuilderTemplate(state.serialize()));

function previewOverlay() {
  const { head, html, css } = composed.value;
  // Same two-pass pipeline the live overlay uses, so the composed preview
  // matches what OBS will show - pipes, `??` defaults, conditionals and
  // foreach included. See renderTemplateSource.
  const previewBody = renderTemplateSource(html, previewData.value, locale.value, true);
  const previewCss = renderTemplateSource(css, previewData.value, locale.value, false);

  previewHtml.value = `<!DOCTYPE html>
<html lang="en">
  <head><style>${previewCss}</style>${head}</head>
  <body>${previewBody}</body>
</html>`;
  showPreview.value = true;
  measurePreview();
}

async function save() {
  if (!name.value.trim()) {
    toast('Give your overlay a name first.', 'warning');
    return;
  }
  if (state.placements.value.length === 0) {
    toast('Place at least one block before saving.', 'warning');
    return;
  }

  saving.value = true;
  try {
    const { sanitized, removed } = sanitizeHtmlFields({ ...composed.value, name: name.value, description: description.value });
    const compiledCss = await compileTailwindCss({
      html: sanitized.html ?? '',
      head: sanitized.head ?? '',
      css: sanitized.css ?? '',
    });

    const { data } = await axios.post(
      route('templates.store'),
      {
        name: sanitized.name,
        description: sanitized.description,
        head: sanitized.head,
        html: sanitized.html,
        css: sanitized.css,
        compiled_css: compiledCss,
        type: 'static',
        is_public: isPublic.value,
        metadata: { builder: state.serialize() },
      },
      { headers: { Accept: 'application/json' } },
    );

    // The composed output carries the Builder's own CSS and head verbatim, so
    // the count above covers those editors too. Clean them to match, as the
    // edit page does: mostly moot when the navigation below happens, but the
    // controls import can throw and leave the user sitting on this page.
    state.sanitizeCustom();

    const controls = state.controlsForImport();
    if (controls.length) {
      await axios.post(`/templates/${data.template.id}/controls/import`, {
        controls: controls.map((c) => ({ ...c, action: 'create' })),
      });
    }

    // Handed to the template page rather than toasted here: this component is
    // about to unmount, and there is no moment at which a local toast would be
    // readable.
    if (removed > 0) {
      stashSaveNotice({
        message: `Saved! Removed ${removed} unsafe pattern${removed === 1 ? '' : 's'} (scripts, event handlers, or javascript: URIs).`,
        type: 'warning',
      });
    }

    router.visit(route('templates.show', data.template.id));
  } catch (error) {
    const message = axios.isAxiosError(error)
      ? ((Object.values(error.response?.data?.errors ?? {}).flat()[0] as string | undefined) ?? 'Save failed. Please try again.')
      : 'Save failed. Please try again.';
    toast(message, 'error');
  } finally {
    saving.value = false;
  }
}

// Keyboard: arrows move the selected block, shift+arrows resize, Delete removes.
function onKeydown(e: KeyboardEvent) {
  if (!state.selectedId.value || pickerOpen.value || showPreview.value) return;
  if (isTextEntryTarget(e.target)) return;

  const id = state.selectedId.value;
  const actions: Record<string, () => void> = {
    ArrowLeft: () => (e.shiftKey ? state.resize(id, -1, 0) : state.move(id, -1, 0)),
    ArrowRight: () => (e.shiftKey ? state.resize(id, 1, 0) : state.move(id, 1, 0)),
    ArrowUp: () => (e.shiftKey ? state.resize(id, 0, -1) : state.move(id, 0, -1)),
    ArrowDown: () => (e.shiftKey ? state.resize(id, 0, 1) : state.move(id, 0, 1)),
    Delete: () => state.remove(id),
    Backspace: () => state.remove(id),
  };
  if (actions[e.key]) {
    e.preventDefault();
    actions[e.key]();
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
  <Head title="Builder" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <div class="mb-6 flex items-start justify-between">
        <Heading
          title="Builder"
          description="Set up a grid, click a cell, pick a block. Your overlay compiles to plain HTML and CSS."
          description-class="text-sm text-muted-foreground"
        />
        <div class="flex shrink-0 items-center gap-2">
          <button type="button" class="btn btn-cancel" :disabled="!state.placements.value.length" @click="previewOverlay">
            Preview
            <ExternalLink class="ml-2 h-4 w-4" />
          </button>
          <button type="button" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" :disabled="saving" @click="save">
            <Save class="mr-2 h-4 w-4" />
            {{ saving ? 'Saving...' : 'Save Overlay' }}
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_280px]">
        <div class="min-w-0 space-y-4">
          <div class="border border-sidebar-border bg-sidebar-accent p-4">
            <BuilderGridControls :grid="state.grid.value" @update="state.setGrid" />
          </div>

          <BuilderCanvas
            :grid="state.grid.value"
            :canvas="state.canvas.value"
            :placements="state.placements.value"
            :selected-id="state.selectedId.value"
            :sample-data="previewData"
            :is-cell-occupied="(x, y) => state.occupied(x, y)"
            :stale-placement-ids="stalePlacementIds"
            :custom-css="state.appliedCss.value"
            :custom-head="state.appliedHead.value"
            @cell-click="onCellClick"
            @select="(id) => (state.selectedId.value = id)"
            @move-to="(id, x, y) => state.moveTo(id, x, y)"
            @resize-to="(id, x, y, w, h) => state.setRect(id, x, y, w, h)"
            @sync-all="syncAllFromSource"
          />

          <p class="text-sm text-muted-foreground">
            Click an empty cell to place a block. Drag a block to move it, or select it and use the panel or arrow keys - Shift + arrows to resize,
            Delete to remove.
          </p>

          <BuilderStylePanel
            v-model:css="state.customCss.value"
            v-model:head="state.customHead.value"
            :placements="state.placements.value"
            :css-stale="state.cssStale.value"
            :head-stale="state.headStale.value"
            @send-to-preview="state.applyStyles"
          />
        </div>

        <div class="space-y-4">
          <div class="space-y-4 border border-sidebar-border bg-sidebar-accent p-4">
            <div>
              <label for="builder-name" class="mb-1 block text-sm font-medium text-accent-foreground">Overlay Name *</label>
              <input id="builder-name" v-model="name" type="text" class="input-border w-full" placeholder="My Grid Overlay" data-1p-ignore />
            </div>
            <div>
              <label for="builder-description" class="mb-1 block text-sm font-medium text-accent-foreground">Description</label>
              <textarea id="builder-description" v-model="description" rows="3" class="input-border w-full" />
            </div>
            <PublicToggle v-model="isPublic" label="Overlay" />
          </div>

          <SelectedBlockPanel
            v-if="state.selected.value"
            :placement="state.selected.value"
            :source-stale="stalePlacementIds.has(state.selected.value.instance_id)"
            @move="(dx, dy) => state.move(state.selectedId.value!, dx, dy)"
            @resize="(dw, dh) => state.resize(state.selectedId.value!, dw, dh)"
            @remove="state.remove(state.selectedId.value!)"
            @refresh-source="refreshSelectedFromSource"
          />

          <div class="border border-sidebar-border bg-sidebar-accent p-4 text-sm text-foreground">
            <p class="mb-2">
              Blocks come from the community and your own account.
              <Link :href="`${route('templates.index')}?filter=public&type=block`" class="cursor-pointer text-violet-400 hover:underline"
                >Browse all blocks</Link
              >
            </p>
            <p class="text-xs text-muted-foreground">Blocks that use controls bring them along on save. Blocks sharing a control key stay in sync.</p>
          </div>
        </div>
      </div>
    </div>

    <BlockPickerModal :open="pickerOpen" :blocks="props.blocks" @update:open="pickerOpen = $event" @pick="onPickBlock" />

    <Dialog :open="showPreview" @update:open="showPreview = $event">
      <DialogContent class="max-w-5xl">
        <DialogHeader>
          <DialogTitle>Overlay Preview</DialogTitle>
        </DialogHeader>
        <div ref="previewBox" class="aspect-video w-full overflow-hidden rounded-sm border border-border bg-muted">
          <iframe
            v-if="previewHtml"
            :srcdoc="previewHtml"
            class="border-0"
            sandbox="allow-scripts"
            :style="{
              width: `${state.canvas.value.width}px`,
              height: `${state.canvas.value.height}px`,
              transform: `scale(${previewScale})`,
              transformOrigin: 'top left',
            }"
          />
        </div>
        <div class="flex items-center justify-between">
          <p class="text-sm text-muted-foreground">Tags are shown with sample data in preview.</p>
          <button class="btn btn-cancel cursor-pointer" @click="showPreview = false">Close</button>
        </div>
      </DialogContent>
    </Dialog>

    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />
  </AppLayout>
</template>
