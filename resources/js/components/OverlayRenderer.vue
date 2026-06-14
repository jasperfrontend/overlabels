<template>
  <!-- Health Status Banner -->
  <div v-if="health.hasError.value || health.isRetrying.value" class="overlay-health-banner">
    <div class="overlay-health-banner__inner">
      <div class="overlay-health-banner__icon">!</div>
      <div class="overlay-health-banner__text">
        <div class="overlay-health-banner__message">{{ health.statusMessage.value }}</div>
        <div v-if="health.willAutoReload.value" class="overlay-health-banner__reload">Auto-reloading in {{ health.autoReloadIn.value }}s...</div>
        <div v-else-if="health.isRetrying.value && health.retryCountdown.value > 0" class="overlay-health-banner__retry">
          Next retry in {{ health.retryCountdown.value }}s...
        </div>
      </div>
    </div>
  </div>

  <div v-if="error" class="error">{{ error }}</div>

  <!-- Static Overlay Content -->
  <!-- Rendered via morphdom so existing DOM nodes are preserved across data updates.
       Give repeated elements a [[[choice.id]]] (or similar stable value) in data-key
       to unlock CSS transitions on foreach-rendered children. -->
  <div v-else ref="staticContainer" class="overlay-static-root" />

  <!-- Dynamic Alert Overlay -->
  <!-- Alert contents are morphed too, so live-updating alerts (e.g. poll.progress
       firing alert.triggered repeatedly) reuse data-key'd children instead of
       replacing them on every payload. -->
  <div v-if="!error && currentAlert" class="alert-overlay">
    <div ref="alertContainer" class="alert-content" :id="`alert-content-${currentAlert.timestamp}`" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, watchEffect, onMounted, onUnmounted } from 'vue';
import morphdom from 'morphdom';
import { useEventSub } from '@/composables/useEventSub';
import { useEventsStore } from '@/stores/overlayState';
import { useEventHandler } from '@/composables/useEventHandler';
import { useGiftBombDetector } from '@/composables/useGiftBombDetector';
import { useConditionalTemplates } from '@/composables/useConditionalTemplates';
import { useOverlayHealth } from '@/composables/useOverlayHealth';
import { useEmoteParser } from '@/composables/useEmoteParser';
import { useExpressionEngine } from '@/composables/useExpressionEngine';
import { useCssCustomProperties } from '@/composables/useCssCustomProperties';
import { EVENT_RULES } from '@/composables/useTwitchEventRules';
import { compileCssBindings, replaceTagsWithFormatting } from '@/utils/tagParser';
import { listItemValues } from '@/utils/listItems';

// Canonical set of tag names declared by EVENT_RULES. These are the "twitch"
// (t.*) values exposed to the expression engine; other bare-keyed snapshot
// values in data.value stay where they are.
const TWITCH_TAG_NAMES: ReadonlySet<string> = (() => {
  const names = new Set<string>();
  for (const rules of Object.values(EVENT_RULES)) {
    for (const rule of rules) {
      if (rule && typeof rule.tag === 'string') names.add(rule.tag);
    }
  }
  return names;
})();

interface AlertData {
  head: string;
  html: string;
  css: string;
  compiledCss: string;
  data: Record<string, any>;
  duration: number;
  timestamp: number;
}

let lastUpdate = 0;
const MIN_INTERVAL = 16; // ~ 60fps

function bump() {
  const now = performance.now();
  if (now - lastUpdate < MIN_INTERVAL) return;
  lastUpdate = now;
  // trigger a benign write-action to keep computed re-evaluations sane
  data.value = { ...data.value };
}

const props = defineProps<{
  slug: string;
  token: string;
}>();

const head = ref<string | null>(null);
const rawHtml = ref<string>('');
const css = ref<string>('');
const compiledCssRaw = ref<string>('');
// Alert template compiled CSS preload: { slug: compiled_css }. Populated once
// on overlay mount; consulted when alerts fire so the WebSocket payload can
// carry a slug reference instead of a (potentially chonky) CSS blob per event.
const alertCssPreload = ref<Record<string, string>>({});
// Alert sound URL preload: { slug: url }. Populated once on overlay mount so
// we can emit <link rel="preload" as="audio"> + per-origin <link rel="preconnect">
// tags up front. Tested empirically as ~1s faster first-play vs. instantiating
// Audio() lazily on alert dispatch.
const alertSoundPreload = ref<Record<string, string>>({});
const data = ref<Record<string, any> | undefined>(undefined);
const userLocale = ref<string>('en-US');
const error = ref('');
const templateTags = ref<string[]>([]);
const eventStore = useEventsStore();
const eventHandler = useEventHandler();
const giftBombDetector = useGiftBombDetector();
const { processTemplate } = useConditionalTemplates();
const health = useOverlayHealth();
const emoteParser = useEmoteParser();
const expressionEngine = useExpressionEngine(data);
const cssApplier = useCssCustomProperties();

