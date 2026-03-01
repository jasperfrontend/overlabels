<script setup lang="ts">
import {
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { Code, Grid2x2Check, LogOut, ShieldAlert, Shield, Terminal, SunMoon, Coffee } from 'lucide-vue-next';

const settingsItems = [
  { label: 'Theme Settings', href: route('settings.appearance'), icon: SunMoon },
  { label: 'Integrations', href: route('settings.integrations.index'), icon: Coffee },
]

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
<!--  <DropdownMenuLabel class="p-0 font-normal">-->
<!--    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">-->
<!--      <UserInfo :user="user" :show-email="false" />-->
<!--    </div>-->
<!--  </DropdownMenuLabel>-->

<!--  <DropdownMenuSeparator />-->

  <!-- Theme toggle -->
  <DropdownMenuGroup>

    <DropdownMenuItem v-for="item in settingsItems" :key="item.label" as-child>
      <Link :href="item.href" rel="noopener noreferrer" class="flex items-center w-full cursor-pointer">
        <component :is="item.icon" class="mr-2 h-4 w-4" />
        {{ item.label }}
      </Link>
    </DropdownMenuItem>
  </DropdownMenuGroup>

  <DropdownMenuSeparator />

  <!-- Debug submenu -->
  <DropdownMenuGroup>
    <DropdownMenuSub>
      <DropdownMenuSubTrigger>
        <ShieldAlert class="mr-2 h-4 w-4" />
        Sensitive Data
      </DropdownMenuSubTrigger>
      <DropdownMenuSubContent class="min-w-48">
        <DropdownMenuItem v-for="item in debugItems" :key="item.label" as-child>
          <a :href="item.href" target="_blank" rel="noopener noreferrer" class="flex items-center w-full cursor-pointer">
            <component :is="item.icon" class="mr-2 h-4 w-4" />
            {{ item.label }}
          </a>
        </DropdownMenuItem>
      </DropdownMenuSubContent>
    </DropdownMenuSub>
  </DropdownMenuGroup>

  <DropdownMenuSeparator />

  <DropdownMenuItem :as-child="true">
    <Link class="block w-full cursor-pointer" method="post" :href="route('logout')" @click="handleLogout" as="button">
      <LogOut class="mr-2 h-4 w-4" />
      Log out
    </Link>
  </DropdownMenuItem>
</template>
