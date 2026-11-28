<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import {
  Activity,
  Bell,
  BotIcon,
  BookOpen,
  Blocks,
  Brackets,
  FileText,
  Filter,
  Flag,
  HashIcon,
  Heart,
  House,
  Layers,
  LayoutDashboard,
  LayoutGrid,
  LogIn,
  MapPin,
  Newspaper,
  Lightbulb,
  Package,
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
import { computed, onMounted, ref } from 'vue';
import AppLogo from './AppLogo.vue';
import DiscordIcon from './DiscordIcon.vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => page.props.isAdmin);
const commitHash = __COMMIT_HASH__;

// These arrays call route() for routes that only exist in the authenticated
// Ziggy group, so they must be computed and gated by `user`. Building them
// eagerly would call route() for a logged-out visitor (whose `guest` group
// lacks these names) and Ziggy would throw.
const mainNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        { title: 'Dashboard', href: route('dashboard.index'), icon: LayoutDashboard, shortcut: 'd' },
        { title: 'Products', href: '/products', icon: Package, shortcut: 'p' },
        { title: 'Overlays', href: '/templates?filter=mine&type=static', icon: Layers, shortcut: 'o' },
        { title: 'Alerts', href: '/templates?filter=mine&type=alert', icon: Bell, shortcut: 'a' },
        { title: 'Blocks', href: '/templates?filter=mine&type=block', icon: Blocks, shortcut: 'b' },
        { title: 'Lists', href: route('lists.index'), icon: ListIcon, shortcut: 'l' },
        { title: 'Kits', href: route('kits.index'), icon: LayoutGrid, shortcut: 'k' },
      ]
    : [],
);
const alertsNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        // Reference holds the R (it is Alt+R already), so Recent takes E for
        // "recent events" and Routes its second letter.
        { title: 'Recent', href: route('dashboard.recents'), icon: Activity, shortcut: 'e' },
        { title: 'Streams', href: route('dashboard.stream-sessions'), icon: Radio, shortcut: 's' },
        { title: 'Routes', href: route('dashboard.gps-sessions'), icon: MapPin, shortcut: 't' },
      ]
    : [],
);

const learnNavItems = computed<NavItem[]>(() =>
  user.value
    ? [
        { title: 'Help', href: route('help'), target: '_blank', icon: BookOpen, shortcut: 'h' },
        // Alt+R opens the reference search; G then R opens the reference itself.
        { title: 'Reference', href: route('help.reference'), target: '_blank', icon: Brackets, shortcut: 'r' },
        { title: 'Updates', href: route('updates.index'), icon: Newspaper, shortcut: 'u' },
        // Off-site: target _blank also stops Inertia's Link from trying to visit it.
        { title: 'Discord', href: page.props.discordUrl, target: '_blank', icon: DiscordIcon },
      ]
    : [],
);

// "G then <letter>" jumps to a main navigation page, the Gmail / GitHub /
// Linear convention. The letter lives on the nav item so the chord and the
// destination cannot drift apart. Registered here rather than in the layout
// because the sidebar is where the destinations are declared.
const { register, armedPrefix } = useKeyboardShortcuts();

// Key tips show only while a bare G waits for its letter, not for any other
// prefix a page might arm later.
const gArmed = computed(() => armedPrefix.value.length === 1 && armedPrefix.value[0].length === 1 && armedPrefix.value[0][0] === 'g');

// The letter of the chord that just fired. Completing a chord clears the armed
// prefix, so every tip would vanish at the exact moment the user wants to see
// that theirs landed; this keeps that one tip up, bouncing, until the visit
// it started has finished. A bounded timer backs the visit callbacks so a
// tip can never stick.
const confirmedShortcut = ref<string | null>(null);
let confirmTimer: ReturnType<typeof setTimeout> | null = null;
const CONFIRM_FALLBACK_MS = 4000;
const CONFIRM_NEW_TAB_MS = 900;

function holdKeyTip(letter: string, ms: number): () => void {
  confirmedShortcut.value = letter;
  if (confirmTimer !== null) clearTimeout(confirmTimer);
  const done = () => {
    if (confirmTimer !== null) clearTimeout(confirmTimer);
    confirmTimer = null;
    confirmedShortcut.value = null;
  };
  confirmTimer = setTimeout(done, ms);
  return done;
}

// Whether a nav item points at the page already open: same path, and every
// query param the item declares matches (undeclared ones like page or search
// are ignored, the same rule NavMain uses to highlight the active item).
function isCurrentPage(href: string): boolean {
  const target = new URL(href, window.location.origin);
  if (target.origin !== window.location.origin || target.pathname !== window.location.pathname) return false;
  const current = new URLSearchParams(window.location.search);
  let matches = true;
  target.searchParams.forEach((value, key) => {
    if (current.get(key) !== value) matches = false;
  });
  return matches;
}

// The chords are rate-limited to one at a time. A visit started by a chord
// blocks the next until it finishes, and a new-tab chord blocks for the length
// of its bounce, so repeating G then D (or G then H) cannot queue up a stream
// of reloads or open a pile of tabs.
let chordBusy = false;

onMounted(() => {
  const items = [...mainNavItems.value, ...alertsNavItems.value, ...learnNavItems.value];
  for (const item of items) {
    if (!item.shortcut) continue;
    register(
      `go-to-${item.title.toLowerCase()}`,
      `g ${item.shortcut}`,
      () => {
        if (chordBusy) return;

        // Same behaviour as clicking the item: the two Learn pages open in a
        // new tab, so there is no visit to wait on - just the bounce.
        if (item.target === '_blank') {
          chordBusy = true;
          holdKeyTip(item.shortcut!, CONFIRM_NEW_TAB_MS);
          setTimeout(() => (chordBusy = false), CONFIRM_NEW_TAB_MS);
          window.open(item.href, '_blank', 'noopener,noreferrer');
          return;
        }

        // Already there: bounce the tip so the keys visibly landed, and load nothing.
        if (isCurrentPage(item.href)) {
          holdKeyTip(item.shortcut!, CONFIRM_NEW_TAB_MS);
          return;
        }

        chordBusy = true;
        const tipDone = holdKeyTip(item.shortcut!, CONFIRM_FALLBACK_MS);
        // Released when the visit settles, or by the same bounded fallback as
        // the tip, so a visit that never reports back cannot lock the chords.
        const release = setTimeout(() => (chordBusy = false), CONFIRM_FALLBACK_MS);
        const done = () => {
          clearTimeout(release);
          chordBusy = false;
          tipDone();
        };
        router.visit(item.href, { onFinish: done, onCancel: done });
      },
      { description: item.title, group: 'Go to' },
    );
  }
});

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
    { title: 'Funnel', href: route('admin.funnel.index'), icon: Filter },
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
    { title: 'Highlights', href: route('admin.highlights.index'), icon: Lightbulb },
    { title: 'Twitch Bot', href: route('admin.twitchbot.index'), icon: BotIcon },
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
      <NavMain v-if="user && mainNavItems.length > 0" label="My stuff" :items="mainNavItems" :key-tips="gArmed" :confirmed="confirmedShortcut" />
      <NavMain v-if="user && alertsNavItems.length > 0" label="My events" :items="alertsNavItems" :key-tips="gArmed" :confirmed="confirmedShortcut" />
      <NavMain v-if="user && learnNavItems.length > 0" label="Learn" :items="learnNavItems" :key-tips="gArmed" :confirmed="confirmedShortcut" />
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
