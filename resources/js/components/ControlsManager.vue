<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { PlusIcon, PencilIcon, Trash2Icon, CopyIcon } from 'lucide-vue-next';
import ControlFormModal from '@/components/ControlFormModal.vue';
import RekaToast from '@/components/RekaToast.vue';
import type { OverlayControl } from '@/types';

interface Template {
  id: number;
  slug: string;
}

const props = defineProps<{
  template: Template;
  initialControls: OverlayControl[];
}>();

const emit = defineEmits<{
  (e: 'change', controls: OverlayControl[]): void;
}>();

const controls = ref<OverlayControl[]>([...props.initialControls]);
const modalOpen = ref(false);
const editingControl = ref<OverlayControl | null>(null);
const toastMessage = ref('');
const toastType = ref<'success' | 'error'>('success');
const showToast = ref(false);

function showMsg(msg: string, type: 'success' | 'error' = 'success') {
  toastMessage.value = msg;
  toastType.value = type;
  showToast.value = false;
  setTimeout(() => { showToast.value = true; }, 10);
}

function openAdd() {
  editingControl.value = null;
  modalOpen.value = true;
}

function openEdit(control: OverlayControl) {
  editingControl.value = control;
  modalOpen.value = true;
}

function onSaved(saved: OverlayControl) {
  const idx = controls.value.findIndex(c => c.id === saved.id);
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
  if (!confirm(`Delete control "${control.label || control.key}"? This cannot be undone.`)) return;

  try {
    await axios.delete(`/templates/${props.template.id}/controls/${control.id}`);
    controls.value = controls.value.filter(c => c.id !== control.id);
    emit('change', [...controls.value]);
    showMsg('Control deleted.');
  } catch {
    showMsg('Failed to delete control.', 'error');
  }
}

async function copySnippet(key: string) {
  try {
    await navigator.clipboard.writeText(`[[[c:${key}]]]`);
    showMsg(`[[[c:${key}]]] copied to clipboard!`);
  } catch {
    showMsg('Copy failed.', 'error');
  }
}

const typeBadgeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
  text: 'outline',
  number: 'secondary',
  counter: 'default',
  timer: 'secondary',
  datetime: 'outline',
};
</script>

<template>
  <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />

  <ControlFormModal
    v-model:open="modalOpen"
    :template="template"
    :control="editingControl"
    @saved="onSaved"
  />

  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-muted-foreground">
          Define mutable values your template can reference with
          <code class="rounded bg-sidebar px-1 py-0.5 text-xs">[[[c:key]]]</code>.
        </p>
      </div>
      <button
        class="btn btn-primary btn-sm"
        :disabled="controls.length >= 20"
        :title="controls.length >= 20 ? 'Maximum 20 controls per template' : undefined"
        @click="openAdd"
      >
        <PlusIcon class="mr-1.5 h-3.5 w-3.5" />
        Add control
      </button>
    </div>

    <div v-if="controls.length === 0" class="rounded-sm border border-sidebar bg-sidebar-accent p-8 text-center text-muted-foreground">
      No controls yet. Add one to get started.
    </div>

    <Table v-else>
      <TableHeader>
        <TableRow class="hover:bg-transparent">
          <TableHead>Key</TableHead>
          <TableHead>Label</TableHead>
          <TableHead class="w-[90px]">Type</TableHead>
          <TableHead>Snippet</TableHead>
          <TableHead class="w-[100px] text-right">Actions</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow v-for="ctrl in controls" :key="ctrl.id" class="group">
          <TableCell class="font-mono text-sm font-medium">{{ ctrl.key }}</TableCell>
          <TableCell class="text-muted-foreground">{{ ctrl.label || '—' }}</TableCell>
          <TableCell>
            <Badge :variant="typeBadgeVariant[ctrl.type] ?? 'outline'" class="capitalize">
              {{ ctrl.type }}
            </Badge>
          </TableCell>
          <TableCell>
            <button
              class="flex items-center gap-1.5 rounded-sm border border-dashed border-sidebar px-2 py-0.5 font-mono text-xs text-muted-foreground opacity-60 transition hover:opacity-100 group-hover:opacity-80"
              :title="`Copy [[[c:${ctrl.key}]]] to clipboard`"
              @click="copySnippet(ctrl.key)"
            >
              <CopyIcon class="h-3 w-3 shrink-0" />
              [[[c:{{ ctrl.key }}]]]
            </button>
          </TableCell>
          <TableCell class="text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="btn btn-sm btn-secondary px-2" title="Edit" @click="openEdit(ctrl)">
                <PencilIcon class="h-3.5 w-3.5" />
              </button>
              <button class="btn btn-sm btn-danger px-2" title="Delete" @click="deleteControl(ctrl)">
                <Trash2Icon class="h-3.5 w-3.5" />
              </button>
            </div>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>
</template>
