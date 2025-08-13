<template>
  <div v-if="error" class="error">{{ error }}</div>
  <div v-else>
    <NotificationManager
      ref="notificationManager"
      :queue-config="notificationConfig"
      :default-props="defaultNotificationProps"
      :props-map="notificationPropsMap"
      @notification-shown="onNotificationShown"
      @notification-hidden="onNotificationHidden"
      @queue-updated="onQueueUpdated"
    />

    <div v-html="compiledHtml" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useEventSub } from '@/composables/useEventSub';
import { useEventsStore } from '@/stores/overlayState';
import { useEventHandler } from '@/composables/useEventHandler';
import { useGiftBombDetector } from '@/composables/useGiftBombDetector';
import NotificationManager from '@/components/notifications/NotificationManager.vue';
import type { NormalizedEvent } from '@/types';

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
const notificationManager = ref<InstanceType<typeof NotificationManager> | null>(null);


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

  for (const [key, regex] of tagRegexMap.value.entries()) {
    if(data.value && typeof data.value === 'object' && key in data.value && data.value[key] !== undefined && data.value[key] !== null) {
      html = html.replace(regex, String(data?.value[key]));
    }
  }

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

const notificationConfig = computed(() => ({
  maxQueueSize: 100,
  defaultDisplayDuration: 8000,
  groupingWindow: 4000,
  maxGroupSize: 50,
}));

const defaultNotificationProps = computed(() => ({
  position: 'top-center',
  size: 'medium',
  transitionName: 'notification-slide',
  backgroundColor: 'rgba(0, 0, 0, 0.9)',
  borderColor: '#ff6b00',
  borderWidth: 2,
  borderRadius: 8,
  padding: 16,
  margin: 16,
  fontFamily: 'system-ui, -apple-system, sans-serif',
  fontSize: 16,
  fontColor: '#ffffff',
}));

const notificationPropsMap = computed(() => ({
  'channel.subscribe': {
    borderColor: '#9146ff',
    titleColor: '#9146ff',
  },
  'channel.subscription.gift': {
    borderColor: '#ff0000',
    titleColor: '#ff0000',
    backgroundColor: 'rgba(255, 0, 0, 0.4)',
  },
  'channel.raid': {
    borderColor: '#ff0000',
    titleColor: '#ff0000',
    size: 'large',
    backgroundColor: 'rgba(255, 0, 0, 0.4)',
  },
  'channel.follow': {
    borderColor: '#9146ff',
    titleColor: '#9146ff',
    size: 'small',
  },
  'channel.cheer': {
    borderColor: '#00d4ff',
    titleColor: '#00d4ff',
  },
}));

const onNotificationShown = (event: NormalizedEvent) => {
  console.log('Notification shown:', event.type, event.id);
};

const onNotificationHidden = (event: NormalizedEvent) => {
  console.log('Notification hidden:', event.type, event.id);
  eventStore.clearOverlayTriggers();
};

const onQueueUpdated = (size: number) => {
  console.log('Notification queue size:', size);
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

      injectStyle(css.value);
      document.title = json.meta?.name || 'Overlay';
      document.getElementById('loading')?.remove();
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
</script>