// Phase 1 surgical-patching state. When fastPath is true, the user's CSS was
// rewritten at mount to reference CSS custom properties (`var(--ol-...)`) for
// every [[[tag]]] occurrence. The <style> element is injected once and never
// re-built; tag updates write properties via cssApplier.applyAll(). When
// false, we fall back to the legacy compiledCss computed + injectStyle watcher
// path (CSS contains conditionals or foreach blocks that need text-level
// substitution).
const cssOnFastPath = ref(false);
const cssCompiledStatic = ref<string>('');

// Stream live state — Twitch source controls are muted when offline
const streamLive = ref(false);

// Timer control state — keyed by control key (not c:key)
const timerStates = ref<Record<string, any>>({});
const timerIntervals: Record<string, number> = {};

// Random control state — keyed by control key (not c:key)
const randomConfigs = ref<Record<string, { min: number; max: number }>>({});
const randomIntervals: Record<string, number> = {};

function randomInt(min: number, max: number): number {
  if (min > max) [min, max] = [max, min];
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function startRandomTick(key: string, config: { min: number; max: number; interval?: number }) {
  stopRandomTick(key);
  randomConfigs.value[key] = config;

  // Write an initial random value immediately
  if (data.value) {
    data.value = { ...data.value, [`c:${key}`]: String(randomInt(config.min, config.max)) };
  }

  const interval = Math.max(100, config.interval ?? 1000);
  randomIntervals[key] = window.setInterval(() => {
    if (!data.value) return;
    const cfg = randomConfigs.value[key];
    if (!cfg) return;
    data.value = { ...data.value, [`c:${key}`]: String(randomInt(cfg.min, cfg.max)) };
  }, interval);
}

function stopRandomTick(key: string) {
  if (randomIntervals[key]) {
    clearInterval(randomIntervals[key]);
    delete randomIntervals[key];
  }
  delete randomConfigs.value[key];
}

function computeTimerSeconds(state: any): number {
  const mode = state.mode ?? 'countup';
  const base = Number(state.base_seconds ?? 0);
  const offset = Number(state.offset_seconds ?? 0);
  const running = Boolean(state.running ?? false);
  const startedAt = state.started_at ? new Date(state.started_at).getTime() : null;

  if (mode === 'countto') {
    const target = state.target_datetime ? new Date(state.target_datetime).getTime() : null;
    if (!target) return 0;
    return Math.max(0, Math.floor((target - Date.now()) / 1000));
  }

  let elapsed = offset;
  if (running && startedAt) {
    elapsed = offset + Math.floor((Date.now() - startedAt) / 1000);
  }

  return mode === 'countdown' ? Math.max(0, base - elapsed) : elapsed;
}

function startTimerTick(key: string, state: any) {
  stopTimerTick(key);
  timerStates.value[key] = state;

  const runningVal = (state.running || state.mode === 'countto') ? '1' : '0';

  // Write the current value immediately
  if (data.value) {
    data.value = { ...data.value, [`c:${key}`]: String(computeTimerSeconds(state)), [`c:${key}:running`]: runningVal };
  }

  if (!state.running && state.mode !== 'countto') return;

  timerIntervals[key] = window.setInterval(() => {
    if (!data.value) return;
    data.value = { ...data.value, [`c:${key}`]: String(computeTimerSeconds(timerStates.value[key])), [`c:${key}:running`]: runningVal };
  }, 250);
}

function stopTimerTick(key: string) {
  if (timerIntervals[key]) {
    clearInterval(timerIntervals[key]);
    delete timerIntervals[key];
  }
  if (data.value) {
    data.value = { ...data.value, [`c:${key}:running`]: '0' };
  }
}

// Alert system state
const currentAlert = ref<AlertData | null>(null);
const alertTimeout = ref<number | null>(null);
const userId = ref<string | null>(null);

function parseSource(source: string | null | undefined, encode: boolean = true): string {
  if (!source) return '';
  let result = source;

  if (data.value && typeof data.value === 'object') {
    result = processTemplate(result, data.value, { locale: userLocale.value, encode });
    result = replaceTagsWithFormatting(result, data.value, userLocale.value, encode);
  }

  return result;
}

const compiledHtml = computed(() => parseSource(rawHtml.value, true));
// On the fast path, compiledCss resolves to the statically-rewritten stylesheet
// (no `data.value` dependency), so the computed never re-runs after mount and
// the watcher fires exactly once for the initial inject. Tag updates flow
// through cssApplier.applyAll() via the watch(data, ...) hook below.
const compiledCss = computed(() => {
  if (cssOnFastPath.value) return cssCompiledStatic.value;
  return parseSource(css.value, false);
});
watch(compiledCss, (newCss) => {
  if (cssOnFastPath.value) return;
  injectStyle(newCss);
});

// Phase 1 fast-path runtime: every reassignment of data.value drives a sweep
// of the bound CSS custom properties. The applier's internal cache short-
// circuits writes for unchanged values, so high-frequency expression ticks
// only touch the DOM for properties whose formatted value actually changed.
// On the slow path this is a no-op because cssApplier has no bindings.
watch(
  data,
  (newData) => {
    cssApplier.applyAll(newData);
  },
  { flush: 'post' },
);

// Template ref for the static overlay container. morphdom patches its children
// in place rather than replacing innerHTML wholesale, so any element with a
// stable `data-key` (or `id`) survives re-renders - CSS transitions on those
// elements then have a real from-state to animate from.
const staticContainer = ref<HTMLElement | null>(null);

function getMorphNodeKey(node: Node): string | undefined {
  if (node.nodeType !== 1) return undefined;
  const el = node as Element;
  return el.getAttribute('data-key') || el.id || undefined;
}

watchEffect(
  () => {
    const html = compiledHtml.value;
    const el = staticContainer.value;
    if (!el) return;

    const template = document.createElement('div');
    template.innerHTML = html;

    morphdom(el, template, {
      childrenOnly: true,
      getNodeKey: getMorphNodeKey,
    });
  },
  { flush: 'post' },
);

// Alert overlay gets the same treatment below, once compiledAlertHtml is
// declared (see after the alert section).
const alertContainer = ref<HTMLElement | null>(null);

function injectStyle(styleString: string) {
  const existing = document.getElementById('overlay-style');
  if (existing) existing.remove();

  const style = document.createElement('style');
  style.id = 'overlay-style';
  style.textContent = styleString;
  document.head.appendChild(style);
}

// Utility CSS (Tailwind-compatible, compiled from the template's class usage)
// is injected before the user's own `css` so user-authored rules can override
// the generated utilities when they clash.
function injectCompiledStyle(styleString: string) {
  const existing = document.getElementById('overlay-compiled-style');
  if (existing) existing.remove();
  if (!styleString) return;

  const style = document.createElement('style');
  style.id = 'overlay-compiled-style';
  style.textContent = styleString;
  const userStyle = document.getElementById('overlay-style');
  if (userStyle && userStyle.parentNode) {
    userStyle.parentNode.insertBefore(style, userStyle);
  } else {
    document.head.appendChild(style);
  }
}

function injectHead(headString: string | null) {
  if (!headString) return;

  // Parse the head HTML string to extract individual elements
  const parser = new DOMParser();
  const doc = parser.parseFromString(`<head>${headString}</head>`, 'text/html');
  const headElements = doc.head.children;

  // Remove any previously injected custom head elements
  document.querySelectorAll('[data-overlay-head]').forEach((el) => el.remove());

  // Inject each element from the template head into the actual document head
  Array.from(headElements).forEach((element) => {
    const clonedElement = element.cloneNode(true) as Element;
    clonedElement.setAttribute('data-overlay-head', 'true');
    document.head.appendChild(clonedElement);
  });
}

// Fields whose values arrive as already-safe HTML (encoded user text + parser-
// generated <img> emote tags from useEmoteParser). Pre-substituted before the
// regular tag pass so they are NOT re-encoded; the emote parser is responsible
// for encoding any donor-supplied chars before adding emote img markup.
const HTML_SAFE_ALERT_FIELDS = ['event.message.text', 'event.user_input'] as const;

function escapeRegex(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Alert rendering system
const compiledAlertHtml = computed(() => {
  if (!currentAlert.value) return '';

  let html = currentAlert.value.html;
  const alertData = currentAlert.value.data;

  if (!alertData || typeof alertData !== 'object') {
    return replaceTagsWithFormatting(html, {}, userLocale.value);
  }

  // Process conditional logic first (operates on raw alertData so [[[if:...]]]
  // can branch on the original values).
  html = processTemplate(html, alertData, { locale: userLocale.value, encode: true });

  // Pre-substitute html-safe fields (emote-parsed) so they bypass encoding,
  // then strip them from the data passed to the encoded substitution pass.
  const dataForEncodedPass: Record<string, any> = { ...alertData };
  for (const field of HTML_SAFE_ALERT_FIELDS) {
    const safeHtml = alertData[field];
    if (typeof safeHtml !== 'string') continue;
    const tagPattern = new RegExp(`\\[\\[\\[${escapeRegex(field)}\\]\\]\\]`, 'g');
    html = html.replace(tagPattern, safeHtml);
    delete dataForEncodedPass[field];
  }

  html = replaceTagsWithFormatting(html, dataForEncodedPass, userLocale.value);

  return html;
});

// Live-updating alert templates (poll progress, hype train) receive repeated
// `alert.triggered` events with the same template + new data while the
// wrapping <transition> stays mounted. Morph the alert content so data-key'd
// children survive those updates - otherwise their CSS transitions have no
// from-state and the DOM console flashes the whole subtree on every tick.
watchEffect(
  () => {
    const html = compiledAlertHtml.value;
    const el = alertContainer.value;
    if (!el) return;

    const template = document.createElement('div');
    template.innerHTML = html;

    morphdom(el, template, {
      childrenOnly: true,
      getNodeKey: getMorphNodeKey,
    });
  },
  { flush: 'post' },
);

// Alert management functions
function showAlert(alertData: AlertData) {
  // Clear any existing alert
  if (alertTimeout.value) {
    clearTimeout(alertTimeout.value);
    alertTimeout.value = null;
  }

  // Inject alert CSS. Compiled (utility) CSS goes first so the author's inline
  // alert CSS can override it when rules collide.
  injectAlertCompiledStyle(alertData.compiledCss);
  if (alertData.css) {
    injectAlertStyle(alertData.css);
  }

  // Show the alert
  currentAlert.value = alertData;

  // Auto-hide after duration
  alertTimeout.value = window.setTimeout(() => {
    dismissAlert();
  }, alertData.duration);
}

function dismissAlert() {
  if (currentAlert.value) {
    currentAlert.value = null;
    eventStore.clearOverlayTriggers();
  }
  if (alertTimeout.value) {
    clearTimeout(alertTimeout.value);
    alertTimeout.value = null;
  }
}

function injectAlertStyle(styleString: string) {
  const existing = document.getElementById('alert-style');
  if (existing) existing.remove();

  const style = document.createElement('style');
  style.id = 'alert-style';
  style.textContent = styleString;
  document.head.appendChild(style);
}

// Compiled utility CSS for an alert template, resolved from the preload map
// and injected before the user's inline alert CSS so author-written rules win
// on conflicts. Mirrors `injectCompiledStyle` for the static overlay.
function injectAlertCompiledStyle(styleString: string) {
  const existing = document.getElementById('alert-compiled-style');
  if (existing) existing.remove();
  if (!styleString) return;

  const style = document.createElement('style');
  style.id = 'alert-compiled-style';
  style.textContent = styleString;
  const userAlertStyle = document.getElementById('alert-style');
  if (userAlertStyle && userAlertStyle.parentNode) {
    userAlertStyle.parentNode.insertBefore(style, userAlertStyle);
  } else {
    document.head.appendChild(style);
  }
}

onMounted(async () => {
  data.value = {};

  // Use resilient fetch with retry
  const result = await health.fetchWithRetry(props.slug, props.token);

  if (result.ok) {
    const json = result.data;
    head.value = json.template.head;
    rawHtml.value = json.template.html ?? '';

    // Ensure tags is always an array, even if the server sends something unexpected
    templateTags.value = Array.isArray(json.template.tags) ? json.template.tags : [];

    css.value = json.template.css ?? '';
    compiledCssRaw.value = json.template.compiled_css ?? '';
    alertCssPreload.value = json.alert_css_preload ?? {};
    alertSoundPreload.value = json.alert_sound_preload ?? {};
    installAudioPreloadLinks(alertSoundPreload.value);
    data.value = json.data ?? {};

    // Mirror every Twitch template-tag value from the initial snapshot into the
    // t:* namespace so expressions can read them as t.followers_total,
    // t.channel_name, t.user_display_name, etc. The server allowlists tags via
    // TemplateDataMapperService + template_tags table, so anything landing here
    // is a legitimate scalar tag value to expose.
    //
    // Excluded: c:* (controls), t:* (already prefixed), user_twitch_id (meta),
    // dotted keys like event.user_name (raw event fields, not tags), and any
    // non-scalar values.
    const tSnapshot: Record<string, any> = {};
    if (data.value && typeof data.value === 'object') {
      for (const [key, val] of Object.entries(data.value)) {
        if (key.startsWith('c:')) continue;
        if (key.startsWith('t:')) continue;
        if (key === 'user_twitch_id') continue;
        if (key.includes('.')) continue;
        if (val === null || typeof val === 'object') continue;
        tSnapshot[`t:${key}`] = val;

        // For tags that EventSub mutates live, also seed the Pinia store so
        // increment-on-follow / increment-on-sub start from the real count.
        if (TWITCH_TAG_NAMES.has(key) && eventStore.tags[key] === undefined) {
          eventStore.tags[key] = val;
        }
      }
    }
    if (Object.keys(tSnapshot).length > 0) {
      data.value = { ...data.value, ...tSnapshot };
    }

    // Keep data.value[`t:*`] in sync with eventStore.tags mutations from EventSub.
    watch(
      () => ({ ...eventStore.tags }),
      (newTags) => {
        if (!data.value) return;
        const patch: Record<string, any> = {};
        for (const [k, v] of Object.entries(newTags)) {
          const prefixedKey = `t:${k}`;
          if (data.value[prefixedKey] !== v) {
            patch[prefixedKey] = v;
          }
        }
        if (Object.keys(patch).length > 0) {
          data.value = { ...data.value, ...patch };
        }
      },
      { deep: true },
    );

    userId.value = json.data?.user_twitch_id || json.data?.user_id || json.data?.channel_id || json.data?.twitch_id || null;

    // Start loading emotes for this broadcaster's channel
    if (userId.value) {
      emoteParser.initialize(Number(userId.value)).catch(() => {
        console.warn('[OverlayRenderer] Emote parser failed to initialize');
      });
    }

    // Initialise user locale for pipe formatters
    userLocale.value = json.locale ?? 'en-US';

    // Initialise stream live state
    streamLive.value = json.stream_live ?? false;

    // Start local ticking for any timer controls that are currently running
    if (json.timer_states && typeof json.timer_states === 'object') {
      for (const [key, state] of Object.entries(json.timer_states)) {
        startTimerTick(key, state);
      }
    }

    // Start local ticking for any random-mode controls
    if (Array.isArray(json.random_controls)) {
      for (const rc of json.random_controls) {
        startRandomTick(rc.key, { min: rc.min, max: rc.max, interval: rc.interval });
      }
    }

    // Register expression controls for frontend evaluation
    if (Array.isArray(json.expression_controls)) {
      for (const expr of json.expression_controls) {
        if (expr.key && expr.expression) {
          expressionEngine.registerExpression(expr.key, expr.expression);
        }
      }
    }

    // Phase 1 surgical patching: try to compile the user's CSS source into a
    // single static stylesheet that references CSS custom properties for every
    // [[[tag]]] occurrence. Bails when the CSS contains conditional or foreach
    // blocks - those select between text segments and can't be reduced to a
    // var() reference.
    const compiled = compileCssBindings(css.value);
    if (compiled.fastPath) {
      cssCompiledStatic.value = compiled.css;
      cssApplier.install(compiled.bindings, userLocale.value);
      cssOnFastPath.value = true;
      // Seed every bound property from the current data snapshot, including
      // values just written by the expression engine above.
      cssApplier.applyAll(data.value);
    }

    injectStyle(compiledCss.value);
    injectCompiledStyle(compiledCssRaw.value);
    injectHead(head.value);

    document.title = json.meta?.name || 'Overlay';
    document.getElementById('loading')?.remove();

    setupAlertListener();

    // Start health monitoring now that we're connected
    health.startHealthChecks(props.slug, props.token);
    health.startPusherMonitoring();
  } else {
    // fetchWithRetry already set status/message and scheduled auto-reload
    document.getElementById('loading')?.remove();
  }

  useEventSub(userId.value, (event) => {
    if (!data.value || typeof data.value !== 'object') data.value = {};

    const restructuredEvent = {
      subscription: {
        type: event.eventType || event.type,
      },
      event: event.eventData || event.data,
    };

    // First, we normalise the event
    const normalisedEvent = eventHandler.processRawEvent(restructuredEvent);

    giftBombDetector.processEvent(normalisedEvent, (processedEvent) => {
      // Dispatch the processed event for notifications
      eventHandler.dispatchEvent(processedEvent);
      // Update the store
      eventStore.addEvent(processedEvent);
      // Update template data
      data.value = { ...data.value, ...(processedEvent.raw?.event || {}) };
      bump();
    });
  });
});

onUnmounted(() => {
  health.destroy();
  expressionEngine.destroy();
  cssApplier.teardown();
  for (const key of Object.keys(timerIntervals)) {
    stopTimerTick(key);
  }
  for (const key of Object.keys(randomIntervals)) {
    stopRandomTick(key);
  }
});

// Set up alert listener for broadcasted alerts
function setupAlertListener() {
  if (!window.Echo) {
    console.error('ERROR: window.Echo is not available');
    return;
  }

  if (!userId.value) {
    console.warn('No user ID available for alert subscription');
    return;
  }

  // Listen for alert broadcasts on the user's private channel. Echo prefixes
  // the wire channel name with `private-`; subscription is gated by the
  // overlay-token broadcasting auth endpoint configured in overlay/app.js.
  const channelName = `alerts.${userId.value}`;

  const channel = window.Echo.private(channelName);

  // Listen for alert broadcasts (Laravel Echo requires dot prefix)
  channel.listen('.alert.triggered', handleAlertTriggered);

  // Pre-synthesized TTS audio for a previously-triggered alert. Arrives
  // asynchronously (synthesis takes ~700ms-2s); we correlate by alert_id
  // and play after the alert's tts_delay_ms relative to when the alert fired.
  channel.listen('.tts.ready', handleTtsAudioReady);

  // Listen for control value updates. Single updates (.control.updated) come
  // from user actions; batched updates (.control.batch) collapse a service
  // tick's many key changes (e.g. a GPS ping) into one event to kill fan-out.
  channel.listen('.control.updated', handleControlUpdated);
  channel.listen('.control.batch', handleControlBatch);

  // Listen for user-managed List saves/deletes (the new Lists feature).
  // Lists ship as c:list:<slug> = JSON array string + c:list:<slug>.N
  // indexed scalars + .count, so both bare-tag usage and foreach loops
  // update without an overlay refresh.
  channel.listen('.list.updated', handleListUpdated);
  channel.listen('.list.deleted', handleListDeleted);

  // Listen for stream online/offline status
  channel.listen('.stream.status', (event: any) => {
    streamLive.value = Boolean(event.live);
  });

  // Hard-reload when the template itself is saved
  channel.listen('.template.updated', (event: any) => {
    if (event.overlay_slug === props.slug) {
      window.location.reload();
    }
  });

  channel.subscribed(() => {
    console.log('Successfully subscribed to channel:', channelName);
  });

  channel.error((err: any) => {
    console.error('Channel subscription error:', err);
  });
}

function handleControlUpdated(event: any) {
  applyControlUpdate(event);
}

// A batched broadcast carries many control updates from one service tick under
// a single channel message. Apply each through the same path as a single update;
// the batch's updated_at stamps any element that doesn't carry its own.
function handleControlBatch(event: any) {
  if (!event || !Array.isArray(event.updates)) return;
  for (const u of event.updates) {
    applyControlUpdate({ ...u, updated_at: u.updated_at ?? event.updated_at });
  }
}

function applyControlUpdate(event: any) {
  // User-scoped (service-managed) controls broadcast with an empty overlay_slug — apply to all overlays.
  // Template-scoped controls include the slug and are filtered to match only their overlay.
  const isUserScoped = !event.overlay_slug;
  if (!isUserScoped && event.overlay_slug !== props.slug) return;
  if (!data.value || typeof data.value !== 'object') return;

  // Store companion _at timestamp (Unix epoch seconds) for every control update
  const atKey = `c:${event.key}_at`;
  const timestamp = event.updated_at ? String(event.updated_at) : String(Math.floor(Date.now() / 1000));

  if (event.type === 'expression' && event.expression) {
    // Re-register the expression with the updated formula
    expressionEngine.registerExpression(event.key, event.expression);
    data.value = { ...data.value, [atKey]: timestamp };
  } else if (event.type === 'timer' && event.timer_state) {
    // For timers, start/update the local tick interval using the broadcast state
    startTimerTick(event.key, event.timer_state);
    data.value = { ...data.value, [atKey]: timestamp };
  } else if (event.random_state) {
    // For random controls, start/update the random tick interval
    startRandomTick(event.key, event.random_state);
    data.value = { ...data.value, [atKey]: timestamp };
  } else {
    // If this key was previously random, stop its interval
    if (randomIntervals[event.key]) {
      stopRandomTick(event.key);
    }
    // event.key may be namespaced (e.g. "kofi:donations_received") — store as "c:kofi:donations_received"
    data.value = {
      ...data.value,
      [`c:${event.key}`]: event.value,
      [atKey]: timestamp,
    };
  }
}

function handleListUpdated(event: any) {
  if (!data.value || typeof data.value !== 'object') return;
  if (!event?.slug) return;

  const baseKey = `c:list:${event.slug}`;
  // Items arrive as objects ({id,value,...}). Every scalar tag projects to
  // the value string (mirroring the server's OverlayTemplateController
  // projection), so the bare tag and .N / :first / :last / :sum stay
  // backward compatible and foreach keeps iterating scalar values. The full
  // objects are exposed separately under :json for richer consumers.
  const items: any[] = Array.isArray(event.items) ? event.items : [];
  const values = listItemValues(items);

  // Recompute the derived read tags (:first, :last, :empty, :sum)
  // alongside the index + count keys so [[[c:list:slug:first]]] and
  // friends update live on broadcast without an overlay refresh.
  // :random is deliberately NOT recomputed - it stays whatever the
  // server picked at overlay mount so it doesn't flicker on every
  // append. If you want a re-roll, reload the overlay.
  const patch: Record<string, any> = {
    [baseKey]: JSON.stringify(values),
    [`${baseKey}:json`]: JSON.stringify(items),
    [`${baseKey}.count`]: String(values.length),
    [`${baseKey}:count`]: String(values.length),
    [`${baseKey}:first`]: values.length > 0 ? values[0] : '',
    [`${baseKey}:last`]: values.length > 0 ? values[values.length - 1] : '',
    [`${baseKey}:empty`]: values.length === 0 ? '1' : '0',
    [`${baseKey}:sum`]: computeListSum(event.slug, values),
  };
  values.forEach((value, i) => {
    patch[`${baseKey}.${i}`] = value;
  });

  // Expiry: server ships expires_at as Unix seconds (or null when the
  // streamer cleared it). Patch the static tag and re-seat the synthetic
  // countdown timer so changes propagate without an overlay reload.
  const expiresAt: number | null = typeof event.expires_at === 'number' ? event.expires_at : null;
  patch[`${baseKey}:expires_at`] = expiresAt !== null ? String(expiresAt) : '';
  const countdownKey = `list:${event.slug}:countdown`;
  if (expiresAt !== null) {
    startTimerTick(countdownKey, {
      mode: 'countto',
      base_seconds: 0,
      offset_seconds: 0,
      running: true,
      started_at: null,
      target_datetime: new Date(expiresAt * 1000).toISOString(),
    });
  } else {
    stopTimerTick(countdownKey);
    // Zero out the data slot too so a template referencing the countdown
    // doesn't keep showing the last computed value after expires_at was
    // cleared by the streamer.
    patch[`c:${countdownKey}`] = '';
  }

  // Strip any indexed keys from the prior version of this list that
  // don't exist in the new payload, so shrinking a list to 3 items
  // doesn't leave .3, .4, ... lingering in the data store and visible
  // to foreach materialisation.
  const next = { ...data.value, ...patch };
  const prefix = `${baseKey}.`;
  for (const key of Object.keys(next)) {
    if (!key.startsWith(prefix)) continue;
    if (key === `${baseKey}.count`) continue;
    const tail = key.slice(prefix.length);
    if (!/^\d+$/.test(tail)) continue;
    if (parseInt(tail, 10) >= items.length) {
      delete next[key];
    }
  }

  data.value = next;
}

/**
 * Mirrors the server-side sumListItems in OverlayTemplateController.
 * Whitespace-only / empty entries treated as 0; any other non-numeric
 * content fails loudly with an inline error string. Kept in sync with
 * the PHP version so initial-render and broadcast-update produce
 * identical strings.
 */
function computeListSum(slug: string, items: any[]): string {
  let total = 0;
  let sawNumber = false;

  for (let i = 0; i < items.length; i++) {
    const raw = items[i];
    const trimmed = String(raw ?? '').trim();
    if (trimmed === '') continue;
    if (!/^-?\d+(\.\d+)?$/.test(trimmed)) {
      return `ERR: list '${slug}' has non-numeric item '${raw}' at position ${i}`;
    }
    total += parseFloat(trimmed);
    sawNumber = true;
  }

  if (!sawNumber) return '0';
  return Number.isInteger(total) ? String(Math.trunc(total)) : String(total);
}

function handleListDeleted(event: any) {
  if (!data.value || typeof data.value !== 'object') return;
  if (!event?.slug) return;

  const baseKey = `c:list:${event.slug}`;
  const dotPrefix = `${baseKey}.`;
  const colonPrefix = `${baseKey}:`;
  const next = { ...data.value };
  for (const key of Object.keys(next)) {
    if (key === baseKey || key.startsWith(dotPrefix) || key.startsWith(colonPrefix)) {
      delete next[key];
    }
  }
  data.value = next;
}

function handleAlertTriggered(event: any) {
  const alertData = event.alert;
  if (!alertData) {
    console.error('No alert data in Echo event');
    return;
  }

  // Skip if this overlay is not in the target whitelist
  const targetSlugs: string[] | null = alertData.target_overlay_slugs ?? null;
  if (targetSlugs !== null && !targetSlugs.includes(props.slug)) return;

  // Parse emotes in user-generated text fields before template substitution
  const processedData = { ...alertData.data };

  const EMOTE_TEXT_FIELDS: Array<{ field: string; emotesField?: string }> = [
    { field: 'event.message.text', emotesField: 'event.message.emotes' },
    { field: 'event.user_input' },
  ];

  for (const { field, emotesField } of EMOTE_TEXT_FIELDS) {
    if (typeof processedData[field] === 'string' && processedData[field]) {
      processedData[field] = emoteParser.parseEmotes(
        processedData[field],
        emotesField ? processedData[emotesField] : undefined,
      );
    }
  }

  const mergedData = {
    ...data.value,
    ...processedData,
  };

  // Compiled utility CSS is not inlined on the broadcast - we look it up in
  // the preload map by alert_template_slug. Miss case (brand-new alert created
  // mid-session, not yet in the map) falls back to empty string, which still
  // renders correctly with only the author's inline CSS.
  const alertSlug: string | undefined = alertData.alert_template_slug;
  const compiledCss = alertSlug ? (alertCssPreload.value[alertSlug] ?? '') : '';

  showAlert({
    head: alertData.head,
    html: alertData.html,
    css: alertData.css,
    compiledCss,
    data: mergedData,
    duration: alertData.duration,
    timestamp: alertData.timestamp || Date.now(),
  });

  // Sound URL comes from the broadcast - it's the canonical "current" value
  // and self-updates when the streamer edits their alert template (no overlay
  // reload needed). alertSoundPreload only exists to inject <link rel="preload">
  // tags on mount that pre-warm the browser's HTTP cache for URLs that haven't
  // changed since the overlay loaded; it must NOT be used as the source of
  // truth for which URL to play, or stale preloads beat fresh broadcasts.
  playAlertSound(alertData.alert_sound_url);

  // Record the alert so when its TtsAudioReady broadcast arrives (after
  // server-side ElevenLabs synthesis) we can schedule playback at the right
  // offset from when this alert fired.
  if (typeof alertData.alert_id === 'string' && alertData.alert_id) {
    rememberPendingTts(alertData.alert_id, alertData.tts_delay_ms);
  }
}

// Singleton audio element: one shared player for all alert sounds. New alerts
// cancel the previous one (pause + reset currentTime) before playing, so a
// rapid-fire 20x event trigger plays the sound once-per-fire instead of
// stacking 20 overlapping copies into an unlistenable wash of audio.
let alertSoundPlayer: HTMLAudioElement | null = null;

function playAlertSound(url: unknown): void {
  if (typeof url !== 'string' || !url) return;
  if (typeof window === 'undefined' || typeof Audio === 'undefined') return;
  try {
    if (alertSoundPlayer) {
      alertSoundPlayer.pause();
      alertSoundPlayer.currentTime = 0;
    }
    alertSoundPlayer = new Audio(url);
    void alertSoundPlayer.play().catch((err) => {
      // Most common cause: browser autoplay policy on an unactivated page.
      // OBS browser sources don't gate autoplay, so this only bites in
      // regular tabs before any user gesture.
      console.warn('Alert sound playback failed', err);
    });
  } catch (err) {
    console.warn('Alert sound setup failed', err);
  }
}

// Emit <link rel="preconnect"> per unique audio origin and <link rel="preload"
// as="audio"> per URL. Called once after the mount payload arrives. Idempotent
// against the document we just landed in - we don't bother removing old links
// because the renderer's <head> only loads once per overlay session.
function installAudioPreloadLinks(map: Record<string, string>): void {
  if (typeof document === 'undefined') return;
  const urls = Object.values(map).filter((u): u is string => typeof u === 'string' && u !== '');
  if (urls.length === 0) return;

  const origins = new Set<string>();
  for (const url of urls) {
    try {
      origins.add(new URL(url).origin);
    } catch { /* malformed URL - skip */ }
  }
  for (const origin of origins) {
    const preconnect = document.createElement('link');
    preconnect.rel = 'preconnect';
    preconnect.href = origin;
    preconnect.crossOrigin = 'anonymous';
    document.head.appendChild(preconnect);
  }
  for (const url of urls) {
    const preload = document.createElement('link');
    preload.rel = 'preload';
    preload.as = 'audio';
    preload.href = url;
    document.head.appendChild(preload);
  }
}

// TTS is server-rendered: ElevenLabs synthesizes the resolved sentence on the
// queue, then broadcasts TtsAudioReady with a public mp3 URL. The overlay
// records every alert's fire-time + tts_delay_ms in `pendingTts` so it can
// schedule playback at the right offset whenever the audio shows up.
// Synthesis often beats the delay window (so audio plays at tts_delay_ms);
// when it doesn't, audio plays as soon as it arrives.
interface PendingTtsEntry {
  firedAt: number;
  delayMs: number;
}

const pendingTts = new Map<string, PendingTtsEntry>();
const PENDING_TTS_MAX_AGE_MS = 60_000;

// Singleton player mirrors playAlertSound's pattern: a rapid second TTS
// cancels the first instead of stacking voices.
let ttsAudioPlayer: HTMLAudioElement | null = null;
let ttsPendingTimer: ReturnType<typeof setTimeout> | null = null;

function rememberPendingTts(alertId: string, delayMsRaw: unknown): void {
  const delayMs = typeof delayMsRaw === 'number' && delayMsRaw > 0
    ? Math.min(delayMsRaw, 60_000)
    : 0;
  pendingTts.set(alertId, { firedAt: Date.now(), delayMs });

  // Evict anything older than the max-age cap. Cheap because the map only
  // ever holds a handful of entries (one per recent alert) - guards against
  // synthesis failures leaving zombie entries.
  const cutoff = Date.now() - PENDING_TTS_MAX_AGE_MS;
  for (const [id, entry] of pendingTts) {
    if (entry.firedAt < cutoff) pendingTts.delete(id);
  }
}

function handleTtsAudioReady(event: any): void {
  const alertId = event?.alert_id;
  const audioUrl = event?.audio_url;
  if (typeof alertId !== 'string' || typeof audioUrl !== 'string' || !audioUrl) return;

  const entry = pendingTts.get(alertId);
  // Late-arriving audio for an alert we've forgotten about: play immediately.
  // No record means no scheduled SFX to wait for either.
  const elapsed = entry ? Date.now() - entry.firedAt : Infinity;
  const remaining = entry ? Math.max(0, entry.delayMs - elapsed) : 0;
  pendingTts.delete(alertId);

  if (ttsPendingTimer !== null) {
    clearTimeout(ttsPendingTimer);
    ttsPendingTimer = null;
  }

  const play = () => {
    ttsPendingTimer = null;
    try {
      if (ttsAudioPlayer) {
        ttsAudioPlayer.pause();
        ttsAudioPlayer.currentTime = 0;
      }
      ttsAudioPlayer = new Audio(audioUrl);
      void ttsAudioPlayer.play().catch((err) => {
        console.warn('TTS playback failed', err);
      });
    } catch (err) {
      console.warn('TTS audio setup failed', err);
    }
  };

  if (remaining === 0) {
    play();
  } else {
    ttsPendingTimer = setTimeout(play, remaining);
  }
}
</script>
