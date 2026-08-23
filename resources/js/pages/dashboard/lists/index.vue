<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import CollectionList from '@/components/CollectionList.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearchInput from '@/components/FilterSearchInput.vue';
import RekaToast from '@/components/RekaToast.vue';
import { useSearchFilters } from '@/composables/useSearchFilters';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { ListIcon, PlusIcon, LockIcon, ChefHat, List, SearchIcon } from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';
import { listItemValues, type ListItem } from '@/utils/listItems';

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
  filters?: { search?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Lists', href: '/dashboard/lists' },
];

const lists = ref<ListRow[]>([...props.lists]);
watch(
  () => props.lists,
  (next) => {
    lists.value = [...next];
  },
  { deep: true },
);

const toastMessage = ref<string | null>(null);
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');

// ──────────────────────────────────────────────────────────────────────────────
// Search - the same URL-backed filter bar as /templates and /dashboard/recents.
// The server matches slug, label, AND item contents; the query string is the
// state, so a filtered view deep-links and survives back/forward.
// ──────────────────────────────────────────────────────────────────────────────

function normalizeListFilters(input?: { search?: string }) {
  // Only accept string values - a malformed query param could arrive as an
  // array, and the server treats anything non-string as no search anyway.
  return { search: typeof input?.search === 'string' ? input.search : '' };
}

function buildQuery(): Record<string, string> {
  const params: Record<string, string> = {};
  if (filters.value.search) params.search = filters.value.search;
  return params;
}

const { filters, debounceSearch } = useSearchFilters({
  serverFilters: () => props.filters,
  normalize: normalizeListFilters,
  apply: () =>
    router.get(route('lists.index'), buildQuery(), {
      preserveState: true,
      preserveScroll: true,
      // Search-as-you-type would otherwise leave a history entry per keystroke
      // batch, so going back walks you through `t`, `te`, `tes` before leaving.
      replace: true,
      only: ['lists', 'filters'],
    }),
});

// The search the current rows were filtered by - the server's echo, not the
// box's live value, so hints and empty states stay in step with the rows on
// screen while a newer query is still in flight.
const appliedSearch = computed(() => normalizeListFilters(props.filters).search.trim().toLowerCase());

// Lowercased item that matched the query, if the match was on contents only
// (not slug/label). Surfaced as a "matches: ..." hint so a content hit is
// visible even though the matching item isn't otherwise shown on the row.
function contentMatch(list: ListRow, q: string): string | null {
  return list.items.find((item) => item.toLowerCase().includes(q)) ?? null;
}

/** A list plus, when the query only matched its contents, the item that hit. */
interface ListSearchResult {
  list: ListRow;
  hint: string | null;
}

// Rows arrive already filtered; what's left to compute here is the hint.
const filteredLists = computed<ListSearchResult[]>(() => {
  const q = appliedSearch.value;
  if (!q) return lists.value.map((list) => ({ list, hint: null }));

  return lists.value.map((list) => {
    const inSlug = list.slug.toLowerCase().includes(q);
    const inLabel = (list.label ?? '').toLowerCase().includes(q);
    // Only show the content hint when the match is purely on contents.
    return { list, hint: inSlug || inLabel ? null : contentMatch(list, q) };
  });
});

const rowKey = ({ list }: ListSearchResult) => list.id;
const rowHref = ({ list }: ListSearchResult) => route('lists.show', list.slug);
const rowLabel = ({ list }: ListSearchResult) => list.label || list.slug;
const rowClass = ({ list }: ListSearchResult) => (list.disabled_at !== null ? 'collection-row-destructive' : undefined);

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
  if (lists.value.some((l) => l.slug === s)) return 'You already have a list with this slug.';
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

  router.post(
    route('lists.store'),
    {
      slug: newSlug.value,
      label: newLabel.value || null,
      items,
    },
    {
      onError: (errors) => {
        slugError.value = errors.slug ?? 'Failed to create list.';
      },
    },
  );
}

