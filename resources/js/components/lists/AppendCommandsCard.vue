<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { CornerDownRightIcon, PencilIcon, PlusIcon, Trash2Icon } from '@lucide/vue';
import { useConfirm } from '@/composables/useConfirm';
import AppenderCommandDialog from '@/components/lists/AppenderCommandDialog.vue';
import type { AppenderRow, ToastType } from '@/types/lists';

const props = defineProps<{
  listId: number;
  listSlug: string;
}>();

const emit = defineEmits<{
  toast: [message: string, type: ToastType];
}>();

const { confirm } = useConfirm();

const appenders = ref<AppenderRow[]>([]);
const loading = ref(false);
const modalOpen = ref(false);
const editing = ref<AppenderRow | null>(null);

onMounted(load);

async function load() {
  loading.value = true;
  try {
    const res = await axios.get(`/dashboard/lists/${props.listId}/appenders`);
    appenders.value = res.data.appenders ?? [];
  } catch {
    appenders.value = [];
  } finally {
    loading.value = false;
  }
}

function openAdd() {
  editing.value = null;
  modalOpen.value = true;
}

function openEdit(a: AppenderRow) {
  editing.value = a;
  modalOpen.value = true;
}

function onSaved(appender: AppenderRow, created: boolean) {
  if (created) {
    appenders.value.push(appender);
    emit('toast', `!${appender.command} created.`, 'success');
  } else {
    const idx = appenders.value.findIndex((a) => a.id === appender.id);
    if (idx >= 0) appenders.value[idx] = appender;
    emit('toast', `!${appender.command} saved.`, 'success');
  }
}

async function remove(a: AppenderRow) {
  if (!(await confirm({ message: `Delete command !${a.command}?`, confirmLabel: 'Delete' }))) return;
  try {
    await axios.delete(`/dashboard/lists/${props.listId}/appenders/${a.id}`);
    appenders.value = appenders.value.filter((x) => x.id !== a.id);
    emit('toast', `!${a.command} deleted.`, 'success');
  } catch {
    emit('toast', 'Failed to delete command.', 'error');
  }
}

const DEDUP_LABELS: Record<string, string> = {
  none: 'no dedup',
  per_chatter: 'once per chatter',
  per_chatter_per_stream: 'once per chatter per stream',
};
</script>

<template>
  <div class="border border-sidebar-border bg-black/2 p-5 dark:bg-[#0a0512]/50">
    <div class="flex flex-wrap items-start gap-3">
      <div class="min-w-0 flex-1">
        <h3 class="text-[15px] font-semibold text-foreground">Append commands</h3>
        <p class="mt-0.5 max-w-105 text-xs text-muted-foreground">
          Chat commands that append to this list when fired. Bot Command syntax like <code>[[[bot:from_user]]]</code> works in the value template.
        </p>
      </div>
      <button class="btn btn-primary btn-sm shrink-0 rounded-full" @click="openAdd">
        <PlusIcon class="h-3 w-3" />
        <span class="ml-1.5">Add command</span>
      </button>
    </div>

    <div v-if="loading" class="mt-4 text-sm text-muted-foreground">Loading…</div>
    <div v-else-if="appenders.length === 0" class="mt-4 border border-dashed border-sidebar-border py-6 text-center text-sm text-muted-foreground">
      No append commands yet. Add one to let chatters grow this list.
    </div>
    <div v-else class="mt-4 space-y-3">
      <div v-for="a in appenders" :key="a.id" class="border border-sidebar-border p-4 hover:border-foreground/25">
        <div class="flex flex-wrap items-center gap-2">
          <code class="bg-green-500/10 px-2 py-0.5 text-[13px] font-bold text-green-700 dark:text-green-400">!{{ a.command }}</code>
          <span class="rounded-full border border-violet-500/45 px-2.5 py-0.5 text-[11px] font-medium text-violet-700 dark:text-violet-300">
            {{ a.permission_level }}
          </span>
          <span class="rounded-full border border-sidebar-border px-2.5 py-0.5 text-[11px] text-muted-foreground">
            {{ DEDUP_LABELS[a.dedup_policy] }}
          </span>
          <span v-if="a.max_size" class="rounded-full border border-sidebar-border px-2.5 py-0.5 text-[11px] text-muted-foreground">
            max {{ a.max_size }}
          </span>
          <span
            v-if="a.cooldown_seconds > 0"
            class="rounded-full border border-sidebar-border px-2.5 py-0.5 font-mono text-[11px] text-muted-foreground"
          >
            {{ a.cooldown_seconds }}s cd
          </span>
          <span v-if="!a.enabled" class="rounded-full border border-red-500/50 px-2.5 py-0.5 text-[11px] text-red-700 dark:text-red-400">
            disabled
          </span>
          <div class="flex-1"></div>
          <button
            title="Edit command"
            class="grid h-6.5 w-6.5 cursor-pointer place-items-center rounded border border-black/10 text-muted-foreground hover:border-black/35 hover:text-foreground dark:border-white/10 dark:hover:border-white/35"
            @click="openEdit(a)"
          >
            <PencilIcon class="h-3 w-3" />
          </button>
          <button
            title="Delete command"
            class="grid h-6.5 w-6.5 cursor-pointer place-items-center rounded border border-black/10 text-muted-foreground hover:border-red-500/50 hover:text-red-600 dark:border-white/10 dark:hover:text-red-400"
            @click="remove(a)"
          >
            <Trash2Icon class="h-3 w-3" />
          </button>
        </div>
        <p class="mt-2.5 truncate font-mono text-[11.5px] text-foreground/55" :title="a.value_template">appends&nbsp;&nbsp;{{ a.value_template }}</p>
        <div v-if="a.success_reply || a.args_empty_reply" class="mt-2 flex flex-col gap-1">
          <div v-if="a.success_reply" class="flex items-center gap-2 text-[11.5px] text-foreground/45">
            <CornerDownRightIcon class="h-3 w-3 shrink-0" />
            <span class="shrink-0 text-foreground/30">success</span>
            <span class="truncate font-mono" :title="a.success_reply">{{ a.success_reply }}</span>
          </div>
          <div v-if="a.args_empty_reply" class="flex items-center gap-2 text-[11.5px] text-foreground/45">
            <CornerDownRightIcon class="h-3 w-3 shrink-0" />
            <span class="shrink-0 text-foreground/30">no args</span>
            <span class="truncate font-mono" :title="a.args_empty_reply">{{ a.args_empty_reply }}</span>
          </div>
        </div>
      </div>
    </div>

    <AppenderCommandDialog
      v-model:open="modalOpen"
      :list-id="listId"
      :list-slug="listSlug"
      :appender="editing"
      @saved="onSaved"
      @toast="(message, type) => emit('toast', message, type)"
    />
  </div>
</template>
