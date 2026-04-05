<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ExternalLinkIcon, PencilIcon, MoreVertical, ChevronRight, LinkIcon, Trash2, GitFork, Eye } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { useEventColors, EVENT_TYPE_LABELS } from '@/composables/useEventColors';
import type { OverlayTemplate } from '@/types';

const props = defineProps<{
  templates: OverlayTemplate[];
  showOwner?: boolean;
  showEvent?: boolean;
  currentUserId?: number;
}>();

const { eventTypeDotClass, eventTypeHoverBorderClass } = useEventColors();

/** Returns { eventType, source? } from the first Twitch or external mapping. */
function firstEvent(t: OverlayTemplate): { eventType: string; source?: string } | null {
  const twitch = t.event_mappings?.[0];
  if (twitch) return { eventType: twitch.event_type };

  const ext = t.external_event_mappings?.[0];
  if (ext) return { eventType: ext.event_type, source: ext.service };

  return null;
}

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

async function copyLink(path: string) {
  try {
    await navigator.clipboard.writeText(location.origin + path);
  } catch {
    // optional: toast
  }
}

function handleFork(t: OverlayTemplate) {
  if (confirm('Copy this template to your own account?')) {
    router.post(`/templates/${t.id}/fork`);
  }
}

function canDelete(t: OverlayTemplate) {
  return isOwn(t) && !t.kits_exists;
}

function handleDelete(t: OverlayTemplate) {
  if (!canDelete(t)) return;
  if (confirm(`Delete "${t.name}"? This cannot be undone.`)) {
    const returnUrl = window.location.pathname + window.location.search;
    router.delete(`/templates/${t.id}`, {
      onSuccess: () => router.visit(returnUrl),
    });
  }
}

function eventLabel(ev: { eventType: string; source?: string }): string {
  if (ev.source) {
    const serviceTypes = {
      kofi: { donation: 'Ko-fi Donation', subscription: 'Ko-fi Subscription', shop_order: 'Ko-fi Shop Order', commission: 'Ko-fi Commission' },
      streamlabs: { donation: 'StreamLabs Donation' },
      gpslogger: { location_update: 'GPS Location Update' },
    } as Record<string, Record<string, string>>;
    return serviceTypes[ev.source]?.[ev.eventType] ?? `${ev.source}: ${ev.eventType}`;
  }
  return EVENT_TYPE_LABELS[ev.eventType] ?? ev.eventType;
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div
      v-for="t in templates"
      :key="t.id"
      :class="[
        'group flex items-start justify-between gap-3 rounded-sm border ' +
        'border-violet-300/30 bg-sidebar-accent hover:bg-background p-3 ' +
        'transition-all ease-in-out cursor-pointer hover:border-l-3 ' +
        'active:bg-violet-400/20 dark:active:bg-violet-600/30 duration-100',
        showEvent && firstEvent(t)
          ? eventTypeHoverBorderClass(firstEvent(t)!.eventType, firstEvent(t)!.source)
          : 'hover:border-l-violet-500',
      ]"
      role="button"
      tabindex="0"
      @click="router.visit(detailsHref(t))"
      @keydown.enter.prevent="router.visit(detailsHref(t))"
      @keydown.space.prevent="router.visit(detailsHref(t))"
    >
      <!-- Left side -->
      <div class="flex min-w-0 flex-1 flex-col gap-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
          <div
            v-if="showEvent && firstEvent(t)"
            class="h-2 w-2 shrink-0 rounded-full"
            :class="eventTypeDotClass(firstEvent(t)!.eventType, firstEvent(t)!.source)"
          ></div>
          <span class="font-medium">{{ t.name }}</span>
          <Badge v-if="!t.is_public" variant="destructive" class="text-xs">Private</Badge>
        </div>

        <div
          v-if="showEvent && firstEvent(t)"
          class="text-xs text-muted-foreground/60"
          :class="firstEvent(t) ? 'pl-4' : ''"
        >
          {{ eventLabel(firstEvent(t)!) }}
        </div>
      </div>

      <!-- Right side: actions -->
      <div class="flex shrink-0 items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100" @click.stop @keydown.stop>
        <a
          v-if="isOwn(t)"
          class="btn btn-sm btn-primary"
          :href="editHref(t)"
          :title="`Edit ${t.name}`"
        >
          <PencilIcon class="h-3.5 w-3.5" />
        </a>
        <a
          v-else
          class="btn btn-sm btn-primary"
          :href="detailsHref(t)"
          :title="`View ${t.name}`"
        >
          <ChevronRight class="h-3.5 w-3.5" />
        </a>

        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <button class="btn btn-sm btn-secondary px-2" title="More actions">
              <MoreVertical class="h-3.5 w-3.5" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuItem as-child>
              <Link :href="detailsHref(t)"><Eye class="mr-2 h-4 w-4" />View details</Link>
            </DropdownMenuItem>
            <DropdownMenuItem v-if="isOwn(t)" as-child>
              <Link :href="editHref(t)"><PencilIcon class="mr-2 h-4 w-4" />Edit</Link>
            </DropdownMenuItem>
            <DropdownMenuItem v-if="t.is_public" as-child>
              <a :href="previewHref(t)" target="_blank"><ExternalLinkIcon class="mr-2 h-4 w-4" />Preview</a>
            </DropdownMenuItem>

            <DropdownMenuItem v-if="canDelete(t)" class="text-destructive focus:text-destructive" @click="handleDelete(t)">
              <Trash2 class="mr-2 h-4 w-4" />Delete
            </DropdownMenuItem>
            <DropdownMenuItem v-else-if="isOwn(t)" disabled class="text-muted-foreground text-xs">
              Part of a kit - cannot delete
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem v-if="!isOwn(t) && t.is_public" @click="handleFork(t)">
              <GitFork class="mr-2 h-4 w-4" />Copy template
            </DropdownMenuItem>
            <DropdownMenuItem @click="copyLink(detailsHref(t))">
              <LinkIcon class="mr-2 h-4 w-4" />Copy link
            </DropdownMenuItem>

            <template v-if="showOwner && t.owner">
              <DropdownMenuSeparator />
              <DropdownMenuItem class="text-muted-foreground">
                <div class="flex items-center gap-2 text-xs">
                  <img v-if="t.owner.avatar" :src="t.owner.avatar" :alt="t.owner.name" class="h-4 w-4 rounded-full" />
                  {{ t.owner.name }}
                </div>
              </DropdownMenuItem>
            </template>

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
  </div>
</template>
