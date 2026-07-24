<script setup lang="ts">
import {
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { Blocks, LogOut, Settings } from '@lucide/vue';

const settingsItems = [
  { label: 'Account Settings', href: route('settings.account'), icon: Settings },
  { label: 'Integrations', href: route('settings.integrations.index'), icon: Blocks },
];

interface Props {
  user: User;
}

defineProps<Props>();

const handleLogout = () => {
  router.flushAll();
};
</script>

<template>
  <DropdownMenuGroup>
    <DropdownMenuItem v-for="item in settingsItems" :key="item.label" as-child>
      <Link :href="item.href" class="flex items-center w-full cursor-pointer">
        <component :is="item.icon" class="mr-2 h-4 w-4" />
        {{ item.label }}
      </Link>
    </DropdownMenuItem>
  </DropdownMenuGroup>

  <DropdownMenuSeparator />

  <DropdownMenuItem :as-child="true">
    <Link class="block w-full cursor-pointer" method="post" :href="route('logout')" @click="handleLogout" as="button">
      <LogOut class="mr-2 h-4 w-4" />
      Log out
    </Link>
  </DropdownMenuItem>
</template>
