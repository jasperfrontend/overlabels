<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Eraser, MessageSquarePlus } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AddToObsButton from '@/components/AddToObsButton.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import FontPicker from '@/components/products/FontPicker.vue';
import type { AppPageProps, BreadcrumbItem, ForeachCaps } from '@/types';

interface Choice {
  value: string;
  label: string;
  hint: string;
}

interface Preset {
  key: string;
  label: string;
  blurb: string;
  values: Record<string, string>;
}

interface Control {
  id: number;
  key: string;
  label: string;
  type: string;
  value: string;
  config: Record<string, unknown>;
}

const props = defineProps<{
  product: { slug: string; name: string };
  overlay: { id: number; name: string; slug: string };
  preview_url: string;
  presets: Preset[];
  skins: Choice[];
  choices: Record<string, Choice[]>;
  groups: { title: string; keys: string[] }[];
  controls: Record<string, Control>;
  chat_window: number;
  chat_window_max: number;
  chat_filters: { hide_commands: boolean; hidden_logins: string[] };
  max_hidden_logins: number;
  suggested_fonts: { value: string; hint: string }[];
  fonts_url: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Products', href: '/products' },
  { title: props.product.name, href: `/products/${props.product.slug}` },
  { title: 'Design', href: `/products/${props.product.slug}/design` },
];

const page = usePage<AppPageProps>();

// The frame's URL is read once. Re-reading the prop would mean a re-render
// could swap the src and reload the preview mid-design; the token behind it is
// held in the session for the same reason (ChatDesigner::previewToken).
const frameSrc = ref(props.preview_url);
const frame = ref<HTMLIFrameElement | null>(null);

// A local copy, because every knob writes its own control and reads its own
// value back. The server's prop is the state at page load and nothing more.
const controls = reactive<Record<string, Control>>(JSON.parse(JSON.stringify(props.controls)));

// What the server last confirmed, key by key, tracked apart from `controls`.
// A knob writes its own value the moment it moves, so the local value is what
// was ASKED for, not what was saved. Comparing a write against the local value
// is what made every debounced knob a silent no-op: writeControlSoon had
// already assigned it, so writeControl saw no change and never posted, and
// every slider and color picker on the page moved without saving anything.
const saved = reactive<Record<string, string>>(Object.fromEntries(Object.entries(props.controls).map(([key, control]) => [key, control.value])));
const windowSize = ref(props.chat_window);

// Only failures are said out loud. Every successful change is visible in the
// frame on the right, which is the whole point of the page.
const problem = ref<string | null>(null);
const problemKey = ref(0);

function fail(message: string) {
  problem.value = message;
  problemKey.value += 1;
}

/* ------------------------------------------------------------------ knobs */

function valueOf(key: string): string {
  return controls[key]?.value ?? '';
}

function numberBound(key: string, bound: 'min' | 'max', fallback: number): number {
  const raw = controls[key]?.config?.[bound];
  return typeof raw === 'number' ? raw : Number(raw ?? fallback) || fallback;
}

const debounces: Record<string, ReturnType<typeof setTimeout>> = {};

/**
 * One knob, one control, one POST, through the endpoint the controls tab uses.
 *
 * Nothing else has to happen for the overlay to change: the endpoint
 * broadcasts, and both the preview frame and OBS are listening. That is the
 * whole demo, and it is why the designer writes controls directly rather than
 * collecting a form and saving it.
 */
async function writeControl(key: string, value: string): Promise<void> {
  const control = controls[key];
  if (!control || saved[key] === value) return;

  const previous = saved[key] ?? control.value;
  // Moved before the request, so a slider feels immediate and the preset
  // strip re-derives as you drag rather than a beat later.
  control.value = value;
  saved[key] = value;

  try {
    const { data } = await axios.post(`/templates/${props.overlay.id}/controls/${control.id}/value`, { value });
    // The server clamps numbers to the control's own min/max, so its answer
    // wins over what the input sent.
    if (typeof data?.value === 'string') {
      control.value = data.value;
      saved[key] = data.value;
    }
  } catch (error: unknown) {
    control.value = previous;
    saved[key] = previous;
    fail(axios.isAxiosError(error) ? (error.response?.data?.message ?? 'That control did not save.') : 'That control did not save.');
  }
}

/** For inputs that fire continuously: color pickers and range sliders. */
function writeControlSoon(key: string, value: string): void {
  if (controls[key]) controls[key].value = value;
  clearTimeout(debounces[key]);
  debounces[key] = setTimeout(() => void writeControl(key, value), 250);
}

/* ---------------------------------------------------------------- presets */

