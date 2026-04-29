<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { TvMinimalPlay } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';
import { useSessionDataFormatter } from '@/composables/useSessionDataFormatter';

// Formatters available once UI is built. Destructure what you need:
// const { formatDate, formatTime, formatDuration, userLocale } = useSessionDataFormatter();
void useSessionDataFormatter;


interface StreamSession {
  session_id: number;
  started_at: string;
  ended_at: string;
  completed: boolean;
  duration_seconds: number;
  helix_stream_id: string | null;
  window: {
    start: string;
    end: string;
    pre_buffer_seconds: number;
    post_buffer_seconds: number;
    anchored_on_eventsub: {
      online: boolean;
      offline: boolean;
    };
  }
  anchors: {
    stream_online_at: string | null;
    stream_offline_at: string | null;
  };
  event_counts: Record<string, number>;
  stats: {
    follows: {
      count: number;
    };
    new_subscribers: {
      count: number;
    };
    resubs: {
      count: number;
      total_cumulative_months: number;
      recent_messages: {
        tier: number;
        months: number;
      }[];
    };
    gift_subs: {
      count: number;
      total_subs_gifted: number;
    };
    raids_received: {
      count: number;
      total_viewers: number;
      raids: {
        viewers: number;
        channel_id: string;
        started_at: string;
      };
    };
    cheers: {
      count: number;
      total_bits: number;
    };
    channel_point_redemptions: {
      count: number;
      total_cost: number;
      by_reward: {
        reward_id: string;
        count: number;
      };
    };
    polls: {
      id: string;
      question: string;
      options: {
        id: string;
        text: string;
        votes: number;
      }[];
      ended_at: string;
    }[];
  }
}

const props = defineProps<{
  sessions: StreamSession[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Stream Sessions', href: '/dashboard/stream-sessions' },
];

const toastMessage = ref<string | null>(null);
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Stream Sessions" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
      <div class="flex items-center gap-3">
        <TvMinimalPlay class="h-6 w-6" />
        <Heading
          title="Stream Sessions"
          description="View all your stream sessions here."
          description-class="text-foreground"
        />
      </div>

      <div v-if="sessions.length === 0" class="text-foreground flex flex-col gap-2 text-sm max-w-2xl">
        <p>No Stream sessions yet.</p>
      </div>

      <div class="space-y-4">
        <div
          v-for="session in sessions"
          :key="session.session_id"
          class="rounded-lg border border-sidebar-border bg-card p-4 space-y-3"
        >

        </div>
      </div>

      <pre class="text-xs bg-card border rounded-lg p-4 overflow-auto whitespace-pre-wrap break-all">{{ JSON.stringify(props.sessions, null, 2) }}</pre>
    </div>

    <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
