<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { House, LayoutGrid, Radio, Grid2x2Check, Code, Shield, Building, Users, Brackets, FileText } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: route('dashboard.index'),
    icon: House,
  },
  {
    title: 'Recents',
    href: route('dashboard.recents'),
    icon: Users,
  },
  {
    title: 'Overlay kits',
    href: route('kits.index'),
    icon: LayoutGrid,
  },
  {
    title: 'All Overlays',
    href: route('templates.index'),
    icon: Building,
  },
  {
    title: 'Alerts Builder',
    href: route('events.index'),
    icon: Radio,
  },
];
const userNavItems: NavItem[] = [
  {
    title: 'Syntax Help',
    href: route('help'),
    icon: Brackets,
  },
  {
    title: 'Manifesto',
    href: route('manifesto'),
    icon: FileText,
  },
  {
    title: 'Token Generator',
    href: route('tokens.index'),
    icon: Shield,
  },
  {
    title: 'Tags Generator',
    href: route('tags.generator'),
    icon: Code,
  },
  {
    title: 'Your Twitch Data',
    href: route('twitchdata'),
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
            <Link :href="route('dashboard.index')">
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
