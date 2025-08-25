<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { House, LayoutGrid, Radio, Grid2x2Check, Code, Shield, Building, HelpingHandIcon, Group, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
    icon: House,
  },
  {
    title: 'Community',
    href: '/dashboard/recents',
    icon: Users,
  },
  {
    title: 'NEW: Kits',
    href: '/kits',
    icon: LayoutGrid,
  },
  {
    title: 'Template Builder',
    href: '/templates',
    icon: Building,
  },

  {
    title: 'Events Builder',
    href: '/events',
    icon: Radio,
  },

  {
    title: 'Help',
    href: '/help',
    icon: HelpingHandIcon,
  },
];
const userNavItems: NavItem[] = [
  {
    title: 'Your Twitch Data',
    href: '/twitchdata',
    icon: Grid2x2Check,
  },
  {
    title: 'Tags Generator',
    href: '/tags',
    icon: Code,
  },
  {
    title: 'Token Generator',
    href: '/tokens',
    icon: Shield,
  },
]
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <Link :href="route('dashboard')">
              <AppLogo />
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain :items="mainNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavUser :items="userNavItems" />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
