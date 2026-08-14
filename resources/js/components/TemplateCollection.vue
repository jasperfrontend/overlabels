<script setup lang="ts">
/**
 * A collection of overlay templates. Replaces TemplateTable (which rendered no
 * table) and TemplateList (which was a near-copy of it), so /templates and the
 * dashboard now share one row, one kebab menu and one set of behaviours.
 */
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Check, ChevronRight, ExternalLinkIcon, Eye, GitFork, LinkIcon, MoreVertical, PencilIcon, Trash2 } from '@lucide/vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import CollectionList from '@/components/CollectionList.vue';
import ProviderIcon from '@/components/ProviderIcon.vue';
import { useEventColors, EVENT_TYPE_LABELS } from '@/composables/useEventColors';
import { serviceLabel } from '@/utils/services';
import type { OverlayTemplate } from '@/types';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();
const props = defineProps<{
  templates: OverlayTemplate[];
  /** Adds the owner to the kebab menu. Used on the public /templates index. */
  showOwner?: boolean;
  /** Adds the bound event type, coloured by event, under the name. */
  showEvent?: boolean;
  currentUserId?: number;
  /** Shown when the collection is empty. */
  emptyMessage?: string;
}>();

const { eventTypeDotClass, eventTypeHoverBorderClass } = useEventColors();

/**
 * The first Twitch or external mapping.
 *
 * `source` is always set, because the icon and the colour both need to know a
 * Twitch binding is Twitch - without it, an event type absent from EVENT_STYLES
 * (a hype train, a goal) can't even fall back to Twitch purple.
 *
 * `service` is set for external bindings ONLY, and drives the label prefix.
 * Keep the two apart: reusing `source` there would turn "Follow" into
 * "Twitch Follow".
 */
function firstEvent(t: OverlayTemplate): { eventType: string; source: string; service?: string } | null {
  const twitch = t.event_mappings?.[0];
  if (twitch) return { eventType: twitch.event_type, source: 'twitch' };

  const ext = t.external_event_mappings?.[0];
  if (ext) return { eventType: ext.event_type, source: ext.service, service: ext.service };

  return null;
}

/**
 * "Ko-fi Donation", "Throne Gift", "Follow". Derived from SERVICE_LABELS rather
 * than a curated per-service map, which had gone stale at Ko-fi and Streamlabs
 * and left the other three donation services reading "bmac: donation".
 */
function eventLabel(ev: { eventType: string; service?: string }): string {
  if (!ev.service) return EVENT_TYPE_LABELS[ev.eventType] ?? ev.eventType;

  const type = ev.eventType
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');

  return `${serviceLabel(ev.service)} ${type}`;
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

/** Kit membership is enforced server-side; the menu says so rather than 500ing. */
function canDelete(t: OverlayTemplate) {
  return isOwn(t) && !t.kits_exists;
}

function rowClass(t: OverlayTemplate): string | undefined {
  const ev = props.showEvent ? firstEvent(t) : null;
  return ev ? eventTypeHoverBorderClass(ev.eventType, ev.source) : undefined;
}

function formatDateShort(iso: string) {
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  }).format(new Date(iso));
}

const copiedId = ref<number | null>(null);

async function copyLink(t: OverlayTemplate) {
  try {
    await navigator.clipboard.writeText(location.origin + detailsHref(t));
    copiedId.value = t.id;
    setTimeout(() => (copiedId.value = null), 2000);
  } catch {
    // Clipboard denied - nothing useful to say, and the link is on screen.
  }
}

async function handleFork(t: OverlayTemplate) {
  if (await confirm({ message: 'Copy this template to your own account?', confirmLabel: 'Copy', tone: 'neutral' })) {
    router.post(`/templates/${t.id}/fork`);
  }
}

