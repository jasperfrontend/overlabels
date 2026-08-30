<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
  Activity,
  Bell,
  BotIcon,
  BookOpen,
  Blocks,
  Brackets,
  FileText,
  Flag,
  HashIcon,
  Heart,
  House,
  Layers,
  LayoutGrid,
  LogIn,
  MapPin,
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
  Users,
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
// Ziggy group, so they must be computed and gated by `user`. Building them
// eagerly would call route() for a logged-out visitor (whose `guest` group
// lacks these names) and Ziggy would throw.
const mainNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        { title: 'Overlays', href: '/templates?filter=mine&type=static', icon: Layers },
        { title: 'Alerts', href: '/templates?filter=mine&type=alert', icon: Bell },
        { title: 'Blocks', href: '/templates?filter=mine&type=block', icon: Blocks },
        { title: 'Lists', href: route('lists.index'), icon: ListIcon },
        { title: 'Kits', href: route('kits.index'), icon: LayoutGrid },
      ]
    : [],
);
const alertsNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        { title: 'Recent', href: route('dashboard.recents'), icon: Activity },
        { title: 'Streams', href: route('dashboard.stream-sessions'), icon: Radio },
        { title: 'Routes', href: route('dashboard.gps-sessions'), icon: MapPin },
      ]
    : [],
);

const learnNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        { title: 'Help', href: route('help'), target: '_blank', icon: BookOpen },
        { title: 'Reference', href: route('help.reference'), target: '_blank', icon: Brackets },
        { title: 'Updates', href: route('updates.index'), icon: Newspaper },
      ]
    : [],
);

const helpNavItems: NavItem[] = [
  { title: 'Help', href: '/help', icon: BookOpen },
  { title: 'Updates', href: '/updates', icon: Newspaper },
  { title: 'Conditional Tags', href: '/help/conditionals', icon: Brackets },
  { title: 'Controls', href: '/help/controls', icon: SlidersHorizontal },
  { title: 'Math Engine', href: '/help/math', icon: Sigma },
  { title: 'Formatting Pipes', href: '/help/formatting', icon: Pipette },
  { title: 'Twitch Chat Bot', href: '/help/bot', icon: BotIcon },
  { title: 'Free Resources', href: '/help/resources', icon: BookOpen },
  { title: 'Why Ko-fi', href: '/help/why-kofi', icon: Heart },
  { title: 'Manifesto', href: '/help/manifesto', icon: FileText },
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
    { title: 'Tokens', href: route('admin.tokens.index'), icon: HashIcon },
    { title: 'Sessions', href: route('admin.sessions.index'), icon: House },
    { title: 'Bans', href: route('admin.bans.index'), icon: ShieldBan },
    { title: 'User Reports', href: route('admin.reports.index'), icon: Flag },
    { title: 'Access Logs', href: route('admin.logs.index'), icon: ScrollText },
    { title: 'Audit Log', href: route('admin.audit.index'), icon: FileText },
    { title: 'Lockdown', href: route('admin.lockdown.index'), icon: ShieldAlert },
    { title: 'Updates', href: route('admin.updates.index'), icon: Newspaper },
  ];
});
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton as-child>
            <!-- Guests land on '/', which is a plain Blade view rather than an
                 Inertia page, so it needs a real anchor - an Inertia <Link>
                 would XHR it and get the non-Inertia error dialog. -->
            <Link v-if="user" :href="route('dashboard.index')">
              <AppLogo />
            </Link>
            <a v-else href="/" class="cursor-pointer">
              <AppLogo />
            </a>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain v-if="user && mainNavItems.length > 0" label="My stuff" :items="mainNavItems" />
      <NavMain v-if="user && alertsNavItems.length > 0" label="My events" :items="alertsNavItems" />
      <NavMain v-if="user && learnNavItems.length > 0" label="Learn" :items="learnNavItems" />
      <NavMain v-if="isAdmin" label="Admin" :items="adminNavItems" />
      <NavMain v-if="!user" label="Learn" :items="helpNavItems" />
      <div v-if="user" class="px-4 pt-2 text-[11px] group-data-[collapsible=icon]:hidden">
        <kbd class="rounded-sm border border-violet-500 px-1 py-0.5 text-[10px] dark:border-violet-400">Ctrl</kbd> +
        <kbd class="rounded-sm border border-violet-500 px-1 py-0.5 text-[10px] dark:border-violet-400">K</kbd> shortcuts
        <div class="mt-2 h-0" />
        <kbd class="rounded-sm border border-violet-500 px-1 py-0.5 text-[10px] dark:border-violet-400">Ctrl</kbd> +
        <kbd class="rounded-sm border border-violet-500 px-1 py-0.5 text-[10px] dark:border-violet-400">Space</kbd> go to
        <div class="mt-2 h-0" />
        <kbd class="rounded-sm border border-violet-500 px-1 py-0.5 text-[10px] dark:border-violet-400">Alt</kbd> +
        <kbd class="rounded-sm border border-violet-500 px-1 py-0.5 text-[10px] dark:border-violet-400">R</kbd> Reference
      </div>
    </SidebarContent>

    <SidebarFooter>
      <SidebarMenu v-if="!user">
        <SidebarMenuItem>
          <SidebarMenuButton as-child>
            <a href="/auth/redirect/twitch" class="flex cursor-pointer items-center">
              <LogIn class="mr-2 h-4 w-4" />
              Log in
            </a>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
      <div class="px-3 pb-2 text-[10px] text-muted-foreground/50 group-data-[collapsible=icon]:hidden">
        <a
          :href="`https://github.com/jasperfrontend/overlabels/commit/${commitHash}`"
          target="_blank"
          rel="noopener noreferrer"
          class="transition-colors hover:text-muted-foreground hover:underline"
        >
          {{ commitHash }}
        </a>
        |
        <a
          href="https://uptime.overlabels.com/"
          target="_blank"
          rel="noopener noreferrer"
          class="transition-colors hover:text-muted-foreground hover:underline"
          >Uptime</a
        >
      </div>
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
