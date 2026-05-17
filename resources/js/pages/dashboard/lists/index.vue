<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import {
  ListIcon,
  PlusIcon,
  CopyIcon,
  Trash2Icon,
  LockIcon,
  ChefHat,
  List,
  MessageSquareIcon,
  PencilIcon,
  PowerIcon,
  PowerOffIcon,
  DicesIcon,
  EraserIcon,
  ArrowUpFromLineIcon,
  ArrowDownFromLineIcon,
  CopyPlusIcon,
  HashIcon,
  ArrowDownToLineIcon,
  ArrowUpToLineIcon,
  ShuffleIcon,
  HistoryIcon,
  PinIcon,
  RotateCcwIcon,
  TerminalIcon,
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

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
}

const props = defineProps<{
  lists: ListRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Lists', href: '/dashboard/lists' },
];

const lists = ref<ListRow[]>([...props.lists]);
watch(() => props.lists, (next) => { lists.value = [...next]; }, { deep: true });

// Active row: the one currently being edited in the right pane.
const activeId = ref<number | null>(lists.value[0]?.id ?? null);
const activeList = computed<ListRow | null>(() => lists.value.find(l => l.id === activeId.value) ?? null);

// Draft state for the right-pane editor. The textarea holds raw text;
// split-on-newline happens at save time so the user can have empty lines
// and duplicates intentionally.
const draftLabel = ref('');
const draftItemsText = ref('');
const isDirty = ref(false);
const saving = ref(false);

watch(activeList, (next) => {
  draftLabel.value = next?.label ?? '';
  draftItemsText.value = (next?.items ?? []).join('\n');
  isDirty.value = false;
}, { immediate: true });

watch([draftLabel, draftItemsText], () => {
  if (!activeList.value) return;
  const baseline = (activeList.value.items ?? []).join('\n');
  isDirty.value = draftLabel.value !== (activeList.value.label ?? '') || draftItemsText.value !== baseline;
});

const toastMessage = ref<string | null>(null);
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');

// Create-list form state.
const showCreate = ref(false);
const newSlug = ref('');
const newLabel = ref('');
const newItemsText = ref('');
const slugError = ref<string | null>(null);

const SLUG_PATTERN = /^[a-z][a-z0-9_]{0,49}$/;

function validateSlug(s: string): string | null {
  if (!s) return 'Slug is required.';
  if (!SLUG_PATTERN.test(s)) return 'Slug must start with a lowercase letter; only letters, digits, and underscores.';
  if (lists.value.some(l => l.slug === s)) return 'You already have a list with this slug.';
  return null;
}

function createList() {
  slugError.value = validateSlug(newSlug.value);
  if (slugError.value) return;

  // An empty textarea splits to [""] - one empty-string item - which then
  // shows up as a phantom blank first row when chat-appenders later
  // add to the list. Distinguishing "" from real content fixes that
  // without touching the "lists are lists" contract for any non-empty
  // typed content.
  const items = newItemsText.value === '' ? [] : newItemsText.value.split('\n');

  router.post(route('lists.store'), {
    slug: newSlug.value,
    label: newLabel.value || null,
    items,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toastMessage.value = `List '${newSlug.value}' created.`;
      toastType.value = 'success';
      showCreate.value = false;
      newSlug.value = '';
      newLabel.value = '';
      newItemsText.value = '';
      slugError.value = null;
    },
    onError: (errors) => {
      slugError.value = errors.slug ?? 'Failed to create list.';
    },
  });
}

function saveActive() {
  if (!activeList.value || saving.value) return;
  saving.value = true;
  const items = draftItemsText.value === '' ? [] : draftItemsText.value.split('\n');

  router.put(route('lists.update', activeList.value.id), {
    label: draftLabel.value || null,
    items,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isDirty.value = false;
      toastMessage.value = `'${activeList.value?.slug}' saved.`;
      toastType.value = 'success';
    },
    onError: (errors) => {
      toastMessage.value = Object.values(errors)[0] as string ?? 'Save failed.';
      toastType.value = 'error';
    },
    onFinish: () => {
      saving.value = false;
    },
  });
}

function deleteActive() {
  if (!activeList.value) return;
  if (activeList.value.recipe_instance_id !== null) {
    toastMessage.value = 'Recipe-managed lists must be removed via the recipe.';
    toastType.value = 'warning';
    return;
  }
  if (!confirm(`Delete list '${activeList.value.slug}'? This cannot be undone.`)) return;

  router.delete(route('lists.destroy', activeList.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      toastMessage.value = `'${activeList.value?.slug}' deleted.`;
      toastType.value = 'success';
      activeId.value = lists.value[0]?.id ?? null;
    },
    onError: (errors) => {
      toastMessage.value = Object.values(errors)[0] as string ?? 'Delete failed.';
      toastType.value = 'error';
    },
  });
}

function toggleDisabled() {
  if (!activeList.value) return;
  const nextDisabled = activeList.value.disabled_at === null;

  router.put(route('lists.update', activeList.value.id), {
    disabled: nextDisabled,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toastMessage.value = nextDisabled
        ? `'${activeList.value?.slug}' disabled. Chat appenders will silently no-op.`
        : `'${activeList.value?.slug}' re-enabled.`;
      toastType.value = 'success';
    },
    onError: () => {
      toastMessage.value = 'Failed to toggle list state.';
      toastType.value = 'error';
    },
  });
}