async function handleDelete(t: OverlayTemplate) {
  if (!canDelete(t)) return;
  if (await confirm({ message: `Delete "${t.name}"? This cannot be undone.`, confirmLabel: 'Delete' })) {
    const returnUrl = window.location.pathname + window.location.search;
    router.delete(`/templates/${t.id}`, {
      onSuccess: () => router.visit(returnUrl),
    });
  }
}
</script>

<template>
  <CollectionList
    :items="props.templates"
    :item-key="(t: OverlayTemplate) => t.id"
    :href="detailsHref"
    :label="(t: OverlayTemplate) => t.name"
    :row-class="rowClass"
    :empty-message="props.emptyMessage"
  >
    <template #item="{ item: t }">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-3">
          <span class="font-medium">{{ t.name }}</span>
          <Badge v-if="!t.is_public" variant="destructive" class="text-xs">Private</Badge>
        </div>

        <span v-if="t.description" class="max-w-[90%] truncate text-xs text-muted-foreground">
          {{ t.description }}
        </span>

        <!--
          Icon + label, the same pairing as the events feed (EventsTable.vue):
          shape carries the source, colour reinforces the event type. Colour
          alone left this line reading as a stray hyperlink.
        -->
        <div
          v-if="props.showEvent && firstEvent(t)"
          class="mt-0.5 flex items-center gap-1.5 text-xs"
          :class="eventTypeDotClass(firstEvent(t)!.eventType, firstEvent(t)!.source)"
        >
          <ProviderIcon :source="firstEvent(t)!.source" class="h-3.5 w-3.5 shrink-0" />
          <span>{{ eventLabel(firstEvent(t)!) }}</span>
        </div>
      </div>
    </template>

    <template #actions="{ item: t }">
      <a v-if="isOwn(t)" class="btn btn-sm btn-primary cursor-pointer" :href="editHref(t)" :title="`Edit ${t.name}`">
        <PencilIcon class="h-3.5 w-3.5" />
      </a>
      <a v-else class="btn btn-sm btn-primary cursor-pointer" :href="detailsHref(t)" :title="`View ${t.name}`">
        <ChevronRight class="h-3.5 w-3.5" />
      </a>

      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button class="btn btn-sm btn-secondary cursor-pointer px-2" title="More actions">
            <Check v-if="copiedId === t.id" class="h-3.5 w-3.5 text-green-500" />
            <MoreVertical v-else class="h-3.5 w-3.5" />
          </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-52">
          <DropdownMenuItem as-child>
            <Link :href="detailsHref(t)" class="cursor-pointer"><Eye class="mr-2 h-4 w-4" />View details</Link>
          </DropdownMenuItem>

          <DropdownMenuItem v-if="isOwn(t)" as-child>
            <Link :href="editHref(t)" class="cursor-pointer"><PencilIcon class="mr-2 h-4 w-4" />Edit</Link>
          </DropdownMenuItem>

          <DropdownMenuItem v-if="t.is_public" as-child>
            <a :href="previewHref(t)" target="_blank" class="cursor-pointer"> <ExternalLinkIcon class="mr-2 h-4 w-4" />Preview </a>
          </DropdownMenuItem>

          <DropdownMenuItem v-if="canDelete(t)" class="cursor-pointer text-destructive" @click="handleDelete(t)">
            <Trash2 class="mr-2 h-4 w-4" />Delete
          </DropdownMenuItem>
          <DropdownMenuItem v-else-if="isOwn(t)" disabled class="text-xs text-muted-foreground"> Part of a kit - cannot delete </DropdownMenuItem>

          <DropdownMenuSeparator />

          <DropdownMenuItem v-if="!isOwn(t) && t.is_public" class="cursor-pointer" @click="handleFork(t)">
            <GitFork class="mr-2 h-4 w-4" />Copy template
          </DropdownMenuItem>

          <DropdownMenuItem class="cursor-pointer" @click="copyLink(t)"> <LinkIcon class="mr-2 h-4 w-4" />Copy link </DropdownMenuItem>

          <template v-if="props.showOwner && t.owner">
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
    </template>
  </CollectionList>
</template>
