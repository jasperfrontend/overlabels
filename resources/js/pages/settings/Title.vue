<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Label } from '@/components/ui/label';
import { useLivingTitle } from '@/composables/useLivingTitle';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { AlertTriangle, Gamepad2, Pause, Play, Save, Search, Sparkles, Tv } from '@lucide/vue';

interface LivingTitleProps {
  enabled: boolean;
  template: string;
  last_written: string | null;
  written_at: number | null;
  paused: boolean;
  paused_title: string | null;
  last_error: string | null;
}

interface ChannelProps {
  title: string;
  game_id: string;
  game_name: string;
}

interface Category {
  id: string;
  name: string;
  box_art_url: string;
}

const props = defineProps<{
  livingTitle: LivingTitleProps;
  hasScope: boolean;
  channel: ChannelProps | null;
  maxLength: number;
  debounceSeconds: number;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Stream title', href: '/settings/title' },
];

// ── Title ─────────────────────────────────────────────────────────────────

const enabled = ref(props.livingTitle.enabled);
const template = ref(props.livingTitle.template);

// The pause and the last error come from the live state, not the prop: a
// channel.update arriving while this page is open must flip the banner
// without a reload. Falls back to the prop until the first broadcast.
const live = useLivingTitle();
const paused = computed(() => live.state.value?.paused ?? props.livingTitle.paused);
const pausedTitle = computed(() => live.state.value?.paused_title ?? props.livingTitle.paused_title);
const lastError = computed(() =>
  live.state.value && 'last_error' in live.state.value ? (live.state.value.last_error ?? null) : props.livingTitle.last_error,
);

const saving = ref(false);
const saveError = ref('');

function save() {
  saving.value = true;
  saveError.value = '';

  router.patch(
    route('settings.title.update'),
    { enabled: enabled.value, template: template.value },
    {
      preserveScroll: true,
      onError: (errors) => {
        saveError.value = errors.template ?? errors.enabled ?? 'Saving failed.';
      },
      onFinish: () => {
        saving.value = false;
      },
    },
  );
}

const resuming = ref(false);

function resume() {
  resuming.value = true;
  router.post(route('settings.title.resume'), {}, { preserveScroll: true, onFinish: () => (resuming.value = false) });
}

// ── Preview: the template as typed, against the account's real values ─────

const previewOutput = ref('');
const previewLength = ref(0);
const previewTruncated = ref(false);
const previewLoading = ref(false);
const previewError = ref('');
let previewTimer: ReturnType<typeof setTimeout> | null = null;

async function refreshPreview() {
  if (template.value.trim() === '') {
    previewOutput.value = '';
    previewLength.value = 0;
    previewTruncated.value = false;
    previewError.value = '';
    return;
  }

  previewLoading.value = true;

  try {
    const { data } = await axios.post(route('settings.title.preview'), { template: template.value });
    previewOutput.value = data.resolved;
    previewLength.value = data.length;
    previewTruncated.value = data.truncated;
    previewError.value = '';
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } };
    previewError.value = err.response?.data?.message ?? 'Preview failed.';
  } finally {
    previewLoading.value = false;
  }
}

watch(
  template,
  () => {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, 400);
  },
  { immediate: true },
);

const overLength = computed(() => previewLength.value > props.maxLength);

// ── Category: search on debounce, set on click, never remembered ──────────

const categoryQuery = ref('');
const categories = ref<Category[]>([]);
const searching = ref(false);
const settingCategory = ref<string | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

async function searchCategories() {
  const q = categoryQuery.value.trim();

  if (q.length < 2) {
    categories.value = [];
    return;
  }

  searching.value = true;

  try {
    const { data } = await axios.get(route('settings.title.categories'), { params: { q } });
    categories.value = data.categories ?? [];
  } catch {
    categories.value = [];
  } finally {
    searching.value = false;
  }
}

watch(categoryQuery, () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(searchCategories, 300);
});

function setCategory(category: Category) {
  settingCategory.value = category.id;

  router.post(
    route('settings.title.category'),
    { id: category.id, name: category.name },
    {
      preserveScroll: true,
      onSuccess: () => {
        categoryQuery.value = '';
        categories.value = [];
      },
      onFinish: () => {
        settingCategory.value = null;
      },
    },
  );
}

function boxArt(url: string): string {
  return url.replace('{width}', '52').replace('{height}', '72');
}

