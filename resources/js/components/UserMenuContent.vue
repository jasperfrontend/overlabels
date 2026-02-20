<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { useAppearance } from '@/composables/useAppearance';
import { Bug, Code, Grid2x2Check, LogOut, Monitor, Moon, Shield, Sun, Terminal } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
  { value: 'light', Icon: Sun, label: 'Light' },
  { value: 'dark', Icon: Moon, label: 'Dark' },
  { value: 'system', Icon: Monitor, label: 'System' },
] as const;

const debugItems = [
  { label: 'Token Generator', href: route('tokens.index'), icon: Shield },
  { label: 'Tags Generator', href: route('tags.generator'), icon: Code },
  { label: 'Your Twitch Data', href: route('twitchdata'), icon: Grid2x2Check },
  { label: 'Testing Guide', href: route('testing.index'), icon: Terminal },
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
  <DropdownMenuLabel class="p-0 font-normal">
    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
      <UserInfo :user="user" :show-email="false" />
    </div>
  </DropdownMenuLabel>

  <DropdownMenuSeparator />

  <!-- Theme toggle -->
  <DropdownMenuGroup>
    <DropdownMenuItem :as-child="true">
      <div class="flex rounded-lg gap-0 justify-between bg-neutral-100 dark:bg-neutral-800">
        <button
          v-for="{ value, label } in tabs"
          :key="value"
          @click="updateAppearance(value)"
          :class="[
            'flex items-center text-sm rounded-md px-3 py-1.5 transition-colors cursor-pointer',
            appearance === value
              ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
              : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
          ]"
        >
          {{ label }}
        </button>
      </div>
    </DropdownMenuItem>
  </DropdownMenuGroup>

  <DropdownMenuSeparator />

  <!-- Debug submenu -->
  <DropdownMenuGroup>
    <DropdownMenuSub>
      <DropdownMenuSubTrigger>
        <Bug class="mr-2 h-4 w-4" />
        Debug
      </DropdownMenuSubTrigger>
      <DropdownMenuSubContent class="min-w-48">
        <DropdownMenuItem v-for="item in debugItems" :key="item.label" as-child>
          <a :href="item.href" target="_blank" rel="noopener noreferrer" class="flex items-center w-full">
            <component :is="item.icon" class="mr-2 h-4 w-4" />
            {{ item.label }}
          </a>
        </DropdownMenuItem>
      </DropdownMenuSubContent>
    </DropdownMenuSub>
  </DropdownMenuGroup>

  <DropdownMenuSeparator />

  <DropdownMenuItem :as-child="true">
    <Link class="block w-full" method="post" :href="route('logout')" @click="handleLogout" as="button">
      <LogOut class="mr-2 h-4 w-4" />
      Log out
    </Link>
  </DropdownMenuItem>
</template>
