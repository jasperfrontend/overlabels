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
  <div v-else v-html="compiledHtml" />

  <!-- Dynamic Alert Overlay -->
  <transition :name="activeTransitionName" @leave="onAlertLeave">
    <div v-if="!error && currentAlert" class="alert-overlay">
      <div v-html="compiledAlertHtml" class="alert-content" :id="`alert-content-${currentAlert.timestamp}`" />
    </div>
  </transition>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useEventSub } from '@/composables/useEventSub';
import { useEventsStore } from '@/stores/overlayState';
import { useEventHandler } from '@/composables/useEventHandler';
import { useGiftBombDetector } from '@/composables/useGiftBombDetector';
import { useConditionalTemplates } from '@/composables/useConditionalTemplates';
import { useOverlayHealth } from '@/composables/useOverlayHealth';
import { useEmoteParser } from '@/composables/useEmoteParser';
import { useExpressionEngine } from '@/composables/useExpressionEngine';
import { applyFormatter } from '@/utils/formatters';

interface AlertData {
  head: string;
  html: string;
  css: string;
  data: Record<string, any>;
  duration: number;
  transitionIn: string;
  transitionOut: string;
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

  // Write the current value immediately
  if (data.value) {
    data.value = { ...data.value, [`c:${key}`]: String(computeTimerSeconds(state)) };
  }

  if (!state.running && state.mode !== 'countto') return;

  timerIntervals[key] = window.setInterval(() => {
    if (!data.value) return;
    data.value = { ...data.value, [`c:${key}`]: String(computeTimerSeconds(timerStates.value[key])) };
  }, 250);
}

function stopTimerTick(key: string) {
  if (timerIntervals[key]) {
    clearInterval(timerIntervals[key]);
    delete timerIntervals[key];
  }
}

// Alert system state
const activeTransitionName = ref('fade');
const currentAlert = ref<AlertData | null>(null);
const alertTimeout = ref<number | null>(null);
const userId = ref<string | null>(null);

// Utility: escape regex special characters in keys
function escapeRegExp(string: string) {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Matches [[[tag_name]]] and [[[tag_name|formatter]]] and [[[tag_name|formatter:args]]]
// Pipe args allow word chars, dots, colons, and hyphens (for date patterns like dd-MM-yyyy)
const TAG_REGEX = /\[\[\[([\w.:]+)(?:\|([\w.:\-]+))?]]]/g;

function replaceTagsWithFormatting(source: string, sourceData: Record<string, any>): string {
  return source.replace(TAG_REGEX, (_match, key: string, pipe: string | undefined) => {
    const val = sourceData[key];
    if (val === undefined || val === null || typeof val === 'object') return '';
    const strVal = String(val);
    if (pipe) {
      return applyFormatter(strVal, pipe, userLocale.value);
    }
    return strVal;
  });
}

function parseSource(source: string | null | undefined): string {
  if (!source) return '';
  let result = source;

  if (data.value && typeof data.value === 'object') {
    result = processTemplate(result, data.value);
    result = replaceTagsWithFormatting(result, data.value);
  }

  return result;
}

const compiledHtml = computed(() => parseSource(rawHtml.value));
const compiledCss = computed(() => parseSource(css.value));
watch(compiledCss, (newCss) => injectStyle(newCss));

function injectStyle(styleString: string) {
  const existing = document.getElementById('overlay-style');
  if (existing) existing.remove();

  const style = document.createElement('style');
  style.id = 'overlay-style';
  style.textContent = styleString;
  document.head.appendChild(style);
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

// Alert rendering system
const compiledAlertHtml = computed(() => {
  if (!currentAlert.value) return '';

  let html = currentAlert.value.html;
  const alertData = currentAlert.value.data;

  if (!alertData || typeof alertData !== 'object') {
    return replaceTagsWithFormatting(html, {});
  }

  // First process conditional logic, then replace tags with formatting
  html = processTemplate(html, alertData);
  html = replaceTagsWithFormatting(html, alertData);

  return html;
});

// Alert management functions
function showAlert(alertData: AlertData) {
  // Clear any existing alert
  if (alertTimeout.value) {
    clearTimeout(alertTimeout.value);
    alertTimeout.value = null;
  }

  // Inject alert CSS
  if (alertData.css) {
    injectAlertStyle(alertData.css);
  }

  // Set enter transition before showing alert — Vue reads :name at transition-start time
  activeTransitionName.value = alertData.transitionIn || 'fade';

  // Show the alert
  currentAlert.value = alertData;

  // Auto-hide after duration
  alertTimeout.value = window.setTimeout(() => {
    dismissAlert();
  }, alertData.duration);
}

function dismissAlert() {
  if (currentAlert.value) {
    activeTransitionName.value = currentAlert.value.transitionOut || 'fade';
    currentAlert.value = null;
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

const onAlertLeave = () => {
  eventStore.clearOverlayTriggers();
};

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
    data.value = json.data ?? {};

    userId.value = json.data?.user_twitch_id || json.data?.user_id || json.data?.channel_id || json.data?.twitch_id || null;

    // Start loading emotes for this broadcaster's channel
    if (userId.value) {
      emoteParser.initialize(Number(userId.value)).catch(() => {
        console.warn('[OverlayRenderer] Emote parser failed to initialize');
      });
    }

    // Initialize user locale for pipe formatters
    userLocale.value = json.locale ?? 'en-US';

    // Initialize stream live state
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
        startRandomTick(rc.key, { min: rc.min, max: rc.max });
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

    injectStyle(compiledCss.value);
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

  useEventSub((event) => {
    if (!data.value || typeof data.value !== 'object') data.value = {};

    const restructuredEvent = {
      subscription: {
        type: event.eventType || event.type,
      },
      event: event.eventData || event.data,
    };

    // First, we normalize the event
    const normalizedEvent = eventHandler.processRawEvent(restructuredEvent);

    giftBombDetector.processEvent(normalizedEvent, (processedEvent) => {
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

  // Listen for alert broadcasts on the user's channel
  const channelName = `alerts.${userId.value}`;

  // Use Laravel Echo to listen for real-time alert broadcasts
  const channel = window.Echo.channel(channelName);

  // Listen for alert broadcasts (Laravel Echo requires dot prefix)
  channel.listen('.alert.triggered', handleAlertTriggered);

  // Listen for control value updates
  channel.listen('.control.updated', handleControlUpdated);

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
    // event.key may be namespaced (e.g. "kofi:kofis_received") — store as "c:kofi:kofis_received"
    data.value = {
      ...data.value,
      [`c:${event.key}`]: event.value,
      [atKey]: timestamp,
    };
  }
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

  showAlert({
    head: alertData.head,
    html: alertData.html,
    css: alertData.css,
    data: mergedData,
    duration: alertData.duration,
    transitionIn: alertData.transition_in || 'fade',
    transitionOut: alertData.transition_out || 'fade',
    timestamp: alertData.timestamp || Date.now(),
  });
}
</script>
