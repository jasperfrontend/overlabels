<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Maximize, Minimize, Pause, Play, Volume2, VolumeX } from '@lucide/vue';
import ProviderIcon from '@/components/ProviderIcon.vue';
import { playables, stageDocument, STAGE_SIZE, type KitPreviewData, type PlayingAlert } from '@/utils/alertPreview';
import { serviceLabel } from '@/utils/services';

/**
 * A kit's alerts, played with sample data in a sandboxed frame. Before anyone
 * clicks, it cycles through every alert silently so the page is never a still
 * picture; the first click hands control over, with sound. See alertPreview.ts
 * for what is rendered and how.
 */
const props = defineProps<{ preview: KitPreviewData }>();

const items = computed(() => playables(props.preview));
const playing = ref<PlayingAlert | null>(null);
const current = ref<string | null>(null);
const nonce = ref(0);
const soundOn = ref(true);
const touched = ref(false);
// Not remembered anywhere: a pause left over from another visit would read
// as a broken preview.
const paused = ref(false);
const FADE_MS = 300;

const doc = computed(() => stageDocument(props.preview, playing.value, nonce.value));

// Fit the 1920x1080 frame inside the box it is given, centred. On the page
// the box is 16:9 so only the width matters; in full screen it is whatever
// the screen leaves above the event buttons.
const rootEl = ref<HTMLElement | null>(null);
const stageEl = ref<HTMLElement | null>(null);
const scale = ref(0.5);
const offset = ref({ x: 0, y: 0 });
let observer: ResizeObserver | null = null;

// Full screen exists because a quiet kit (Subtle) shrinks to a stamp at the
// page's ~40% and cannot be judged there. iPhone Safari has no element full
// screen, so the button is simply not offered there.
//
// The event buttons stay a fixed strip BELOW the frame, never on top of it:
// an alert can land anywhere on the frame and nothing outside can know where
// (the frame runs no script), so anything floating over it will cover one
// sooner or later. Costs a little scale on a 1080p screen; tried and dropped
// a fading overlay for exactly that reason.
const canFullscreen = ref(false);
const isFullscreen = ref(false);

function toggleFullscreen() {
  if (document.fullscreenElement) {
    void document.exitFullscreen().catch(() => {});
  } else {
    void rootEl.value?.requestFullscreen().catch(() => {});
  }
}

function onFullscreenChange() {
  isFullscreen.value = document.fullscreenElement === rootEl.value && rootEl.value !== null;
}

let hideTimer: ReturnType<typeof setTimeout> | null = null;
let demoTimer: ReturnType<typeof setTimeout> | null = null;
let fadeTimer: ReturnType<typeof setTimeout> | null = null;
let demoIndex = 0;
let audio: HTMLAudioElement | null = null;

const keyOf = (item: { trigger: { template_id: number; service: string | null; event_type: string | null }; variant: { key: string } }) =>
  `${item.trigger.template_id}:${item.trigger.service}:${item.trigger.event_type}:${item.variant.key}`;

function show(index: number, withSound: boolean) {
  const item = items.value[index];
  if (!item) return;
  if (hideTimer) clearTimeout(hideTimer);

  playing.value = { alert: item.alert, variant: item.variant };
  current.value = keyOf(item);
  nonce.value++;

  if (withSound && soundOn.value && item.alert.sound_url) {
    try {
      audio?.pause();
      audio = new Audio(item.alert.sound_url);
      void audio.play().catch(() => {});
    } catch {
      /* a sound that cannot play never stops the alert */
    }
  }

  hideTimer = setTimeout(() => {
    playing.value = null;
    current.value = null;
  }, item.trigger.duration_ms);

  return item.trigger.duration_ms;
}

function play(index: number) {
  touched.value = true;
  paused.value = false;
  if (fadeTimer) clearTimeout(fadeTimer);
  if (demoTimer) clearTimeout(demoTimer);
  show(index, true);
}

