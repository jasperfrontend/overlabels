<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

interface NavGroup {
  label: string | null;
  hint?: string;
  items: NavItem[];
}

const sidebarNavGroups: NavGroup[] = [
  {
    label: null,
    items: [
      { title: 'Account', href: '/settings/account' },
      { title: 'Chat', href: '/settings/chat' },
      { title: 'Integrations', href: '/settings/integrations' },
      { title: 'Bot commands', href: '/settings/bot/commands' },
      { title: 'Bot aliases', href: '/settings/bot/aliases' },
      { title: 'Triggers', href: '/settings/triggers' },
      { title: 'Wiring', href: '/settings/wiring' },
      { title: 'Usage', href: '/settings/usage' },
      { title: 'Controls', href: '/settings/controls' },
    ],
  },
  {
    label: 'Developer tools',
    hint: 'Sensitive data. Do not open on stream.',
    items: [
      { title: 'Token Generator', href: '/settings/tokens' },
      { title: 'Tags Generator', href: '/settings/tags' },
      { title: 'Your Twitch Data', href: '/settings/twitchdata' },
      { title: 'Testing Guide', href: '/settings/testing' },
    ],
  },
];

const page = usePage();

const currentPath = page.url.split('?')[0];

// Prefix match, so /settings/integrations/kofi and /settings/bot/commands/create
// still light up their menu item. Exact match left every sub-page unhighlighted.
const isActive = (href: string) => currentPath === href || currentPath.startsWith(href + '/');
</script>

<template>
  <div class="px-4 py-6">
    <Heading title="Settings" description="Manage your account, integrations, and overlay defaults." />

    <div class="mt-4 flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:space-x-12">
      <aside class="w-full max-w-xl lg:w-48">
        <nav class="flex flex-col space-y-1 space-x-0">
          <template v-for="(group, index) in sidebarNavGroups" :key="group.label ?? index">
            <div v-if="group.label" class="px-4 pt-4 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
              {{ group.label }}
            </div>
            <p v-if="group.hint" class="px-4 pb-1 text-xs text-muted-foreground">
              {{ group.hint }}
            </p>

            <Link
              v-for="item in group.items"
              :key="item.href"
              :href="item.href"
              class="btn btn-sm btn-square btn-ghost justify-start border-l-2 border-transparent px-4 py-2 text-sm font-medium hover:border-sidebar-accent hover:bg-sidebar-accent/5"
              :class="{ 'bg-sidebar-accent text-violet-500 dark:text-violet-400': isActive(item.href) }"
            >
              {{ item.title }}
            </Link>
          </template>
        </nav>
      </aside>

      <Separator class="my-6 md:hidden" />

      <div class="min-w-0 flex-1">
        <section class="max-w-4xl space-y-12">
          <slot />
        </section>
      </div>
    </div>
  </div>
</template>
