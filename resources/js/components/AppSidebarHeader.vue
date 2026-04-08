<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import { usePage } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';
import type { AppPageProps } from '@/types';
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
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex w-full items-center">
          <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
          </div>
          <div class="ml-auto w-auto flex items-center">
            <div v-if="user" class="p-3">
              <DropdownMenu>
                <DropdownMenuTrigger class="flex items-center gap-2 p-2 px-4 rounded hover:bg-sidebar-accent cursor-pointer outline-none">
                  <UserInfo :user="user" :show-email="false" />
                </DropdownMenuTrigger>
                <DropdownMenuContent class="min-w-56 rounded-lg" side="bottom" align="end" :side-offset="4">
                  <UserMenuContent :user="user" />
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <DarkModeToggle />
          </div>
        </div>
    </header>
</template>
