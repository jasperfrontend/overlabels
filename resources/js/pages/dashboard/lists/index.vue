<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { ListIcon, PlusIcon, CopyIcon, Trash2Icon, LockIcon, ChefHat, MessageSquareIcon, PencilIcon, PowerIcon, PowerOffIcon } from 'lucide-vue-next';
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
}

function applyListUpdated(payload: ListUpdatedPayload) {
  const idx = lists.value.findIndex(l => l.slug === payload.slug);
  if (idx === -1) return;
  lists.value[idx] = {
    ...lists.value[idx],
    items: payload.items ?? [],
    updated_at: payload.updated_at,
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
</script>

<template>
  <Head title="Lists" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
      <div class="flex items-start justify-between gap-3">
        <Heading
          title="Lists"
          description="Reusable lists you can reference from any overlay via [[[c:list:<slug>]]] or loop with [[[foreach:c:list:<slug> as item]]]. Lists are lists - we preserve exactly what you type, empties and duplicates included."
        />
        <Button class="cursor-pointer shrink-0" @click="showCreate = !showCreate">
          <PlusIcon class="h-4 w-4" />
          <span class="ml-1.5">New list</span>
        </Button>
      </div>

      <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @close="toastMessage = null" />

      <Card v-if="showCreate" class="border-sidebar">
        <CardContent class="space-y-3 p-4">
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <Label for="new-slug">Slug</Label>
              <Input
                id="new-slug"
                v-model="newSlug"
                placeholder="pizza_toppings"
                class="cursor-text font-mono"
              />
              <p v-if="slugError" class="mt-1 text-xs text-destructive">{{ slugError }}</p>
              <p v-else class="mt-1 text-xs text-muted-foreground">
                Used in tags: <span class="font-mono">[[[c:list:{{ newSlug || 'your_slug' }}]]]</span>
              </p>
            </div>
            <div>
              <Label for="new-label">Label (optional)</Label>
              <Input id="new-label" v-model="newLabel" placeholder="Pizza toppings" />
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
          <div class="flex justify-end gap-2">
            <Button variant="outline" class="cursor-pointer" @click="showCreate = false">Cancel</Button>
            <Button class="cursor-pointer" @click="createList">Create</Button>
          </div>
        </CardContent>
      </Card>

      <div v-if="lists.length === 0" class="rounded-lg border border-dashed p-10 text-center">
        <ChefHat class="mx-auto h-10 w-10 text-muted-foreground" />
        <p class="mt-4 text-foreground">No lists yet.</p>
        <p class="mt-1 text-sm text-muted-foreground">
          Create one above to use it across your overlays.
        </p>
      </div>

      <div v-else class="grid gap-4 md:grid-cols-[260px,1fr]">
        <!-- Left: list of lists -->
        <div class="space-y-1">
          <button
            v-for="list in lists"
            :key="list.id"
            type="button"
            class="flex w-full cursor-pointer items-start justify-between gap-2 rounded-md border border-sidebar px-3 py-2 text-left text-sm transition hover:bg-sidebar-accent"
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
        <div v-if="activeList" class="space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-sidebar p-3">
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

          <div>
            <Label for="active-label">Label</Label>
            <Input
              id="active-label"
              v-model="draftLabel"
              :disabled="!!isActiveLocked"
              placeholder="(optional)"
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
              <Button
                variant="ghost"
                class="cursor-pointer text-destructive hover:text-destructive"
                :disabled="!!isActiveLocked || activeList.recipe_instance_id !== null"
                :title="activeList.recipe_instance_id !== null ? 'Delete the recipe instance to remove this list.' : 'Delete this list permanently.'"
                @click="deleteActive"
              >
                <Trash2Icon class="h-4 w-4" />
                <span class="ml-1.5">Delete list</span>
              </Button>
              <Button
                variant="ghost"
                class="cursor-pointer"
                :title="activeList.disabled_at !== null ? 'Re-enable: chat appenders can write again.' : 'Disable: chat appenders silently no-op; existing items stay visible.'"
                @click="toggleDisabled"
              >
                <component :is="activeList.disabled_at !== null ? PowerIcon : PowerOffIcon" class="h-4 w-4" />
                <span class="ml-1.5">{{ activeList.disabled_at !== null ? 'Enable list' : 'Disable list' }}</span>
              </Button>
            </div>
            <Button
              class="cursor-pointer"
              :disabled="!isDirty || saving || !!isActiveLocked"
              @click="saveActive"
            >
              {{ saving ? 'Saving…' : isDirty ? 'Save changes' : 'Saved' }}
            </Button>
          </div>

          <!-- Append commands section -->
          <div class="mt-6 rounded-md border border-sidebar p-4">
            <div class="mb-3 flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-foreground">Append commands</h3>
                <p class="mt-0.5 text-xs text-muted-foreground">
                  Chat commands that append to this list when fired. Use Bot Expression syntax like
                  <span class="font-mono">[[[bot:from_user]]]</span> in the value template.
                </p>
              </div>
              <Button size="sm" class="cursor-pointer shrink-0" @click="openAppenderAdd">
                <PlusIcon class="h-3.5 w-3.5" />
                <span class="ml-1">Add command</span>
              </Button>
            </div>

            <div v-if="appendersLoading" class="text-sm text-muted-foreground">Loading…</div>
            <div v-else-if="appenders.length === 0" class="rounded border border-dashed py-6 text-center text-sm text-muted-foreground">
              No append commands yet. Add one to let chatters grow this list.
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="a in appenders"
                :key="a.id"
                class="flex flex-wrap items-start justify-between gap-2 rounded border border-sidebar p-2.5"
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
          <div class="space-y-3">
            <div>
              <Label for="ap-command">Command (no leading !)</Label>
              <Input id="ap-command" v-model="appenderForm.command" placeholder="raffle" class="font-mono" />
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
                <Input id="ap-cooldown" v-model.number="appenderForm.cooldown_seconds" type="number" min="0" />
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
                <Input id="ap-max" v-model.number="appenderForm.max_size" type="number" min="1" />
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
            <Button variant="outline" class="cursor-pointer" @click="appenderModalOpen = false">Cancel</Button>
            <Button class="cursor-pointer" :disabled="savingAppender" @click="saveAppender">
              {{ savingAppender ? 'Saving…' : 'Save' }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
