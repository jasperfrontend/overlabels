<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

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
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel v-if="label">{{ label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title">
                    <Link v-if="item.target" :href="item.href" :target="item.target" rel="noopener noreferrer">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
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
