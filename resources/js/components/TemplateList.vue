<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Link, router } from '@inertiajs/vue3';
import { AppWindow, Check, ExternalLinkIcon, Eye, GitFork, LinkIcon, MoreVertical, PencilIcon, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import type { OverlayTemplate } from '@/types';

const props = defineProps<{
  templates: OverlayTemplate[];
  showOwner?: boolean;
  showEvent?: boolean;
  currentUserId?: number;
}>();

// --- helpers ---

function detailsHref(t: OverlayTemplate) {
  return `/templates/${t.id}`;
}
function editHref(t: OverlayTemplate) {
  return `/templates/${t.id}/edit`;
}
function previewHref(t: OverlayTemplate) {
  return `/overlay/${t.slug}/public`;
}

function isOwn(t: OverlayTemplate) {
  return !!props.currentUserId && t.owner?.id === props.currentUserId;
}

function formatDateShort(iso: string) {
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  }).format(new Date(iso));
}

function formatDateFull(iso: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'full',
    timeStyle: 'short',
  }).format(new Date(iso));
}

function relativeTime(iso: string) {
  const diff = new Date(iso).getTime() - Date.now();
  const abs = Math.abs(diff);
  const minute = 60_000;
  const hour = 60 * minute;
  const day = 24 * hour;
  const week = 7 * day;
  const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
  if (abs < hour) return rtf.format(Math.round(diff / minute), 'minute');
  if (abs < day) return rtf.format(Math.round(diff / hour), 'hour');
  if (abs < week) return rtf.format(Math.round(diff / day), 'day');
  return rtf.format(Math.round(diff / week), 'week');
}

function compact(n: number) {
  return new Intl.NumberFormat(undefined, { notation: 'compact' }).format(n);
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

function handleFork(t: OverlayTemplate) {
  if (confirm('Are you sure you want to fork this template to your own account?')) {
    router.post(`/templates/${t.id}/fork`);
  }
}

function handleDelete(t: OverlayTemplate) {
  if (confirm(`Delete "${t.name}"? This cannot be undone.`)) {
    const returnUrl = window.location.pathname + window.location.search;
    router.delete(`/templates/${t.id}`, {
      onSuccess: () => router.visit(returnUrl),
    });
  }
}
</script>

<template>
  <div class="my-4 w-auto rounded-sm border bg-background">
    <div v-for="t in templates" :key="t.id" class="group text-sm">
      <Link
        :href="detailsHref(t)"
        class="flex flex-row justify-between p-4 transition-colors hover:bg-sidebar-accent"
        :class="{ 'rounded-t-sm': templates.indexOf(t) === 0 }"
      >
        <Heading :title="t.name" title-class="text-md" :description="t.description ?? undefined" />

        <!-- Actions -->
        <div class="self-center text-right" :class="copiedId === t.id ? 'opacity-100' : 'opacity-20 group-hover:opacity-100'">
          <div class="flex items-center justify-end gap-1">

            <!-- Kebab menu -->
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <button class="btn btn-sm btn-secondary px-2 ml-2 md:ml-0 cursor-pointer" title="More actions">
                  <Check v-if="copiedId === t.id" class="h-3.5 w-3.5 text-green-500" />
                  <MoreVertical v-else class="h-3.5 w-3.5" />
                </button>
              </DropdownMenuTrigger>

              <DropdownMenuContent align="end" class="w-52">

                <DropdownMenuItem as-child>
                  <Link :href="detailsHref(t)" :title="`View details of ${t.name}`" class="cursor-pointer">
                    <Eye class="mr-2 h-4 w-4" />
                    Details
                  </Link>
                </DropdownMenuItem>

                <DropdownMenuItem as-child>
                  <Link
                    v-if="t.is_public"
                    :href="previewHref(t)"
                    target="_blank"
                    :title="`Preview ${t.name}`"
                    class="cursor-pointer"
                  >
                    <AppWindow class="mr-2 h-4 w-4" />
                    Preview (inline)
                  </Link>
                </DropdownMenuItem>

                <DropdownMenuItem as-child>
                  <a
                    v-if="t.is_public"
                    :href="previewHref(t)"
                    target="_blank"
                    :title="`Preview ${t.name}`"
                    class="cursor-pointer"
                  >
                    <ExternalLinkIcon class="mr-2 h-4 w-4" />
                    Preview (new tab)
                  </a>
                </DropdownMenuItem>

                <DropdownMenuItem v-if="isOwn(t)" as-child>
                  <Link :href="editHref(t)" class="cursor-pointer">
                    <PencilIcon class="mr-2 h-4 w-4" />
                    Edit
                  </Link>
                </DropdownMenuItem>

                <DropdownMenuItem v-if="isOwn(t)" class="text-destructive focus:text-destructive cursor-pointer" @click="handleDelete(t)">
                  <Trash2 class="mr-2 h-4 w-4" />
                  Delete
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem v-if="!isOwn(t) && t.is_public" @click="handleFork(t)" class="cursor-pointer">
                  <GitFork class="mr-2 h-4 w-4" />
                  Fork template
                </DropdownMenuItem>

                <DropdownMenuItem @click="copyLink(detailsHref(t), t.id)" class="cursor-pointer">
                  <LinkIcon class="mr-2 h-4 w-4" />
                  Copy link
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem class="text-muted-foreground">
                  <div class="flex w-full flex-col gap-1 text-xs">
                    <div>Created: {{ formatDateShort(t.created_at) }}</div>
                    <div>Updated: {{ formatDateShort(t.updated_at) }}</div>
                  </div>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </Link>
    </div>
  </div>
</template>
