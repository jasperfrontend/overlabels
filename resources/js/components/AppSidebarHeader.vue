<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import DiscordIcon from '@/components/DiscordIcon.vue';
import StreamStatus from '@/components/StreamStatus.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { AppPageProps, BreadcrumbItemType } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Blocks, LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';

withDefaults(
  defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
  }>(),
  {
    breadcrumbs: () => [],
  },
);

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);

// Header links on desktop, the cog menu on mobile. One list for both.
const settingsLinks = [
  { label: 'Settings', href: route('settings.account'), icon: Settings },
  { label: 'Integrations', href: route('settings.integrations.index'), icon: Blocks },
];
</script>

<template>
  <header
    class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
  >
    <div class="flex w-full items-center gap-2">
      <SidebarTrigger class="-ml-1" />
      <template v-if="breadcrumbs && breadcrumbs.length > 0">
        <Breadcrumbs class="hidden md:block" :breadcrumbs="breadcrumbs" />
      </template>

      <template v-if="user">
        <!-- Next to the sidebar button on mobile, pushed right beside the links on desktop. -->
        <StreamStatus class="ml-2 md:mr-2 md:ml-auto" />

        <nav class="hidden items-center gap-1 md:flex">
          <!-- Off-site, so a plain link in a new tab rather than an Inertia visit. -->
          <a :href="page.props.discordUrl" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost">
            <DiscordIcon class="size-4" />
            Discord
          </a>
          <Link v-for="item in settingsLinks" :key="item.label" :href="item.href" class="btn btn-sm btn-ghost">
            <component :is="item.icon" class="size-4" />
            {{ item.label }}
          </Link>
        </nav>

        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="ml-auto h-9 w-9 cursor-pointer md:hidden">
              <Settings class="size-4" />
              <span class="sr-only">Settings menu</span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="min-w-48" align="end">
            <DropdownMenuItem as-child>
              <a :href="page.props.discordUrl" target="_blank" rel="noopener noreferrer" class="flex w-full cursor-pointer items-center">
                <DiscordIcon class="mr-2 size-4" />
                Discord
              </a>
            </DropdownMenuItem>
            <DropdownMenuItem v-for="item in settingsLinks" :key="item.label" as-child>
              <Link :href="item.href" class="flex w-full cursor-pointer items-center">
                <component :is="item.icon" class="mr-2 size-4" />
                {{ item.label }}
              </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
              <Link class="flex w-full cursor-pointer items-center" method="post" :href="route('logout')" as="button" @click="router.flushAll()">
                <LogOut class="mr-2 size-4" />
                Log out
              </Link>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>

      <!-- Wrapped: the toggle's root is a dropdown with no element to take a class. -->
      <div :class="{ 'ml-auto': !user }">
        <DarkModeToggle />
      </div>
    </div>
  </header>
</template>
