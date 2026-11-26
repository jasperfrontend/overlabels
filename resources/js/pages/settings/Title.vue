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
import { AlertTriangle, Gamepad2, PartyPopper, Pause, Play, Save, Search, Sparkles, Tv, X } from '@lucide/vue';
import SettingSwitch from '@/components/SettingSwitch.vue';

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

// "Thank your raiders": one click adds this to the end of the template. The
// condition hides the whole line until the first raid of the stream.
const RAIDERS_SNIPPET = ' [[[if:raiders]]]| Thanks raiders: [[[raiders]]][[[endif]]]';
const hasRaiders = computed(() => template.value.includes('[[[raiders'));

function addRaiders() {
  if (hasRaiders.value) return;
  const current = template.value.trimEnd();
  template.value = current === '' ? RAIDERS_SNIPPET.trimStart() : current + RAIDERS_SNIPPET;
}

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

// One save path. The heading's switch saves on/off at once, together with
// whatever is in the template box; the Save button keeps the switch as it is.
function save(nextEnabled: boolean = enabled.value) {
  enabled.value = nextEnabled;
  saving.value = true;
  saveError.value = '';

  router.patch(
    route('settings.title.update'),
    { enabled: nextEnabled, template: template.value },
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

// The picker opens inline, under the category it changes, so its results are
// never pushed below the bottom of the page.
const pickingCategory = ref(false);
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
        pickingCategory.value = false;
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
        <!-- The heading carries the on/off: "Living title" is the feature. -->
        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            title="Living title"
            description="Write your stream title with tags in it, and Overlabels keeps it true on Twitch. A follow arrives, the number in your title moves."
          />
          <label class="flex shrink-0 cursor-pointer items-center gap-2 pt-1 text-sm">
            <span :class="enabled ? 'text-foreground' : 'text-muted-foreground'">{{ enabled ? 'On' : 'Off' }}</span>
            <SettingSwitch :model-value="enabled" label="Keep my title updated" @update:model-value="save" />
          </label>
        </div>

        <div v-if="!hasScope" class="flex items-start gap-3 border border-[#1DAAFF]/40 bg-[#1DAAFF]/10 p-4 text-sm">
          <AlertTriangle class="mt-0.5 size-4 shrink-0 text-[#0A78BE] dark:text-[#1DAAFF]" />
          <div class="space-y-2">
            <p>Twitch has not yet allowed Overlabels to change your title. That is a one-time permission, added for this feature.</p>
            <a href="/auth/redirect/twitch?reauth=1" class="btn btn-sm btn-secondary cursor-pointer">Reauthorize Twitch</a>
          </div>
        </div>

        <div v-if="paused" class="flex items-start gap-3 border border-[#1DAAFF]/40 bg-[#1DAAFF]/10 p-4 text-sm">
          <Pause class="mt-0.5 size-4 shrink-0 text-[#0A78BE] dark:text-[#1DAAFF]" />
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

        <!-- One card, read top to bottom: what Twitch shows now, what you
             write, what that becomes, what you can add, save. -->
        <div class="divide-y divide-sidebar-border border border-sidebar-border">
          <section class="space-y-2 p-4" aria-labelledby="now-heading">
            <h3 id="now-heading" class="flex items-center gap-2 text-xs tracking-wide text-muted-foreground uppercase">
              <Tv class="size-3.5" />
              Now on Twitch
            </h3>
            <p v-if="channel?.title" class="text-base font-medium wrap-break-word">{{ channel.title }}</p>
            <p v-else class="text-sm text-muted-foreground italic">Twitch did not tell us your current title.</p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
              <span class="flex items-center gap-1.5">
                <Gamepad2 class="size-3.5 shrink-0" />
                {{ channel?.game_name || 'No category' }}
              </span>
              <button
                type="button"
                class="cursor-pointer text-[#0A78BE] hover:underline dark:text-[#1DAAFF]"
                @click="pickingCategory = !pickingCategory"
              >
                {{ pickingCategory ? 'Close' : 'Change category' }}
              </button>
            </div>

            <div v-if="pickingCategory" class="space-y-2 pt-2">
              <p class="text-xs text-muted-foreground">Picking one sets it on Twitch right away, once. Your title never changes it back.</p>
              <div class="relative">
                <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                  v-model="categoryQuery"
                  type="text"
                  class="input-border w-full pr-9 pl-9 text-sm"
                  placeholder="Search categories"
                  autocomplete="off"
                  aria-label="Search categories"
                />
                <button
                  type="button"
                  class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                  aria-label="Close category search"
                  @click="pickingCategory = false"
                >
                  <X class="size-4" />
                </button>
              </div>
              <p v-if="searching" class="text-xs text-muted-foreground italic">searching...</p>
              <ul v-else-if="categories.length > 0" class="max-h-72 divide-y divide-sidebar-border overflow-y-auto border border-sidebar-border">
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
          </section>

          <section class="space-y-2 p-4">
            <div class="flex items-center justify-between">
              <Label for="title-template" class="text-xs font-normal tracking-wide text-muted-foreground uppercase">Your template</Label>
              <span class="text-xs whitespace-nowrap tabular-nums" :class="overLength ? 'text-rose-400' : 'text-muted-foreground'">
                {{ previewLength }} / {{ maxLength }}
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
              Tags, <code class="font-mono">[[[c:key]]]</code> controls, <code class="font-mono">[[[if:...]]]</code> and pipes all work.
              <a href="/tags" target="_blank" class="cursor-pointer underline">See all your tags</a>.
            </p>
          </section>

          <section class="space-y-2 bg-sidebar-accent/30 p-4" aria-live="polite">
            <h3 class="flex items-center gap-2 text-xs tracking-wide text-muted-foreground uppercase">
              <Sparkles class="size-3.5" />
              Becomes
              <span v-if="previewLoading" class="normal-case italic">resolving...</span>
            </h3>
            <div class="min-h-6 text-base">
              <div v-if="previewError" class="flex items-center gap-2 text-sm text-rose-400">
                <AlertTriangle class="size-4 shrink-0" />
                {{ previewError }}
              </div>
              <div v-else-if="!previewOutput" class="text-sm text-muted-foreground italic">Type a template above.</div>
              <div v-else class="font-medium wrap-break-word transition-opacity duration-150" :class="{ 'opacity-50': previewLoading }">
                {{ previewOutput }}
              </div>
            </div>
            <p v-if="previewTruncated" class="text-xs text-rose-400">
              Renders to {{ previewLength }} characters. Twitch allows {{ maxLength }}, so it is cut there.
            </p>
          </section>

          <!-- One-click title features: each adds a ready-made line to the
               template. The first is thanking raiders. -->
          <section class="space-y-2 p-4">
            <h3 class="text-xs tracking-wide text-muted-foreground uppercase">Add to your title</h3>
            <div class="flex flex-wrap items-center gap-3">
              <button
                type="button"
                class="btn btn-chill cursor-pointer disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="hasRaiders"
                :title="RAIDERS_SNIPPET.trim()"
                @click="addRaiders"
              >
                <PartyPopper class="mr-2 size-4" />
                {{ hasRaiders ? 'Thanking your raiders' : 'Thank your raiders' }}
              </button>
            </div>
            <p class="text-xs text-muted-foreground">
              Names every channel that raids you this stream, newest first, in whatever room your title leaves. Hidden until the first raid.
            </p>
          </section>

          <div class="flex flex-wrap items-center gap-3 p-4">
            <button
              type="button"
              :disabled="saving"
              class="btn btn-primary cursor-pointer disabled:cursor-not-allowed disabled:opacity-60"
              @click="save()"
            >
              <Save class="mr-2 size-4" />
              {{ saving ? 'Saving...' : 'Save title' }}
            </button>
            <p class="text-xs text-muted-foreground">
              Written to Twitch at most once every {{ debounceSeconds }} seconds, and only when the text changed.
            </p>
            <p v-if="saveError" class="w-full text-sm text-destructive">{{ saveError }}</p>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
