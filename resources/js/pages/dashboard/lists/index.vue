<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import {
  ListIcon,
  PlusIcon,
  LockIcon,
  ChefHat,
  List,
  PowerOffIcon,
  SearchIcon,
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
  chat_permissions: Record<string, string>;
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

const toastMessage = ref<string | null>(null);
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');

// ──────────────────────────────────────────────────────────────────────────────
// Search - client-side filter over slug, label, AND item contents. Lists are
// bounded per user and the index already loads full items, so no round-trip.
// ──────────────────────────────────────────────────────────────────────────────

const search = ref('');

// Lowercased item that matched the query, if the match was on contents only
// (not slug/label). Surfaced as a "matches: ..." hint so a content hit is
// visible even though the matching item isn't otherwise shown on the row.
function contentMatch(list: ListRow, q: string): string | null {
  return list.items.find(item => item.toLowerCase().includes(q)) ?? null;
}

const filteredLists = computed<{ list: ListRow; hint: string | null }[]>(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return lists.value.map(list => ({ list, hint: null }));

  const out: { list: ListRow; hint: string | null }[] = [];
  for (const list of lists.value) {
    const inSlug = list.slug.toLowerCase().includes(q);
    const inLabel = (list.label ?? '').toLowerCase().includes(q);
    const item = contentMatch(list, q);
    if (inSlug || inLabel || item) {
      // Only show the content hint when the match is purely on contents.
      out.push({ list, hint: inSlug || inLabel ? null : item });
    }
  }
  return out;
});

function lastUpdated(ts: number | null): string {
  if (!ts) return '';
  const delta = Math.max(0, Math.floor(Date.now() / 1000) - ts);
  if (delta < 60) return 'just now';
  if (delta < 3600) return `${Math.floor(delta / 60)}m ago`;
  if (delta < 86400) return `${Math.floor(delta / 3600)}h ago`;
  return `${Math.floor(delta / 86400)}d ago`;
}

// ──────────────────────────────────────────────────────────────────────────────
// Create-list form (modal). store() redirects to the new list's show page on
// success, so we don't refresh the rail here - Inertia navigates us there.
// ──────────────────────────────────────────────────────────────────────────────

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
  // shows up as a phantom blank first row when chat-appenders later add to the
  // list. Distinguishing "" from real content fixes that without touching the
  // "lists are lists" contract for any non-empty typed content.
  const items = newItemsText.value === '' ? [] : newItemsText.value.split('\n');

  router.post(route('lists.store'), {
    slug: newSlug.value,
    label: newLabel.value || null,
    items,
  }, {
    onError: (errors) => {
      slugError.value = errors.slug ?? 'Failed to create list.';
    },
  });
}

// ──────────────────────────────────────────────────────────────────────────────
// Live updates - keep the rows fresh as chat appenders (or other tabs) mutate,
// create, or delete lists.
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
    // Unknown slug - a new list (created in another tab). Refresh just the
    // lists prop so it appears in the collection.
    router.reload({ only: ['lists'] });
    return;
  }
  lists.value[idx] = {
    ...lists.value[idx],
    items: payload.items ?? [],
    updated_at: payload.updated_at,
    expires_at: payload.expires_at !== undefined ? payload.expires_at : lists.value[idx].expires_at,
    disabled_at: payload.disabled_at !== undefined ? payload.disabled_at : lists.value[idx].disabled_at,
  };
}

function applyListDeleted(slug: string) {
  const idx = lists.value.findIndex(l => l.slug === slug);
  if (idx === -1) return;
  lists.value.splice(idx, 1);
}

let echoChannel: any = null;
let echoChannelName: string | null = null;

