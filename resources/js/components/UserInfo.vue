<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();
const showAvatar = computed(() => props?.user?.avatar && props?.user?.avatar !== '');
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg" v-if="user">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg" v-else>
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            X
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight" v-if="user">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
    </div>
    <div v-else>
        <Link href="/login">
            <span class="truncate font-medium">User not logged in</span><br />
            <span class="truncate text-xs text-muted-foreground">Please Login</span>
        </Link>
    </div>
</template>
