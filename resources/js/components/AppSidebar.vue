<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { House, LayoutGrid, Radio, Grid2x2Check, Code, Shield, Building, HelpingHandIcon, Users, Brackets } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
    icon: House,
  },
  {
    title: 'Recents',
    href: '/dashboard/recents',
    icon: Users,
  },
  {
    title: 'All Templates',
    href: '/templates',
    icon: Building,
  },
  {
    title: 'Template Kits',
    href: '/kits',
    icon: LayoutGrid,
  },
  {
    title: 'Alerts Builder',
    href: '/alerts',
    icon: Radio,
  },
];
const userNavItems: NavItem[] = [
  {
    title: 'Syntax Help',
    href: '/help',
    icon: Brackets,
  },
  {
    title: 'Token Generator',
    href: '/tokens',
    icon: Shield,
  },
  {
    title: 'Tags Generator',
    href: '/tags',
    icon: Code,
  },
  {
    title: 'Your Twitch Data',
    href: '/twitchdata',
    icon: Grid2x2Check,
  },


];
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
