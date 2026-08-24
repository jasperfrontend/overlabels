<script setup lang="ts">
import { computed, ref } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { PlusIcon, PencilIcon, Trash2Icon, CopyIcon, CopyPlusIcon, LockIcon, Blocks } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import GroupedCollection from '@/components/GroupedCollection.vue';
import type { CollectionGroup } from '@/types/collection';
import ControlFormModal from '@/components/ControlFormModal.vue';
import RekaToast from '@/components/RekaToast.vue';
import type { OverlayControl, OverlayTemplate } from '@/types';
import { SERVICE_LABELS } from '@/utils/services';
import { CONTROL_TYPE_LABELS as TYPE_LABELS, CONTROL_TYPE_ORDER as TYPE_ORDER } from '@/utils/controls';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();
interface UserList {
  id: number;
  slug: string;
  label?: string | null;
  items_count: number;
  disabled: boolean;
}

const props = defineProps<{
  template: OverlayTemplate;
  initialControls: OverlayControl[];
  connectedServices?: string[];
  userScopedControls?: OverlayControl[];
  userLists?: UserList[];
}>();

const emit = defineEmits<{
  (e: 'change', controls: OverlayControl[]): void;
}>();

const controls = ref<OverlayControl[]>([...props.initialControls]);
const modalOpen = ref(false);
const editingControl = ref<OverlayControl | null>(null);
const copyingFrom = ref<OverlayControl | null>(null);
const toastMessage = ref('');
const toastType = ref<'success' | 'error'>('success');
const showToast = ref(false);

function showMsg(msg: string, type: 'success' | 'error' = 'success') {
  toastMessage.value = msg;
  toastType.value = type;
  showToast.value = false;
  setTimeout(() => {
    showToast.value = true;
  }, 10);
}

function openAdd() {
  editingControl.value = null;
  copyingFrom.value = null;
  modalOpen.value = true;
}

function openEdit(control: OverlayControl) {
  editingControl.value = control;
  copyingFrom.value = null;
  modalOpen.value = true;
}

function openCopy(control: OverlayControl) {
  editingControl.value = null;
  copyingFrom.value = control;
  modalOpen.value = true;
}

function onSaved(saved: OverlayControl) {
  const idx = controls.value.findIndex((c) => c.id === saved.id);
  if (idx >= 0) {
    controls.value[idx] = saved;
  } else {
    controls.value.push(saved);
  }
  controls.value.sort((a, b) => a.sort_order - b.sort_order);
  emit('change', [...controls.value]);
  showMsg(editingControl.value ? 'Control updated.' : 'Control added.');
}

async function deleteControl(control: OverlayControl) {
  if (!(await confirm({ message: `Delete control "${control.label || control.key}"? This cannot be undone.`, confirmLabel: 'Delete' }))) return;

  try {
    await axios.delete(`/templates/${props.template.id}/controls/${control.id}`);
    controls.value = controls.value.filter((c) => c.id !== control.id);
    emit('change', [...controls.value]);
    showMsg('Control deleted.');
  } catch {
    showMsg('Failed to delete control.', 'error');
  }
}

function snippetKey(ctrl: OverlayControl): string {
  return ctrl.source_managed && ctrl.source ? `${ctrl.source}:${ctrl.key}` : ctrl.key;
}

// Builder-composed overlays only: flag template controls whose tag no longer
// appears in the compiled output, i.e. every block that used them has been
// removed from the canvas. The value is deliberately preserved (re-adding the
// block reattaches it) - this badge just makes the leftovers visible so the
// user can decide. template_tags is re-extracted server-side on every save.
const builderComposed = computed(() => !!props.template?.metadata?.builder);

