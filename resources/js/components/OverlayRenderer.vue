<template>
  <div v-if="error" class="error">{{ error }}</div>
  <div v-else>
    <div v-html="compiledHtml" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useEventSub } from '@/composables/useEventSub';

let lastUpdate = 0
const MIN_INTERVAL = 16 // ~ 60fps

function bump() {
  const now = performance.now()
  if (now - lastUpdate < MIN_INTERVAL) return
  lastUpdate = now
  // trigger a benign write-action to keep computed re-evaluations sane
  data.value = { ...data.value }
}

const props = defineProps<{
  slug: string;
  token: string;
}>();

const rawHtml = ref<string>('')
const css = ref('');
const data = ref<Record<string, any> | undefined>(undefined)
const error = ref('');
const templateTags = ref<string[]>([])

function escapeRegExp(s: string) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

const compiledHtml = computed(() => {
  const htmlSource = rawHtml.value
  const map = (data.value && typeof data.value === 'object') ? data.value : {}

  // If no tags yet, just return
  if (!templateTags.value || templateTags.value.length === 0) return htmlSource

  let html = htmlSource

  for (const key of templateTags.value) {
    console.log('replacing', key)
    const val = map[key]

    // allow undefined/null → empty string
    if (val === undefined || val === null) continue
    if (typeof val === 'object') continue

    const replacement = String(val)
    const pattern = new RegExp(`\\[\\[\\[${escapeRegExp(key)}]]]`, 'g')
    html = html.replace(pattern, replacement)
  }

  return html
})

// --- Helpers ---
const get = (obj: any, path: string) => path.split('.').reduce((o, k) => (o && k in o ? o[k] : undefined), obj);

const NUMERIC_TAGS = new Set([
  'followers_total',
  'followed_total',
  'subscribers_total',
  'subscribers_points',
  'user_view_count',
  'goals_latest_target',
  'goals_latest_current',
  'channel_delay',
]);

const BOOL_TAGS = new Set(['subscribers_latest_is_gift', 'channel_is_branded']);

function coerceForTag(tag: string, raw: any) {
  if (NUMERIC_TAGS.has(tag)) return Number(raw ?? 0);
  if (BOOL_TAGS.has(tag)) return Boolean(raw);
  return raw ?? '';
}

// Apply one op onto the overlay data
function applyOp(data: Record<string, any>, op: any, event: any) {
  const by = op.byPath ? get(event, op.byPath) : op.by;
  const value = op.from ? get(event, op.from) : op.value;

  switch (op.op) {
    case 'set': {
      data[op.tag] = coerceForTag(op.tag, value);
      break;
    }
    case 'inc': {
      const cur = Number(data[op.tag] ?? 0);
      const delta = Number(coerceForTag(op.tag, by ?? 1));
      data[op.tag] = cur + delta;
      break;
    }
    case 'dec': {
      const cur = Number(data[op.tag] ?? 0);
      const delta = Number(coerceForTag(op.tag, by ?? 1));
      data[op.tag] = cur - delta;
      break;
    }
    case 'max': {
      const nv = Number(coerceForTag(op.tag, value));
      const cur = Number(data[op.tag] ?? 0);
      data[op.tag] = Math.max(cur, nv);
      break;
    }
    case 'push': {
      const arr = Array.isArray(data[op.tag]) ? data[op.tag] : [];
      const v = coerceForTag(op.tag, value);
      if (v !== undefined) arr.unshift(v);
      if (op.limit && arr.length > op.limit) arr.length = op.limit;
      data[op.tag] = arr;
      break;
    }
  }
}

// EventSub → template tag mapping rules
const EVENT_RULES: Record<string, Array<any>> = {
  // FOLLOWS
  'channel.follow': [
    { op: 'inc', tag: 'followers_total', by: 1 },
    { op: 'set', tag: 'followers_latest_user_name', from: 'data.user_name' },
    { op: 'set', tag: 'followers_latest_user_id', from: 'data.user_id' },
    { op: 'set', tag: 'followers_latest_date', from: 'data.followed_at' },
  ],

  // SUBS (new sub message)
  'channel.subscribe': [
    { op: 'inc', tag: 'subscribers_total', by: 1 },
    { op: 'set', tag: 'subscribers_latest_user_name', from: 'data.user_name' },
    { op: 'set', tag: 'subscribers_latest_tier', from: 'data.tier' },
    { op: 'set', tag: 'subscribers_latest_is_gift', from: 'data.is_gift' },
    { op: 'set', tag: 'subscribers_latest_gifter_name', from: 'data.gifter_name' },
  ],

  // MASS GIFT (gift bomb)
  'channel.subscription.gift': [
    // Twitch CLI often sends "total" for count this message gifted
    { op: 'inc', tag: 'subscribers_total', byPath: 'data.total' },
    { op: 'set', tag: 'subscribers_latest_is_gift', value: true },
    { op: 'set', tag: 'subscribers_latest_gifter_name', from: 'data.user_name' },
  ],

  // CHEER
  'channel.cheer': [
    { op: 'set', tag: 'last_cheer_user', from: 'data.user_name' },
    { op: 'set', tag: 'last_cheer_bits', from: 'data.bits' },
  ],

  // RAID
  'channel.raid': [
    { op: 'set', tag: 'last_raid_from', from: 'data.from_broadcaster_user_name' },
    { op: 'max', tag: 'last_raid_viewers_peak', value: 0 }, // ensures numeric init
    { op: 'max', tag: 'last_raid_viewers_peak', from: 'data.viewers' },
  ],
};

function injectStyle(styleString: string) {
  const existing = document.getElementById('overlay-style');
  if (existing) existing.remove();

  const style = document.createElement('style');
  style.id = 'overlay-style';
  style.textContent = styleString;
  document.head.appendChild(style);
}

onMounted(async () => {
  data.value = {}
  try {
    const response = await fetch('/api/overlay/render', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slug: props.slug, token: props.token }),
    });

    if (!response.ok) throw new Error('Failed to load overlay');

    const json = await response.json();
    rawHtml.value = json.template.html;
    templateTags.value = json.template.tags ?? []
    css.value = json.template.css;
    data.value = json.data ?? {}

    injectStyle(css.value);

    document.title = json.meta?.name || 'Overlay';
    document.getElementById('loading')?.remove();
  } catch (err) {
    error.value = err.message;
    document.getElementById('loading')?.remove();
  }

  useEventSub((event) => {
    if (!data.value || typeof data.value !== 'object') data.value = {}
    const rules = EVENT_RULES[event.type]
    if (!rules) return
    for (const op of rules) applyOp(data.value, op, event)
    bump()
  })
});
</script>
