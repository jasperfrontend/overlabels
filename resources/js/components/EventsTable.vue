<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Clock, Info, MoreVertical, RefreshCw } from 'lucide-vue-next';

interface TwitchEvent {
  id: number;
  event_type: string;
  event_data: Record<string, unknown>;
  created_at: string;
}

defineProps<{
  events: TwitchEvent[];
}>();

const replayingId = ref<number | null>(null);

const nonReplayableTypes = ['stream.online', 'stream.offline'];

function canReplay(type: string): boolean {
  return !nonReplayableTypes.includes(type);
}

function replay(event: TwitchEvent) {
  replayingId.value = event.id;
  router.post(`/events/${event.id}/replay`, {}, {
    preserveScroll: true,
    onFinish: () => {
      replayingId.value = null;
    },
  });
}

const eventLabels: Record<string, string> = {
  'channel.follow': 'Follow',
  'channel.subscribe': 'Subscription',
  'channel.subscription.gift': 'Gift Sub',
  'channel.subscription.message': 'Resub',
  'channel.cheer': 'Cheer',
  'channel.raid': 'Raid',
  'channel.channel_points_custom_reward_redemption.add': 'Redemption',
  'stream.online': 'Stream Online',
  'stream.offline': 'Stream Offline',
};

function label(type: string): string {
  return eventLabels[type] ?? type;
}

function who(event: TwitchEvent): string | null {
  const d = event.event_data;
  if (event.event_type === 'channel.raid') return (d.from_broadcaster_user_name as string) ?? null;
  if (event.event_type === 'stream.online' || event.event_type === 'stream.offline') return null;
  return (d.user_name as string) ?? null;
}

function details(event: TwitchEvent): string | null {
  const d = event.event_data;
  switch (event.event_type) {
    case 'channel.subscribe':
    case 'channel.subscription.message':
      return d.tier ? `Tier ${String(d.tier).replace('1000', '1').replace('2000', '2').replace('3000', '3')}` : null;
    case 'channel.subscription.gift':
      return d.total ? `${d.total} gifts` : null;
    case 'channel.cheer':
      return d.bits ? `${d.bits} bits` : null;
    case 'channel.raid':
      return d.viewers ? `${d.viewers} viewers` : null;
    case 'channel.channel_points_custom_reward_redemption.add':
      return (d.reward as Record<string, unknown>)?.title as string ?? null;
    default:
      return null;
  }
}

function relativeTime(iso: string): string {
  const diff = new Date(iso).getTime() - Date.now();
  const abs = Math.abs(diff);
  const minute = 60_000;
  const hour = 60 * minute;
  const day = 24 * hour;
  const week = 7 * day;
  const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
  if (abs < minute) return 'just now';
  if (abs < hour) return rtf.format(Math.round(diff / minute), 'minute');
  if (abs < day) return rtf.format(Math.round(diff / hour), 'hour');
  if (abs < week) return rtf.format(Math.round(diff / day), 'day');
  return rtf.format(Math.round(diff / week), 'week');
}

function badgeVariant(type: string): 'default' | 'secondary' | 'outline' | 'destructive' {
  if (type.startsWith('channel.subscribe') || type === 'channel.subscription.gift' || type === 'channel.subscription.message') return 'default';
  if (type === 'channel.cheer') return 'default';
  if (type === 'channel.raid') return 'secondary';
  if (type === 'stream.offline') return 'destructive';
  return 'outline';
}
</script>

<template>
  <Table>
    <TableHeader>
      <TableRow class="hover:bg-transparent">
        <TableHead class="w-[120px]">Event</TableHead>
        <TableHead>Who</TableHead>
        <TableHead class="w-[48px]"><span class="sr-only">Actions</span></TableHead>
      </TableRow>
    </TableHeader>

    <TableBody>
      <TableRow v-for="event in events" :key="event.id">
        <TableCell>
          <Badge :variant="badgeVariant(event.event_type)">
            {{ label(event.event_type) }}
          </Badge>
        </TableCell>

        <TableCell>
          <span v-if="who(event)" class="font-medium">{{ who(event) }}</span>
          <span v-else class="text-muted-foreground/50 italic">-</span>
        </TableCell>

        <TableCell class="text-right">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button class="btn btn-sm btn-secondary px-2" title="More actions">
                <MoreVertical class="h-3.5 w-3.5" />
              </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" class="w-52">
              <DropdownMenuItem
                v-if="canReplay(event.event_type)"
                :disabled="replayingId === event.id"
                @click="replay(event)"
              >
                <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': replayingId === event.id }" />
                Replay alert
              </DropdownMenuItem>

              <DropdownMenuSeparator v-if="canReplay(event.event_type) && (details(event) || true)" />

              <DropdownMenuItem v-if="details(event)" disabled class="text-muted-foreground">
                <Info class="mr-2 h-4 w-4" />
                {{ details(event) }}
              </DropdownMenuItem>

              <DropdownMenuItem disabled class="text-muted-foreground">
                <Clock class="mr-2 h-4 w-4" />
                {{ relativeTime(event.created_at) }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
</template>
