<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

interface SourceControl {
  key: string;
  label: string | null;
  type: string;
  config: Record<string, any> | null;
  sort_order: number;
}

const props = defineProps<{
  open: boolean;
  forkedTemplateId: number;
  forkedTemplateSlug: string;
  sourceControls: SourceControl[];
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'done'): void;
}>();

interface WizardRow {
  action: 'create' | 'skip';
  key: string;
  label: string | null;
  type: string;
  config: Record<string, any> | null;
  sort_order: number;
}

const rows = ref<WizardRow[]>([]);
const importing = ref(false);
const importError = ref('');

watch(() => props.open, (open) => {
  if (open) {
    importError.value = '';
    rows.value = props.sourceControls.map(c => ({
      action: 'create',
      key: c.key,
      label: c.label,
      type: c.type,
      config: c.config,
      sort_order: c.sort_order,
    }));
  }
});

async function confirm() {
  importing.value = true;
  importError.value = '';
  try {
    await axios.post(`/templates/${props.forkedTemplateId}/controls/import`, {
      controls: rows.value.map(r => ({
        action: r.action,
        key: r.key,
        label: r.label,
        type: r.type,
        config: r.config,
        sort_order: r.sort_order,
      })),
    });
    emit('update:open', false);
    emit('done');
    router.visit(`/templates/${props.forkedTemplateId}`);
  } catch (err: any) {
    importError.value = err.response?.data?.message ?? 'Import failed. Please try again.';
  } finally {
    importing.value = false;
  }
}

function skip() {
  emit('update:open', false);
  emit('done');
  router.visit(`/templates/${props.forkedTemplateId}`);
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-w-2xl">
      <DialogHeader>
        <DialogTitle>Import Controls from Source</DialogTitle>
        <DialogDescription>
          The original template had controls. Choose which ones to recreate in your fork.
          You can customize the key before importing.
        </DialogDescription>
      </DialogHeader>

      <p v-if="importError" class="text-sm text-destructive">{{ importError }}</p>

      <Table>
        <TableHeader>
          <TableRow class="hover:bg-transparent">
            <TableHead class="w-[90px]">Action</TableHead>
            <TableHead>Key</TableHead>
            <TableHead>Label</TableHead>
            <TableHead class="w-[90px]">Type</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="(row, idx) in rows" :key="idx">
            <TableCell>
              <div class="flex gap-3">
                <label class="flex items-center gap-1 cursor-pointer text-sm">
                  <input type="radio" :value="'create'" v-model="row.action" />
                  Create
                </label>
                <label class="flex items-center gap-1 cursor-pointer text-sm text-muted-foreground">
                  <input type="radio" :value="'skip'" v-model="row.action" />
                  Skip
                </label>
              </div>
            </TableCell>
            <TableCell>
              <Input
                v-model="row.key"
                :disabled="row.action === 'skip'"
                class="h-7 text-sm font-mono"
                placeholder="key"
              />
            </TableCell>
            <TableCell class="text-muted-foreground text-sm">{{ row.label || '—' }}</TableCell>
            <TableCell>
              <Badge variant="secondary" class="capitalize text-xs">{{ row.type }}</Badge>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <DialogFooter class="gap-2">
        <button class="btn btn-cancel" @click="skip">
          Skip all, take me to the fork
        </button>
        <button class="btn btn-primary" :disabled="importing" @click="confirm">
          {{ importing ? 'Importing...' : 'Import selected' }}
        </button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