// ──────────────────────────────────────────────────────────────────────────────
// Live updates - keep the rows fresh as chat appenders (or other tabs) mutate,
// create, or delete lists.
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
  const idx = lists.value.findIndex((l) => l.slug === payload.slug);
  if (idx === -1) {
    // Unknown slug - a new list (created in another tab). Refresh just the
    // lists prop so it appears in the collection.
    router.reload({ only: ['lists'] });
    return;
  }
  lists.value[idx] = {
    ...lists.value[idx],
    // The broadcast carries item objects; the collection view (and its
    // content search) work in value strings, matching the Inertia payload.
    items: listItemValues(payload.items ?? []),
    updated_at: payload.updated_at,
    expires_at: payload.expires_at !== undefined ? payload.expires_at : lists.value[idx].expires_at,
    disabled_at: payload.disabled_at !== undefined ? payload.disabled_at : lists.value[idx].disabled_at,
  };
}

function applyListDeleted(slug: string) {
  const idx = lists.value.findIndex((l) => l.slug === slug);
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
  } catch {
    /* ignore */
  }
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
          <List class="mr-2 h-6 w-6" />
          <Heading title="Lists" />
        </div>

        <button class="btn btn-primary shrink-0 cursor-pointer" @click="showCreate = !showCreate">
          <PlusIcon class="h-4 w-4" />
          <span class="ml-1.5">New list</span>
        </button>
      </div>

      <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @close="toastMessage = null" />

      <!-- Filters Section - page-wide above the two-column split, matching the other dashboard routes -->
      <FilterBar v-if="lists.length > 0 || appliedSearch">
        <FilterSearchInput v-model="filters.search" placeholder="Search lists by name, slug, or contents..." @search="debounceSearch" />
      </FilterBar>

      <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_356px]">
        <div class="min-w-0 space-y-4">
          <!-- Create-list modal/card -->
          <Card v-if="showCreate" class="border-sidebar-border">
            <CardContent class="space-y-6">
              <div class="grid gap-3 md:grid-cols-2">
                <div>
                  <Label for="new-slug">Slug</Label>
                  <input id="new-slug" v-model="newSlug" placeholder="pizza_toppings" class="input-border cursor-text font-mono" />
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

          <!-- Empty state: no lists at all. With a search applied, an empty result
           means "no match", which the collection's empty slot handles below. -->
          <EmptyState
            v-if="lists.length === 0 && !appliedSearch"
            dashed
            :icon="ChefHat"
            title="No lists yet."
            message="Create one above to use it across your overlays."
          />

          <CollectionList v-else :items="filteredLists" :item-key="rowKey" :href="rowHref" :label="rowLabel" :row-class="rowClass">
            <template #item="{ item: { list, hint } }">
              <div class="flex flex-wrap items-center gap-1.5">
                <ListIcon class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                <span class="truncate font-medium text-foreground">{{ list.label || list.slug }}</span>
                <LockIcon v-if="!list.user_editable && list.recipe_instance_id !== null" class="h-3 w-3 text-muted-foreground" />
                <Badge v-if="list.recipe" variant="secondary" class="text-[10px]">{{ list.recipe.name }}</Badge>
              </div>
              <div class="mt-0.5 font-mono text-[11px] text-muted-foreground">{{ list.slug }}</div>
              <div class="mt-0.5 text-[11px] text-muted-foreground">
                {{ list.items.length }} item{{ list.items.length === 1 ? '' : 's' }}
                <span v-if="list.updated_at">• updated {{ lastUpdated(list.updated_at) }}</span>
                <!-- Text twin of the red accent bar, so disabled isn't color-only -->
                <span v-if="list.disabled_at !== null">• <span class="collection-row-state">disabled</span></span>
              </div>
              <div v-if="hint" class="mt-0.5 truncate text-[11px] text-muted-foreground">
                matches: <span class="font-mono text-foreground">{{ hint }}</span>
              </div>
            </template>

            <!-- Empty state: search matched nothing. The no-lists-at-all case is
               handled above, before the search box renders. -->
            <template #empty>
              <EmptyState dashed :icon="SearchIcon" :message="`No lists match &quot;${filters.search}&quot;. Try a different name, slug, or item.`" />
            </template>
          </CollectionList>
        </div>

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
                      <input id="meta-cmd" v-model="metaForm.command" class="input-border h-8 w-32 font-mono" />
                    </div>
                  </div>

                  <button class="btn btn-primary h-8 cursor-pointer" :disabled="savingMeta" @click="saveMeta">
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
    </div>
  </AppLayout>
</template>
