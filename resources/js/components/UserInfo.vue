<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { useLivingTitle } from '@/composables/useLivingTitle';
import { useStreamState } from '@/composables/useStreamState';
import type { User } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Pause } from '@lucide/vue';
import { computed } from 'vue';

const { isLive, isTransitioning, uptime } = useStreamState();
// "Title paused" is the one living-title state worth a place in the header:
// it means Overlabels stopped writing your title because you changed it on
// Twitch, and nobody sits on the settings page during a stream to see that.
const { paused: titlePaused } = useLivingTitle();

interface Props {
  user: User;
}

const props = defineProps<Props>();

const page = usePage();
const auth = computed(() => page.props.auth);

const { getInitials } = useInitials();
const showAvatar = computed(() => props?.user?.avatar && props?.user?.avatar !== '');
</script>

<template>
  <span class="relative inline-flex size-10 items-center justify-center p-1">
    <Avatar v-if="showAvatar" class="size-8 overflow-hidden rounded-full">
      <AvatarImage v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
      <AvatarFallback class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
        {{ getInitials(auth.user?.name) }}
      </AvatarFallback>
    </Avatar>
    <span v-if="isLive" class="absolute top-0 right-0 size-2 rounded-full bg-green-500 ring-2 ring-background" />
    <span v-else-if="isTransitioning" class="absolute top-0 right-0 size-2 animate-pulse rounded-full bg-orange-400 ring-2 ring-background" />
  </span>

  <div class="grid flex-1 text-left text-sm leading-tight" v-if="user">
    <span class="truncate font-medium">{{ user.name }}</span>
    <span v-if="isLive" class="w-30 font-mono text-xs text-green-400">Live for {{ uptime }}</span>
    <span v-else-if="isTransitioning" class="w-25 font-mono text-xs text-yellow-400">Checking stream</span>
    <span v-else class="w-25 font-mono text-xs text-muted-foreground">Not streaming</span>
    <Link
      v-if="titlePaused"
      href="/settings/title"
      class="relative z-10 inline-flex cursor-pointer items-center gap-1 font-mono text-xs text-amber-400 hover:underline"
      title="Your title was changed on Twitch, so Overlabels stopped writing it. Click to resume."
    >
      <Pause class="size-3" />
      Title paused
    </Link>
  </div>
  <div v-else>
    <!-- Plain anchor: '/' is a Blade view, not an Inertia page. -->
    <a href="/" class="cursor-pointer">
      <span class="truncate text-xs text-muted-foreground">Connect</span>
    </a>
  </div>
</template>
