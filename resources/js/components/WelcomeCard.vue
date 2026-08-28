<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Card } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { Activity, Bell, Cable, Layers, Undo2, Wrench, X } from '@lucide/vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);

const { getInitials } = useInitials();

// Per-device preference, like the hidden event types and the expanded control
// groups. Read synchronously during setup so a card that was dismissed on this
// device never paints before it is hidden again.
const STORAGE_KEY = 'overlabels:welcome-card-dismissed';

function loadDismissed(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) === '1';
  } catch {
    // Storage unavailable (private mode) - show the card.
    return false;
  }
}

function saveDismissed(value: boolean): void {
  try {
    if (value) localStorage.setItem(STORAGE_KEY, '1');
    else localStorage.removeItem(STORAGE_KEY);
  } catch {
    // Ignore storage failures - the in-memory ref still holds for this session.
  }
}

const dismissed = ref(loadDismissed());

function setDismissed(value: boolean): void {
  dismissed.value = value;
  saveDismissed(value);
}

// The four things the dashboard below already shows, as one destination each.
// Colours come from the documented palette in overlabels-buttons.css and are
// used here as a palette rather than for their action semantics: one per tile,
// so the four are told apart pre-attentively rather than by reading all four
// labels. Class strings stay full literals so Tailwind's scanner keeps them.
// Update: all buttons now just have the same classes, it's much cleaner.
// and since all buttons are equally "primary / important", this is fine.
const tiles = [
  {
    label: 'My overlays',
    icon: Layers,
    href: route('templates.index', { direction: 'desc', filter: 'mine', search: '', type: 'static' }),
    class: 'btn-primary',
    isNew: false,
    title: 'View, edit and create your static overlays',
  },
  {
    label: 'My alerts',
    icon: Bell,
    href: route('templates.index', { direction: 'desc', filter: 'mine', search: '', type: 'alert' }),
    class: 'btn-primary',
    isNew: false,
    title: 'View, edit and create your alerts',
  },
  {
    label: 'Recent events',
    icon: Activity,
    href: route('dashboard.recents'),
    class: 'btn-primary',
    isNew: false,
    title: 'View recent events like follows, donations, and more.',
  },
  {
    label: 'My settings',
    icon: Wrench,
    href: route('settings.account'),
    class: 'btn-primary',
    isNew: true,
    title: 'NEW: all your settings are now centralised in one place.',
  },
  {
    label: 'Wiring status',
    icon: Cable,
    href: route('wiring.index'),
    class: 'btn-primary',
    isNew: true,
    title: 'NEW: check if everything you have created is wired up properly.',
  },
];
</script>

<template>
  <!-- py-0 hands all vertical padding to the inner wrapper, so the card has one
       source of spacing rather than Card's py-4 stacked on top of it. -->
  <Card v-if="!dismissed" class="relative m-4 mb-6 rounded-md py-0">
    <button
      type="button"
      class="btn btn-plain btn-xs absolute top-2 right-2"
      title="Dismiss the welcome card"
      aria-label="Dismiss the welcome card"
      @click="setDismissed(true)"
    >
      <X class="h-4 w-4" />
    </button>

    <div class="flex flex-col items-center px-4 py-12 text-center">
      <Avatar class="size-20 ring-2 ring-violet-400/40 ring-offset-2 ring-offset-background">
        <AvatarImage v-if="user?.avatar" :src="user.avatar" :alt="user.name" />
        <AvatarFallback class="bg-neutral-200 text-xl font-semibold text-black dark:bg-neutral-700 dark:text-white">
          {{ getInitials(user?.name) }}
        </AvatarFallback>
      </Avatar>

      <h2 class="mt-4 text-2xl font-semibold text-foreground">Welcome, {{ user?.name }}</h2>

      <div class="mt-5 h-px w-full max-w-md bg-muted-foreground/10" />

      <div class="mt-5 grid w-full max-w-2xl grid-cols-2 gap-3 sm:grid-cols-4">
        <Tooltip v-for="tile in tiles" :key="tile.label">
          <TooltipTrigger as-child>
            <Link
              :href="tile.href"
              class="btn group relative flex-col gap-2 px-3 py-4 text-center leading-tight"
              :class="{
                'ring-2 ring-violet-400 ring-offset-2 ring-offset-background transition hover:ring-fuchsia-400 focus:ring-fuchsia-400': tile.isNew,
                [tile.class]: true,
              }"
            >
              <span
                v-if="tile.isNew"
                class="absolute top-2 right-2 h-auto rounded-full bg-violet-700 px-2 pt-0.5 pb-1 text-sm leading-none text-white transition-colors duration-300 group-hover:bg-fuchsia-400 dark:bg-violet-600 dark:group-hover:bg-fuchsia-600"
                aria-hidden="true"
                >new</span
              >
              <component :is="tile.icon" class="h-6 w-6" />
              <span>{{ tile.label }}</span>
            </Link>
          </TooltipTrigger>
          <TooltipContent side="top" :side-offset="6" class="max-w-56 text-center text-sm">
            {{ tile.title }}
          </TooltipContent>
        </Tooltip>
      </div>
    </div>
  </Card>

  <div v-else class="mt-4 mr-4 mb-6 flex justify-end">
    <button type="button" class="btn btn-chill btn-xs" @click="setDismissed(false)">
      <Undo2 class="mr-2 h-3.5 w-3.5" />
      Show welcome
    </button>
  </div>
</template>
