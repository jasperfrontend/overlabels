<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { ListIcon, PlusIcon, CopyIcon, Trash2Icon, LockIcon, ChefHat } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface ListRow {
  id: number;
  slug: string;
  label: string | null;
  items: string[];
  min_items: number;
  max_items: number | null;
  user_editable: boolean;
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

  const items = newItemsText.value.split('\n');

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
  const items = draftItemsText.value.split('\n');

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
              class="cursor-pointer"
              :disabled="!isDirty || saving || !!isActiveLocked"
              @click="saveActive"
            >
              {{ saving ? 'Saving…' : isDirty ? 'Save changes' : 'Saved' }}
            </Button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
