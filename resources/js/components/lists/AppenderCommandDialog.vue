<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { AppenderRow, ToastType } from '@/types/lists';

const props = defineProps<{
  open: boolean;
  listId: number;
  listSlug: string;
  /** The appender being edited, or null to create a new one. */
  appender: AppenderRow | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  saved: [appender: AppenderRow, created: boolean];
  toast: [message: string, type: ToastType];
}>();

const form = ref({
  command: '',
  permission_level: 'everyone',
  cooldown_seconds: 0,
  value_template: '[[[bot:from_user]]]',
  args_empty_reply: '' as string,
  success_reply: '' as string,
  dedup_policy: 'per_chatter' as 'none' | 'per_chatter' | 'per_chatter_per_stream',
  max_size: null as number | null,
  enabled: true,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);

// Re-seed the form every time the dialog opens so a cancelled edit never
// leaks into the next open.
watch(
  () => props.open,
  (open) => {
    if (!open) return;
    const a = props.appender;
    form.value = a
      ? {
          command: a.command,
          permission_level: a.permission_level,
          cooldown_seconds: a.cooldown_seconds,
          value_template: a.value_template,
          args_empty_reply: a.args_empty_reply ?? '',
          success_reply: a.success_reply ?? '',
          dedup_policy: a.dedup_policy,
          max_size: a.max_size,
          enabled: a.enabled,
        }
      : {
          command: '',
          permission_level: 'everyone',
          cooldown_seconds: 0,
          value_template: '[[[bot:from_user]]]',
          args_empty_reply: '',
          success_reply: '',
          dedup_policy: 'per_chatter',
          max_size: null,
          enabled: true,
        };
    errors.value = {};
  },
);

async function save() {
  saving.value = true;
  errors.value = {};

  const body = {
    command: form.value.command,
    permission_level: form.value.permission_level,
    cooldown_seconds: form.value.cooldown_seconds,
    value_template: form.value.value_template,
    args_empty_reply: form.value.args_empty_reply || null,
    success_reply: form.value.success_reply || null,
    dedup_policy: form.value.dedup_policy,
    max_size: form.value.max_size || null,
    enabled: form.value.enabled,
  };

  try {
    if (props.appender) {
      const res = await axios.put(`/dashboard/lists/${props.listId}/appenders/${props.appender.id}`, body);
      emit('saved', res.data.appender, false);
    } else {
      const res = await axios.post(`/dashboard/lists/${props.listId}/appenders`, body);
      emit('saved', res.data.appender, true);
    }
    emit('update:open', false);
  } catch (err: any) {
    if (err?.response?.status === 422 && err.response?.data?.errors) {
      const next: Record<string, string> = {};
      for (const [field, msgs] of Object.entries(err.response.data.errors as Record<string, string[]>)) {
        next[field] = msgs[0];
      }
      errors.value = next;
    } else {
      emit('toast', 'Failed to save command.', 'error');
    }
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-h-[85dvh] gap-0 overflow-y-auto p-0 sm:max-w-[960px]">
      <!-- Header: title + live command chip, enabled switch on the right -->
      <DialogHeader class="flex-row flex-wrap items-center justify-between gap-3 border-b border-sidebar-border px-5 py-4 text-left md:px-7 md:py-5">
        <DialogTitle class="flex flex-wrap items-baseline gap-2.5 tracking-tight">
          {{ appender ? 'Edit command' : 'New append command' }}
          <code
            v-if="form.command"
            class="rounded-xs bg-violet-500/15 px-2 py-0.5 font-mono text-[15px] font-normal text-violet-700 dark:text-violet-400"
          >
            !{{ form.command }}
          </code>
        </DialogTitle>
        <div class="flex items-center gap-2 text-[13px] text-foreground/80">
          <span class="font-medium">Enabled</span>
          <button
            role="switch"
            :aria-checked="form.enabled"
            aria-label="Enabled"
            class="relative h-[17px] w-[30px] shrink-0 cursor-pointer rounded-full border"
            :class="form.enabled ? 'border-green-500/60 bg-green-500/25' : 'border-black/15 bg-black/5 dark:border-white/15 dark:bg-white/6'"
            @click="form.enabled = !form.enabled"
          >
            <span
              class="absolute top-[2px] h-[11px] w-[11px] rounded-full transition-all"
              :class="form.enabled ? 'left-[16px] bg-green-600 dark:bg-green-400' : 'left-[2px] bg-black/40 dark:bg-white/40'"
            ></span>
          </button>
        </div>
      </DialogHeader>

      <div class="grid md:grid-cols-[320px_minmax(0,1fr)]">
        <!-- Behavior column -->
        <div class="flex flex-col gap-5 border-b border-sidebar-border p-5 md:border-r md:border-b-0 md:px-7 md:py-6">
          <div class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">Behavior</div>

          <div class="flex flex-col gap-1.5">
            <Label for="ap-command"
              >Command <span class="font-normal text-muted-foreground">(without <code>!</code>)</span></Label
            >
            <input id="ap-command" v-model="form.command" placeholder="raffle" class="input-border w-full font-mono" />
            <p v-if="errors.command" class="text-xs text-destructive">{{ errors.command }}</p>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label for="ap-perm">Permission</Label>
            <select id="ap-perm" v-model="form.permission_level" class="input-border w-full cursor-pointer">
              <option value="everyone">Everyone</option>
              <option value="subscriber">Subscribers</option>
              <option value="vip">VIPs</option>
              <option value="moderator">Moderators</option>
              <option value="broadcaster">Broadcaster</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1.5">
              <Label for="ap-cooldown">Cooldown (s)</Label>
              <input id="ap-cooldown" v-model.number="form.cooldown_seconds" type="number" min="0" class="input-border w-full font-mono" />
            </div>
            <div class="flex flex-col gap-1.5">
              <Label for="ap-max">Max size</Label>
              <input id="ap-max" v-model.number="form.max_size" type="number" min="1" placeholder="Unlimited" class="input-border w-full font-mono" />
            </div>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label for="ap-dedup">Dedup policy</Label>
            <select id="ap-dedup" v-model="form.dedup_policy" class="input-border w-full cursor-pointer">
              <option value="none">None (allow duplicates)</option>
              <option value="per_chatter">Once per chatter</option>
              <option value="per_chatter_per_stream">Once per chatter per stream</option>
            </select>
            <p class="text-xs text-muted-foreground">Blank max size = unlimited. Dedup runs on each append.</p>
          </div>
        </div>

        <!-- Templates & replies column -->
        <div class="flex flex-col gap-5 p-5 md:px-7 md:py-6">
          <div class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">Templates &amp; replies</div>

          <div class="flex flex-col gap-1.5">
            <Label for="ap-template">Value template</Label>
            <textarea
              id="ap-template"
              v-model="form.value_template"
              rows="2"
              class="input-border w-full resize-y font-mono text-sm"
              placeholder="[[[bot:from_user]]]"
            ></textarea>
            <p class="text-xs text-muted-foreground">
              Bot Command syntax. Pipe formatters work: <span class="font-mono">[[[bot:fired_at|date:HH:mm]]]</span>.
            </p>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label for="ap-success">Success reply <span class="font-normal text-muted-foreground">(optional)</span></Label>
            <textarea
              id="ap-success"
              v-model="form.success_reply"
              rows="2"
              class="input-border w-full resize-y font-mono text-sm"
              placeholder="@[[[bot:from_user]]], your entry has been added to the list."
            ></textarea>
            <p class="text-xs text-muted-foreground">
              Spoken in chat after a successful append. Same template syntax as the value template, plus this list's read tags like
              <span class="font-mono">[[[c:list:{{ listSlug }}:count]]]</span> (resolved after the append, so the count includes it). Leave blank for
              silent.
            </p>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label for="ap-empty">Empty-args reply <span class="font-normal text-muted-foreground">(optional)</span></Label>
            <textarea
              id="ap-empty"
              v-model="form.args_empty_reply"
              rows="2"
              class="input-border w-full resize-y font-mono text-sm"
              placeholder="@[[[bot:from_user]]] add something after !raffle"
            ></textarea>
            <p class="text-xs text-muted-foreground">
              Spoken in chat when the template uses <span class="font-mono">[[[bot:args]]]</span> but the chatter didn't supply any. Leave blank for
              silent.
            </p>
          </div>
        </div>
      </div>

      <DialogFooter class="gap-2.5 border-t border-sidebar-border px-5 py-3.5 md:px-7 md:py-4">
        <button class="btn btn-chill btn-sm cursor-pointer rounded-full" @click="emit('update:open', false)">Cancel</button>
        <button class="btn btn-primary btn-sm cursor-pointer rounded-full" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
