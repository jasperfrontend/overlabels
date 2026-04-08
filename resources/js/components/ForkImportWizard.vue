<script setup lang="ts">
import { ref, computed, watch } from 'vue';
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
import { InfoIcon } from 'lucide-vue-next';

const SERVICE_LABELS: Record<string, string> = {
  kofi: 'Ko-fi',
  streamlabs: 'StreamLabs',
  gpslogger: 'GPS Logger',
};

interface SourceControl {
  key: string;
  label: string | null;
  type: string;
  value: string | null;
  config: Record<string, any> | null;
  sort_order: number;
}

const props = withDefaults(defineProps<{
  open: boolean;
  forkedTemplateId: number;
  forkedTemplateSlug: string;
  sourceControls: SourceControl[];
  requiredServices?: string[];
  connectedServices?: string[];
}>(), {
  requiredServices: () => [],
  connectedServices: () => [],
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'done'): void;
}>();

interface WizardRow {
  action: 'create' | 'skip';
  key: string;
  label: string | null;
  type: string;
  value: string | null;
  config: Record<string, any> | null;
  sort_order: number;
}

const rows = ref<WizardRow[]>([]);
const importing = ref(false);
const importError = ref('');

const missingServices = computed(() =>
  props.requiredServices.filter(s => !props.connectedServices.includes(s))
);

const hasControls = computed(() => props.sourceControls.length > 0);

function serviceLabel(key: string): string {
  return SERVICE_LABELS[key] ?? key;
}

watch(() => props.open, (open) => {
  if (open) {
    importError.value = '';
    rows.value = props.sourceControls.map(c => ({
      action: 'create',
      key: c.key,
      label: c.label,
      type: c.type,
      value: c.value,
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
        value: r.value,
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
        <DialogDescription v-if="hasControls">
          The original template had controls. Choose which ones to recreate in your copy.
          You can customize the key before importing.
        </DialogDescription>
        <DialogDescription v-else>
          The original template uses external integrations.
        </DialogDescription>
      </DialogHeader>

      <!-- Missing services warning -->
      <div v-if="missingServices.length > 0" class="flex gap-3 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-foreground">
        <InfoIcon class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
        <div>
          <p class="font-medium">This template uses integrations you haven't connected yet:</p>
          <ul class="mt-1 list-inside list-disc">
            <li v-for="service in missingServices" :key="service">
              <strong>{{ serviceLabel(service) }}</strong>
            </li>
          </ul>
          <p class="mt-2 text-muted-foreground">
            Controls from these services won't update until you connect them in
            <a href="/settings/integrations" class="underline hover:text-foreground">Settings &rarr; Integrations</a>.
          </p>
        </div>
      </div>

      <!-- All services connected -->
      <div v-else-if="requiredServices.length > 0" class="flex gap-3 rounded-md border border-green-500/30 bg-green-500/10 p-3 text-sm text-foreground">
        <InfoIcon class="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
        <div>
          <p>
            This template uses
            <strong v-for="(service, i) in requiredServices" :key="service">
              {{ serviceLabel(service) }}<span v-if="i < requiredServices.length - 1">, </span>
            </strong>
            - you already have {{ requiredServices.length === 1 ? 'it' : 'them' }} connected. You're all set!
          </p>
        </div>
      </div>

      <p v-if="importError" class="text-sm text-destructive">{{ importError }}</p>

      <template v-if="hasControls">
        <div class="h-100 overflow-scroll">
          <Table>
            <TableHeader>
              <TableRow class="hover:bg-transparent">
                <TableHead class="w-22.5">Action</TableHead>
                <TableHead>Key</TableHead>
                <TableHead>Label</TableHead>
                <TableHead class="w-22.5">Type</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody class="divide-y">
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
        </div>
      </template>

      <DialogFooter class="gap-2">
        <button v-if="!hasControls" class="btn btn-primary" @click="skip">
          Got it, take me to the overlay
        </button>
        <template v-else>
          <button class="btn btn-cancel" @click="skip">
            Skip all, take me to the overlay
          </button>
          <button class="btn btn-primary" :disabled="importing" @click="confirm">
            {{ importing ? 'Importing...' : 'Import selected' }}
          </button>
        </template>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