onBeforeUnmount(() => {
  if (previewTimer) clearTimeout(previewTimer);
  if (searchTimer) clearTimeout(searchTimer);
});
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Stream title" />

    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Living title"
          description="Write your stream title with tags in it, and Overlabels keeps it true on Twitch. A follow arrives, the number in your title moves."
        />

        <div v-if="!hasScope" class="flex items-start gap-3 border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
          <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-400" />
          <div class="space-y-2">
            <p>Twitch has not yet allowed Overlabels to change your title. That is a one-time permission, added for this feature.</p>
            <a href="/auth/redirect/twitch?reauth=1" class="btn btn-sm btn-secondary cursor-pointer">Reauthorize Twitch</a>
          </div>
        </div>

        <div v-if="channel && (channel.title || channel.game_name)" class="border border-sidebar-border p-4">
          <div class="mb-2 flex items-center gap-2 text-xs tracking-wide text-muted-foreground uppercase">
            <Tv class="size-3.5" />
            On Twitch right now
          </div>
          <p v-if="channel.title" class="text-base font-medium wrap-break-word">{{ channel.title }}</p>
          <p v-if="channel.game_name" class="mt-1.5 flex items-center gap-1.5 text-sm text-muted-foreground">
            <Gamepad2 class="size-3.5 shrink-0" />
            <span>{{ channel.game_name }}</span>
          </p>
        </div>

        <div v-if="paused" class="flex items-start gap-3 border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
          <Pause class="mt-0.5 size-4 shrink-0 text-amber-400" />
          <div class="space-y-2">
            <p>
              <span class="font-medium">Paused.</span> Your title was changed on Twitch to <span class="font-medium">"{{ pausedTitle }}"</span>, so
              Overlabels stopped writing rather than overwrite you. Resume when you want the template back in charge.
            </p>
            <button type="button" :disabled="resuming" class="btn btn-sm btn-secondary cursor-pointer disabled:cursor-not-allowed" @click="resume">
              <Play class="mr-1 size-3.5" />
              {{ resuming ? 'Resuming...' : 'Resume' }}
            </button>
          </div>
        </div>

        <div v-else-if="lastError" class="flex items-start gap-3 border border-rose-500/40 bg-rose-500/10 p-4 text-sm">
          <AlertTriangle class="mt-0.5 size-4 shrink-0 text-rose-400" />
          <p>{{ lastError }}</p>
        </div>

        <div class="space-y-3">
          <label class="flex cursor-pointer items-start gap-3">
            <input v-model="enabled" type="checkbox" class="mt-1 size-4 cursor-pointer" />
            <span class="text-sm">
              <span class="font-medium">Keep my title updated</span>
              <span class="block text-muted-foreground">
                Re-rendered on every event and every control change, written to Twitch at most once every {{ debounceSeconds }} seconds and only when
                the text actually changed.
              </span>
            </span>
          </label>
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <Label for="title-template">Title template</Label>
            <span class="text-xs whitespace-nowrap" :class="overLength ? 'text-rose-400' : 'text-muted-foreground'">
              {{ previewLength }} / {{ maxLength }} chars
            </span>
          </div>
          <textarea
            id="title-template"
            v-model="template"
            rows="2"
            class="input-border w-full font-mono text-sm"
            placeholder="Road to 2K | [[[followers_total]]] followers | playing [[[channel_game]]]"
          ></textarea>
          <p class="text-xs text-muted-foreground">
            Any tag from <a href="/tags" target="_blank" class="cursor-pointer underline">your tags</a>, any control as
            <code class="font-mono">[[[c:key]]]</code>, plus <code class="font-mono">[[[if:...]]]</code> and pipes like
            <code class="font-mono">|number</code>. Not <code class="font-mono">[[[channel_title]]]</code>: once this is on, that IS the title.
          </p>
        </div>

        <div class="border border-sidebar-border bg-sidebar-accent/30 p-4">
          <div class="mb-2 flex items-center gap-2 text-xs tracking-wide text-muted-foreground uppercase">
            <Sparkles class="size-3.5" />
            Preview with your real values
            <span v-if="previewLoading" class="normal-case italic">resolving...</span>
          </div>
          <div class="min-h-10 text-sm">
            <div v-if="previewError" class="flex items-center gap-2 text-rose-400">
              <AlertTriangle class="size-4 shrink-0" />
              {{ previewError }}
            </div>
            <div v-else-if="!previewOutput" class="text-muted-foreground italic">(empty)</div>
            <div v-else class="wrap-break-word transition-opacity duration-150" :class="{ 'opacity-50': previewLoading }">
              {{ previewOutput }}
            </div>
          </div>
          <p v-if="previewTruncated" class="mt-2 text-xs text-rose-400">
            Renders to {{ previewLength }} characters. Twitch allows {{ maxLength }}, so it is cut there.
          </p>
        </div>

        <div class="flex flex-col items-start gap-3">
          <button
            type="button"
            :disabled="saving"
            class="btn btn-primary cursor-pointer disabled:cursor-not-allowed disabled:opacity-60"
            @click="save"
          >
            <Save class="mr-2 size-4" />
            {{ saving ? 'Saving...' : 'Save title' }}
          </button>
          <p v-if="saveError" class="text-sm text-destructive">{{ saveError }}</p>
        </div>

        <hr class="border-sidebar-border" />

        <div class="space-y-3">
          <HeadingSmall
            title="Category"
            description="Set your stream category from here. Picking one sets it on Twitch right away, once - your title never changes it back."
          />
          <div class="relative">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <input
              v-model="categoryQuery"
              type="text"
              class="input-border w-full pl-9 text-sm"
              placeholder="Search categories"
              autocomplete="off"
              aria-label="Search categories"
            />
          </div>
          <p v-if="searching" class="text-xs text-muted-foreground italic">searching...</p>
          <ul v-else-if="categories.length > 0" class="divide-y divide-sidebar-border border border-sidebar-border">
            <li v-for="category in categories" :key="category.id">
              <button
                type="button"
                :disabled="settingCategory !== null"
                class="flex w-full cursor-pointer items-center gap-3 p-2 text-left text-sm hover:bg-sidebar-accent/40 disabled:cursor-not-allowed"
                @click="setCategory(category)"
              >
                <img v-if="category.box_art_url" :src="boxArt(category.box_art_url)" alt="" class="h-9 w-auto shrink-0" loading="lazy" />
                <span class="grow">{{ category.name }}</span>
                <span v-if="settingCategory === category.id" class="text-xs text-muted-foreground italic">setting...</span>
                <span v-else-if="channel && channel.game_id === category.id" class="text-xs text-muted-foreground">current</span>
              </button>
            </li>
          </ul>
          <p v-else-if="categoryQuery.trim().length >= 2" class="text-xs text-muted-foreground">No categories match.</p>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