/**
 * Which preset the overlay currently holds, derived here on every keystroke.
 *
 * Same rule as the product page: one drifted value means no preset is active,
 * because that is the truth. Nothing is stored either side.
 */
const activePreset = computed(
  () => props.presets.find((preset) => Object.entries(preset.values).every(([key, value]) => valueOf(key) === value))?.key ?? null,
);

const applying = ref<string | null>(null);

async function applyPreset(key: string): Promise<void> {
  applying.value = key;

  try {
    const { data } = await axios.post(`/products/${props.product.slug}/presets/${key}`);
    for (const [controlKey, value] of Object.entries((data?.values ?? {}) as Record<string, string>)) {
      if (!controls[controlKey]) continue;
      // The preset was written server-side, so this IS the saved value now.
      // Leaving `saved` stale here would make the next knob that lands back on
      // a pre-preset value a no-op again.
      controls[controlKey].value = value;
      saved[controlKey] = value;
    }
  } catch {
    fail('That look did not apply.');
  } finally {
    applying.value = null;
  }
}

/* ----------------------------------------------------------- chat window */

/*
 * The window cap and the two display filters are account preferences, not
 * controls on this overlay. They do not ride the control broadcast, and none of
 * them changes anything visible in the preview: the sample chat has no commands
 * in it and no real logins, so there is nothing for either filter to catch.
 *
 * That is why this trio confirms itself. Every other knob on the page is its
 * own receipt - you see the overlay move - and these three would otherwise
 * write silently into a page where everything else answers instantly.
 */
const feedSaved = ref(false);
let savedFlash: ReturnType<typeof setTimeout>;

function confirmSaved(): void {
  feedSaved.value = true;
  clearTimeout(savedFlash);
  savedFlash = setTimeout(() => (feedSaved.value = false), 2000);
}

async function writeWindow(size: number): Promise<void> {
  const caps: ForeachCaps = { ...(page.props.auth.user.foreach_caps as ForeachCaps), chat: size };

  try {
    await axios.patch('/settings/foreach-caps', caps);
    page.props.auth.user.foreach_caps = caps;
    confirmSaved();
  } catch {
    fail('That window size did not save.');
  }
}

let windowDebounce: ReturnType<typeof setTimeout>;

function writeWindowSoon(size: number): void {
  windowSize.value = size;
  clearTimeout(windowDebounce);
  windowDebounce = setTimeout(() => void writeWindow(size), 250);
}

/* ------------------------------------------------------------ chat filters */

const hideCommands = ref(props.chat_filters.hide_commands);
const hiddenLoginsText = ref(props.chat_filters.hidden_logins.join('\n'));
const savedLoginCount = ref(props.chat_filters.hidden_logins.length);

// What the endpoint keeps after normalising: it lowercases, strips a leading
// @, drops anything that is not a Twitch login and dedupes. So the typed count
// is only ever used for the over-cap warning, and the saved count comes back
// from the server.
const typedLoginCount = computed(
  () =>
    hiddenLoginsText.value
      .split(/[\r\n,]+/)
      .map((line) => line.trim())
      .filter((line) => line !== '').length,
);

const overHiddenCap = computed(() => typedLoginCount.value > props.max_hidden_logins);

/**
 * Both filters go in one request, because the endpoint takes both and
 * `hide_commands` is required. The textarea is never rewritten from the
 * response: normalising what someone is still typing would fight them.
 */
async function writeChatFilters(): Promise<void> {
  try {
    const { data } = await axios.patch('/settings/chat', {
      hide_commands: hideCommands.value,
      hidden_logins: hiddenLoginsText.value,
    });
    savedLoginCount.value = (data?.chat_filters?.hidden_logins ?? []).length;
    confirmSaved();
  } catch {
    fail('Those chat settings did not save.');
  }
}

function writeHideCommands(on: boolean): void {
  hideCommands.value = on;
  void writeChatFilters();
}

let filtersDebounce: ReturnType<typeof setTimeout>;

/** Longer than the knobs: this one is typed into, not dragged. */
function writeHiddenLoginsSoon(): void {
  clearTimeout(filtersDebounce);
  filtersDebounce = setTimeout(() => void writeChatFilters(), 800);
}

/* ---------------------------------------------------------------- preview */

/**
 * What OBS browser source this layout wants, and therefore what shape the
 * preview is. A ticker in a 500x800 box would look nothing like a ticker.
 */
const SOURCE_SIZES: Record<string, { w: number; h: number }> = {
  bottom: { w: 500, h: 800 },
  top: { w: 500, h: 800 },
  ticker: { w: 1920, h: 80 },
};

