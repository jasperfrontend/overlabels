<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import type { AppPageProps } from '@/types';
import {
  Activity,
  Bell,
  BookOpen,
  Brackets,
  Coffee,
  FileText,
  Heart,
  House,
  Layers,
  LayoutGrid,
  Megaphone,
  Pipette,
  Search,
  Settings,
  Shield,
  SlidersHorizontal,
  Terminal,
} from 'lucide-vue-next';

const page = usePage<AppPageProps>();
const isAdmin = computed(() => page.props.isAdmin);

const open = ref(false);
const query = ref('');
const selectedIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

interface PaletteItem {
  id: string;
  label: string;
  section: string;
  href: string;
  icon: typeof House;
  keywords?: string[];
}

// Curated list of navigable destinations, not a raw route dump.
// This is intentional: users want destinations, not POST endpoints.
const items = computed<PaletteItem[]>(() => {
  const list: PaletteItem[] = [
    { id: 'dashboard', label: 'Dashboard', section: 'Navigation', href: route('dashboard.index'), icon: House, keywords: ['home', 'start'] },
    { id: 'overlays', label: 'My overlays', section: 'Navigation', href: '/templates?direction=desc&filter=mine&search=&type=static', icon: Layers, keywords: ['templates', 'static'] },
    { id: 'alerts', label: 'My alerts', section: 'Navigation', href: '/templates?direction=desc&filter=mine&search=&type=alert', icon: Bell, keywords: ['notifications'] },
    { id: 'recents', label: 'Recent events', section: 'Navigation', href: route('dashboard.recents'), icon: Activity, keywords: ['history', 'activity'] },
    { id: 'alerts-builder', label: 'Alerts builder', section: 'Navigation', href: route('events.index'), icon: Megaphone, keywords: ['events', 'mappings'] },
    { id: 'kits', label: 'Overlay kits', section: 'Navigation', href: route('kits.index'), icon: LayoutGrid, keywords: ['bundles', 'packages'] },
    { id: 'create-overlay', label: 'Create new overlay', section: 'Navigation', href: route('templates.create'), icon: Layers, keywords: ['new', 'template', 'add'] },

    { id: 'appearance', label: 'Theme Settings', section: 'Settings', href: route('settings.appearance'), icon: Settings, keywords: ['dark', 'light', 'mode', 'theme'] },
    { id: 'integrations', label: 'Integrations', section: 'Settings', href: route('settings.integrations.index'), icon: Coffee, keywords: ['kofi', 'streamlabs', 'connect'] },

    { id: 'help-tags', label: 'Conditional Tags', section: 'Learn', href: route('help'), icon: Brackets, keywords: ['syntax', 'documentation', 'docs'] },
    { id: 'help-controls', label: 'Controls', section: 'Learn', href: route('help.controls'), icon: SlidersHorizontal, keywords: ['documentation', 'docs'] },
    { id: 'help-formatting', label: 'Formatting Pipes', section: 'Learn', href: route('help.formatting'), icon: Pipette, keywords: ['duration', 'currency', 'number', 'date', 'pipe', 'format'] },
    { id: 'resources', label: 'Free Resources', section: 'Learn', href: route('resources'), icon: BookOpen, keywords: ['links', 'tools'] },
    { id: 'why-kofi', label: 'Why Ko-fi', section: 'Learn', href: route('why-kofi'), icon: Heart, keywords: ['donate', 'support'] },
    { id: 'manifesto', label: 'Manifesto', section: 'Learn', href: route('manifesto'), icon: FileText, keywords: ['about', 'philosophy'] },

    { id: 'tokens', label: 'Token Generator', section: 'Tools', href: route('tokens.index'), icon: Shield, keywords: ['access', 'auth'] },
    { id: 'twitchdata', label: 'Your Twitch Data', section: 'Tools', href: route('twitchdata'), icon: Terminal, keywords: ['api', 'debug', 'refresh'] },
    { id: 'testing', label: 'Testing Guide', section: 'Tools', href: route('testing.index'), icon: Terminal, keywords: ['debug', 'test'] },
  ];

  if (isAdmin.value) {
    list.push(
      { id: 'admin-dashboard', label: 'Admin Dashboard', section: 'Admin', href: route('admin.dashboard'), icon: Shield, keywords: ['admin'] },
      { id: 'admin-users', label: 'Admin Users', section: 'Admin', href: route('admin.users.index'), icon: Shield, keywords: ['admin', 'accounts'] },
      { id: 'admin-templates', label: 'Admin Overlays', section: 'Admin', href: route('admin.templates.index'), icon: Shield, keywords: ['admin'] },
      { id: 'admin-events', label: 'Admin Events', section: 'Admin', href: route('admin.events.index'), icon: Shield, keywords: ['admin'] },
      { id: 'admin-audit', label: 'Audit Log', section: 'Admin', href: route('admin.audit.index'), icon: Shield, keywords: ['admin', 'log'] },
      { id: 'admin-lockdown', label: 'Lockdown', section: 'Admin', href: route('admin.lockdown.index'), icon: Shield, keywords: ['admin', 'emergency'] },
    );
  }

  return list;
});

