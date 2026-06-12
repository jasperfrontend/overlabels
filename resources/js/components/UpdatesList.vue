<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Link, router } from '@inertiajs/vue3';
import { Check, Eye, LinkIcon, MoreVertical, PencilIcon, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import type { Update } from '@/types';

const props = defineProps<{
  updates: Update[];
  isAdmin?: boolean;
}>();

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

async function copyLink(path: string, id: number) {
  try {
    await navigator.clipboard.writeText(location.origin + path);
    copiedId.value = id;
    setTimeout(() => (copiedId.value = null), 2000);
  } catch {
    // ignore
  }
}

function handleDelete(u: Update) {
  if (confirm(`Delete "${u.title}"? This cannot be undone.`)) {
    router.delete(`/admin/updates/${u.id}`);
  }
}
</script>

<template>
  <div class="my-4 w-auto flex flex-col gap-2 rounded-sm">
    <div v-for="u in props.updates" :key="u.id" class="group text-sm">
      <Link :href="detailsHref(u)" class="flex flex-row justify-between p-4 gap-4 overlabels-background">
        <div class="flex-1 min-w-0">
          <Heading
            :title="u.title"
            title-class="text-md"
            :description="u.excerpt ?? undefined"
            description-class="text-xs"
          />
          <div v-if="u.tags && u.tags.length" class="mt-2 flex flex-wrap gap-1">
            <span
              v-for="tag in u.tags"
              :key="tag"
              class="inline-flex items-center rounded-sm bg-sidebar px-2 py-0.5 text-xs text-foreground"
            >
              {{ tag }}
            </span>
          </div>
          <div class="mt-1 text-xs text-muted-foreground">
            {{ formatDateShort(u.published_at) }}
          </div>
        </div>

        <div class="self-center text-right" :class="copiedId === u.id ? 'opacity-100' : 'opacity-20 group-hover:opacity-100'">
          <div class="flex items-center justify-end gap-1">
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <button class="btn btn-sm btn-secondary px-2 ml-2 md:ml-0 cursor-pointer" title="More actions">
                  <Check v-if="copiedId === u.id" class="h-3.5 w-3.5 text-green-500" />
                  <MoreVertical v-else class="h-3.5 w-3.5" />
                </button>
              </DropdownMenuTrigger>

              <DropdownMenuContent align="end" class="w-52">
                <DropdownMenuItem as-child>
                  <Link :href="detailsHref(u)" :title="`Read ${u.title}`" class="cursor-pointer">
                    <Eye class="mr-2 h-4 w-4" />
                    Read post
                  </Link>
                </DropdownMenuItem>

                <DropdownMenuItem @click="copyLink(detailsHref(u), u.id)" class="cursor-pointer">
                  <LinkIcon class="mr-2 h-4 w-4" />
                  Copy link
                </DropdownMenuItem>

                <template v-if="props.isAdmin">
                  <DropdownMenuSeparator />

                  <DropdownMenuItem as-child>
                    <Link :href="editHref(u)" class="cursor-pointer">
                      <PencilIcon class="mr-2 h-4 w-4" />
                      Edit
                    </Link>
                  </DropdownMenuItem>

                  <DropdownMenuItem class="text-destructive focus:text-destructive cursor-pointer" @click="handleDelete(u)">
                    <Trash2 class="mr-2 h-4 w-4" />
                    Delete
                  </DropdownMenuItem>
                </template>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </Link>
    </div>
  </div>
</template>