// The frame runs no script, so nothing can freeze an alert mid-motion. Pause
// fades the stage out under a notice instead, and once it is out of sight
// clears it, so nothing keeps moving behind the notice. Resume picks the
// cycle back up where it was.
function pause() {
  paused.value = true;
  if (hideTimer) clearTimeout(hideTimer);
  if (demoTimer) clearTimeout(demoTimer);
  audio?.pause();
  fadeTimer = setTimeout(() => {
    playing.value = null;
    current.value = null;
  }, FADE_MS);
}

function resume() {
  if (fadeTimer) clearTimeout(fadeTimer);
  paused.value = false;
  playing.value = null;
  current.value = null;
  if (!touched.value) demo();
}

function demo() {
  if (touched.value || items.value.length === 0) return;
  const duration = show(demoIndex % items.value.length, false) ?? 5000;
  demoIndex++;
  demoTimer = setTimeout(demo, duration + 900);
}

onMounted(() => {
  if (stageEl.value) {
    observer = new ResizeObserver(([entry]) => {
      const { width, height } = entry.contentRect;
      const s = Math.min(width / STAGE_SIZE.w, height / STAGE_SIZE.h);
      scale.value = s;
      offset.value = { x: (width - STAGE_SIZE.w * s) / 2, y: (height - STAGE_SIZE.h * s) / 2 };
    });
    observer.observe(stageEl.value);
  }
  canFullscreen.value = Boolean(document.fullscreenEnabled && rootEl.value?.requestFullscreen);
  document.addEventListener('fullscreenchange', onFullscreenChange);
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  if (!reduced) demoTimer = setTimeout(demo, 600);
});

onBeforeUnmount(() => {
  observer?.disconnect();
  document.removeEventListener('fullscreenchange', onFullscreenChange);
  if (hideTimer) clearTimeout(hideTimer);
  if (demoTimer) clearTimeout(demoTimer);
  if (fadeTimer) clearTimeout(fadeTimer);
  audio?.pause();
});

// Buttons grouped by where the event comes from, in trigger order.
const groups = computed(() => {
  const out: Array<{
    service: string | null;
    label: string;
    entries: Array<{ index: number; label: string; key: string; eventType: string | null }>;
  }> = [];
  items.value.forEach((item, index) => {
    const service = item.trigger.service;
    let group = out.find((g) => g.service === service);
    if (!group) {
      group = { service, label: service ? serviceLabel(service) : 'Alerts', entries: [] };
      out.push(group);
    }
    group.entries.push({ index, label: buttonLabel(item.variant.label, service), key: keyOf(item), eventType: item.trigger.event_type });
  });
  // A group that is one event in several flavours names the flavours only:
  // Throne's three samples read "Gift", "Surprise gift", "Crowdfunded" rather
  // than "Gift or Contribution - ..." three times over.
  for (const group of out) {
    const oneEvent = group.entries.length > 1 && group.entries.every((e) => e.eventType === group.entries[0].eventType);
    if (oneEvent && group.entries.every((e) => e.label.includes(' - '))) {
      for (const e of group.entries) e.label = capitalise(e.label.slice(e.label.lastIndexOf(' - ') + 3));
    }
  }
  return out;
});

const capitalise = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

/** "Ko-fi Subscription - first month" under a Ko-fi heading reads as "Subscription - first month". */
function buttonLabel(label: string, service: string | null): string {
  if (!service) return label;
  const prefix = serviceLabel(service);
  return label.startsWith(prefix + ' ') ? label.slice(prefix.length + 1) : label;
}

const hasSound = computed(() => props.preview.alerts.some((a) => a.sound_url));
</script>

