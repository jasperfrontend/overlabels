<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import RekaToast from '@/components/RekaToast.vue';
import AppendCommandsCard from '@/components/lists/AppendCommandsCard.vue';
import SnapshotsCard from '@/components/lists/SnapshotsCard.vue';
import { ArrowDownFromLineIcon, ArrowLeftIcon, ArrowUpFromLineIcon, CheckIcon, LockIcon, PlayIcon } from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';
import type { ToastType } from '@/types/lists';
import { listItemValues, type ListItem } from '@/utils/listItems';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();
interface ListRow {
  id: number;
  slug: string;
  label: string | null;
  items: string[];
  min_items: number;
  max_items: number | null;
  user_editable: boolean;
  disabled_at: number | null;
  entry_ttl_seconds: number | null;
  expires_at: number | null;
  recipe_instance_id: number | null;
  recipe: { slug: string | null; name: string | null; version: number | null; instance_slug: string | null } | null;
  tag: string;
  updated_at: number | null;
  chat_permissions: Record<string, string>;
}

const props = defineProps<{
  list: ListRow;
}>();

// Local working copy of the list so optimistic toggles and live (Echo) updates
// can mutate in place. Re-synced whenever the server sends fresh props (every
// focused PATCH returns back(), re-rendering this page).
const list = ref<ListRow>({ ...props.list });
watch(
  () => props.list,
  (next) => {
    list.value = { ...next };
  },
  { deep: true },
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Lists', href: '/dashboard/lists' },
  { title: list.value.label || list.value.slug, href: `/dashboard/lists/${list.value.slug}` },
]);

// Draft state for the items editor. The textarea holds raw text; split-on-
// newline happens at save time so the user can have empty lines and duplicates
// intentionally.
const draftLabel = ref('');
const draftItemsText = ref('');
const isDirty = ref(false);
const saving = ref(false);

watch(
  list,
  (next) => {
    draftLabel.value = next?.label ?? '';
    draftItemsText.value = (next?.items ?? []).join('\n');
    isDirty.value = false;
  },
  { immediate: true },
);

watch([draftLabel, draftItemsText], () => {
  const baseline = (list.value.items ?? []).join('\n');
  isDirty.value = draftLabel.value !== (list.value.label ?? '') || draftItemsText.value !== baseline;
});

const toastMessage = ref<string | null>(null);
const toastType = ref<ToastType>('info');

function showToast(message: string, type: ToastType) {
  toastMessage.value = message;
  toastType.value = type;
}

// The header "Save changes" chip covers both the items editor and the expiry
// panel. The server treats expiry fields as a focused PATCH (items are ignored
// when they're present), so a save with both dirty is two chained PUTs.
const anythingDirty = computed(() => isDirty.value || expiryIsDirty.value);

function saveAll() {
  if (saving.value) return;
  const wantItems = isDirty.value && !isActiveLocked.value;
  const wantExpiry = expiryIsDirty.value;
  if (!wantItems && !wantExpiry) return;

  // Capture the expiry payload up front: the items PUT re-syncs props, which
  // resets the expiry draft fields before the chained request fires.
  const expiryPayload = {
    entry_ttl_seconds: ttlSecondsComposed.value,
    expires_at: expiresAtUnix.value,
  };

  saving.value = true;

  const onSaved = () => {
    toastMessage.value = `'${list.value.slug}' saved.`;
    toastType.value = 'success';
  };
  const onFailed = (errors: Record<string, string>) => {
    toastMessage.value = (Object.values(errors)[0] as string) ?? 'Save failed.';
    toastType.value = 'error';
  };

  const saveExpiry = () =>
    router.put(route('lists.update', list.value.id), expiryPayload, {
      preserveScroll: true,
      onSuccess: onSaved,
      onError: onFailed,
      onFinish: () => {
        saving.value = false;
      },
    });

  if (!wantItems) {
    saveExpiry();
    return;
  }

  let chained = false;
  router.put(
    route('lists.update', list.value.id),
    {
      label: draftLabel.value || null,
      items: draftItemsText.value === '' ? [] : draftItemsText.value.split('\n'),
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        isDirty.value = false;
        if (wantExpiry) {
          chained = true;
          saveExpiry();
        } else {
          onSaved();
        }
      },
      onError: onFailed,
      onFinish: () => {
        if (!chained) saving.value = false;
      },
    },
  );
}

