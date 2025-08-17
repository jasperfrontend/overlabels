<template>
  <div v-if="error" class="error">{{ error }}</div>
  <div v-else>
    <!-- Dynamic Alert Overlay -->
    <transition
      :name="currentAlert?.transition || 'fade'"
      @enter="onAlertEnter"
      @leave="onAlertLeave"
    >
      <div
        v-if="currentAlert"
        class="alert-overlay"
      >
        <div
          v-html="compiledAlertHtml"
          class="alert-content"
          :id="`alert-content-${currentAlert.timestamp}`"

        />
      </div>
    </transition>

    <!-- Static Overlay Content -->
    <div v-html="compiledHtml" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useEventSub } from '@/composables/useEventSub';
import { useEventsStore } from '@/stores/overlayState';
import { useEventHandler } from '@/composables/useEventHandler';
import { useGiftBombDetector } from '@/composables/useGiftBombDetector';

interface AlertData {
  html: string;
  css: string;
  data: Record<string, any>;
  duration: number;
  transition: string;
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

const rawHtml = ref<string>('');
const css = ref('');
const data = ref<Record<string, any> | undefined>(undefined);
const error = ref('');
const templateTags = ref<string[]>([]);
const eventStore = useEventsStore();
const eventHandler = useEventHandler();
const giftBombDetector = useGiftBombDetector();

// Alert system state
const currentAlert = ref<AlertData | null>(null);
const alertTimeout = ref<number | null>(null);
const userId = ref<string | null>(null);


// Utility: escape regex special characters in keys
function escapeRegExp(string: string) {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Pre-build regex maps only for tags that have valid string/number values
const tagRegexMap = computed(() => {
  const m = new Map<string, RegExp>();

  const sourceData = data.value && typeof data.value === 'object' ? data.value : {};

  // Ensure templateTags is always an array before iterating
  const tags = Array.isArray(templateTags.value) ? templateTags.value : [];

  // Only log critical info
  if (tags.length === 0) {
    console.warn('[OverlayRenderer] No template tags received from server!');
  }

  for (const key of tags) {
    const val = sourceData[key];

    // Skip keys with undefined/null or object values
    if (val === undefined || val === null) continue;
    if (typeof val === 'object') continue;

    m.set(key, new RegExp(`\\[\\[\\[${escapeRegExp(key)}]]]`, 'g'));
  }
  return m;
});

const compiledHtml = computed(() => {
  let html = rawHtml.value;

  // First pass: replace all known tags with their values
  for (const [key, regex] of tagRegexMap.value.entries()) {
    if(data.value && typeof data.value === 'object' && key in data.value && data.value[key] !== undefined && data.value[key] !== null) {
      html = html.replace(regex, String(data?.value[key]));
    }
  }

  // Second pass: replace any remaining template tags with empty string
  // This prevents showing raw tags like [[[total]]] before data arrives
  html = html.replace(/\[\[\[[\w.]+]]]/g,'');

  return html;
});

function injectStyle(styleString: string) {
  const existing = document.getElementById('overlay-style');
  if (existing) existing.remove();

  const style = document.createElement('style');
  style.id = 'overlay-style';
  style.textContent = styleString;
  document.head.appendChild(style);
}

// Alert rendering system
const compiledAlertHtml = computed(() => {
  if (!currentAlert.value) return '';

  let html = currentAlert.value.html;
  const alertData = currentAlert.value.data;

  if (!alertData || typeof alertData !== 'object') {
    // If no data, replace all template tags with empty string to avoid showing raw tags
    html = html.replace(/\[\[\[[\w.]+]]]/g, '');
    return html;
  }

  // Create a map of all tags to process - both static and dynamic
  const allTags = new Map<string, any>();

  // First, add all data from the merged data (includes both static and dynamic)
  for (const [key, value] of Object.entries(alertData)) {
    if (value !== undefined && value !== null && typeof value !== 'object') {
      allTags.set(key, value);
    }
  }

  // First pass: replace all known tags with their values
  for (const [key, value] of allTags.entries()) {
    const regex = new RegExp(`\\[\\[\\[${escapeRegExp(key)}]]]`, 'g');
    html = html.replace(regex, String(value));
  }

  // Second pass: replace any remaining template tags with empty string
  // This handles tags that don't have data yet (like event.total before it arrives)
  html = html.replace(/\[\[\[[\w.]+]]]/g, '');

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

  // Show the alert
  currentAlert.value = alertData;
  console.log('Showing alert for', alertData.duration, 'ms');

  // Auto-hide after duration
  alertTimeout.value = window.setTimeout(() => {
    hideAlert();
  }, alertData.duration);
}

function hideAlert() {
  currentAlert.value = null;
  if (alertTimeout.value) {
    clearTimeout(alertTimeout.value);
    alertTimeout.value = null;
  }

  // Remove alert styles
  // const alertStyle = document.getElementById('alert-style');
  // if (alertStyle) {
  //   alertStyle.remove();
  // }

  console.log('Alert hidden');
}

function injectAlertStyle(styleString: string) {
  const existing = document.getElementById('alert-style');
  if (existing) existing.remove();

  const style = document.createElement('style');
  style.id = 'alert-style';
  style.textContent = styleString;
  document.head.appendChild(style);
}

const onAlertEnter = () => {
  console.log('Alert enter animation');
};

const onAlertLeave = () => {
  console.log('Alert leave animation');
  eventStore.clearOverlayTriggers();
};

onMounted(async () => {
  data.value = {};
  try {
    const response = await fetch('/api/overlay/render', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slug: props.slug, token: props.token }),
    });

    if (response.ok) {
      const json = await response.json();

      rawHtml.value = json.template.html;

      // Ensure tags is always an array, even if the server sends something unexpected
      templateTags.value = Array.isArray(json.template.tags) ? json.template.tags : [];

      css.value = json.template.css;
      data.value = json.data ?? {};

      // Extract user ID for alert channel subscription
      console.log('DEBUG: Overlay data received:', {
        user_id: json.data?.user_id,
        channel_id: json.data?.channel_id,
        twitch_id: json.data?.twitch_id,
        user_twitch_id: json.data?.user_twitch_id,
        available_keys: Object.keys(json.data || {})
      });
      userId.value = json.data?.user_twitch_id || json.data?.user_id || json.data?.channel_id || json.data?.twitch_id || null;
      console.log('DEBUG: Set userId to:', userId.value);

      injectStyle(css.value);
      document.title = json.meta?.name || 'Overlay';
      document.getElementById('loading')?.remove();

      // Set up alert listening
      console.log('DEBUG: About to setup alert listener');
      setupAlertListener();
      console.log('DEBUG: Alert listener setup completed');
    } else {
      console.error('Failed to load overlay', response.status, response.statusText);
    }
  } catch (err: any) {
    error.value = err.message;
    document.getElementById('loading')?.remove();
  }

  useEventSub((event) => {
    if (!data.value || typeof data.value !== 'object') data.value = {};

    const restructuredEvent = {
      subscription: {
        type: event.eventType || event.type
      },
      event: event.eventData || event.data
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

// Set up alert listener for broadcasted alerts
function setupAlertListener() {
  console.log('DEBUG: setupAlertListener called');

  if (!window.Echo) {
    console.error('ERROR: window.Echo is not available');
    return;
  }

  console.log('DEBUG: window.Echo exists');

  console.log('DEBUG: userId.value is:', userId.value);
  if (!userId.value) {
    console.warn('No user ID available for alert subscription');
    return;
  }

  // Listen for alert broadcasts on the user's channel
  const channelName = `alerts.${userId.value}`;
  console.log('Setting up alert listener for channel:', channelName);

  // Test if Echo is connected
  try {
    console.log('Echo connector state:', window.Echo.connector.pusher.connection.state);
    console.log('Echo connector options:', {
      key: window.Echo.options.key,
      cluster: window.Echo.options.cluster,
      encrypted: window.Echo.options.encrypted
    });
  } catch (error) {
    console.error('Echo connection error:', error);
  }

  // Use Laravel Echo to listen for real-time alert broadcasts
  console.log('Creating Echo channel for:', channelName);
  const channel = window.Echo.channel(channelName);

  // Listen for alert broadcasts (Laravel Echo requires dot prefix)
  channel.listen('.alert.triggered', handleAlertTriggered);

  // Add debugging for channel events
  channel.subscribed(() => {
    console.log('✅ Successfully subscribed to channel:', channelName);
  });

  channel.error((error: any) => {
    console.error('❌ Channel subscription error:', error);
  });

}

function handleAlertTriggered(event: any) {
  console.log('Alert triggered via Echo:', event);

  const alertData = event.alert;
  if (!alertData) {
    console.error('No alert data in Echo event');
    return;
  }

  // Merge current overlay data with event data for template rendering
  // This ensures both static tags (from data.value) and dynamic tags (from alertData.data) are available
  const mergedData = {
    ...data.value,
    ...alertData.data
  };

  // Debug logging to verify the merge
  console.log('Merged data for alert:', {
    staticDataKeys: Object.keys(data.value || {}),
    eventDataKeys: Object.keys(alertData.data || {}),
    mergedDataKeys: Object.keys(mergedData),
    sampleStaticTag: mergedData['subscribers_total'],
    sampleEventTag: mergedData['event.user_name']
  });

  showAlert({
    html: alertData.html,
    css: alertData.css,
    data: mergedData,
    duration: alertData.duration,
    transition: alertData.transition,
    timestamp: alertData.timestamp || Date.now()
  });
}
</script>