const sourceSize = computed(() => SOURCE_SIZES[valueOf('layout')] ?? SOURCE_SIZES.bottom);

// The frame renders at the real browser-source size and is scaled down to fit,
// so 22px type looks like 22px type relative to the source rather than
// relative to whatever space the column happens to have.
const stage = ref<HTMLElement | null>(null);
const stageSize = reactive({ w: 0, h: 0 });
const scale = computed(() => {
  if (!stageSize.w || !stageSize.h) return 1;
  return Math.min(1, stageSize.w / sourceSize.value.w, stageSize.h / sourceSize.value.h);
});

let observer: ResizeObserver | null = null;

/* ------------------------------------------------------------ sample chat */

const rate = ref(0);

function postToFrame(payload: Record<string, unknown>): void {
  frame.value?.contentWindow?.postMessage({ ol: 'chat-sample', ...payload }, window.location.origin);
}

function setRate(perMinute: number): void {
  rate.value = perMinute;
  postToFrame({ rate: perMinute });
}

/** The overlay says when its feed exists; until then a message would be lost. */
function onFrameMessage(event: MessageEvent): void {
  if (event.source !== frame.value?.contentWindow) return;
  if ((event.data as { ol?: string; ready?: boolean } | null)?.ol !== 'chat-sample') return;
  if ((event.data as { ready?: boolean }).ready) postToFrame({ rate: rate.value });
}