async function deleteActive() {
  if (list.value.recipe_instance_id !== null) {
    toastMessage.value = 'Recipe-managed lists must be removed via the recipe.';
    toastType.value = 'warning';
    return;
  }
  if (!(await confirm({ message: `Delete list '${list.value.slug}'? This cannot be undone.`, confirmLabel: 'Delete' }))) return;

  router.delete(route('lists.destroy', list.value.id), {
    onError: (errors) => {
      toastMessage.value = (Object.values(errors)[0] as string) ?? 'Delete failed.';
      toastType.value = 'error';
    },
  });
}

function toggleDisabled() {
  const nextDisabled = list.value.disabled_at === null;

  router.put(
    route('lists.update', list.value.id),
    {
      disabled: nextDisabled,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        toastMessage.value = nextDisabled ? `'${list.value.slug}' disabled. Chat appenders will silently no-op.` : `'${list.value.slug}' re-enabled.`;
        toastType.value = 'success';
      },
      onError: () => {
        toastMessage.value = 'Failed to toggle list state.';
        toastType.value = 'error';
      },
    },
  );
}

const tagCopied = ref(false);
let tagCopiedTimer: number | undefined;

async function copyTag(tag: string) {
  try {
    await navigator.clipboard.writeText(tag);
    tagCopied.value = true;
    clearTimeout(tagCopiedTimer);
    tagCopiedTimer = window.setTimeout(() => {
      tagCopied.value = false;
    }, 1400);
  } catch {
    toastMessage.value = 'Clipboard write failed.';
    toastType.value = 'error';
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Expiry config (entry-TTL + whole-list expires_at)
// ──────────────────────────────────────────────────────────────────────────────

const ttlValue = ref<number | null>(null);
const ttlUnit = ref<'seconds' | 'minutes' | 'hours'>('minutes');
const expiresAtLocal = ref<string>('');

watch(
  list,
  (next) => {
    if (!next || next.entry_ttl_seconds === null) {
      ttlValue.value = null;
      ttlUnit.value = 'minutes';
    } else {
      // Pick the largest unit that divides evenly so editing feels natural:
      // 3600 -> 1 hour, 90 -> 90 seconds (not 1.5 minutes).
      const s = next.entry_ttl_seconds;
      if (s % 3600 === 0) {
        ttlValue.value = s / 3600;
        ttlUnit.value = 'hours';
      } else if (s % 60 === 0) {
        ttlValue.value = s / 60;
        ttlUnit.value = 'minutes';
      } else {
        ttlValue.value = s;
        ttlUnit.value = 'seconds';
      }
    }
    expiresAtLocal.value = next?.expires_at ? unixToLocalInput(next.expires_at) : '';
  },
  { immediate: true },
);

function unixToLocalInput(unix: number): string {
  const d = new Date(unix * 1000);
  // datetime-local wants YYYY-MM-DDTHH:mm in local time.
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function localInputToUnix(local: string): number | null {
  if (!local) return null;
  const ts = new Date(local).getTime();
  return Number.isFinite(ts) ? Math.floor(ts / 1000) : null;
}

const ttlSecondsComposed = computed<number | null>(() => {
  if (ttlValue.value === null || ttlValue.value <= 0) return null;
  const mult = ttlUnit.value === 'hours' ? 3600 : ttlUnit.value === 'minutes' ? 60 : 1;
  return Math.floor(ttlValue.value * mult);
});

const expiresAtUnix = computed<number | null>(() => localInputToUnix(expiresAtLocal.value));

const expiryIsDirty = computed(() => {
  const currentTtl = list.value.entry_ttl_seconds;
  const currentExpires = list.value.expires_at;
  return ttlSecondsComposed.value !== currentTtl || expiresAtUnix.value !== currentExpires;
});

// Live preview of how long until expires_at fires - ticks every second so
// streamers can sanity-check the date picker without doing math in their head.
const nowTick = ref(Math.floor(Date.now() / 1000));
let nowTickInterval: number | undefined;
onMounted(() => {
  nowTickInterval = window.setInterval(() => {
    nowTick.value = Math.floor(Date.now() / 1000);
  }, 1000);
});
onUnmounted(() => {
  if (nowTickInterval) clearInterval(nowTickInterval);
  clearTimeout(tagCopiedTimer);
});

const expiryCountdown = computed<string>(() => {
  const ts = expiresAtUnix.value;
  if (ts === null) return '';
  const delta = ts - nowTick.value;
  if (delta <= 0) return 'expired';
  return formatDuration(delta);
});

function formatDuration(seconds: number): string {
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const mins = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;
  if (days > 0) return `${days}d ${hours}h ${mins}m`;
  if (hours > 0) return `${hours}h ${mins}m ${secs}s`;
  if (mins > 0) return `${mins}m ${secs}s`;
  return `${secs}s`;
}

function clearExpiresAt() {
  expiresAtLocal.value = '';
}

const activeItemCount = computed(() => (draftItemsText.value === '' ? 0 : draftItemsText.value.split('\n').length));
const isActiveLocked = computed(() => !list.value.user_editable && list.value.recipe_instance_id !== null);

// ──────────────────────────────────────────────────────────────────────────────
// Live updates - subscribe to the user's broadcast channel so chat-appender
// activity (or another browser tab) updates this page in place.
// ──────────────────────────────────────────────────────────────────────────────

const page = usePage();

interface ListUpdatedPayload {
  slug: string;
  items: (ListItem | string)[] | null;
  updated_at: number | null;
  expires_at?: number | null;
  disabled_at?: number | null;
}

function applyListUpdated(payload: ListUpdatedPayload) {
  if (payload.slug !== list.value.slug) return;
  // The broadcast carries item objects; the detail view works in value
  // strings (matching the Inertia payload and the value textarea editor).
  const values = listItemValues(payload.items ?? []);
  list.value = {
    ...list.value,
    items: values,
    updated_at: payload.updated_at,
    expires_at: payload.expires_at !== undefined ? payload.expires_at : list.value.expires_at,
    disabled_at: payload.disabled_at !== undefined ? payload.disabled_at : list.value.disabled_at,
  };

  // If the user has unsaved edits, leave the textarea alone - their pending
  // edits win until they save or navigate away. Otherwise refresh it so a
  // chatter's append shows up in their view too.
  if (!isDirty.value) {
    draftItemsText.value = values.join('\n');
  }
}

let echoChannel: any = null;
let echoChannelName: string | null = null;

onMounted(() => {
  loadMeta();

  const twitchId = (page.props.auth as any)?.user?.twitch_id;
  if (!twitchId || !(window as any).Echo) return;

  echoChannelName = `alerts.${twitchId}`;
  echoChannel = (window as any).Echo.private(echoChannelName);

  echoChannel.listen('.list.updated', (payload: ListUpdatedPayload) => {
    applyListUpdated(payload);
  });
  echoChannel.listen('.list.deleted', (payload: ListUpdatedPayload) => {
    // This list was deleted elsewhere - bounce back to the collection.
    if (payload.slug === list.value.slug) {
      router.visit(route('lists.index'));
    }
  });
});

onUnmounted(() => {
  if (echoChannel) {
    echoChannel.stopListening('.list.updated');
    echoChannel.stopListening('.list.deleted');
  }
  if (echoChannelName) {
    (window as any).Echo?.leave(`private-${echoChannelName}`);
  }
});

// ──────────────────────────────────────────────────────────────────────────────
// List actions (dashboard buttons that mirror the !list meta-command)
// ──────────────────────────────────────────────────────────────────────────────

const runningAction = ref<string | null>(null);
const snapshotsCard = ref<InstanceType<typeof SnapshotsCard> | null>(null);

async function runAction(action: string, args: string = '', requiresConfirm = false, confirmText = '') {
  if (requiresConfirm && !(await confirm({ message: confirmText || `Run '${action}' on '${list.value.slug}'?`, confirmLabel: 'Run' }))) return;

  runningAction.value = action;
  try {
    const res = await axios.post(`/dashboard/lists/${list.value.id}/actions`, { action, args });
    toastMessage.value = res.data.reply || `'${action}' done.`;
    toastType.value = 'success';
    if (['clear', 'draw', 'pop'].includes(action)) {
      snapshotsCard.value?.reload();
    }
  } catch (err: any) {
    toastMessage.value = err?.response?.data?.message || `'${action}' failed.`;
    toastType.value = 'error';
  } finally {
    runningAction.value = null;
  }
}

function runCount() {
  runAction('count');
}
function runFirst() {
  const n = prompt(`How many from the start? (default 1)`, '1');
  if (n === null) return;
  runAction('first', n.trim());
}
function runLast() {
  const n = prompt(`How many from the end? (default 1)`, '1');
  if (n === null) return;
  runAction('last', n.trim());
}
function runRandom() {
  const n = prompt(`How many random items? (default 1)`, '1');
  if (n === null) return;
  runAction('random', n.trim());
}
function runSearch() {
  const q = prompt('Search for what? Replies with the first match.', '');
  if (q === null || q.trim() === '') return;
  runAction('search', q.trim());
}
function runSearchAll() {
  const q = prompt('Search for what? Replies with all matches.', '');
  if (q === null || q.trim() === '') return;
  runAction('searchall', q.trim());
}
function runDraw() {
  runAction('draw', '', true, `Draw a winner from '${list.value.slug}'? The winner is removed from the list.`);
}
function runClear() {
  runAction('clear', '', true, `Clear ALL items from '${list.value.slug}'? A snapshot is taken first; you can restore.`);
}
function runPop(which: 'first' | 'last') {
  runAction('pop', which, true, `Remove the ${which} item from '${list.value.slug}'?`);
}
function runClone() {
  const slug = prompt(`New slug for the clone of '${list.value.slug}':`, '');
  if (!slug || !slug.trim()) return;
  runAction('clone', slug.trim());
}
async function runState(which: 'disable' | 'enable') {
  await runAction(which);
  // The action flips disabled_at server-side; pull fresh props so the
  // status pill and danger zone reflect it even without an Echo update.
  router.reload({ only: ['list'] });
}

// ──────────────────────────────────────────────────────────────────────────────
// Chat actions panel: one row per action, a run button plus a viewer-access
// toggle (on = everyone can run it in chat, off = moderator+ only).
// ──────────────────────────────────────────────────────────────────────────────

const ACTION_GROUPS: { title: string; items: { key: string; label: string; run?: () => void; pop?: boolean }[] }[] = [
  {
    title: 'Inspect',
    items: [
      { key: 'count', label: 'count', run: runCount },
      { key: 'first', label: 'first / #N', run: runFirst },
      { key: 'last', label: 'last', run: runLast },
      { key: 'random', label: 'random', run: runRandom },
      { key: 'search', label: 'search', run: runSearch },
      { key: 'searchall', label: 'searchall', run: runSearchAll },
    ],
  },
  {
    title: 'Pop and draw',
    items: [
      { key: 'pop', label: 'pop first|last', pop: true },
      { key: 'draw', label: 'draw winner', run: runDraw },
    ],
  },
  {
    title: 'Whole list',
    items: [
      { key: 'clone', label: 'clone', run: runClone },
      { key: 'clear', label: 'clear', run: runClear },
    ],
  },
  {
    title: 'State',
    items: [
      { key: 'disable', label: 'disable', run: () => runState('disable') },
      { key: 'enable', label: 'enable', run: () => runState('enable') },
    ],
  },
];

function isActionOpen(action: string): boolean {
  return list.value.chat_permissions?.[action] === 'everyone';
}

const permissionSaving = ref(false);

function toggleActionPermission(action: string) {
  // Optimistic update so the toggle feels responsive; revert on error.
  const checked = !isActionOpen(action);
  const previous = { ...(list.value.chat_permissions || {}) };
  const next = { ...previous, [action]: checked ? 'everyone' : 'moderator' };
  list.value.chat_permissions = next;

  permissionSaving.value = true;
  router.put(
    route('lists.update', list.value.id),
    {
      chat_permissions: next,
    },
    {
      preserveScroll: true,
      onError: () => {
        list.value.chat_permissions = previous;
        toastMessage.value = 'Failed to save permission. Reverted.';
        toastType.value = 'error';
      },
      onFinish: () => {
        permissionSaving.value = false;
      },
    },
  );
}

// ──────────────────────────────────────────────────────────────────────────────
// Meta-command name (read-only here; edited on the index page). Used only to
// label the Actions/permissions copy with the streamer's chosen command.
// ──────────────────────────────────────────────────────────────────────────────

const metaCommand = ref<{ command: string; enabled: boolean } | null>(null);

async function loadMeta() {
  try {
    const res = await axios.get('/dashboard/lists/meta-command');
    metaCommand.value = res.data.meta;
  } catch {
    /* ignore */
  }
}
</script>

<template>
  <Head :title="list.label || list.slug" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-6xl p-4 pb-16">
      <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @close="toastMessage = null" />

      <a
        :href="route('lists.index')"
        class="mb-4 inline-flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
      >
        <ArrowLeftIcon class="h-3.5 w-3.5" />
        All lists
      </a>

      <!-- Title row: name, state pill, save chip -->
      <div class="mb-2 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ list.label || list.slug }}</h1>
        <span
          class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium"
          :class="
            list.disabled_at === null ? 'border-green-500/50 text-green-700 dark:text-green-400' : 'border-muted-foreground/40 text-muted-foreground'
          "
        >
          <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
          {{ list.disabled_at === null ? 'Active' : 'Disabled' }}
        </span>
        <span v-if="list.recipe" class="rounded-full border border-violet-500/40 px-2.5 py-0.5 text-[11px] text-violet-700 dark:text-violet-300">
          from {{ list.recipe.name }}
        </span>
        <span
          v-if="isActiveLocked"
          class="inline-flex items-center gap-1 rounded-full border border-muted-foreground/40 px-2.5 py-0.5 text-[11px] text-muted-foreground"
        >
          <LockIcon class="h-2.5 w-2.5" />
          Locked
        </span>
        <div class="flex-1"></div>
        <button v-if="anythingDirty || saving" class="btn btn-primary btn-sm rounded-full" :disabled="saving" @click="saveAll">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
        <span v-else class="inline-flex items-center gap-1.5 text-xs text-green-700/80 dark:text-green-400/80">
          <CheckIcon class="h-3.5 w-3.5" />
          Saved
        </span>
      </div>

      <!-- Template tag row -->
      <div class="mb-6 flex flex-wrap items-center gap-2">
        <span class="text-xs text-muted-foreground">Template tag</span>
        <code class="px-2.5 py-0.5 text-xs">{{ list.tag }}</code>
        <button
          class="cursor-pointer rounded-full border bg-transparent px-3 py-1 text-[11px] font-medium"
          :class="
            tagCopied
              ? 'border-green-500/60 text-green-700 dark:text-green-400'
              : 'border-black/20 text-foreground hover:border-black/50 dark:border-white/20 dark:hover:border-white/60'
          "
          title="Copy template tag"
          @click="copyTag(list.tag)"
        >
          {{ tagCopied ? 'Copied' : 'Copy' }}
        </button>
      </div>

      <!-- Disabled nudge -->
      <div v-if="list.disabled_at !== null" class="list-nudge mb-5 flex flex-wrap items-center gap-4 px-6 py-5">
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-red-500/25 text-xl font-bold text-red-700 dark:text-white">!</div>
        <div class="min-w-0 flex-1 text-sm leading-normal">
          <div class="font-semibold text-foreground dark:text-white">This list is disabled</div>
          <div class="text-foreground/70 dark:text-white/65">Chat appends and actions are paused. Existing items stay visible in overlays.</div>
        </div>
        <button
          class="shrink-0 cursor-pointer rounded-full border border-black/30 bg-transparent px-4 py-1.5 text-xs font-medium text-foreground hover:bg-black/5 dark:border-white/55 dark:text-white dark:hover:border-white/80 dark:hover:bg-white/8"
          @click="toggleDisabled"
        >
          Enable list
        </button>
      </div>

      <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_356px]">
        <!-- Main column -->
        <div class="flex min-w-0 flex-col gap-6">
          <!-- Editor card -->
          <div class="border border-sidebar-border bg-black/2 p-5 dark:bg-[#0a0512]/50">
            <label for="active-label" class="mb-1.5 block text-[11px] font-medium tracking-wider text-muted-foreground uppercase">Label</label>
            <input id="active-label" v-model="draftLabel" :disabled="!!isActiveLocked" placeholder="(optional)" class="input-border w-full" />

            <div class="mt-5 mb-1.5 flex items-baseline gap-2">
              <label for="active-items" class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase">Items</label>
              <span class="text-[11px] text-muted-foreground/70">one per line</span>
              <div class="flex-1"></div>
              <span class="font-mono text-[11px] text-muted-foreground">{{ activeItemCount }} line{{ activeItemCount === 1 ? '' : 's' }}</span>
            </div>
            <textarea
              id="active-items"
              v-model="draftItemsText"
              :disabled="!!isActiveLocked"
              spellcheck="false"
              placeholder="Nothing here yet. Items appear when a chat command fires, or type them in - one per line."
              class="input-border block min-h-[300px] w-full resize-y font-mono text-xs leading-relaxed"
            ></textarea>
            <p class="mt-2 text-[11.5px] text-muted-foreground/70">Empty lines and duplicates are preserved exactly.</p>
          </div>

          <!-- Append commands card -->
          <AppendCommandsCard :list-id="list.id" :list-slug="list.slug" @toast="showToast" />
        </div>

        <!-- Side column -->
        <div class="flex min-w-0 flex-col gap-6">
          <!-- Chat actions card -->
          <div class="border border-sidebar-border bg-black/2 p-5 dark:bg-[#0a0512]/50">
            <h3 class="text-[15px] font-semibold text-foreground">Chat actions</h3>
            <p class="mt-0.5 text-xs leading-normal text-muted-foreground">
              Run here, or in chat as <code>!{{ metaCommand?.command || 'list' }} {{ list.slug }} &lt;action&gt;</code>. Destructive actions snapshot
              first.
            </p>

            <div v-for="group in ACTION_GROUPS" :key="group.title" class="mt-3.5">
              <div class="mb-1 font-mono text-[10px] tracking-wider text-muted-foreground/80 uppercase">{{ group.title }}</div>
              <div
                v-for="item in group.items"
                :key="item.key"
                class="flex items-center gap-2.5 rounded px-0.5 py-1 hover:bg-black/3 dark:hover:bg-white/3"
              >
                <template v-if="item.pop">
                  <button
                    title="Pop first item"
                    class="grid h-6 w-6 shrink-0 cursor-pointer place-items-center rounded-full border border-black/20 text-foreground/60 hover:border-green-600 hover:text-green-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/20 dark:hover:border-green-500 dark:hover:text-green-400"
                    :disabled="runningAction !== null"
                    @click="() => runPop('first')"
                  >
                    <ArrowUpFromLineIcon class="h-3 w-3" />
                  </button>
                  <button
                    title="Pop last item"
                    class="grid h-6 w-6 shrink-0 cursor-pointer place-items-center rounded-full border border-black/20 text-foreground/60 hover:border-green-600 hover:text-green-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/20 dark:hover:border-green-500 dark:hover:text-green-400"
                    :disabled="runningAction !== null"
                    @click="() => runPop('last')"
                  >
                    <ArrowDownFromLineIcon class="h-3 w-3" />
                  </button>
                </template>
                <button
                  v-else
                  title="Run now"
                  class="grid h-6 w-6 shrink-0 cursor-pointer place-items-center rounded-full border border-black/20 text-foreground/60 hover:border-green-600 hover:text-green-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/20 dark:hover:border-green-500 dark:hover:text-green-400"
                  :disabled="runningAction !== null"
                  @click="item.run?.()"
                >
                  <PlayIcon class="h-2.5 w-2.5" />
                </button>
                <span class="flex-1 font-mono text-xs text-foreground/80">{{ item.label }}</span>
                <button
                  role="switch"
                  :aria-checked="isActionOpen(item.key)"
                  :title="
                    isActionOpen(item.key)
                      ? 'Everyone can run this in chat. Click for moderators only.'
                      : 'Moderators only. Click to open to everyone.'
                  "
                  class="relative h-[17px] w-[30px] shrink-0 cursor-pointer rounded-full border"
                  :class="
                    isActionOpen(item.key) ? 'border-green-500/60 bg-green-500/25' : 'border-black/15 bg-black/5 dark:border-white/15 dark:bg-white/6'
                  "
                  @click="toggleActionPermission(item.key)"
                >
                  <span
                    class="absolute top-[2px] h-[11px] w-[11px] rounded-full transition-all"
                    :class="isActionOpen(item.key) ? 'left-[16px] bg-green-600 dark:bg-green-400' : 'left-[2px] bg-black/40 dark:bg-white/40'"
                  ></span>
                </button>
              </div>
            </div>

            <div class="mt-3.5 border-t border-sidebar-border pt-3 text-[11px] text-muted-foreground/80">
              Toggle on = everyone can run it in chat. Off = moderators only.
              <span v-if="permissionSaving" class="ml-1 italic">Saving…</span>
            </div>
          </div>

          <!-- Expiry card -->
          <div class="border border-sidebar-border bg-black/2 p-5 dark:bg-[#0a0512]/50">
            <h3 class="text-[15px] font-semibold text-foreground">Expiry</h3>
            <p class="mt-0.5 text-xs text-muted-foreground">Both checks run every minute.</p>

            <label for="entry-ttl" class="mt-3.5 mb-1.5 block text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
              Per-item age-out
            </label>
            <div class="flex gap-2">
              <input id="entry-ttl" v-model.number="ttlValue" type="number" min="1" placeholder="off" class="input-border min-w-0 flex-1 font-mono" />
              <select v-model="ttlUnit" class="input-border flex-none cursor-pointer">
                <option value="seconds">seconds</option>
                <option value="minutes">minutes</option>
                <option value="hours">hours</option>
              </select>
            </div>
            <p class="mt-1.5 text-[11px] text-muted-foreground/70">Older items are removed on the next sweep. Max 30 days.</p>

            <label for="expires-at" class="mt-3.5 mb-1.5 block text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
              Whole-list deadline
            </label>
            <div class="flex gap-2">
              <input
                id="expires-at"
                v-model="expiresAtLocal"
                type="datetime-local"
                class="input-border min-w-0 flex-1 cursor-pointer font-mono text-xs"
              />
              <button v-if="expiresAtLocal" class="btn btn-chill btn-xs shrink-0 rounded-full" @click="clearExpiresAt">Clear</button>
            </div>
            <p class="mt-1.5 text-[11px]">
              <span v-if="expiryCountdown" class="text-foreground">
                At the deadline the list is snapshotted, cleared, and further appends are disabled. In
                <span class="font-mono">{{ expiryCountdown }}</span></span
              >
              <span v-else class="text-muted-foreground/70">No deadline set.</span>
            </p>

            <div class="mt-3 border-t border-sidebar-border pt-3 text-[11px] leading-[1.9] text-muted-foreground/80">
              Tags <code class="text-[10.5px]">{{ list.tag.replace(']]]', ':expires_at]]]') }}</code>
              <code class="text-[10.5px]">{{ list.tag.replace(']]]', ':countdown]]]') }}</code>
            </div>
          </div>

          <!-- Snapshots card -->
          <SnapshotsCard ref="snapshotsCard" :list-id="list.id" :list-slug="list.slug" @toast="showToast" />

          <!-- Danger zone -->
          <div class="border border-red-500/20 bg-black/2 p-5 dark:bg-[#0a0512]/50">
            <h3 class="mb-3 text-[15px] font-semibold text-foreground">Danger zone</h3>
            <div class="flex items-center gap-3 border-b border-sidebar-border pb-3">
              <p class="min-w-0 flex-1 text-xs leading-normal text-muted-foreground">
                {{
                  list.disabled_at !== null
                    ? 'The list keeps its items but appends and chat actions are paused.'
                    : 'Pause appends and chat actions. Items are kept.'
                }}
              </p>
              <button
                class="btn btn-sm shrink-0 rounded-full"
                :class="list.disabled_at !== null ? 'btn-cancel' : 'btn-warning'"
                @click="toggleDisabled"
              >
                {{ list.disabled_at !== null ? 'Enable list' : 'Disable list' }}
              </button>
            </div>
            <div class="flex items-center gap-3 pt-3">
              <p class="min-w-0 flex-1 text-xs leading-normal text-muted-foreground">
                Delete the list, its items and its snapshots. There is no undo.
              </p>
              <button
                class="btn btn-danger btn-sm shrink-0 rounded-full"
                :disabled="!!isActiveLocked || list.recipe_instance_id !== null"
                :title="list.recipe_instance_id !== null ? 'Delete the recipe instance to remove this list.' : 'Delete this list permanently.'"
                @click="deleteActive"
              >
                Delete list
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Disabled-list nudge banner: soft red radial wash anchored on the left,
   full-strength in dark mode (the design's signature look), a light tint in
   light mode. */
.list-nudge {
  border-radius: 24px;
  border: 1px solid rgb(239 68 68 / 0.25);
  background: radial-gradient(ellipse 60% 140% at 0% 50%, rgb(220 38 38 / 0.14) 0%, rgb(220 38 38 / 0.06) 35%, transparent 70%);
}
.dark .list-nudge {
  border: 0;
  background: radial-gradient(
    ellipse 60% 140% at 0% 50%,
    rgb(220 38 38 / 0.55) 0%,
    rgb(220 38 38 / 0.28) 35%,
    rgb(30 18 22 / 0.9) 70%,
    rgb(18 15 20 / 0.95) 100%
  );
}
</style>
