<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Eye, GitFork, ExternalLinkIcon, PencilIcon, MoreVertical, Clock, ChevronRight, LinkIcon } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import type { OverlayTemplate } from '@/types';

const props = defineProps<{
  template: OverlayTemplate;
  showOwner?: boolean;
  currentUserId?: number;
}>();

const detailsHref = computed(() => `/templates/${props.template.id}`);
const editHref = computed(() => `/templates/${props.template.id}/edit`);
const previewHref = computed(() => `/overlay/${props.template.slug}/public`);

// --- time helpers (no dependencies) ---
function formatDateShort(iso: string) {
  const d = new Date(iso);
  return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: '2-digit' }).format(d);
}
function formatDateFull(iso: string) {
  const d = new Date(iso);
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'full', timeStyle: 'short' }).format(d);
}
function relativeTimeFromNow(iso: string) {
  const now = Date.now();
  const then = new Date(iso).getTime();
  const diff = then - now; // negative if in past
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

// --- number helper ---
function compact(n: number) {
  return new Intl.NumberFormat(undefined, { notation: 'compact' }).format(n);
}

// --- menu utilities ---
async function copyLink(path: string) {
  try {
    await navigator.clipboard.writeText(location.origin + path);
  } catch {
    // optional: toast
  }
}

// expose to template
const fullUpdatedAt = computed(() => formatDateFull(props.template.updated_at));
const relativeUpdatedAt = computed(() => relativeTimeFromNow(props.template.updated_at));
const shortCreatedAt = computed(() => formatDateShort(props.template.created_at));
const shortUpdatedAt = computed(() => formatDateShort(props.template.updated_at));

const isOwnTemplate = computed(() => !!props.currentUserId && props.template.owner?.id === props.currentUserId);

const handleFork = () => {
  confirm('Are you sure you want to fork this template to your own account?') && router.post(`/templates/${props.template.id}/fork`);
};
</script>

<template>
  <Card class="group relative flex h-full flex-col">
    <!-- HEADER -->
    <CardHeader class="space-y-2">
      <div class="flex items-start justify-between gap-3">
        <CardTitle class="min-w-0 text-base leading-5">
          <Link :href="detailsHref" class="block truncate transition-colors hover:text-accent-foreground/80" :title="template.name">
            {{ template.name }}
          </Link>
        </CardTitle>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
          <!-- type -->
          <Badge variant="secondary" class="capitalize">
            {{ template.type }}
          </Badge>

          <!-- visibility -->
          <Badge :variant="template.is_public ? 'outline' : 'destructive'">
            {{ template.is_public ? 'Public' : 'Private' }}
          </Badge>
        </div>
      </div>

      <CardDescription v-if="template.description" class="line-clamp-2 text-sm">
        {{ template.description }}
      </CardDescription>
      <CardDescription v-else class="text-sm text-muted-foreground/70 italic"> No description. </CardDescription>
    </CardHeader>

    <!-- CONTENT -->
    <CardContent class="flex flex-1 flex-col justify-end gap-3">
      <!-- Optional owner row (marketplace / shared templates) -->
      <div v-if="showOwner && template.owner" class="flex items-center gap-2 rounded-md border bg-muted/30 px-2 py-1">
        <img v-if="template.owner.avatar" :src="template.owner.avatar" :alt="template.owner.name" class="h-6 w-6 rounded-full" />
        <div class="min-w-0">
          <div class="truncate text-sm"><span class="text-muted-foreground">by</span> {{ template.owner.name }}</div>
        </div>
      </div>

      <!-- FOOTER BAR -->
      <div class="flex items-center justify-between gap-2 border-t pt-5 mt-5">
        <!-- left: quiet metadata -->
        <div class="flex min-w-0 items-center gap-3 text-xs text-muted-foreground">
          <div class="flex items-center gap-1" :title="`${template.view_count} views`">
            <Eye class="h-3.5 w-3.5" />
            <span>{{ compact(template.view_count) }}</span>
          </div>

          <div class="flex items-center gap-1" :title="`${template.fork_count} forks`">
            <GitFork class="h-3.5 w-3.5" />
            <span>{{ compact(template.fork_count) }}</span>
          </div>

          <div class="hidden items-center gap-1 sm:flex" :title="fullUpdatedAt">
            <Clock class="h-3.5 w-3.5" />
            <span class="truncate">Updated {{ relativeUpdatedAt }}</span>
          </div>
        </div>

        <!-- right: actions -->
        <div class="flex items-center gap-2">
          <!-- PRIMARY -->
          <a v-if="isOwnTemplate" class="btn btn-sm btn-primary" :href="editHref" :title="`Edit ${template.name}`">
            Edit
            <PencilIcon class="ml-2 h-4 w-4" />
          </a>

          <a v-else class="btn btn-sm btn-primary" :href="detailsHref" :title="`View details of ${template.name}`">
            Details
            <ChevronRight class="ml-2 h-4 w-4" />
          </a>

          <!-- PREVIEW as icon button (less clutter) -->
          <a v-if="template.is_public" class="btn btn-sm btn-secondary px-2" :href="previewHref" target="_blank" :title="`Preview ${template.name}`">
            <ExternalLinkIcon class="h-4 w-4" />
          </a>

          <button v-else class="btn btn-sm btn-secondary px-2 opacity-50" disabled :title="`Private template: preview from Edit screen`">
            <ExternalLinkIcon class="h-4 w-4" />
          </button>

          <!-- KEBAB MENU: everything else -->
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button class="btn btn-sm btn-secondary px-2" :title="`More actions`">
                <MoreVertical class="h-4 w-4" />
              </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" class="w-52">
              <DropdownMenuItem as-child>
                <Link :href="detailsHref">
                  <Eye class="mr-2 h-4 w-4" />
                  View details
                </Link>
              </DropdownMenuItem>

              <DropdownMenuItem v-if="isOwnTemplate" as-child>
                <Link :href="editHref">
                  <PencilIcon class="mr-2 h-4 w-4" />
                  Edit
                </Link>
              </DropdownMenuItem>

              <DropdownMenuSeparator />

              <DropdownMenuItem v-if="!isOwnTemplate && template.is_public" @click="handleFork">
                <GitFork class="mr-2 h-4 w-4" />
                Fork template
              </DropdownMenuItem>

              <DropdownMenuItem @click="copyLink(detailsHref)">
                <LinkIcon class="mr-2 h-4 w-4" />
                Copy link
              </DropdownMenuItem>

              <DropdownMenuSeparator />

              <DropdownMenuItem class="text-muted-foreground">
                <div class="flex w-full flex-col gap-1 text-xs">
                  <div>Created: {{ shortCreatedAt }}</div>
                  <div>Updated: {{ shortUpdatedAt }}</div>
                </div>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
