<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
  Eye,
  GitFork,
  ExternalLinkIcon,
  PencilIcon,
  MoreVertical,
  Clock,
  ChevronRight,
  LinkIcon,
} from 'lucide-vue-next';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';

interface Template {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  type: 'static' | 'alert';
  is_public: boolean;
  view_count: number;
  fork_count: number;
  owner?: {
    id: number;
    name: string;
    avatar?: string;
  };
  created_at: string;
  updated_at: string;
}

const props = defineProps<{
  templates: Template[];
  showOwner?: boolean;
  currentUserId?: number;
}>();

// --- helpers ---

function detailsHref(t: Template) {
  return `/templates/${t.id}`;
}
function editHref(t: Template) {
  return `/templates/${t.id}/edit`;
}
function previewHref(t: Template) {
  return `/overlay/${t.slug}/public`;
}

function isOwn(t: Template) {
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

async function copyLink(path: string) {
  try {
    await navigator.clipboard.writeText(location.origin + path);
  } catch {
    // optional: toast
  }
}

function handleFork(t: Template) {
  if (confirm('Are you sure you want to fork this template to your own account?')) {
    router.post(`/templates/${t.id}/fork`);
  }
}
</script>

<template>
  <Table>
    <TableHeader>
      <TableRow class="hover:bg-transparent">
        <TableHead>Name</TableHead>
        <TableHead class="hidden lg:table-cell">Description</TableHead>
        <TableHead class="w-[90px]">Type</TableHead>
        <TableHead class="w-[90px]">Visibility</TableHead>
        <TableHead v-if="showOwner" class="hidden md:table-cell">Owner</TableHead>
        <TableHead class="hidden w-[70px] text-right sm:table-cell">Views</TableHead>
        <TableHead class="hidden w-[70px] text-right sm:table-cell">Forks</TableHead>
        <TableHead class="hidden w-[130px] md:table-cell">Updated</TableHead>
        <TableHead class="w-[120px] text-right">Actions</TableHead>
      </TableRow>
    </TableHeader>

    <TableBody>
      <TableRow v-for="t in templates" :key="t.id">
        <!-- Name -->
        <TableCell class="font-medium">
          <Link
            :href="detailsHref(t)"
            class="transition-colors hover:text-accent-foreground/80"
          >
            {{ t.name }}
          </Link>
        </TableCell>

        <!-- Description -->
        <TableCell class="hidden max-w-[280px] lg:table-cell">
          <span v-if="t.description" class="line-clamp-1 text-muted-foreground" :title="t.description">
            {{ t.description }}
          </span>
          <span v-else class="text-muted-foreground/50 italic">None</span>
        </TableCell>

        <!-- Type -->
        <TableCell>
          <Badge variant="secondary" class="capitalize">{{ t.type }}</Badge>
        </TableCell>

        <!-- Visibility -->
        <TableCell>
          <Badge :variant="t.is_public ? 'outline' : 'destructive'">
            {{ t.is_public ? 'Public' : 'Private' }}
          </Badge>
        </TableCell>

        <!-- Owner -->
        <TableCell v-if="showOwner" class="hidden md:table-cell">
          <div v-if="t.owner" class="flex items-center gap-2">
            <img
              v-if="t.owner.avatar"
              :src="t.owner.avatar"
              :alt="t.owner.name"
              class="h-5 w-5 rounded-full"
            />
            <span class="truncate text-sm">{{ t.owner.name }}</span>
          </div>
        </TableCell>

        <!-- Views -->
        <TableCell class="hidden text-right tabular-nums sm:table-cell">
          <div class="flex items-center justify-end gap-1 text-muted-foreground" :title="`${t.view_count} views`">
            <Eye class="h-3.5 w-3.5" />
            <span>{{ compact(t.view_count) }}</span>
          </div>
        </TableCell>

        <!-- Forks -->
        <TableCell class="hidden text-right tabular-nums sm:table-cell">
          <div class="flex items-center justify-end gap-1 text-muted-foreground" :title="`${t.fork_count} forks`">
            <GitFork class="h-3.5 w-3.5" />
            <span>{{ compact(t.fork_count) }}</span>
          </div>
        </TableCell>

        <!-- Updated -->
        <TableCell class="hidden md:table-cell">
          <div
            class="flex items-center gap-1 text-xs text-muted-foreground"
            :title="formatDateFull(t.updated_at)"
          >
            <Clock class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ relativeTime(t.updated_at) }}</span>
          </div>
        </TableCell>

        <!-- Actions -->
        <TableCell class="text-right">
          <div class="flex items-center justify-end gap-1">
            <!-- Primary action -->
            <a
              v-if="isOwn(t)"
              class="btn btn-sm btn-primary"
              :href="editHref(t)"
              :title="`Edit ${t.name}`"
            >
              Edit
              <PencilIcon class="ml-1.5 h-3.5 w-3.5" />
            </a>
            <a
              v-else
              class="btn btn-sm btn-primary"
              :href="detailsHref(t)"
              :title="`View details of ${t.name}`"
            >
              Details
              <ChevronRight class="ml-1.5 h-3.5 w-3.5" />
            </a>

            <!-- Preview -->
            <a
              v-if="t.is_public"
              class="btn btn-sm btn-secondary px-2"
              :href="previewHref(t)"
              target="_blank"
              :title="`Preview ${t.name}`"
            >
              <ExternalLinkIcon class="h-3.5 w-3.5" />
            </a>
            <button
              v-else
              class="btn btn-sm btn-secondary px-2 opacity-50"
              disabled
              :title="`Private template: preview from Edit screen`"
            >
              <ExternalLinkIcon class="h-3.5 w-3.5" />
            </button>

            <!-- Kebab menu -->
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <button class="btn btn-sm btn-secondary px-2" title="More actions">
                  <MoreVertical class="h-3.5 w-3.5" />
                </button>
              </DropdownMenuTrigger>

              <DropdownMenuContent align="end" class="w-52">
                <DropdownMenuItem as-child>
                  <Link :href="detailsHref(t)">
                    <Eye class="mr-2 h-4 w-4" />
                    View details
                  </Link>
                </DropdownMenuItem>

                <DropdownMenuItem v-if="isOwn(t)" as-child>
                  <Link :href="editHref(t)">
                    <PencilIcon class="mr-2 h-4 w-4" />
                    Edit
                  </Link>
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem
                  v-if="!isOwn(t) && t.is_public"
                  @click="handleFork(t)"
                >
                  <GitFork class="mr-2 h-4 w-4" />
                  Fork template
                </DropdownMenuItem>

                <DropdownMenuItem @click="copyLink(detailsHref(t))">
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
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
</template>
