<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
  Activity,
  Bell,
  BotIcon,
  BookOpen,
  Brackets,
  FileText,
  HashIcon,
  Heart,
  House,
  Layers,
  LayoutGrid,
  LogIn,
  MessageSquare,
  MessageSquareCode,
  Newspaper,
  Pipette,
  Radio,
  ScrollText,
  ShieldAlert,
  ShieldBan,
  ShieldCheck,
  ListIcon,
  Sigma,
  SlidersHorizontal,
  Users
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => page.props.isAdmin);
//@ts-expect-error on runtime __COMMIT_HASH__ is replaced by the actual commit hash through Vite
const commitHash = __COMMIT_HASH__;

// These arrays call route() for routes that only exist in the authenticated
// Ziggy group, so they must be computed and gated by `user` - building them
// eagerly would call route() for a logged-out visitor (whose `guest` group
// lacks these names) and Ziggy would throw.
const mainNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        { title: 'Overlays', href: '/templates?filter=mine&type=static', icon: Layers },
        { title: 'Alerts', href: '/templates?filter=mine&type=alert', icon: Bell },
        { title: 'Lists', href: route('lists.index'), icon: ListIcon },
        { title: 'Kits', href: route('kits.index'), icon: LayoutGrid }
      ]
    : []
);
const botNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        { title: 'Expressions', href: route('settings.bot.expressions.index'), icon: MessageSquare },
        { title: 'Aliases', href: route('settings.bot.aliases.index'), icon: MessageSquareCode }
      ]
    : []
);

const alertsNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        { title: 'Recent', href: route('dashboard.recents'), icon: Activity },
        { title: 'Streams', href: route('dashboard.stream-sessions'), icon: Radio },
        { title: 'Routes', href: route('dashboard.gps-sessions'), icon: Radio }
      ]
    : []
);

const learnNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        { title: 'Learn', href: route('help'), icon: BookOpen },
        { title: 'Reference', href: route('help.reference'), icon: Brackets },
        { title: 'Updates', href: route('updates.index'), icon: Newspaper }
      ]
    : []
);

const helpNavItems: NavItem[] = [
  { title: 'Help', href: '/help', icon: BookOpen },
  { title: 'Conditional Tags', href: '/help/conditionals', icon: Brackets },
  { title: 'Controls', href: '/help/controls', icon: SlidersHorizontal },
  { title: 'Math Engine', href: '/help/math', icon: Sigma },
  { title: 'Formatting Pipes', href: '/help/formatting', icon: Pipette },
  { title: 'Twitch Chat Bot', href: '/help/bot', icon: BotIcon },
  { title: 'Free Resources', href: '/help/resources', icon: BookOpen },
  { title: 'Why Ko-fi', href: '/help/why-kofi', icon: Heart },
  { title: 'Manifesto', href: '/help/manifesto', icon: FileText }
];

const isOnAdminPage = computed(() => page.url.startsWith('/admin'));
// route('admin.*') only exists in the `admin` Ziggy group, so these must be
// built lazily and gated by `isAdmin` - a logged-in non-admin's `user` group
// excludes admin.* and Ziggy would throw if these ran eagerly.
const adminNavItems = computed<NavItem[]>(() => {
  if (!isAdmin.value) return [];

  const dashboard: NavItem = { title: 'Dashboard', href: route('admin.dashboard'), icon: ShieldCheck };
  if (!isOnAdminPage.value) return [dashboard];

  return [
    dashboard,
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
    { title: 'Updates', href: route('admin.updates.index'), icon: Newspaper }
  ];
});

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
      <NavMain v-if="user && mainNavItems.length > 0" label="My stuff" :items="mainNavItems" />
      <NavMain v-if="user && alertsNavItems.length > 0" label="My events" :items="alertsNavItems" />
      <NavMain v-if="user && botNavItems.length > 0" label="Chat bot" :items="botNavItems" />
      <NavMain v-if="user && learnNavItems.length > 0" label="Learn" :items="learnNavItems" />
      <NavMain v-if="isAdmin" label="Admin" :items="adminNavItems" />
      <NavMain v-if="!user" label="Learn" :items="helpNavItems" />
      <div v-if="user" class="px-4 pt-2 text-[11px] text-muted-foreground group-data-[collapsible=icon]:hidden">
        <kbd class="border rounded px-1 py-0.5 text-[10px]">Ctrl</kbd> + <kbd
        class="border rounded px-1 py-0.5 text-[10px]">K</kbd> shortcuts
        <div class="h-0 mt-1" />
        <kbd class="border rounded px-1 py-0.5 text-[10px]">Ctrl</kbd> + <kbd
        class="border rounded px-1 py-0.5 text-[10px]">Space</kbd> go to
      </div>
    </SidebarContent>

    <SidebarFooter>
      <SidebarMenu v-if="!user">
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
        <a :href="`https://github.com/jasperfrontend/overlabels/commit/${commitHash}`" target="_blank"
           rel="noopener noreferrer" class="hover:text-muted-foreground transition-colors">
          {{ commitHash }}
        </a>
      </div>
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
