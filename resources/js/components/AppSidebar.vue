<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Brackets, Building, FileText, House, LayoutGrid, Radio, SlidersHorizontal, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
  { title: 'Dashboard', href: route('dashboard.index'), icon: House },
  { title: 'My activity', href: route('dashboard.recents'), icon: Users },
  { title: 'All Overlays', href: route('templates.index'), icon: Building },
];
const alertsNavItems: NavItem[] = [{ title: 'Alerts Builder', href: route('events.index'), icon: Radio }];

const kitsNavItems: NavItem[] = [{ title: 'Overlay Kits', href: route('kits.index'), icon: LayoutGrid }];

const learnNavItems: NavItem[] = [
  { title: 'Syntax Help', href: route('help'), icon: Brackets },
  { title: 'Controls', href: route('help.controls'), icon: SlidersHorizontal },
  { title: 'Manifesto', href: route('manifesto'), icon: FileText },
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
      <NavMain label="Platform" :items="mainNavItems" />
      <NavMain label="Alerts" :items="alertsNavItems" />
      <NavMain label="Kits" :items="kitsNavItems" />
      <NavMain label="Learn" :items="learnNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
