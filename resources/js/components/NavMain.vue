<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';

defineProps<{
  label: string | null | undefined;
  items: NavItem[];
}>();

const page = usePage();

const isActive = (href: string): boolean => {
  let itemPath: string;
  let itemSearch: string;
  try {
    const url = new URL(href);
    itemPath = url.pathname;
    itemSearch = url.search;
  } catch {
    const [path, search] = href.split('?');
    itemPath = path;
    itemSearch = search ? `?${search}` : '';
  }
  if (itemSearch) {
    return page.url === `${itemPath}${itemSearch}`;
  }
  return page.url.split('?')[0] === itemPath;
};
</script>

<template>
  <SidebarGroup class="mb-2 px-2 py-0">
    <SidebarGroupLabel v-if="label" class="text-violet-400 dark:text-violet-300">{{ label }}</SidebarGroupLabel>
    <SidebarMenu>
      <SidebarMenuItem v-for="item in items" :key="item.title">
        <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title">
          <!-- The trailing icon makes the title span no longer :last-child, so it needs
               `truncate` spelled out here - the button's own selector stops matching it. -->
          <Link v-if="item.target" :href="item.href" :target="item.target" rel="noopener noreferrer" class="group/nav-link">
            <component :is="item.icon" />
            <span class="truncate">{{ item.title }}</span>
            <ExternalLink
              class="ml-auto opacity-0 transition-opacity group-hover/nav-link:opacity-100 group-focus-visible/nav-link:opacity-100 group-data-[collapsible=icon]:hidden"
            />
          </Link>
          <Link v-else :href="item.href">
            <component :is="item.icon" />
            <span>{{ item.title }}</span>
          </Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>
</template>
