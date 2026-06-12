<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { CalendarIcon, Copy, EyeIcon, UserStar } from '@lucide/vue';

const page = usePage();
const userLocale = computed<string | undefined>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.locale || undefined;
});

const props = defineProps<{
  createdAt: string;
  updatedAt: string;
  viewCount: number;
  forkCount: number;
  templateTags?: string[] | null;
  forkParent?: { id: number; slug: string; name: string } | null;
  slug: string;
  owner: string;
}>();

const forkTitle = computed(() => {
  if (!props.forkParent) return '';
  return `Copied from "${props.forkParent.name}"`;
});

type SortMode = 'appearance' | 'alphabetical';
const sortMode = ref<SortMode>('appearance');

const sortedTags = computed(() => {
  if (!props.templateTags) return [];
  if (sortMode.value === 'alphabetical') {
    return [...props.templateTags].sort((a, b) => a.localeCompare(b));
  }
  return props.templateTags;
});

const copiedTag = ref<string | null>(null);
let copiedTimeout: ReturnType<typeof setTimeout> | null = null;

function copyTag(tag: string, event: MouseEvent) {
  const btn = event.currentTarget as HTMLElement;
  btn.style.minWidth = `${btn.offsetWidth}px`;
  const wrapped = `[[[${tag}]]]`;
  navigator.clipboard.writeText(wrapped);
  copiedTag.value = tag;
  if (copiedTimeout) clearTimeout(copiedTimeout);
  copiedTimeout = setTimeout(() => {
    copiedTag.value = null;
    btn.style.minWidth = '';
  }, 2000);
}
</script>

<template>
  <div>
    <div class="bg-background">
      <div class="max-w-2xl grid grid-cols-1 md:grid-cols-2 gap-2 rounded-sm p-4 text-sm">
        <div
          class="flex items-center"
          :title="`Created ${new Date(createdAt).toLocaleDateString(userLocale)}`"
        >
          <span class="text-violet-400"><CalendarIcon class="size-4 -mt-0.5" /></span>
          <span class="ml-2">Created: {{ new Date(createdAt).toLocaleDateString(userLocale) }}</span>
        </div>

        <div
          v-if="new Date(createdAt).toLocaleDateString(userLocale) !== new Date(updatedAt).toLocaleDateString(userLocale)"
          class="flex items-center"
          :title="`Last updated ${new Date(updatedAt).toLocaleDateString(userLocale)}`"
        >
          <span class="text-violet-400"><CalendarIcon class="size-4 -mt-0.5" /></span>
          <span class="ml-2">Last updated: {{ new Date(updatedAt).toLocaleDateString(userLocale) }}</span>
        </div>
        <div class="flex items-center" :title="`${viewCount} ${viewCount === 1 ? ' view' : 'views'}`">
          <span class="text-violet-400"><EyeIcon class="size-4 -mt-0.5" /></span>
          <span class="ml-2">{{ viewCount === 1 ? 'View' : 'Views' }}: {{ viewCount }}</span>
        </div>
        <div class="flex items-center" :title="`Owned by ${owner}`">
          <span class="text-violet-400"><UserStar class="size-4 -mt-0.5" /></span>
          <span class="ml-2">Owner: {{ owner }}</span>
        </div>
        <div class="flex items-center" :title="`Copies ${forkCount}`">
          <span class="text-violet-400"><Copy class="size-4 -mt-0.5" /></span>
          <span class="ml-2">Copies: {{ forkCount }}</span>
        </div>
        <div v-if="forkParent" class="col-span-2">
          <span class="text-violet-400">Copied from:</span>
          <Link :href="`/templates/${forkParent.id}`" class="ml-2 text-violet-400 hover:underline" :title="forkTitle">
            {{ forkParent.name }}
          </Link>
        </div>
      </div>
    </div>

    <div v-if="templateTags && templateTags.length > 0" class="rounded-sm bg-background p-4 text-sm">
      <div class="mb-2 flex items-center justify-between">
        <p class="text-muted-foreground">Template Tags Used</p>
        <div class="flex gap-1">
          <button
            type="button"
            class="cursor-pointer rounded px-2 py-0.5 text-xs transition-colors"
            :class="sortMode === 'appearance' ? 'bg-violet-500/20 text-violet-400' : 'text-muted-foreground hover:text-foreground'"
            @click="sortMode = 'appearance'"
          >
            Order of appearance
          </button>
          <button
            type="button"
            class="cursor-pointer rounded px-2 py-0.5 text-xs transition-colors"
            :class="sortMode === 'alphabetical' ? 'bg-violet-500/20 text-violet-400' : 'text-muted-foreground hover:text-foreground'"
            @click="sortMode = 'alphabetical'"
          >
            A - Z
          </button>
        </div>
      </div>
      <div class="flex flex-wrap gap-1">
        <button
          v-for="tag in sortedTags"
          :key="tag"
          type="button"
          class="btn btn-chill btn-xs cursor-pointer font-mono transition-colors"
          :class="copiedTag === tag ? 'ring-1 ring-green-500 dark:bg-green-300 text-accent-foreground dark:text-accent' : ''"
          :title="`Copy [[[${tag}]]] to clipboard`"
          @click="copyTag(tag, $event)"
        >
          {{ copiedTag === tag ? 'Copied!' : tag }}
        </button>
      </div>
    </div>
  </div>
</template>