async function copyTag(tag: string) {
  try {
    await navigator.clipboard.writeText(tag);
    toastMessage.value = `Copied ${tag}`;
    toastType.value = 'success';
  } catch {
    toastMessage.value = 'Clipboard write failed.';
    toastType.value = 'error';
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Expiry config (entry-TTL + whole-list expires_at)
// ──────────────────────────────────────────────────────────────────────────────

// Draft state for the expiry panel. ttlValue + ttlUnit compose into the
// integer seconds we POST; expiresAtLocal is the <input type="datetime-local">
// string, which we convert to Unix seconds at save time. expiresAtLocal
// stays in the user's local timezone for editing; the server stores UTC.
const ttlValue = ref<number | null>(null);
const ttlUnit = ref<'seconds' | 'minutes' | 'hours'>('minutes');
const expiresAtLocal = ref<string>('');
const expirySaving = ref(false);

watch(activeList, (next) => {
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
}, { immediate: true });

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
  if (!activeList.value) return false;
  const currentTtl = activeList.value.entry_ttl_seconds;
  const currentExpires = activeList.value.expires_at;
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

function saveExpiry() {
  if (!activeList.value || expirySaving.value) return;
  expirySaving.value = true;

  router.put(route('lists.update', activeList.value.id), {
    entry_ttl_seconds: ttlSecondsComposed.value,
    expires_at: expiresAtUnix.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toastMessage.value = `Expiry updated for '${activeList.value?.slug}'.`;
      toastType.value = 'success';
    },
    onError: (errors) => {
      toastMessage.value = (Object.values(errors)[0] as string) ?? 'Save failed.';
      toastType.value = 'error';
    },
    onFinish: () => {
      expirySaving.value = false;
    },
  });
}

function clearTtl() {
  ttlValue.value = null;
}

function clearExpiresAt() {
  expiresAtLocal.value = '';
}

const activeItemCount = computed(() => draftItemsText.value.split('\n').length);
const isActiveLocked = computed(() => activeList.value && !activeList.value.user_editable && activeList.value.recipe_instance_id !== null);

// ──────────────────────────────────────────────────────────────────────────────
// Append commands per list
// ──────────────────────────────────────────────────────────────────────────────

interface AppenderRow {
  id: number;
  target_list_id: number;
  command: string;
  permission_level: string;
  cooldown_seconds: number;
  value_template: string;
  args_empty_reply: string | null;
  dedup_policy: 'none' | 'per_chatter' | 'per_chatter_per_stream';
  max_size: number | null;
  enabled: boolean;
  last_fired_at: number | null;
}

const appenders = ref<AppenderRow[]>([]);
const appendersLoading = ref(false);

async function loadAppenders(listId: number) {
  appendersLoading.value = true;
  try {
    const res = await axios.get(`/dashboard/lists/${listId}/appenders`);
    appenders.value = res.data.appenders ?? [];
  } catch {
    appenders.value = [];
  } finally {
    appendersLoading.value = false;
  }
}

watch(activeId, (id) => {
  if (id !== null) loadAppenders(id);
  else appenders.value = [];
}, { immediate: true });

// Modal state for adding/editing an appender.
const appenderModalOpen = ref(false);
const editingAppender = ref<AppenderRow | null>(null);
const appenderForm = ref({
  command: '',
  permission_level: 'everyone',
  cooldown_seconds: 0,
  value_template: '[[[bot:from_user]]]',
  args_empty_reply: '' as string,
  dedup_policy: 'per_chatter' as 'none' | 'per_chatter' | 'per_chatter_per_stream',
  max_size: null as number | null,
  enabled: true,
});
const appenderFormErrors = ref<Record<string, string>>({});
const savingAppender = ref(false);

function openAppenderAdd() {
  editingAppender.value = null;
  appenderForm.value = {
    command: '',
    permission_level: 'everyone',
    cooldown_seconds: 0,
    value_template: '[[[bot:from_user]]]',
    args_empty_reply: '',
    dedup_policy: 'per_chatter',
    max_size: null,
    enabled: true,
  };
  appenderFormErrors.value = {};
  appenderModalOpen.value = true;
}

function openAppenderEdit(a: AppenderRow) {
  editingAppender.value = a;
  appenderForm.value = {
    command: a.command,
    permission_level: a.permission_level,
    cooldown_seconds: a.cooldown_seconds,
    value_template: a.value_template,
    args_empty_reply: a.args_empty_reply ?? '',
    dedup_policy: a.dedup_policy,
    max_size: a.max_size,
    enabled: a.enabled,
  };
  appenderFormErrors.value = {};
  appenderModalOpen.value = true;
}

async function saveAppender() {
  if (!activeList.value) return;
  savingAppender.value = true;
  appenderFormErrors.value = {};

  const body = {
    command: appenderForm.value.command,
    permission_level: appenderForm.value.permission_level,
    cooldown_seconds: appenderForm.value.cooldown_seconds,
    value_template: appenderForm.value.value_template,
    args_empty_reply: appenderForm.value.args_empty_reply || null,
    dedup_policy: appenderForm.value.dedup_policy,
    max_size: appenderForm.value.max_size || null,
    enabled: appenderForm.value.enabled,
  };

  try {
    if (editingAppender.value) {
      const res = await axios.put(`/dashboard/lists/${activeList.value.id}/appenders/${editingAppender.value.id}`, body);
      const updated = res.data.appender;
      const idx = appenders.value.findIndex(a => a.id === updated.id);
      if (idx >= 0) appenders.value[idx] = updated;
      toastMessage.value = `!${updated.command} saved.`;
    } else {
      const res = await axios.post(`/dashboard/lists/${activeList.value.id}/appenders`, body);
      appenders.value.push(res.data.appender);
      toastMessage.value = `!${res.data.appender.command} created.`;
    }
    toastType.value = 'success';
    appenderModalOpen.value = false;
  } catch (err: any) {
    if (err?.response?.status === 422 && err.response?.data?.errors) {
      const errors: Record<string, string> = {};
      for (const [field, msgs] of Object.entries(err.response.data.errors as Record<string, string[]>)) {
        errors[field] = msgs[0];
      }
      appenderFormErrors.value = errors;
    } else {
      toastMessage.value = 'Failed to save command.';
      toastType.value = 'error';
    }
  } finally {
    savingAppender.value = false;
  }
}

async function deleteAppender(a: AppenderRow) {
  if (!activeList.value) return;
  if (!confirm(`Delete command !${a.command}?`)) return;
  try {
    await axios.delete(`/dashboard/lists/${activeList.value.id}/appenders/${a.id}`);
    appenders.value = appenders.value.filter(x => x.id !== a.id);
    toastMessage.value = `!${a.command} deleted.`;
    toastType.value = 'success';
  } catch {
    toastMessage.value = 'Failed to delete command.';
    toastType.value = 'error';
  }
}

const DEDUP_LABELS: Record<string, string> = {
  none: 'no dedup',
  per_chatter: 'once per chatter',
  per_chatter_per_stream: 'once per chatter per stream',
};

// ──────────────────────────────────────────────────────────────────────────────
// Live updates - subscribe to the user's broadcast channel so chat-appender
// activity (or another browser tab) updates this page in place.
// ──────────────────────────────────────────────────────────────────────────────

const page = usePage();

interface ListUpdatedPayload {
  slug: string;
  items: string[] | null;
  updated_at: number | null;
  expires_at?: number | null;
  disabled_at?: number | null;
}

function applyListUpdated(payload: ListUpdatedPayload) {
  const idx = lists.value.findIndex(l => l.slug === payload.slug);
  if (idx === -1) {
    // Unknown slug - this is a new list (just cloned, or created in
    // another browser tab). Refresh the lists prop from the server so
    // it appears in the rail. Inertia partial reloads only the lists
    // prop, no full page reload.
    router.reload({ only: ['lists'] });
    return;
  }
  lists.value[idx] = {
    ...lists.value[idx],
    items: payload.items ?? [],
    updated_at: payload.updated_at,
    // expires_at / disabled_at only present on broadcasts from sources
    // that thread them through (the dispatchFor helper does). Falling
    // back to existing means a broadcast without the field doesn't
    // accidentally clear it.
    expires_at: payload.expires_at !== undefined ? payload.expires_at : lists.value[idx].expires_at,
    disabled_at: payload.disabled_at !== undefined ? payload.disabled_at : lists.value[idx].disabled_at,
  };

  // If the user is currently editing this list AND has unsaved changes,
  // leave the textarea alone - their pending edits win until they save
  // or navigate away. If they're not dirty, refresh the textarea so the
  // chatter's append shows up in their view too.
  if (activeId.value === lists.value[idx].id && !isDirty.value) {
    draftItemsText.value = (payload.items ?? []).join('\n');
  }
}

function applyListDeleted(slug: string) {
  const idx = lists.value.findIndex(l => l.slug === slug);
  if (idx === -1) return;
  const wasActive = lists.value[idx].id === activeId.value;
  lists.value.splice(idx, 1);
  if (wasActive) {
    activeId.value = lists.value[0]?.id ?? null;
  }
}

let echoChannel: any = null;
let echoChannelName: string | null = null;

onMounted(() => {
  const twitchId = (page.props.auth as any)?.user?.twitch_id;
  if (!twitchId || !(window as any).Echo) return;

  echoChannelName = `alerts.${twitchId}`;
  echoChannel = (window as any).Echo.private(echoChannelName);

  echoChannel.listen('.list.updated', (payload: ListUpdatedPayload) => {
    applyListUpdated(payload);
  });
  echoChannel.listen('.list.deleted', (payload: ListUpdatedPayload) => {
    applyListDeleted(payload.slug);
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

async function runAction(action: string, args: string = '', requiresConfirm = false, confirmText = '') {
  if (!activeList.value) return;
  if (requiresConfirm && !confirm(confirmText || `Run '${action}' on '${activeList.value.slug}'?`)) return;

  runningAction.value = action;
  try {
    const res = await axios.post(`/dashboard/lists/${activeList.value.id}/actions`, { action, args });
    toastMessage.value = res.data.reply || `'${action}' done.`;
    toastType.value = 'success';
    if (['clear', 'draw', 'pop'].includes(action)) {
      await loadSnapshots(activeList.value.id);
    }
  } catch (err: any) {
    toastMessage.value = err?.response?.data?.message || `'${action}' failed.`;
    toastType.value = 'error';
  } finally {
    runningAction.value = null;
  }
}

function runCount() { runAction('count'); }
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
function runDraw() {
  runAction('draw', '', true, `Draw a winner from '${activeList.value?.slug}'? The winner is removed from the list.`);
}
function runClear() {
  runAction('clear', '', true, `Clear ALL items from '${activeList.value?.slug}'? A snapshot is taken first; you can restore.`);
}
function runPop(which: 'first' | 'last') {
  runAction('pop', which, true, `Remove the ${which} item from '${activeList.value?.slug}'?`);
}
function runClone() {
  const slug = prompt(`New slug for the clone of '${activeList.value?.slug}':`, '');
  if (!slug || !slug.trim()) return;
  runAction('clone', slug.trim());
}

// ──────────────────────────────────────────────────────────────────────────────
// Snapshots
// ──────────────────────────────────────────────────────────────────────────────

interface SnapshotRow {
  id: number;
  reason: string;
  items: string[];
  item_count: number;
  pinned: boolean;
  created_at: number;
}

const snapshots = ref<SnapshotRow[]>([]);
const snapshotsLoading = ref(false);
const showSnapshots = ref(false);

async function loadSnapshots(listId: number) {
  snapshotsLoading.value = true;
  try {
    const res = await axios.get(`/dashboard/lists/${listId}/snapshots`);
    snapshots.value = res.data.snapshots ?? [];
  } catch {
    snapshots.value = [];
  } finally {
    snapshotsLoading.value = false;
  }
}

watch(activeId, (id) => {
  if (id !== null) loadSnapshots(id);
  else snapshots.value = [];
});

async function takeManualSnapshot() {
  if (!activeList.value) return;
  try {
    await axios.post(`/dashboard/lists/${activeList.value.id}/snapshots/manual`);
    await loadSnapshots(activeList.value.id);
    toastMessage.value = 'Snapshot taken.';
    toastType.value = 'success';
  } catch {
    toastMessage.value = 'Failed to take snapshot.';
    toastType.value = 'error';
  }
}

async function restoreSnapshot(snap: SnapshotRow) {
  if (!activeList.value) return;
  if (!confirm(`Restore '${activeList.value.slug}' to this snapshot (${snap.item_count} items)? A safety snapshot of the current state is taken first.`)) return;
  try {
    await axios.post(`/dashboard/lists/${activeList.value.id}/snapshots/${snap.id}/restore`);
    await loadSnapshots(activeList.value.id);
    toastMessage.value = `Restored to snapshot (${snap.item_count} items).`;
    toastType.value = 'success';
  } catch {
    toastMessage.value = 'Restore failed.';
    toastType.value = 'error';
  }
}

async function togglePin(snap: SnapshotRow) {
  if (!activeList.value) return;
  try {
    const res = await axios.patch(`/dashboard/lists/${activeList.value.id}/snapshots/${snap.id}/pin`);
    snap.pinned = res.data.pinned;
    toastMessage.value = snap.pinned ? 'Snapshot pinned (survives retention sweep).' : 'Snapshot unpinned.';
    toastType.value = 'success';
  } catch {
    toastMessage.value = 'Toggle pin failed.';
    toastType.value = 'error';
  }
}

async function deleteSnapshot(snap: SnapshotRow) {
  if (!activeList.value) return;
  if (!confirm(`Delete this snapshot? Cannot be undone.`)) return;
  try {
    await axios.delete(`/dashboard/lists/${activeList.value.id}/snapshots/${snap.id}`);
    snapshots.value = snapshots.value.filter(s => s.id !== snap.id);
    toastMessage.value = 'Snapshot deleted.';
    toastType.value = 'success';
  } catch {
    toastMessage.value = 'Delete failed.';
    toastType.value = 'error';
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

// ──────────────────────────────────────────────────────────────────────────────
// Meta-command settings (!list <slug> <action>)
// ──────────────────────────────────────────────────────────────────────────────

const metaCommand = ref<{ command: string; enabled: boolean } | null>(null);
const metaForm = ref({ command: 'list', enabled: true });
const metaError = ref<string | null>(null);
const savingMeta = ref(false);

async function loadMeta() {
  try {
    const res = await axios.get('/dashboard/lists/meta-command');
    metaCommand.value = res.data.meta;
    if (metaCommand.value) {
      metaForm.value.command = metaCommand.value.command;
      metaForm.value.enabled = metaCommand.value.enabled;
    }
  } catch { /* ignore */ }
}

async function saveMeta() {
  savingMeta.value = true;
  metaError.value = null;
  try {
    const res = await axios.put('/dashboard/lists/meta-command', metaForm.value);
    metaCommand.value = res.data.meta;
    toastMessage.value = `!${metaCommand.value?.command} ${metaCommand.value?.enabled ? 'enabled' : 'disabled'}.`;
    toastType.value = 'success';
  } catch (err: any) {
    metaError.value = err?.response?.data?.errors?.command?.[0] ?? 'Failed to save.';
  } finally {
    savingMeta.value = false;
  }
}

onMounted(() => {
  loadMeta();
});
</script>

<template>
  <Head title="Lists" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full space-y-4 p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <List class="h-6 w-6 mr-2" />
          <Heading
            title="Lists"
          />
        </div>

        <button class="btn btn-primary cursor-pointer shrink-0" @click="showCreate = !showCreate">
          <PlusIcon class="h-4 w-4" />
          <span class="ml-1.5">New list</span>
        </button>
      </div>

      <p class="text-sm">Reusable lists you can reference from any overlay via [[[c:list:&lt;slug&gt;]]] or loop with [[[foreach:c:list:&lt;slug&gt; as item]]]. Lists are lists - we preserve exactly what you type, empties and duplicates included.</p>
      <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @close="toastMessage = null" />

      <!-- Meta-command settings: opt into !list (mod+) for chat actions -->
      <Card class="border-sidebar-border mb-6 bg-sidebar-accent">
        <CardContent>
          <div class="flex items-start gap-3">
            <TerminalIcon class="mt-0.5 h-5 w-5 shrink-0" />
            <div class="min-w-0 flex-1 space-y-2">
              <div>
                <h3 class="text-sm font-semibold text-foreground">!list meta-command (mod+ in chat)</h3>
                <p class="mt-0.5 text-xs text-muted-foreground">
                  By default, mod actions live under <span class="text-foreground">!list</span>. If that doesn't work with your stream
                  configuration, you can set another command here.
                </p>
              </div>
              <Label for="meta-cmd" class="text-xs">Command name</Label>
              <div class="flex flex-wrap items-center gap-2">
                <div>
                  <div class="flex items-center gap-1">
                    <span class="font-mono text-sm text-muted-foreground">!</span>
                    <input id="meta-cmd" v-model="metaForm.command" class="w-32 h-8 font-mono input-border" />
                  </div>
                </div>

                <button size="sm" class="btn h-8 btn-primary cursor-pointer" :disabled="savingMeta" @click="saveMeta">
                  {{ savingMeta ? 'Saving…' : metaCommand ? 'Update' : 'Enable !list' }}
                </button>
              </div>
              <p v-if="metaError" class="text-xs text-destructive">{{ metaError }}</p>
              <p v-else-if="metaCommand?.enabled" class="text-xs text-muted-foreground">
                Active in chat: <span class="font-mono">!{{ metaCommand.command }}</span>
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card v-if="showCreate" class="border-sidebar-border">
        <CardContent class="space-y-6">
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <Label for="new-slug">Slug</Label>
              <input
                id="new-slug"
                v-model="newSlug"
                placeholder="pizza_toppings"
                class="cursor-text font-mono input-border"
              />
              <p v-if="slugError" class="mt-1 text-xs text-destructive">{{ slugError }}</p>
              <p v-else class="mt-1 text-xs text-muted-foreground">
                Used in tags: <span class="font-mono">[[[c:list:{{ newSlug || 'your_slug' }}]]]</span>
              </p>
            </div>
            <div>
              <Label for="new-label">Label (optional)</Label>
              <input id="new-label" class="input-border" v-model="newLabel" placeholder="Pizza toppings" />
            </div>
          </div>
          <div>
            <Label for="new-items">Items (one per line)</Label>
            <textarea
              id="new-items"
              v-model="newItemsText"
              rows="6"
              class="input-border w-full font-mono text-sm"
              placeholder="Pepperoni&#10;Mushroom&#10;Pineapple"
            ></textarea>
          </div>
          <div class="flex justify-between gap-2">
            <button class="btn btn-tertiary cursor-pointer" @click="showCreate = false">Cancel</button>
            <button class="btn btn-primary cursor-pointer" @click="createList">Create</button>
          </div>
        </CardContent>
      </Card>

      <div v-if="lists.length === 0" class="border border-sidebar-border border-dashed p-10 text-center">
        <ChefHat class="mx-auto h-10 w-10 text-muted-foreground" />
        <p class="mt-4 text-foreground">No lists yet.</p>
        <p class="mt-1 text-sm text-muted-foreground">
          Create one above to use it across your overlays.
        </p>
      </div>

      <div v-else class="grid gap-4 md:grid-cols-[260px_1fr]">
        <!-- Left: list of lists -->
        <div class="space-y-2">
          <button
            v-for="list in lists"
            :key="list.id"
            type="button"
            class="flex w-full cursor-pointer items-start justify-between gap-2 border border-sidebar-border p-2 text-left text-sm transition hover:bg-sidebar-accent"
            :class="{ 'bg-sidebar-accent border-violet-400/40': activeId === list.id }"
            @click="activeId = list.id"
          >
            <div class="min-w-0">
              <div class="flex items-center gap-1.5">
                <ListIcon class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                <span class="truncate font-medium text-foreground">{{ list.label || list.slug }}</span>
                <LockIcon v-if="!list.user_editable && list.recipe_instance_id !== null" class="h-3 w-3 text-muted-foreground" />
              </div>
              <div class="mt-0.5 font-mono text-[11px] text-muted-foreground">{{ list.slug }}</div>
              <div class="mt-0.5 text-[11px] text-muted-foreground">
                {{ list.items.length }} item{{ list.items.length === 1 ? '' : 's' }}
                <span v-if="list.recipe" class="ml-1">• {{ list.recipe.name }}</span>
              </div>
            </div>
          </button>
        </div>

        <!-- Right: editor for the active list -->
        <div v-if="activeList" class="space-y-6">
          <div class="flex flex-wrap items-center justify-between gap-2 border border-sidebar-border p-3">
            <div class="flex items-center gap-2">
              <span class="font-mono text-sm text-foreground">{{ activeList.tag }}</span>
              <Button variant="ghost" size="sm" class="cursor-pointer" @click="copyTag(activeList.tag)">
                <CopyIcon class="h-3.5 w-3.5" />
                <span class="ml-1.5">Copy</span>
              </Button>
            </div>
            <div class="flex items-center gap-2">
              <Badge v-if="activeList.disabled_at !== null" variant="destructive">
                <PowerOffIcon class="mr-1 h-3 w-3" />
                Disabled
              </Badge>
              <Badge v-if="activeList.recipe" variant="secondary">
                from {{ activeList.recipe.name }}
              </Badge>
              <Badge v-if="!activeList.user_editable && activeList.recipe_instance_id !== null" variant="outline">
                <LockIcon class="mr-1 h-3 w-3" />
                Locked
              </Badge>
            </div>
          </div>

          <div class="w-full">
            <Label for="active-label">Label</Label>
            <input
              id="active-label"
              v-model="draftLabel"
              :disabled="!!isActiveLocked"
              placeholder="(optional)"
              class="input-border w-full"
            />
          </div>

          <div>
            <div class="flex items-center justify-between">
              <Label for="active-items">Items (one per line)</Label>
              <span class="text-xs text-muted-foreground">{{ activeItemCount }} line{{ activeItemCount === 1 ? '' : 's' }}</span>
            </div>
            <textarea
              id="active-items"
              v-model="draftItemsText"
              :disabled="!!isActiveLocked"
              rows="16"
              class="input-border w-full font-mono text-sm"
            ></textarea>
            <p class="mt-1 text-xs text-muted-foreground">
              Empty lines and duplicates are preserved exactly.
            </p>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <button
                class="btn btn-danger cursor-pointer text-destructive hover:text-destructive"
                :disabled="!!isActiveLocked || activeList.recipe_instance_id !== null"
                :title="activeList.recipe_instance_id !== null ? 'Delete the recipe instance to remove this list.' : 'Delete this list permanently.'"
                @click="deleteActive"
              >
                <Trash2Icon class="h-4 w-4" />
                <span class="ml-1.5">Delete list</span>
              </button>
              <button
                class="btn btn-warning cursor-pointer text-warning hover:text-warning"
                :title="activeList.disabled_at !== null ? 'Re-enable: chat appenders can write again.' : 'Disable: chat appenders silently no-op; existing items stay visible.'"
                @click="toggleDisabled"
              >
                <component :is="activeList.disabled_at !== null ? PowerIcon : PowerOffIcon" class="h-4 w-4" />
                <span class="ml-1.5">{{ activeList.disabled_at !== null ? 'Enable list' : 'Disable list' }}</span>
              </button>
            </div>
            <button
              class="cursor-pointer btn btn-primary"
              :disabled="!isDirty || saving || !!isActiveLocked"
              @click="saveActive"
            >
              {{ saving ? 'Saving…' : isDirty ? 'Save changes' : 'Saved' }}
            </button>
          </div>

          <!-- Expiry panel: per-item age-out + whole-list deadline. -->
          <div class="mt-6 border border-sidebar-border p-4">
            <div class="mb-3">
              <h3 class="text-sm font-semibold text-foreground">Expiry</h3>
              <p class="mt-0.5 text-xs text-muted-foreground">
                Optional. Per-item age-out drops individual entries after their age exceeds the TTL.
                Whole-list expiry snapshots the list, clears items, and disables further appends at the chosen moment.
                Both run every minute.
              </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <!-- Entry TTL -->
              <div>
                <Label for="entry-ttl">Per-item age-out</Label>
                <div class="mt-1 flex items-center gap-2">
                  <input
                    id="entry-ttl"
                    v-model.number="ttlValue"
                    type="number"
                    min="1"
                    placeholder="off"
                    class="input-border cursor-pointer px-2 py-1.5 text-sm"
                  />
                  <select
                    v-model="ttlUnit"
                    class="input-border cursor-pointer px-2 py-2 text-sm"
                  >
                    <option value="seconds">seconds</option>
                    <option value="minutes">minutes</option>
                    <option value="hours">hours</option>
                  </select>
                  <button
                    v-if="ttlValue !== null"
                    size="sm"
                    class="cursor-pointer btn btn-chill px-2 py-1.5 text-sm"
                    @click="clearTtl"
                  >
                    Clear
                  </button>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                  Items older than this are removed on the next sweep. Max 30 days.
                </p>
              </div>

              <!-- Whole-list expires_at -->
              <div>
                <Label for="expires-at">Whole-list deadline</Label>
                <div class="mt-1 flex items-center gap-2">
                  <input
                    id="expires-at"
                    v-model="expiresAtLocal"
                    type="datetime-local"
                    class="cursor-pointer input-border px-2 py-1.5 text-sm"
                  />
                  <button
                    v-if="expiresAtLocal"
                    size="sm"
                    class="cursor-pointer btn btn-chill px-2 py-1.5 text-sm"
                    @click="clearExpiresAt"
                  >
                    Clear
                  </button>
                </div>
                <p class="mt-1 text-xs text-foreground">
                  <span v-if="expiryCountdown">In <span class="font-mono">{{ expiryCountdown }}</span></span>
                  <span v-else class="text-muted-foreground">No deadline set.</span>
                </p>
              </div>
            </div>

            <div class="mt-3 flex items-center justify-between gap-2">
              <p class="text-xs text-muted-foreground">
                Template tags:
                <span class="font-mono">{{ activeList.tag.replace(']]]', ':expires_at]]]') }}</span>,
                <span class="font-mono">{{ activeList.tag.replace(']]]', ':countdown]]]') }}</span>
              </p>
              <button
                size="sm"
                class="cursor-pointer btn btn-primary px-2 py-1.5 text-sm"
                :disabled="!expiryIsDirty || expirySaving"
                @click="saveExpiry"
              >
                {{ expirySaving ? 'Saving…' : expiryIsDirty ? 'Save expiry' : 'Saved' }}
              </button>
            </div>
          </div>

          <!-- Action buttons section: same vocabulary as the chat !list -->
          <div class="mt-6 border border-sidebar-border p-4">
            <div class="mb-3">
              <h3 class="text-sm font-semibold text-foreground">Actions</h3>
              <p class="mt-0.5 text-xs text-muted-foreground">
                Same vocabulary as <span class="font-mono">!{{ metaCommand?.command || 'list' }} {{ activeList.slug }} &lt;action&gt;</span> in chat. Destructive actions snapshot first.
              </p>
            </div>
            <div class="flex flex-col items-start gap-x-6 gap-y-3">
              <!-- Inspect: read-only peeks -->
              <div class="flex flex-wrap items-center gap-2">
                <div class="text-xs font-medium w-full tracking-wide text-foreground">Inspect</div>
                <button class="btn btn-chill cursor-pointer" :disabled="runningAction !== null" @click="runCount">
                  <HashIcon class="h-3.5 w-3.5" /><span class="ml-1">Count</span>
                </button>
                <button class="btn btn-chill cursor-pointer" :disabled="runningAction !== null" @click="runFirst">
                  <ArrowUpToLineIcon class="h-3.5 w-3.5" /><span class="ml-1">First</span>
                </button>
                <button class="btn btn-chill cursor-pointer" :disabled="runningAction !== null" @click="runLast">
                  <ArrowDownToLineIcon class="h-3.5 w-3.5" /><span class="ml-1">Last</span>
                </button>
                <button class="btn btn-chill cursor-pointer" :disabled="runningAction !== null" @click="runRandom">
                  <ShuffleIcon class="h-3.5 w-3.5" /><span class="ml-1">Random</span>
                </button>
              </div>

              <!-- Pop: remove one item -->
              <div class="flex flex-wrap items-center gap-2">
                <div class="text-xs w-full font-medium tracking-wide text-foreground">Pop/draw</div>
                <button class="btn btn-chill cursor-pointer" :disabled="runningAction !== null" @click="() => runPop('first')">
                  <ArrowUpFromLineIcon class="h-3.5 w-3.5" /><span class="ml-1">Pop first</span>
                </button>
                <button class="btn btn-chill cursor-pointer" :disabled="runningAction !== null" @click="() => runPop('last')">
                  <ArrowDownFromLineIcon class="h-3.5 w-3.5" /><span class="ml-1">Pop last</span>
                </button>
                <button class="btn btn-primary cursor-pointer" :disabled="runningAction !== null" @click="runDraw">
                  <DicesIcon class="h-3.5 w-3.5" /><span class="ml-1">Draw winner</span>
                </button>
              </div>

              <!-- Whole list -->
              <div class="flex flex-wrap items-center gap-2">
                <div class="text-xs w-full font-medium tracking-wide text-foreground">List</div>
                <button class="btn btn-chill cursor-pointer" :disabled="runningAction !== null" @click="runClone">
                  <CopyPlusIcon class="h-3.5 w-3.5" /><span class="ml-1">Clone</span>
                </button>
                <button class="btn btn-chill cursor-pointer text-destructive hover:text-destructive" :disabled="runningAction !== null" @click="runClear">
                  <EraserIcon class="h-3.5 w-3.5" /><span class="ml-1">Clear</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Snapshots panel: history of destructive actions, restorable -->
          <div class="mt-4 border border-sidebar-border p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
              <button
                type="button"
                class="flex cursor-pointer items-center gap-2 text-left"
                @click="showSnapshots = !showSnapshots"
              >
                <HistoryIcon class="h-4 w-4 text-muted-foreground" />
                <h3 class="text-sm font-semibold text-foreground">Snapshots</h3>
                <span class="text-xs text-muted-foreground">({{ snapshots.length }})</span>
                <span class="text-xs text-muted-foreground">{{ showSnapshots ? '▾' : '▸' }}</span>
              </button>
              <Button size="sm" variant="ghost" class="cursor-pointer" @click="takeManualSnapshot">
                <PlusIcon class="h-3.5 w-3.5" />
                <span class="ml-1">Save snapshot</span>
              </Button>
            </div>
            <div v-if="showSnapshots">
              <div v-if="snapshotsLoading" class="text-sm text-muted-foreground">Loading…</div>
              <div v-else-if="snapshots.length === 0" class="rounded border border-dashed py-4 text-center text-sm text-muted-foreground">
                No snapshots yet. They're created automatically before clear/draw/pop.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="snap in snapshots"
                  :key="snap.id"
                  class="flex flex-wrap items-center justify-between gap-2 rounded border border-sidebar-border p-1"
                >
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                      <Badge variant="outline" class="text-[10px]">{{ REASON_LABELS[snap.reason] ?? snap.reason }}</Badge>
                      <span class="text-xs text-foreground">{{ snap.item_count }} item{{ snap.item_count === 1 ? '' : 's' }}</span>
                      <span class="text-xs text-muted-foreground">• {{ snapshotAge(snap.created_at) }}</span>
                      <Badge v-if="snap.pinned" variant="secondary" class="text-[10px]">
                        <PinIcon class="mr-1 h-2.5 w-2.5" />
                        Pinned
                      </Badge>
                    </div>
                  </div>
                  <div class="flex items-center gap-1">
                    <Button size="sm" variant="ghost" class="cursor-pointer" :title="snap.pinned ? 'Unpin' : 'Pin (survives retention)'" @click="togglePin(snap)">
                      <PinIcon class="h-3.5 w-3.5" :class="snap.pinned ? 'fill-current' : ''" />
                    </Button>
                    <Button size="sm" variant="ghost" class="cursor-pointer" title="Restore to this snapshot" @click="restoreSnapshot(snap)">
                      <RotateCcwIcon class="h-3.5 w-3.5" />
                    </Button>
                    <Button size="sm" variant="ghost" class="cursor-pointer text-destructive hover:text-destructive" title="Delete this snapshot" @click="deleteSnapshot(snap)">
                      <Trash2Icon class="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Append commands section -->
          <div class="mt-6 border border-sidebar-border p-4">
            <div class="mb-3 flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-foreground">Append commands</h3>
                <p class="mt-0.5 text-xs text-muted-foreground">
                  Chat commands that append to this list when fired. Use Bot Expression syntax like
                  <span class="font-mono">[[[bot:from_user]]]</span> in the value template.
                </p>
              </div>
              <button class="btn btn-primary cursor-pointer shrink-0" @click="openAppenderAdd">
                <PlusIcon class="h-3.5 w-3.5" />
                <span class="ml-1">Add command</span>
              </button>
            </div>

            <div v-if="appendersLoading" class="text-sm text-muted-foreground">Loading…</div>
            <div v-else-if="appenders.length === 0" class="rounded border border-dashed py-6 text-center text-sm text-muted-foreground">
              No append commands yet. Add one to let chatters grow this list.
            </div>
            <div v-else class="space-y-6">
              <div
                v-for="a in appenders"
                :key="a.id"
                class="flex flex-wrap items-start justify-between gap-2 rounded border border-sidebar-border p-2.5"
              >
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-sm font-medium text-foreground">!{{ a.command }}</span>
                    <Badge variant="outline" class="text-[10px]">{{ a.permission_level }}</Badge>
                    <Badge variant="secondary" class="text-[10px]">{{ DEDUP_LABELS[a.dedup_policy] }}</Badge>
                    <Badge v-if="a.max_size" variant="outline" class="text-[10px]">max {{ a.max_size }}</Badge>
                    <Badge v-if="a.cooldown_seconds > 0" variant="outline" class="text-[10px]">{{ a.cooldown_seconds }}s cd</Badge>
                    <Badge v-if="!a.enabled" variant="destructive" class="text-[10px]">disabled</Badge>
                  </div>
                  <p class="mt-1 font-mono text-xs text-muted-foreground truncate" :title="a.value_template">
                    appends: {{ a.value_template }}
                  </p>
                  <p v-if="a.args_empty_reply" class="mt-0.5 flex items-start gap-1 text-xs text-muted-foreground">
                    <MessageSquareIcon class="h-3 w-3 shrink-0 mt-0.5" />
                    <span class="truncate" :title="a.args_empty_reply">empty-args reply: {{ a.args_empty_reply }}</span>
                  </p>
                </div>
                <div class="flex items-center gap-1">
                  <Button size="sm" variant="ghost" class="cursor-pointer" @click="openAppenderEdit(a)">
                    <PencilIcon class="h-3.5 w-3.5" />
                  </Button>
                  <Button size="sm" variant="ghost" class="cursor-pointer text-destructive hover:text-destructive" @click="deleteAppender(a)">
                    <Trash2Icon class="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Append command edit modal -->
      <Dialog v-model:open="appenderModalOpen">
        <DialogContent class="max-w-lg">
          <DialogHeader>
            <DialogTitle>{{ editingAppender ? `Edit !${editingAppender.command}` : 'New append command' }}</DialogTitle>
          </DialogHeader>
          <div class="space-y-6">
            <div>
              <Label for="ap-command">Command (without <code>!</code>)</Label>
              <input id="ap-command" v-model="appenderForm.command" placeholder="raffle" class="font-mono input-border" />
              <p v-if="appenderFormErrors.command" class="mt-1 text-xs text-destructive">{{ appenderFormErrors.command }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <Label for="ap-perm">Permission</Label>
                <select id="ap-perm" v-model="appenderForm.permission_level" class="input-border w-full">
                  <option value="everyone">Everyone</option>
                  <option value="subscriber">Subscriber</option>
                  <option value="vip">VIP</option>
                  <option value="moderator">Moderator</option>
                  <option value="broadcaster">Broadcaster only</option>
                </select>
              </div>
              <div>
                <Label for="ap-cooldown">Cooldown (s)</Label>
                <input id="ap-cooldown" class="input-border" v-model.number="appenderForm.cooldown_seconds" type="number" min="0" />
              </div>
            </div>
            <div>
              <Label for="ap-template">Value template</Label>
              <textarea
                id="ap-template"
                v-model="appenderForm.value_template"
                rows="2"
                class="input-border w-full font-mono text-sm"
                placeholder="[[[bot:from_user]]]"
              ></textarea>
              <p class="mt-1 text-xs text-muted-foreground">
                Bot Expression syntax. Pipe formatters work:
                <span class="font-mono">[[[bot:fired_at|date:HH:mm]]]</span>.
              </p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <Label for="ap-dedup">Dedup policy</Label>
                <select id="ap-dedup" v-model="appenderForm.dedup_policy" class="input-border w-full">
                  <option value="none">None (allow duplicates)</option>
                  <option value="per_chatter">Once per chatter</option>
                  <option value="per_chatter_per_stream">Once per chatter per stream</option>
                </select>
              </div>
              <div>
                <Label for="ap-max">Max size (blank = unlimited)</Label>
                <input id="ap-max" class="input-border" v-model.number="appenderForm.max_size" type="number" min="1" />
              </div>
            </div>
            <div>
              <Label for="ap-empty">Empty-args reply (optional)</Label>
              <textarea
                id="ap-empty"
                v-model="appenderForm.args_empty_reply"
                rows="2"
                class="input-border w-full text-sm"
                placeholder="@[[[bot:from_user]]] add something after !raffle"
              ></textarea>
              <p class="mt-1 text-xs text-muted-foreground">
                Spoken in chat when the template uses <span class="font-mono">[[[bot:args]]]</span> but the chatter didn't supply any. Leave blank for silent.
              </p>
            </div>
            <div class="flex items-center gap-2">
              <input id="ap-enabled" v-model="appenderForm.enabled" type="checkbox" />
              <Label for="ap-enabled" class="cursor-pointer">Enabled</Label>
            </div>
          </div>
          <DialogFooter>
            <button variant="outline" class="btn btn-secondary cursor-pointer" @click="appenderModalOpen = false">Cancel</button>
            <button class="btn btn-primary cursor-pointer" :disabled="savingAppender" @click="saveAppender">
              {{ savingAppender ? 'Saving…' : 'Save' }}
            </button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
