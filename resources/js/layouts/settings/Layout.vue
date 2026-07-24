<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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
            { title: 'Integrations', href: '/settings/integrations' },
            { title: 'Usage', href: '/settings/usage' },
            { title: 'Controls', href: '/settings/controls' },
        ],
    },
    {
        label: 'Developer tools',
        hint: 'Contains sensitive data - avoid opening these on stream.',
        items: [
            { title: 'Token Generator', href: '/tokens' },
            { title: 'Tags Generator', href: '/tags' },
            { title: 'Your Twitch Data', href: '/twitchdata' },
            { title: 'Testing Guide', href: '/testing' },
        ],
    },
];

const page = usePage();

const currentPath = page.url.split('?')[0];
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Settings" description="Manage your account, integrations, and overlay defaults." />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:space-x-12 mt-4">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1 space-x-0">
                    <template v-for="(group, index) in sidebarNavGroups" :key="group.label ?? index">
                        <div
                            v-if="group.label"
                            class="px-4 pt-4 pb-1 text-xs font-medium text-muted-foreground uppercase tracking-wide"
                        >
                            {{ group.label }}
                        </div>
                        <p v-if="group.hint" class="px-4 pb-1 text-xs text-muted-foreground">
                            {{ group.hint }}
                        </p>
                        <Button
                            v-for="item in group.items"
                            :key="item.href"
                            variant="ghost"
                            :class="['w-full justify-start cursor-pointer', { 'bg-muted': currentPath === item.href }]"
                            as-child
                        >
                            <Link :href="item.href">
                                {{ item.title }}
                            </Link>
                        </Button>
                    </template>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
