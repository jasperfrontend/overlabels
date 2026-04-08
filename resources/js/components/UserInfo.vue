<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { useStreamState } from '@/composables/useStreamState';
import type { User } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const { isLive, isTransitioning, uptime } = useStreamState();

interface Props {
  user: User;
  showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  showEmail: false
});

const page = usePage();
const auth = computed(() => page.props.auth);


const { getInitials } = useInitials();
const showAvatar = computed(() => props?.user?.avatar && props?.user?.avatar !== '');
</script>

<template>
  <Button
    variant="ghost"
    size="icon"
    class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
  >
    <Avatar v-if="showAvatar" class="size-8 overflow-hidden rounded-full">
      <AvatarImage v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
      <AvatarFallback
        class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
        {{ getInitials(auth.user?.name) }}
      </AvatarFallback>
    </Avatar>
    <span
      v-if="isLive"
      class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-green-500 ring-2 ring-background"
    />
    <span
      v-else-if="isTransitioning"
      class="absolute -top-0.5 -right-0.5 size-2 animate-pulse rounded-full bg-orange-400 ring-2 ring-background"
    />
  </Button>

  <div class="grid flex-1 text-left text-sm leading-tight" v-if="user">
    <span class="truncate font-medium">{{ user.name }}</span>
    <span v-if="isLive" class="text-xs text-green-400 font-mono w-25">Live for {{ uptime }}</span>
    <span v-else-if="isTransitioning" class="text-xs text-yellow-400 font-mono w-25">Checking stream status</span>
    <span v-else class="text-xs text-muted-foreground font-mono w-25">Not streaming</span>
  </div>
  <div v-else>
    <Link href="/">
      <span class="truncate font-medium">User not logged in</span><br />
      <span class="truncate text-xs text-muted-foreground">Please Login</span>
    </Link>
  </div>
</template>
