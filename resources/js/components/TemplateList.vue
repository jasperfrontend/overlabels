<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Link, router } from '@inertiajs/vue3';
import { ChevronRight, ExternalLinkIcon, Eye, GitFork, LinkIcon, MoreVertical, PencilIcon, Trash2 } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';

interface EventMapping {
  event_type: string;
}

interface Template {
  id: number;
  slug: string;
  name: string;
  description: string | undefined;
  type: 'static' | 'alert';
  is_public: boolean;
  view_count: number;
  fork_count: number;
  owner?: {
    id: number;
    name: string;
    avatar?: string;
  };
  event_mappings?: EventMapping[];
  created_at: string;
  updated_at: string;
}

const props = defineProps<{
  templates: Template[];
  showOwner?: boolean;
  showEvent?: boolean;
  currentUserId?: number;
}>();

const eventTypeColors: Record<string, string> = {
  'channel.follow': 'bg-green-500',
  'channel.subscribe': 'bg-purple-500',
  'channel.subscription.gift': 'bg-pink-500',
  'channel.subscription.message': 'bg-indigo-500',
  'channel.cheer': 'bg-yellow-500',
  'channel.raid': 'bg-red-500',
  'channel.channel_points_custom_reward_redemption.add': 'bg-cyan-500',
  'stream.online': 'bg-green-400',
  'stream.offline': 'bg-red-400',
};

const eventTypeLabels: Record<string, string> = {
  'channel.follow': 'Follow',
  'channel.subscribe': 'Subscribe',
  'channel.subscription.gift': 'Gift Sub',
  'channel.subscription.message': 'Re-sub',
  'channel.cheer': 'Cheer',
  'channel.raid': 'Raid',
  'channel.channel_points_custom_reward_redemption.add': 'Points',
  'stream.online': 'Online',
  'stream.offline': 'Offline',
};

function firstEventMapping(t: Template) {
  return t.event_mappings?.[0] ?? null;
}

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

function handleDelete(t: Template) {
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
        <Heading :title="t.name" title-class="text-md" :description="t.description" />

        <!-- Actions -->
        <div class="self-center text-right opacity-20 group-hover:opacity-100">
          <div class="flex items-center justify-end gap-1">
            <!-- Primary action -->
            <a v-if="isOwn(t)" class="btn btn-sm btn-primary" :href="editHref(t)" :title="`Edit ${t.name}`">
              <PencilIcon class="h-3.5 w-3.5" />
            </a>
            <a v-else class="btn btn-sm btn-primary" :href="detailsHref(t)" :title="`View details of ${t.name}`">
              <ChevronRight class="h-3.5 w-3.5" />
            </a>

            <!-- Preview -->
            <a v-if="t.is_public" class="btn btn-sm btn-secondary px-2" :href="previewHref(t)" target="_blank" :title="`Preview ${t.name}`">
              <ExternalLinkIcon class="h-3.5 w-3.5" />
            </a>
            <button v-else class="btn btn-sm btn-secondary px-2 opacity-50" disabled :title="`Private template: preview from Edit screen`">
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

                <DropdownMenuItem v-if="isOwn(t)" class="text-destructive focus:text-destructive" @click="handleDelete(t)">
                  <Trash2 class="mr-2 h-4 w-4" />
                  Delete
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem v-if="!isOwn(t) && t.is_public" @click="handleFork(t)">
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
        </div>
      </Link>
    </div>
  </div>
</template>