// ──────────────────────────────────────────────────────────────────────────────
// Meta-command settings (!list <slug> <action>) - global, one per user.
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
    <div class="mx-auto w-full space-y-4 p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <List class="h-6 w-6 mr-2" />
          <Heading title="Lists" />
        </div>

        <button class="btn btn-primary cursor-pointer shrink-0" @click="showCreate = !showCreate">
          <PlusIcon class="h-4 w-4" />
          <span class="ml-1.5">New list</span>
        </button>
      </div>

      <p class="text-sm text-foreground">Reusable lists you can reference from any overlay via [[[c:list:&lt;slug&gt;]]] or loop with [[[foreach:c:list:&lt;slug&gt; as item]]]. Lists are lists - we preserve exactly what you type, empties and duplicates included.</p>
      <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @close="toastMessage = null" />

      <!-- Create-list modal/card -->
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

      <!-- Empty state: no lists at all -->
      <div v-if="lists.length === 0" class="border border-sidebar-border border-dashed p-10 text-center">
        <ChefHat class="mx-auto h-10 w-10 text-muted-foreground" />
        <p class="mt-4 text-foreground">No lists yet.</p>
        <p class="mt-1 text-sm text-muted-foreground">
          Create one above to use it across your overlays.
        </p>
      </div>

      <template v-else>
        <!-- Search box: filters by slug, label, and item contents -->
        <div class="relative">
          <SearchIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            v-model="search"
            type="text"
            placeholder="Search lists by name, slug, or contents..."
            class="input-border h-10 w-full pl-9"
          />
        </div>

        <!-- Compact rows -->
        <div v-if="filteredLists.length" class="space-y-1">
          <Link
            v-for="{ list, hint } in filteredLists"
            :key="list.id"
            :href="route('lists.show', list.slug)"
            class="flex cursor-pointer items-start justify-between gap-3 border border-sidebar-border p-3 transition hover:bg-sidebar-accent"
          >
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-1.5">
                <ListIcon class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                <span class="truncate font-medium text-foreground">{{ list.label || list.slug }}</span>
                <LockIcon v-if="!list.user_editable && list.recipe_instance_id !== null" class="h-3 w-3 text-muted-foreground" />
                <Badge v-if="list.disabled_at !== null" variant="destructive" class="text-[10px]">
                  <PowerOffIcon class="mr-1 h-2.5 w-2.5" />
                  Disabled
                </Badge>
                <Badge v-if="list.recipe" variant="secondary" class="text-[10px]">{{ list.recipe.name }}</Badge>
              </div>
              <div class="mt-0.5 font-mono text-[11px] text-muted-foreground">{{ list.slug }}</div>
              <div class="mt-0.5 text-[11px] text-muted-foreground">
                {{ list.items.length }} item{{ list.items.length === 1 ? '' : 's' }}
                <span v-if="list.updated_at">• updated {{ lastUpdated(list.updated_at) }}</span>
              </div>
              <div v-if="hint" class="mt-0.5 truncate text-[11px] text-muted-foreground">
                matches: <span class="font-mono text-foreground">{{ hint }}</span>
              </div>
            </div>
          </Link>
        </div>

        <!-- Empty state: search matched nothing -->
        <div v-else class="border border-sidebar-border border-dashed p-10 text-center">
          <SearchIcon class="mx-auto h-8 w-8 text-muted-foreground" />
          <p class="mt-3 text-foreground">No lists match "{{ search }}".</p>
          <p class="mt-1 text-sm text-muted-foreground">Try a different name, slug, or item.</p>
        </div>
      </template>

      <!-- Meta-command settings: opt into !list (mod+) for chat actions -->
      <Card class="border-sidebar-border bg-sidebar-accent">
        <CardContent>
          <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1 space-y-2">
              <div>
                <h3 class="text-sm font-semibold text-foreground">!list meta-command</h3>
                <p class="mt-0.5 text-xs text-muted-foreground">
                  By default, List actions live under <span class="text-foreground">!list</span>. If that doesn't work with your stream
                  configuration, you can set another command here. Applies to all your lists.
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

                <button class="btn h-8 btn-primary cursor-pointer" :disabled="savingMeta" @click="saveMeta">
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
    </div>
  </AppLayout>
</template>