<template>
  <div ref="rootEl" class="overflow-hidden rounded-lg border border-sidebar bg-card" :class="{ 'flex flex-col rounded-none border-0': isFullscreen }">
    <!-- In full screen the box is wider than 16:9; it goes black so the
         frame's own edges, which corner alerts are placed against, still read. -->
    <div ref="stageEl" class="relative w-full overflow-hidden" :class="isFullscreen ? 'min-h-0 flex-1 bg-black' : 'ol-alert-stage aspect-video'">
      <iframe
        :srcdoc="doc"
        sandbox=""
        tabindex="-1"
        title="Alert preview"
        class="pointer-events-none absolute top-0 left-0 origin-top-left border-0 transition-opacity duration-300"
        :class="[paused ? 'opacity-0' : 'opacity-100', { 'ol-alert-stage': isFullscreen }]"
        :style="{
          width: `${STAGE_SIZE.w}px`,
          height: `${STAGE_SIZE.h}px`,
          transform: `translate(${offset.x}px, ${offset.y}px) scale(${scale})`,
        }"
      />
      <span class="absolute top-2 right-3 font-mono text-[11px] tracking-widest text-white/40 uppercase">Preview, sample data</span>
      <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-300"
        leave-to-class="opacity-0"
      >
        <div v-if="paused" class="absolute inset-0 flex flex-wrap items-center justify-center gap-3 bg-black/60 p-4" role="status">
          <span class="text-sm text-white">Animation paused.</span>
          <button type="button" class="btn btn-secondary btn-xs cursor-pointer" @click="resume">
            <Play class="h-3 w-3" />
            Resume
          </button>
        </div>
      </Transition>
    </div>

    <div class="space-y-3 p-4" :class="{ 'max-h-[40vh] shrink-0 overflow-y-auto border-t border-sidebar': isFullscreen }">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-foreground">
          <span v-if="!touched">Click an event to fire its alert.</span>
          <span v-else>Fire another, or the same one again.</span>
        </p>
        <div class="flex gap-2">
          <button
            type="button"
            class="btn btn-xs cursor-pointer"
            :class="paused ? 'btn-secondary' : 'btn-chill'"
            :aria-pressed="paused"
            @click="paused ? resume() : pause()"
          >
            <component :is="paused ? Play : Pause" class="h-4 w-4" />
            {{ paused ? 'Play' : 'Pause' }}
          </button>
          <button v-if="hasSound" type="button" class="btn btn-chill btn-xs cursor-pointer" :aria-pressed="soundOn" @click="soundOn = !soundOn">
            <component :is="soundOn ? Volume2 : VolumeX" class="h-4 w-4" />
            {{ soundOn ? 'Sound on' : 'Sound off' }}
          </button>
          <button
            v-if="canFullscreen"
            type="button"
            class="btn btn-chill btn-xs cursor-pointer"
            :aria-pressed="isFullscreen"
            :title="isFullscreen ? 'Exit full screen (Esc)' : 'See it at stream size'"
            @click="toggleFullscreen"
          >
            <component :is="isFullscreen ? Minimize : Maximize" class="h-4 w-4" />
            {{ isFullscreen ? 'Exit full screen' : 'Full screen' }}
          </button>
        </div>
      </div>

      <div v-for="group in groups" :key="group.service ?? 'none'" class="grid items-start gap-2 sm:grid-cols-[9.5rem_minmax(0,1fr)]">
        <span class="flex items-center gap-1.5 pt-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          <ProviderIcon v-if="group.service" :source="group.service" class="h-3.5 w-3.5" />
          {{ group.label }}
        </span>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="entry in group.entries"
            :key="entry.key"
            type="button"
            class="btn btn-xs cursor-pointer"
            :class="current === entry.key ? 'btn-secondary' : 'btn-chill'"
            @click="play(entry.index)"
          >
            <Play class="h-3 w-3" />
            {{ entry.label }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* A stand-in for "your stream" behind a transparent overlay: flat, with a
   faint dot grid so the alert's own edges read. No gradient fill. */
.ol-alert-stage {
  background-color: #1b1f27;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24'%3E%3Ccircle cx='2' cy='2' r='1' fill='%23ffffff' fill-opacity='0.06'/%3E%3C/svg%3E");
}
</style>