// Which placed blocks reference each control key. Built by scanning the
// placement snapshots for [[[c:key]]] (and [[[if:c:key ...]]]) tags, so it is
// "used by", not "came from" - always true, even for a hand-made control that
// a block happens to reference. Keys with a colon are namespaced service tags
// (c:kofi:x) and never template-scoped, so they are skipped.
const blockUsage = computed<Map<string, string[]>>(() => {
  const map = new Map<string, string[]>();
  for (const placement of props.template?.metadata?.builder?.placements ?? []) {
    const code = `${placement.snapshot?.head ?? ''}\n${placement.snapshot?.html ?? ''}\n${placement.snapshot?.css ?? ''}`;
    const keys = new Set<string>();
    for (const match of code.matchAll(/\[\[\[(?:if:)?c:([a-zA-Z0-9_.:-]+)/g)) {
      if (!match[1].includes(':')) keys.add(match[1]);
    }
    for (const key of keys) {
      const names = map.get(key) ?? [];
      if (!names.includes(placement.block_name)) names.push(placement.block_name);
      map.set(key, names);
    }
  }
  return map;
});

function usedByBlocks(ctrl: OverlayControl): string[] {
  if (ctrl.source || ctrl.overlay_template_id === null) return [];
  return blockUsage.value.get(ctrl.key) ?? [];
}

function usedByBlocksLabel(ctrl: OverlayControl): string {
  const names = usedByBlocks(ctrl);
  return names.length > 1 ? `${names[0]} +${names.length - 1}` : (names[0] ?? '');
}

function usedByBlocksTitle(ctrl: OverlayControl): string {
  const names = usedByBlocks(ctrl);
  return `Used by block${names.length === 1 ? '' : 's'}: ${names.join(', ')}. Block controls stay fully editable.`;
}

function isBlockOrphan(ctrl: OverlayControl): boolean {
  const tags = props.template?.template_tags;
  if (!builderComposed.value || !Array.isArray(tags)) return false;
  if (ctrl.source || ctrl.overlay_template_id === null) return false;
  return !tags.includes(`c:${ctrl.key}`);
}

async function copySnippet(ctrl: OverlayControl) {
  const key = snippetKey(ctrl);
  try {
    await navigator.clipboard.writeText(`[[[c:${key}]]]`);
    showMsg(`[[[c:${key}]]] copied to clipboard!`);
  } catch {
    showMsg('Copy failed.', 'error');
  }
}

const page = usePage();
const userLocale = computed<string | undefined>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.locale || undefined;
});

function lookupControlLabel(id: number | undefined): string {
  if (!id) return '(unknown control)';
  const all = [...controls.value, ...(props.userScopedControls ?? [])];
  const c = all.find((x) => x.id === id);
  return c ? c.label || c.key : `(control #${id})`;
}

function lookupListLabel(id: number | undefined): string {
  if (!id) return '(unknown list)';
  const l = (props.userLists ?? []).find((x) => x.id === id);
  return l ? l.label || l.slug : `(list #${id})`;
}

function configSummary(ctrl: OverlayControl): string[] {
  const cfg = ctrl.config ?? {};
  const parts: string[] = [];

  if (ctrl.type === 'number' || ctrl.type === 'counter') {
    if (cfg.min != null) parts.push(`Min: ${cfg.min}`);
    if (cfg.max != null) parts.push(`Max: ${cfg.max}`);
    if (cfg.step != null && cfg.step !== 1) parts.push(`Step: ${cfg.step}`);
    if (cfg.reset_value != null) parts.push(`Reset: ${cfg.reset_value}`);
  } else if (ctrl.type === 'timer') {
    const mode = cfg.mode === 'countto' ? 'Count to' : cfg.mode === 'countdown' ? 'Countdown' : 'Count up';
    parts.push(mode);
    if (cfg.mode === 'countdown' && cfg.base_seconds) parts.push(`${cfg.base_seconds}s`);
    if (cfg.mode === 'countto' && cfg.target_datetime) parts.push(new Date(cfg.target_datetime).toLocaleString(userLocale.value));
  } else if (ctrl.type === 'expression') {
    const expr = cfg.expression;
    if (expr) parts.push(expr);
  } else if (ctrl.type === 'list_writer') {
    const source = lookupControlLabel(cfg.source_control_id);
    const target = lookupListLabel(cfg.target_list_id);
    parts.push(`${source} → ${target}`);
  } else if (ctrl.type === 'datetime' && ctrl.value) {
    parts.push(new Date(ctrl.value).toLocaleString(userLocale.value));
  } else if (ctrl.value) {
    parts.push(ctrl.value);
  }

  return parts;
}

// ---- Grouping ----
// Keyed by label, which is what the pre-component manager persisted its
// expanded state under.
const groupedControls = computed<CollectionGroup<OverlayControl>[]>(() => {
  const serviceGroups: Record<string, OverlayControl[]> = {};
  const userControls: Record<string, OverlayControl[]> = {};

  for (const ctrl of controls.value) {
    if (ctrl.source && SERVICE_LABELS[ctrl.source]) {
      (serviceGroups[ctrl.source] ??= []).push(ctrl);
    } else {
      (userControls[ctrl.type] ??= []).push(ctrl);
    }
  }

  const groups: CollectionGroup<OverlayControl>[] = [];

  for (const type of TYPE_ORDER) {
    if (userControls[type]?.length) {
      const label = TYPE_LABELS[type] ?? type;
      groups.push({ key: label, label, items: [...userControls[type]].sort((a, b) => a.sort_order - b.sort_order) });
    }
  }

  for (const [source, ctrls] of Object.entries(serviceGroups)) {
    const label = SERVICE_LABELS[source] ?? source;
    groups.push({ key: label, label, items: [...ctrls].sort((a, b) => a.sort_order - b.sort_order) });
  }

  return groups;
});

function controlMatches(ctrl: OverlayControl, query: string): boolean {
  return (ctrl.label || '').toLowerCase().includes(query) || ctrl.key.toLowerCase().includes(query) || snippetKey(ctrl).toLowerCase().includes(query);
}

const controlsCounter = computed(() => controls.value.length);
</script>

<template>
  <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />

  <ControlFormModal
    v-model:open="modalOpen"
    :template="template"
    :control="editingControl"
    :copy-from="copyingFrom"
    :connected-services="connectedServices"
    :existing-controls="controls"
    :user-scoped-controls="userScopedControls"
    :user-lists="userLists"
    @saved="onSaved"
  />

  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <p class="text-sm text-foreground">
        Define mutable values your template can reference. Check
        <a class="text-violet-400 hover:underline" href="/help/controls" target="_blank">the guide</a>
        to see how to implement Controls in your Overlays.
      </p>
      <button
        type="button"
        class="btn btn-primary btn-sm shrink-0"
        :disabled="controls.length >= 50"
        :title="controls.length >= 50 ? 'Maximum 50 controls per template' : undefined"
        @click="openAdd"
      >
        <PlusIcon class="mr-1.5 h-3.5 w-3.5" />
        Add control
      </button>
    </div>

    <GroupedCollection
      :groups="groupedControls"
      :item-key="(ctrl) => ctrl.id"
      :matches="controlMatches"
      storage-key="controls_manager_expanded"
      noun="control"
      empty-message="No controls yet. Add one to get started."
    >
      <template #item="{ item: ctrl }">
        <div
          class="row group/row collection-row flex cursor-pointer items-start justify-between gap-3 p-3 transition-all"
          role="button"
          tabindex="0"
          :title="`Click to edit ${ctrl.label || ctrl.key}`"
          @click="openEdit(ctrl)"
          @keydown.enter.prevent="openEdit(ctrl)"
          @keydown.space.prevent="openEdit(ctrl)"
        >
          <!-- Left: label + key + config summary -->
          <div class="flex min-w-0 flex-1 flex-col gap-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
              <span class="font-medium text-foreground">{{ ctrl.label || ctrl.key }}</span>
              <Badge variant="outline" class="text-[10px] capitalize">{{ ctrl.type }}</Badge>
              <span
                v-if="ctrl.source_managed && ctrl.source"
                class="inline-flex items-center gap-1 rounded-full border border-muted-foreground/30 bg-mauve-300/50 px-2 py-0.5 text-[10px] text-muted-foreground dark:bg-mauve-700/50"
                :title="`Managed by ${SERVICE_LABELS[ctrl.source] ?? ctrl.source} - cannot be manually changed`"
              >
                <LockIcon class="h-2.5 w-2.5" />
                {{ SERVICE_LABELS[ctrl.source] ?? ctrl.source }}
              </span>
              <span
                v-if="usedByBlocks(ctrl).length"
                class="inline-flex items-center gap-1 rounded-full border border-muted-foreground/30 bg-mauve-300/50 px-2 py-0.5 text-[10px] text-muted-foreground dark:bg-mauve-700/50"
                :title="usedByBlocksTitle(ctrl)"
              >
                <Blocks class="h-2.5 w-2.5" />
                {{ usedByBlocksLabel(ctrl) }}
              </span>
              <span
                v-if="isBlockOrphan(ctrl)"
                class="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] text-amber-600 dark:text-amber-400"
                title="No block on the canvas references this control. Its value is preserved - re-adding a block with the same key picks it back up, or delete it here if you no longer need it."
              >
                Not used by any block
              </span>
            </div>
            <p v-if="ctrl.description" class="text-xs whitespace-pre-line text-foreground">{{ ctrl.description }}</p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
              <span class="font-mono">{{ snippetKey(ctrl) }}</span>
              <span v-for="(part, i) in configSummary(ctrl)" :key="i" class="max-w-64 truncate" :title="part">{{ part }}</span>
            </div>
          </div>

          <!-- Right: snippet pill + actions -->
          <div class="flex shrink-0 items-center gap-2" @click.stop @keydown.stop>
            <button
              v-if="ctrl.type !== 'list_writer'"
              type="button"
              class="cursor-pointer items-center gap-1.5 rounded-sm border border-dashed border-sidebar-accent bg-background/60 px-2 py-1 font-mono text-xs text-muted-foreground opacity-60 transition hover:opacity-100 md:flex"
              :title="`Click to copy [[[c:${snippetKey(ctrl)}]]] to clipboard`"
              @click="copySnippet(ctrl)"
            >
              <CopyIcon class="h-3 w-3 shrink-0" />
              [[[c:{{ snippetKey(ctrl) }}]]]
            </button>
            <div class="flex items-center gap-1 opacity-30 transition group-hover/row:opacity-100 focus-within:opacity-100">
              <button type="button" class="btn btn-sm btn-primary px-2" :title="`Edit Control: ${ctrl.label || ctrl.key}`" @click="openEdit(ctrl)">
                <PencilIcon class="h-3.5 w-3.5" />
              </button>
              <button
                v-if="!ctrl.source_managed"
                type="button"
                class="btn btn-sm btn-primary px-2"
                :title="`Duplicate Control: ${ctrl.label || ctrl.key}`"
                @click="openCopy(ctrl)"
              >
                <CopyPlusIcon class="h-3.5 w-3.5" />
              </button>
              <button
                type="button"
                class="btn btn-sm btn-danger px-2"
                :title="`Delete Control: ${ctrl.label || ctrl.key}`"
                @click="deleteControl(ctrl)"
              >
                <Trash2Icon class="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
        </div>
      </template>
    </GroupedCollection>

    <div class="flex items-center justify-between gap-3 text-xs">
      <span class="text-foreground">Controls with a lock icon are managed by their source and cannot be manually changed.</span>
      <span class="ml-auto shrink-0">{{ controlsCounter }}/50 Controls in use.</span>
    </div>
  </div>
</template>