function fuzzyMatch(text: string, search: string): boolean {
  const searchLower = search.toLowerCase();
  const textLower = text.toLowerCase();

  // Direct substring match
  if (textLower.includes(searchLower)) return true;

  // Fuzzy: every character of search appears in text, in order
  let si = 0;
  for (let ti = 0; ti < textLower.length && si < searchLower.length; ti++) {
    if (textLower[ti] === searchLower[si]) si++;
  }
  return si === searchLower.length;
}

function matchScore(item: PaletteItem, search: string): number {
  const s = search.toLowerCase();
  const label = item.label.toLowerCase();

  // Exact starts-with gets highest priority
  if (label.startsWith(s)) return 3;
  // Substring match
  if (label.includes(s)) return 2;
  // Keyword match
  if (item.keywords?.some((kw) => kw.includes(s))) return 1;
  // Fuzzy match on label
  if (fuzzyMatch(label, s)) return 0.5;
  return -1;
}

const filtered = computed(() => {
  const q = query.value.trim();
  if (!q) return items.value;

  return items.value
    .map((item) => ({ item, score: matchScore(item, q) }))
    .filter(({ score }) => score > 0)
    .sort((a, b) => b.score - a.score)
    .map(({ item }) => item);
});

// Group by section for display
const grouped = computed(() => {
  const groups: { section: string; items: PaletteItem[] }[] = [];
  const seen = new Set<string>();

  for (const item of filtered.value) {
    if (!seen.has(item.section)) {
      seen.add(item.section);
      groups.push({ section: item.section, items: [] });
    }
    groups.find((g) => g.section === item.section)!.items.push(item);
  }
  return groups;
});

// Flat list for keyboard navigation indexing
const flatFiltered = computed(() => filtered.value);

watch(query, () => {
  selectedIndex.value = 0;
});

watch(open, (val) => {
  if (val) {
    query.value = '';
    selectedIndex.value = 0;
    nextTick(() => inputRef.value?.focus());
  }
});

function navigate(item: PaletteItem) {
  open.value = false;
  router.visit(item.href);
}

function onKeydown(e: KeyboardEvent) {
  const total = flatFiltered.value.length;
  if (!total) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedIndex.value = (selectedIndex.value + 1) % total;
    scrollToSelected();
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedIndex.value = (selectedIndex.value - 1 + total) % total;
    scrollToSelected();
  } else if (e.key === 'Enter') {
    e.preventDefault();
    const item = flatFiltered.value[selectedIndex.value];
    if (item) navigate(item);
  }
}

function scrollToSelected() {
  nextTick(() => {
    const el = document.querySelector('[data-palette-selected="true"]');
    el?.scrollIntoView({ block: 'nearest' });
  });
}

const { register } = useKeyboardShortcuts();

onMounted(() => {
  register('command-palette', 'ctrl+space', () => { open.value = !open.value; }, { description: 'Command palette' });
});
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-w-lg gap-0 p-0 overflow-hidden top-[35%]" @interact-outside="open = false">
      <DialogTitle class="sr-only">Command palette</DialogTitle>
      <div class="flex items-center gap-2 border-b px-3">
        <Search class="size-4 shrink-0 text-muted-foreground" />
        <input
          ref="inputRef"
          v-model="query"
          type="text"
          placeholder="Where do you want to go?"
          class="flex-1 bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground"
          @keydown="onKeydown"
        />
        <kbd class="text-[10px] text-muted-foreground/60 border rounded px-1.5 py-0.5">ESC</kbd>
      </div>

      <div class="max-h-72 overflow-y-auto p-1">
        <div v-if="flatFiltered.length === 0" class="p-4 text-center text-sm text-muted-foreground">
          No results found.
        </div>

        <template v-for="group in grouped" :key="group.section">
          <div class="px-2 pt-2 pb-1 text-[11px] font-medium text-muted-foreground/70 uppercase tracking-wide">
            {{ group.section }}
          </div>
          <button
            v-for="(item) in group.items"
            :key="item.id"
            :data-palette-selected="flatFiltered.indexOf(item) === selectedIndex"
            class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-sm cursor-pointer transition-colors"
            :class="flatFiltered.indexOf(item) === selectedIndex ? 'bg-accent text-accent-foreground' : 'text-foreground hover:bg-accent/50'"
            @click="navigate(item)"
            @mouseenter="selectedIndex = flatFiltered.indexOf(item)"
          >
            <component :is="item.icon" class="size-4 shrink-0 text-muted-foreground" />
            <span>{{ item.label }}</span>
          </button>
        </template>
      </div>

      <div class="border-t px-3 py-2 text-[11px] text-muted-foreground/60 flex items-center gap-3">
        <span><kbd class="border rounded px-1">&#8593;</kbd> <kbd class="border rounded px-1">&#8595;</kbd> navigate</span>
        <span><kbd class="border rounded px-1">Enter</kbd> go</span>
        <span><kbd class="border rounded px-1">Esc</kbd> close</span>
      </div>
    </DialogContent>
  </Dialog>
</template>
