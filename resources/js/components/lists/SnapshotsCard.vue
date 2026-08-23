<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { HistoryIcon, PinIcon, RotateCcwIcon, Trash2Icon } from '@lucide/vue';
import { useConfirm } from '@/composables/useConfirm';
import type { ToastType } from '@/types/lists';

interface SnapshotRow {
  id: number;
  reason: string;
  items: string[];
  item_count: number;
  pinned: boolean;
  created_at: number;
}

const props = defineProps<{
  listId: number;
  listSlug: string;
}>();

const emit = defineEmits<{
  toast: [message: string, type: ToastType];
}>();

const { confirm } = useConfirm();

const snapshots = ref<SnapshotRow[]>([]);
const loading = ref(false);
const expanded = ref(false);

onMounted(load);

async function load() {
  loading.value = true;
  try {
    const res = await axios.get(`/dashboard/lists/${props.listId}/snapshots`);
    snapshots.value = res.data.snapshots ?? [];
  } catch {
    snapshots.value = [];
  } finally {
    loading.value = false;
  }
}

// The parent refreshes this card after chat actions that snapshot first
// (clear, draw, pop).
defineExpose({ reload: load });

async function takeManualSnapshot() {
  try {
    await axios.post(`/dashboard/lists/${props.listId}/snapshots/manual`);
    await load();
    emit('toast', 'Snapshot taken.', 'success');
  } catch {
    emit('toast', 'Failed to take snapshot.', 'error');
  }
}

async function restoreSnapshot(snap: SnapshotRow) {
  if (
    !(await confirm({
      message: `Restore '${props.listSlug}' to this snapshot (${snap.item_count} items)? A safety snapshot of the current state is taken first.`,
      confirmLabel: 'Restore',
    }))
  )
    return;
  try {
    await axios.post(`/dashboard/lists/${props.listId}/snapshots/${snap.id}/restore`);
    await load();
    emit('toast', `Restored to snapshot (${snap.item_count} items).`, 'success');
  } catch {
    emit('toast', 'Restore failed.', 'error');
  }
}

async function togglePin(snap: SnapshotRow) {
  try {
    const res = await axios.patch(`/dashboard/lists/${props.listId}/snapshots/${snap.id}/pin`);
    snap.pinned = res.data.pinned;
    emit('toast', snap.pinned ? 'Snapshot pinned (survives retention sweep).' : 'Snapshot unpinned.', 'success');
  } catch {
    emit('toast', 'Toggle pin failed.', 'error');
  }
}

async function deleteSnapshot(snap: SnapshotRow) {
  if (!(await confirm({ message: 'Delete this snapshot? Cannot be undone.', confirmLabel: 'Delete' }))) return;
  try {
    await axios.delete(`/dashboard/lists/${props.listId}/snapshots/${snap.id}`);
    snapshots.value = snapshots.value.filter((s) => s.id !== snap.id);
    emit('toast', 'Snapshot deleted.', 'success');
  } catch {
    emit('toast', 'Delete failed.', 'error');
  }
}

const REASON_LABELS: Record<string, string> = {
  before_clear: 'before clear',
  before_draw: 'before draw',
  before_pop: 'before pop',
  before_restore: 'before restore',
  manual: 'manual',
};

function snapshotAge(ts: number): string {
  const delta = Math.max(0, Math.floor(Date.now() / 1000) - ts);
  if (delta < 60) return `${delta}s ago`;
  if (delta < 3600) return `${Math.floor(delta / 60)}m ago`;
  if (delta < 86400) return `${Math.floor(delta / 3600)}h ago`;
  return `${Math.floor(delta / 86400)}d ago`;
}
</script>

<template>
  <div class="border border-sidebar-border bg-black/2 p-5 dark:bg-[#0a0512]/50">
    <div class="flex items-center gap-3">
      <h3 class="flex min-w-0 flex-1 items-center gap-2 text-[15px] font-semibold text-foreground">
        <HistoryIcon class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
        Snapshots
        <span class="rounded-full border border-sidebar-border px-2 py-px font-mono text-[11px] font-normal text-muted-foreground">
          {{ snapshots.length }}
        </span>
      </h3>
      <button class="btn btn-chill btn-xs shrink-0 rounded-full" @click="takeManualSnapshot">Save snapshot</button>
    </div>
    <p class="mt-2 text-xs text-muted-foreground">
      {{
        snapshots.length === 0
          ? 'No snapshots yet. They are created automatically before clear, draw and pop.'
          : 'Restoring a snapshot replaces the current items.'
      }}
    </p>

    <div v-if="loading" class="mt-3 text-xs text-muted-foreground">Loading…</div>
    <template v-else-if="snapshots.length > 0">
      <button
        type="button"
        class="mt-2 cursor-pointer text-[11px] text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
        @click="expanded = !expanded"
      >
        {{ expanded ? 'Hide snapshots' : 'Show snapshots' }}
      </button>
      <div v-if="expanded" class="mt-2 space-y-2">
        <div v-for="snap in snapshots" :key="snap.id" class="flex flex-wrap items-center gap-2 border border-sidebar-border p-2">
          <span class="rounded-full border border-sidebar-border px-2 py-px text-[10px] text-muted-foreground">
            {{ REASON_LABELS[snap.reason] ?? snap.reason }}
          </span>
          <span class="text-xs text-foreground">{{ snap.item_count }} item{{ snap.item_count === 1 ? '' : 's' }}</span>
          <span class="text-[11px] text-muted-foreground">{{ snapshotAge(snap.created_at) }}</span>
          <div class="flex-1"></div>
          <button
            :title="snap.pinned ? 'Unpin' : 'Pin (survives retention)'"
            class="grid h-6.5 w-6.5 cursor-pointer place-items-center rounded border border-black/10 text-muted-foreground hover:border-black/35 hover:text-foreground dark:border-white/10 dark:hover:border-white/35"
            @click="togglePin(snap)"
          >
            <PinIcon class="h-3 w-3" :class="snap.pinned ? 'fill-current' : ''" />
          </button>
          <button
            title="Restore to this snapshot"
            class="grid h-6.5 w-6.5 cursor-pointer place-items-center rounded border border-black/10 text-muted-foreground hover:border-black/35 hover:text-foreground dark:border-white/10 dark:hover:border-white/35"
            @click="restoreSnapshot(snap)"
          >
            <RotateCcwIcon class="h-3 w-3" />
          </button>
          <button
            title="Delete this snapshot"
            class="grid h-6.5 w-6.5 cursor-pointer place-items-center rounded border border-black/10 text-muted-foreground hover:border-red-500/50 hover:text-red-600 dark:border-white/10 dark:hover:text-red-400"
            @click="deleteSnapshot(snap)"
          >
            <Trash2Icon class="h-3 w-3" />
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
