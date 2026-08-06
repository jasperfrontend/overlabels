<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Link, router } from '@inertiajs/vue3';
import { Check, Eye, LinkIcon, MoreVertical, PencilIcon, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CollectionList from '@/components/CollectionList.vue';
import type { Update } from '@/types';

const props = withDefaults(
  defineProps<{
    updates: Update[];
    isAdmin?: boolean;
    /** Shown when there are no updates. */
    emptyMessage?: string;
  }>(),
  { emptyMessage: 'No updates yet.' },
);

function detailsHref(u: Update) {
  return `/updates/${u.slug}`;
}
function editHref(u: Update) {
  return `/admin/updates/${u.id}/edit`;
}

function formatDateShort(iso: string) {
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  }).format(new Date(iso));
}

const copiedId = ref<number | null>(null);

async function copyLink(u: Update) {
  try {
    await navigator.clipboard.writeText(location.origin + detailsHref(u));
    copiedId.value = u.id;
    setTimeout(() => (copiedId.value = null), 2000);
  } catch {
    // Clipboard denied - nothing useful to say, and the link is on screen.
  }
}

function handleDelete(u: Update) {
  if (confirm(`Delete "${u.title}"? This cannot be undone.`)) {
    router.delete(`/admin/updates/${u.id}`);
  }
}
</script>

<template>
  <CollectionList
    :items="props.updates"
    :item-key="(u: Update) => u.id"
    :href="detailsHref"
    :label="(u: Update) => u.title"
    class="my-4"
    :empty-message="props.emptyMessage"
  >
    <template #item="{ item: u }">
      <div class="flex flex-col gap-1">
        <span class="font-medium">{{ u.title }}</span>

        <span v-if="u.excerpt" class="text-xs">{{ u.excerpt }}</span>

        <div v-if="u.tags && u.tags.length" class="mt-1 flex flex-wrap gap-1">
          <span
            v-for="tag in u.tags"
            :key="tag"
            class="inline-flex items-center rounded-sm bg-sidebar px-2 py-0.5 text-xs text-foreground"
          >
            {{ tag }}
          </span>
        </div>

        <div class="mt-1 text-xs text-muted-foreground">{{ formatDateShort(u.published_at) }}</div>
      </div>
    </template>

    <template #actions="{ item: u }">
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button class="btn btn-sm btn-secondary cursor-pointer px-2" title="More actions">
            <Check v-if="copiedId === u.id" class="h-3.5 w-3.5 text-green-500" />
            <MoreVertical v-else class="h-3.5 w-3.5" />
          </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-52">
          <DropdownMenuItem as-child>
            <Link :href="detailsHref(u)" :title="`Read ${u.title}`" class="cursor-pointer">
              <Eye class="mr-2 h-4 w-4" />Read post
            </Link>
          </DropdownMenuItem>

          <DropdownMenuItem class="cursor-pointer" @click="copyLink(u)">
            <LinkIcon class="mr-2 h-4 w-4" />Copy link
          </DropdownMenuItem>

          <template v-if="props.isAdmin">
            <DropdownMenuSeparator />

            <DropdownMenuItem as-child>
              <Link :href="editHref(u)" class="cursor-pointer"><PencilIcon class="mr-2 h-4 w-4" />Edit</Link>
            </DropdownMenuItem>

            <DropdownMenuItem class="cursor-pointer text-destructive focus:text-destructive" @click="handleDelete(u)">
              <Trash2 class="mr-2 h-4 w-4" />Delete
            </DropdownMenuItem>
          </template>
        </DropdownMenuContent>
      </DropdownMenu>
    </template>
  </CollectionList>
</template>