onMounted(() => {
  window.addEventListener('message', onFrameMessage);

  if (stage.value && typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(([entry]) => {
      stageSize.w = entry.contentRect.width;
      stageSize.h = entry.contentRect.height;
    });
    observer.observe(stage.value);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('message', onFrameMessage);
  observer?.disconnect();
  clearTimeout(windowDebounce);
  clearTimeout(filtersDebounce);
  clearTimeout(savedFlash);
  for (const timer of Object.values(debounces)) clearTimeout(timer);
});

/** Groups render whatever controls the overlay actually has, never a blank row. */
function keysIn(group: { keys: string[] }): string[] {
  return group.keys.filter((key) => !!controls[key]);
}
</script>

<template>
  <Head :title="`Design ${product.name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <Heading
          title="Design your chat"
          description="Every change lands in the overlay as you make it - here and in OBS, at the same moment."
          description-class="text-sm text-muted-foreground"
        />
        <div class="flex shrink-0 items-center gap-2">
          <Link :href="`/products/${product.slug}`" class="btn btn-sm btn-cancel">Back to {{ product.name }}</Link>
        </div>
      </div>

      <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
        <!-- Knobs -->
        <div class="flex flex-col gap-5">
          <!-- Start from. The presets are a starting point here, not the
               finished choice they are on the product page: the skin picker
               and every knob below stay live after one is applied. -->
          <section class="flex flex-col gap-2">
            <h2 class="text-sm font-semibold text-foreground">Start from</h2>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="preset in presets"
                :key="preset.key"
                type="button"
                class="cursor-pointer rounded-full border px-3 py-1 text-sm"
                :class="
                  activePreset === preset.key
                    ? 'border-green-500/60 bg-green-500/10 text-green-700 dark:text-green-300'
                    : 'border-border text-foreground hover:border-foreground/40'
                "
                :disabled="applying !== null"
                :title="preset.blurb"
                @click="applyPreset(preset.key)"
              >
                {{ applying === preset.key ? 'Applying' : preset.label }}
              </button>
            </div>
            <p class="text-sm text-muted-foreground">
              <template v-if="activePreset">A preset, unchanged. Turn any knob below and it becomes your own.</template>
              <template v-else>Your own settings. Pick a preset to start over from one.</template>
            </p>
          </section>

          <!-- Skin. Separate from the presets on purpose: the shape of a
               message and the palette on it are two choices, and Terminal in
               pink is one click from here. -->
          <section v-if="controls.skin" class="flex flex-col gap-2">
            <h2 class="text-sm font-semibold text-foreground">Skin</h2>
            <div class="grid grid-cols-2 gap-1.5">
              <button
                v-for="skin in skins"
                :key="skin.value"
                type="button"
                class="cursor-pointer rounded-sm border px-2.5 py-1.5 text-left text-sm"
                :class="
                  valueOf('skin') === skin.value
                    ? 'border-violet-500 bg-violet-500/10 text-foreground'
                    : 'border-border text-foreground hover:border-foreground/40'
                "
                :title="skin.hint"
                @click="writeControl('skin', skin.value)"
              >
                {{ skin.label }}
              </button>
            </div>
          </section>

          <section v-for="group in groups" :key="group.title" class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-foreground">{{ group.title }}</h2>

            <div v-for="key in keysIn(group)" :key="key" class="flex flex-col gap-1.5">
              <!-- An open vocabulary: every family Bunny Fonts serves. -->
              <template v-if="key === 'font'">
                <label class="text-sm text-foreground" :for="`knob-${key}`">{{ controls[key].label }}</label>
                <FontPicker
                  :id="`knob-${key}`"
                  :model-value="valueOf(key)"
                  :suggested="suggested_fonts"
                  :catalogue-url="fonts_url"
                  @update:model-value="writeControl(key, $event)"
                />
              </template>

              <!-- A closed vocabulary: layout, background. -->
              <template v-else-if="choices[key]">
                <label class="text-sm text-foreground" :for="`knob-${key}`">{{ controls[key].label }}</label>
                <select
                  :id="`knob-${key}`"
                  class="w-full cursor-pointer rounded-sm border border-border bg-background px-2 py-1.5 text-sm text-foreground"
                  :value="valueOf(key)"
                  @change="writeControl(key, ($event.target as HTMLSelectElement).value)"
                >
                  <option v-for="choice in choices[key]" :key="choice.value" :value="choice.value">{{ choice.label }}</option>
                </select>
                <p class="text-xs text-muted-foreground">{{ choices[key].find((choice) => choice.value === valueOf(key))?.hint }}</p>
              </template>

              <!-- On or off. -->
              <template v-else-if="controls[key].type === 'boolean'">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-foreground">
                  <input
                    type="checkbox"
                    :checked="valueOf(key) === '1'"
                    @change="writeControl(key, ($event.target as HTMLInputElement).checked ? '1' : '0')"
                  />
                  {{ controls[key].label }}
                </label>
              </template>

              <!-- A color. -->
              <template v-else-if="controls[key].type === 'color'">
                <label class="text-sm text-foreground" :for="`knob-${key}`">{{ controls[key].label }}</label>
                <div class="flex items-center gap-2">
                  <input
                    :id="`knob-${key}`"
                    type="color"
                    class="size-8 cursor-pointer rounded-sm border border-border bg-transparent p-0.5"
                    :value="valueOf(key)"
                    @input="writeControlSoon(key, ($event.target as HTMLInputElement).value)"
                  />
                  <span class="font-mono text-xs text-muted-foreground">{{ valueOf(key) }}</span>
                </div>
              </template>

              <!-- A number, with the control's own bounds. -->
              <template v-else>
                <label class="flex items-baseline justify-between text-sm text-foreground" :for="`knob-${key}`">
                  {{ controls[key].label }}
                  <span class="text-xs text-muted-foreground tabular-nums">{{ valueOf(key) }}</span>
                </label>
                <input
                  :id="`knob-${key}`"
                  type="range"
                  class="w-full cursor-pointer"
                  :min="numberBound(key, 'min', 0)"
                  :max="numberBound(key, 'max', 100)"
                  :value="valueOf(key)"
                  @input="writeControlSoon(key, ($event.target as HTMLInputElement).value)"
                />
                <p v-if="key === 'lifetime'" class="text-xs text-muted-foreground">
                  {{
                    valueOf('lifetime') === '0'
                      ? 'Messages stay until they scroll off.'
                      : `Each message fades out after ${valueOf('lifetime')} seconds.`
                  }}
                </p>
              </template>
            </div>
          </section>

          <!-- The last three are account preferences rather than controls on
               this overlay: the chat foreach cap and the two display filters.
               None of them rides the control broadcast, and none of them shows
               in the preview either, since the sample chat has no commands in
               it and no real logins. So they say what they do and when. -->
          <section class="flex flex-col gap-4">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
              <h2 class="text-sm font-semibold text-foreground">What the feed shows</h2>
              <span v-if="feedSaved" class="text-xs text-green-600 dark:text-green-400">Saved</span>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="flex items-baseline justify-between text-sm text-foreground" for="knob-window">
                How many messages at once
                <span class="text-xs text-muted-foreground tabular-nums">{{ windowSize }}</span>
              </label>
              <input
                id="knob-window"
                type="range"
                class="w-full cursor-pointer"
                min="1"
                :max="chat_window_max"
                :value="windowSize"
                @input="writeWindowSoon(Number(($event.target as HTMLInputElement).value))"
              />
            </div>

            <label class="flex cursor-pointer items-start gap-2.5">
              <input
                type="checkbox"
                class="mt-0.5"
                :checked="hideCommands"
                @change="writeHideCommands(($event.target as HTMLInputElement).checked)"
              />
              <span class="text-sm">
                <span class="text-foreground">Hide messages starting with <code class="font-mono">!</code></span>
                <span class="block text-xs text-muted-foreground">
                  Keeps bot commands off the overlay. Every message starting with an exclamation mark goes, so
                  <code class="font-mono">!!!</code> and <code class="font-mono">!what a play</code> go too.
                </span>
              </span>
            </label>

            <div class="flex flex-col gap-1.5">
              <label class="text-sm text-foreground" for="knob-hidden-logins">Hidden chatters</label>
              <textarea
                id="knob-hidden-logins"
                v-model="hiddenLoginsText"
                rows="5"
                class="input-border w-full font-mono text-sm"
                placeholder="somebot&#10;anotherbot"
                @input="writeHiddenLoginsSoon"
              ></textarea>
              <p v-if="overHiddenCap" class="text-xs text-destructive">
                {{ typedLoginCount }} names typed. Only the first {{ max_hidden_logins }} are saved.
              </p>
              <p v-else class="text-xs text-muted-foreground">
                One Twitch username per line, up to {{ max_hidden_logins }}.
                <template v-if="savedLoginCount">{{ savedLoginCount }} {{ savedLoginCount === 1 ? 'name is' : 'names are' }} hidden.</template>
              </p>
            </div>

            <p class="text-xs text-muted-foreground">
              These three are account settings rather than overlay controls, so OBS picks them up when the browser source next loads, and the preview
              here follows on reload. Hiding a chatter or a command changes your overlay only: the message is still in chat, still in the VOD, and
              everyone watching still sees it.
            </p>
          </section>

          <div class="border-t border-border pt-4">
            <AddToObsButton :template="overlay" />
          </div>
        </div>

        <!-- Preview -->
        <div class="flex flex-col gap-2 lg:sticky lg:top-4">
          <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div class="flex items-center gap-2">
              <label class="text-sm text-foreground" for="sample-rate">Sample chat</label>
              <input
                id="sample-rate"
                type="range"
                class="w-32 cursor-pointer"
                min="0"
                max="120"
                step="10"
                :value="rate"
                @input="setRate(Number(($event.target as HTMLInputElement).value))"
              />
              <span class="text-xs text-muted-foreground tabular-nums">{{ rate === 0 ? 'paused' : `${rate}/min` }}</span>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" class="btn btn-sm btn-cancel cursor-pointer" @click="postToFrame({ burst: 6 })">
                <MessageSquarePlus class="mr-1.5 size-3.5" />
                More
              </button>
              <button type="button" class="btn btn-sm btn-cancel cursor-pointer" @click="postToFrame({ clear: true })">
                <Eraser class="mr-1.5 size-3.5" />
                Clear
              </button>
            </div>
          </div>

          <div
            ref="stage"
            class="ol-design-stage flex h-[min(72vh,820px)] items-center justify-center overflow-hidden rounded-sm border border-border p-2"
          >
            <div :style="{ width: `${sourceSize.w * scale}px`, height: `${sourceSize.h * scale}px` }" class="relative shrink-0">
              <iframe
                ref="frame"
                :src="frameSrc"
                title="Your chat overlay"
                class="absolute top-0 left-0 origin-top-left border-0"
                :style="{ width: `${sourceSize.w}px`, height: `${sourceSize.h}px`, transform: `scale(${scale})` }"
              />
            </div>
          </div>

          <!-- The right padding keeps the last line clear of the help beacon,
               which is fixed to the bottom-right of every app page. -->
          <p class="pr-12 text-sm text-muted-foreground">
            The real overlay, at {{ sourceSize.w }}&times;{{ sourceSize.h }} - the browser source size this layout wants. The chat in it is invented,
            so it reads the same whether or not you are live. The checkerboard is this page; your overlay is transparent.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>

  <RekaToast v-if="problem" :key="problemKey" :message="problem" type="error" @dismiss="problem = null" />
</template>

<style scoped>
/* An overlay is transparent and sits on a game. A flat panel behind it would
   quietly flatter a look that has no contrast of its own, so the preview
   admits there is something underneath. */
.ol-design-stage {
  background-color: #1b1b20;
  background-image:
    linear-gradient(45deg, #26262d 25%, transparent 25%), linear-gradient(-45deg, #26262d 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #26262d 75%), linear-gradient(-45deg, transparent 75%, #26262d 75%);
  background-size: 24px 24px;
  background-position:
    0 0,
    0 12px,
    12px -12px,
    -12px 0;
}
</style>
