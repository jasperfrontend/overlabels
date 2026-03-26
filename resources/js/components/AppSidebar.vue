<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
  Bell,
  BookOpen,
  Brackets,
  FileText,
  HashIcon,
  Heart,
  House,
  Layers,
  LayoutGrid,
  LogIn,
  Radio,
  ScrollText,
  ShieldAlert,
  ShieldBan,
  ShieldCheck,
  SlidersHorizontal,
  Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => page.props.isAdmin);
//@ts-ignore
const commitHash = __COMMIT_HASH__;

const mainNavItems: NavItem[] = [
  { title: 'Dashboard', href: route('dashboard.index'), icon: House },
  { title: 'My activity', href: route('dashboard.recents'), icon: Users },
  { title: 'My overlays', href: '/templates?direction=desc&filter=mine&search=&type=static', icon: Layers },
];
const alertsNavItems: NavItem[] = [
  { title: 'My alerts', href: '/templates?direction=desc&filter=mine&search=&type=alert', icon: Bell },
  { title: 'Alerts builder', href: route('events.index'), icon: Radio }
];

const kitsNavItems: NavItem[] = [{ title: 'Overlay kits', href: route('kits.index'), icon: LayoutGrid }];

const learnNavItems: NavItem[] = [
  { title: 'Conditional Tags', href: route('help'), icon: Brackets },
  { title: 'Controls', href: route('help.controls'), icon: SlidersHorizontal },
  { title: 'Free Resources', href: route('resources'), icon: BookOpen },
  { title: 'Why Ko-fi', href: route('why-kofi'), icon: Heart },
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
  { title: 'Bans', href: route('admin.bans.index'), icon: ShieldBan },
  { title: 'Access Logs', href: route('admin.logs.index'), icon: ScrollText },
  { title: 'Audit Log', href: route('admin.audit.index'), icon: FileText },
  { title: 'Lockdown', href: route('admin.lockdown.index'), icon: ShieldAlert },
];
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton as-child>
            <Link :href="user ? route('dashboard.index') : '/'">
              <AppLogo />
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain v-if="user" label="" :items="mainNavItems" />
      <NavMain v-if="user" label="Alerts" :items="alertsNavItems" />
      <NavMain v-if="user" label="Kits" :items="kitsNavItems" />
      <NavMain label="Learn" :items="learnNavItems" />
      <NavMain v-if="isAdmin" label="Admin" :items="adminNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavUser v-if="user" />
      <SidebarMenu v-else>
        <SidebarMenuItem>
          <SidebarMenuButton as-child>
            <a href="/auth/redirect/twitch" class="flex items-center cursor-pointer">
              <LogIn class="mr-2 h-4 w-4" />
              Log in
            </a>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
      <div class="px-3 pb-2 text-[10px] text-muted-foreground/50 group-data-[collapsible=icon]:hidden">
        <a :href="`https://github.com/jasperfrontend/overlabels/commit/${commitHash}`" target="_blank" rel="noopener noreferrer" class="hover:text-muted-foreground transition-colors">
          {{ commitHash }}
        </a>
      </div>
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
