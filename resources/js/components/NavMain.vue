<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';

const props = withDefaults(
  defineProps<{
    label: string | null | undefined;
    items: NavItem[];
    // Show each item's chord letter as a key tip, the way Word shows KeyTips
    // once Alt is pressed. The sidebar turns this on while G is armed.
    keyTips?: boolean;
    // The letter of the chord that just fired. Its tip alone stays up, with a
    // bounce, while the page it points at loads - the confirmation that the
    // keystroke landed. Independent of `keyTips`, which drops the moment the
    // chord completes.
    confirmed?: string | null;
  }>(),
  { keyTips: false, confirmed: null },
);

function showTip(item: NavItem): boolean {
  if (!item.shortcut) return false;
  return props.keyTips || props.confirmed === item.shortcut;
}

const page = usePage();

// A filled square the size of a key cap. Dark text on violet reads on both the
// plain row and the active row (which is itself violet), and the shadow lifts
// it off the active row where the two violets sit closest.
const keyTipClass =
  'flex size-5 shrink-0 items-center justify-center rounded-sm bg-violet-400 font-mono text-[11px] leading-none font-semibold text-violet-950 shadow-sm dark:bg-violet-300 group-data-[collapsible=icon]:hidden';

const isActive = (href: string): boolean => {
  let itemPath: string;
  let itemSearch: string;
  try {
    const url = new URL(href);
    itemPath = url.pathname;
    itemSearch = url.search;
  } catch {
    const [path, search] = href.split('?');
    itemPath = path;
    itemSearch = search ? `?${search}` : '';
  }
  const [currentPath, currentSearch] = page.url.split('?');
  if (currentPath !== itemPath) {
    return false;
  }
  // Declared params are constraints; params the item doesn't declare
  // (search, page, sort) are ignored - same rule as HelpContext matching.
  const currentParams = new URLSearchParams(currentSearch ?? '');
  return Array.from(new URLSearchParams(itemSearch)).every(([key, value]) => currentParams.get(key) === value);
};
</script>

<template>
  <SidebarGroup class="mb-2 px-2 py-0">
    <SidebarGroupLabel v-if="label" class="text-violet-400 dark:text-violet-300">{{ label }}</SidebarGroupLabel>
    <SidebarMenu>
      <SidebarMenuItem v-for="item in items" :key="item.title">
        <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title">
          <!-- The trailing icon makes the title span no longer :last-child, so it needs
               `truncate` spelled out here - the button's own selector stops matching it. -->
          <Link v-if="item.target" :href="item.href" :target="item.target" rel="noopener noreferrer" class="group/nav-link">
            <component :is="item.icon" />
            <span class="truncate">{{ item.title }}</span>
            <ExternalLink
              class="ml-auto opacity-0 transition-opacity group-hover/nav-link:opacity-100 group-focus-visible/nav-link:opacity-100 group-data-[collapsible=icon]:hidden"
            />
            <kbd v-if="showTip(item)" :class="[keyTipClass, { 'key-tip-confirm': confirmed === item.shortcut }]" aria-hidden="true">{{
              item.shortcut!.toUpperCase()
            }}</kbd>
          </Link>
          <Link v-else :href="item.href">
            <component :is="item.icon" />
            <!-- `truncate` spelled out for the same reason as above: the key tip can follow it. -->
            <span class="truncate">{{ item.title }}</span>
            <kbd v-if="showTip(item)" :class="[keyTipClass, 'ml-auto', { 'key-tip-confirm': confirmed === item.shortcut }]" aria-hidden="true">{{
              item.shortcut!.toUpperCase()
            }}</kbd>
          </Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>
</template>
