<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
  Bell,
  Brackets,
  Building,
  FileText,
  HashIcon,
  House,
  Layers,
  LayoutGrid,
  Radio,
  ShieldCheck,
  SlidersHorizontal,
  Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const isAdmin = computed(() => page.props.isAdmin);

const mainNavItems: NavItem[] = [
  { title: 'Dashboard', href: route('dashboard.index'), icon: House },
  { title: 'My activity', href: route('dashboard.recents'), icon: Users },
  { title: 'My overlays', href: '/templates?direction=desc&filter=mine&search=&type=static', icon: Layers },
  { title: 'My alerts', href: '/templates?direction=desc&filter=mine&search=&type=alert', icon: Bell },
];
const alertsNavItems: NavItem[] = [{ title: 'Alerts builder', href: route('events.index'), icon: Radio }];

const kitsNavItems: NavItem[] = [{ title: 'Overlay kits', href: route('kits.index'), icon: LayoutGrid }];

const learnNavItems: NavItem[] = [
  { title: 'Syntax help', href: route('help'), icon: Brackets },
  { title: 'Controls', href: route('help.controls'), icon: SlidersHorizontal },
  { title: 'Manifesto', href: route('manifesto'), icon: FileText },
];

const adminNavItems: NavItem[] = [
  { title: 'Dashboard', href: route('admin.dashboard'), icon: ShieldCheck },
  { title: 'Users', href: route('admin.users.index'), icon: Users },
  { title: 'Overlays', href: route('admin.templates.index'), icon: Layers },
  { title: 'Events', href: route('admin.events.index'), icon: Radio },
  { title: 'Tags', href: route('admin.tags.index'), icon: Brackets },
  { title: 'Tokens', href: route('admin.tokens.index'), icon: HashIcon },
  { title: 'Sessions', href: route('admin.sessions.index'), icon: House },
  { title: 'Audit Log', href: route('admin.audit.index'), icon: FileText },
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
      <NavMain label="  Platform" :items="mainNavItems" />
      <NavMain label="Alerts" :items="alertsNavItems" />
      <NavMain label="Kits" :items="kitsNavItems" />
      <NavMain label="Learn" :items="learnNavItems" />
      <NavMain v-if="isAdmin" label="Admin" :items="adminNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
