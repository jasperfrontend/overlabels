<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Eraser, Loader2, MessageSquarePlus, Pause, Play, RotateCcw } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AddToObsButton from '@/components/AddToObsButton.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import ControlKnob from '@/components/controls/ControlKnob.vue';
import { angleColorKeys } from '@/utils/angleKnob';
import { type ConditionSubject, disabledReason } from '@/utils/controlConditions';
import { stageFor, type StageSize } from '@/utils/designerStage';
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

/** A look the streamer saved, under a name of their own. */
interface SavedPreset {
  id: number;
  name: string;
  values: Record<string, string>;
}

/**
 * The account-level extras a manifest may switch on beside the knobs.
 * `sample_chat` is the rate, More and Clear above the preview; the other two
 * are the chat foreach cap and the two display filters, which arrive with
 * their own props only when declared.
 */
type Extra = 'sample_chat' | 'chat_window' | 'chat_filters';

/** A manifest scene. Its events are read by the frame (utils/previewScene.ts), never here. */
interface Scene {
  key: string;
  label: string;
  blurb: string;
  events: unknown[];
}

const props = defineProps<{
  product: { slug: string; name: string };
  overlay: { id: number; name: string; slug: string };
  preview_url: string;
  presets: Preset[];
  skins: Choice[];
  skin_key: string | null;
  groups: { title: string; keys: string[]; personal?: boolean }[];
  controls: Record<string, Control>;
  suggested_fonts: { value: string; hint: string }[];
  fonts_url: string;
  saved_presets: SavedPreset[];
  saved_presets_max: number;
  extras: Extra[];
  stage: StageSize[];
  /** Moments of a server-driven game, played into the preview frame only. */
  scenes: Scene[];
  chat_window?: number;
  chat_window_max?: number;
  /** Top of the sample-chat rate slider, per product; sent with `sample_chat`. */
  sample_chat_max?: number;
  chat_filters?: { hide_commands: boolean; hidden_logins: string[] };
  max_hidden_logins?: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Products', href: '/products' },
  { title: props.product.name, href: `/products/${props.product.slug}/install` },
  { title: 'Design', href: `/products/${props.product.slug}/design` },
];

const page = usePage<AppPageProps>();

function has(extra: Extra): boolean {
  return props.extras.includes(extra);
}

// The frame's URL is read once. Re-reading the prop would mean a re-render
// could swap the src and reload the preview mid-design; the token behind it is
// held in the session for the same reason (ProductDesigner::previewToken).
const frameSrc = ref(props.preview_url);
const frame = ref<HTMLIFrameElement | null>(null);

// A local copy, because every knob writes its own control and reads its own
// value back. The server's prop is the state at page load and nothing more.
const controls = reactive<Record<string, Control>>(JSON.parse(JSON.stringify(props.controls)));

// What the server last confirmed, key by key, tracked apart from `controls`.
// A knob writes its own value the moment it moves, so the local value is what
// was ASKED for, not what was saved. Comparing a write against the local value
// is what made every debounced knob a silent no-op: the mid-drag write had
// already assigned it, so writeControl saw no change and never posted, and
// every slider and color picker on the page moved without saving anything.
const saved = reactive<Record<string, string>>(Object.fromEntries(Object.entries(props.controls).map(([key, control]) => [key, control.value])));
const windowSize = ref(props.chat_window ?? 0);

// Only failures are said out loud. Every successful change is visible in the
// frame on the right, which is the whole point of the page.
const problem = ref<string | null>(null);
const problemKey = ref(0);

function fail(message: string) {
  problem.value = message;
  problemKey.value += 1;
}

/**
 * Keep the history entry as current as the database.
 *
 * Every write on this page goes straight to the server and never through
 * Inertia, so the props Inertia keeps in `history.state` stay what they were at
 * page load. Back and then Forward restores the page from that entry rather
 * than the server: the knobs mount showing the load-time values while the
 * preview frame reloads from the database and draws the real ones. Reproduced
 * on 2026-09-22 - the knob said ticker, the stage sized itself 1920x80, and the
 * frame drew a bottom-stacked feed inside the strip, one message visible.
 *
 * replaceProp rewrites the current entry in place (same trick the What's New
 * card uses), so a restored page agrees with the frame. Called only after the
 * server has confirmed, never on the optimistic assignment.
 */
function remember(prop: string, value: unknown): void {
  router.replaceProp(prop, value);
}

/* ------------------------------------------------------------------ knobs */

function valueOf(key: string): string {
  return controls[key]?.value ?? '';
}

/**
 * Every knob by tag key, for `config.enabled_if`. Read off the live values,
 * so a knob greys out while another one is still being dragged.
 */
const conditionSubjects = computed<Record<string, ConditionSubject>>(() =>
  Object.fromEntries(Object.values(controls).map((c) => [`c:${c.key}`, { key: c.key, label: c.label, config: c.config, value: c.value ?? '' }])),
);

function reasonFor(key: string): string | null {
  return disabledReason(controls[key]?.config, conditionSubjects.value);
}

/** The two live colors an angle knob paints, when its row names two controls that are here. */
function angleColorsFor(key: string): [string, string] | null {
  const keys = angleColorKeys(controls[key]?.config);
  if (!keys || !controls[keys[0]] || !controls[keys[1]]) return null;

  return [valueOf(keys[0]), valueOf(keys[1])];
}

// Per key: is a write on its way, and the newest value asked for meanwhile.
const writing: Record<string, boolean> = {};
const waiting: Record<string, string> = {};

/**
 * One knob, one control, one POST, through the endpoint the Values tab uses.
 *
 * Nothing else has to happen for the overlay to change: the endpoint
 * broadcasts, and both the preview frame and OBS are listening. That is the
 * whole demo, and it is why the designer writes controls directly rather than
 * collecting a form and saving it.
 *
 * At most one write per key is on its way. Anything asked for meanwhile
 * replaces whatever was already waiting, and only the newest is sent once the
 * first answers, so arrow keys held on a slider cost two requests rather than
 * one per press, and an older answer can never land after a newer one and
 * drag the knob back.
 */
async function writeControl(key: string, value: string): Promise<void> {
  const control = controls[key];
  if (!control) return;

  // Moved before the request, so a slider feels immediate and the preset
  // strip re-derives as you drag rather than a beat later.
  control.value = value;

  if (writing[key]) {
    waiting[key] = value;
    return;
  }
  if (saved[key] === value) return;

  writing[key] = true;

  try {
    const { data } = await axios.post(`/templates/${props.overlay.id}/controls/${control.id}/value`, { value });
    // The server clamps numbers to the control's own min/max, so its answer
    // wins over what the input sent - unless a newer value is already waiting.
    saved[key] = typeof data?.value === 'string' ? data.value : value;
    if (!(key in waiting)) control.value = saved[key];
    remember(`controls.${key}.value`, saved[key]);
    // Veil only for the value that will stay. One still waiting brings its own.
    if (!(key in waiting)) expectInFrame([key]);
  } catch (error: unknown) {
    if (!(key in waiting)) control.value = saved[key];
    fail(axios.isAxiosError(error) ? (error.response?.data?.message ?? 'That control did not save.') : 'That control did not save.');
  } finally {
    writing[key] = false;
    if (key in waiting) {
      const next = waiting[key];
      delete waiting[key];
      void writeControl(key, next);
    }
  }
}

/**
 * Mid-drag on a slider or color picker: move the knob, write nothing.
 *
 * The write waits for the release (the knob's `commit`). Writing on every
 * pause in a drag sent one POST, one queued broadcast and one "Applying
 * settings" veil per pause - six pauses, six of each, measured 2026-11-15 -
 * and the preview spent the drag covered while they caught up.
 */
function moveKnob(key: string, value: string): void {
  if (controls[key]) controls[key].value = value;
}

/* ---------------------------------------------------------------- presets */

/** Does the overlay currently hold every value in this bundle? */
function holds(values: Record<string, string>): boolean {
  return Object.entries(values).every(([key, value]) => valueOf(key) === value);
}

/**
 * Which preset the overlay currently holds, derived here on every keystroke.
 *
 * Same rule as the product page: one drifted value means no preset is active,
 * because that is the truth. Nothing is stored either side.
 */
const activePreset = computed(() => props.presets.find((preset) => holds(preset.values))?.key ?? null);

const applying = ref<string | null>(null);

/**
 * A bundle the server has just written onto the controls, built-in or saved:
 * move the knobs, expect it in the frame, and rewrite the history entry once
 * for the whole bundle rather than once per key.
 */
function adoptValues(values: Record<string, string>): void {
  for (const [controlKey, value] of Object.entries(values)) {
    if (!controls[controlKey]) continue;
    // The bundle was written server-side, so this IS the saved value now.
    // Leaving `saved` stale here would make the next knob that lands back on
    // a pre-preset value a no-op again.
    controls[controlKey].value = value;
    saved[controlKey] = value;
  }
  expectInFrame(Object.keys(values).filter((controlKey) => !!controls[controlKey]));
  remember('controls', (current: Record<string, Control>) =>
    Object.fromEntries(
      Object.entries(current).map(([controlKey, control]) => [
        controlKey,
        controlKey in values ? { ...control, value: values[controlKey] } : control,
      ]),
    ),
  );
}

async function applyPreset(key: string): Promise<void> {
  applying.value = key;

  try {
    const { data } = await axios.post(`/products/${props.product.slug}/presets/${key}`);
    adoptValues((data?.values ?? {}) as Record<string, string>);
  } catch {
    fail('That look did not apply.');
  } finally {
    applying.value = null;
  }
}

/* ------------------------------------------------------------ saved looks */

/*
 * The streamer's own looks for this product. A row holds every designer value
 * as it was when it was saved, and "active" is derived by the same comparison
 * the built-ins get - so a look you tuned after applying is shown as "with
 * changes", which is the truth, and Update is how you keep them.
 *
 * `selectedSavedId` is the dropdown's own state, kept apart from which one is
 * active: apply, tweak, update is the whole use of the thing, and a dropdown
 * that snapped back to "nothing" the moment a knob moved would have nothing
 * to update.
 *
 * Only the name ever goes to the server; the values are captured from the
 * controls there. Any name works - the server counts characters, not bytes,
 * and the name never becomes a URL or a key - so there is no maxlength here:
 * HTML counts UTF-16 units and would refuse an emoji name well short of what
 * the server allows.
 */
const savedPresets = ref<SavedPreset[]>(props.saved_presets.map((preset) => ({ ...preset, values: { ...preset.values } })));
const activeSaved = computed(() => savedPresets.value.find((preset) => holds(preset.values))?.id ?? null);
const selectedSavedId = ref<number | null>(activeSaved.value);
const selectedSaved = computed(() => savedPresets.value.find((preset) => preset.id === selectedSavedId.value) ?? null);
const selectedDrifted = computed(() => selectedSaved.value !== null && activeSaved.value !== selectedSaved.value.id);
const atSavedCap = computed(() => savedPresets.value.length >= props.saved_presets_max);
const savedBusy = ref(false);
const newName = ref('');
const renaming = ref(false);
const renameText = ref('');
const confirmingDelete = ref(false);

function savedFailure(error: unknown, fallback: string): string {
  return axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback;
}

function sortSaved(): void {
  savedPresets.value.sort((a, b) => a.name.localeCompare(b.name));
}

function rememberSaved(): void {
  remember(
    'saved_presets',
    savedPresets.value.map((preset) => ({ ...preset, values: { ...preset.values } })),
  );
}

function replaceSaved(preset: SavedPreset): void {
  const index = savedPresets.value.findIndex((row) => row.id === preset.id);
  if (index === -1) savedPresets.value.push(preset);
  else savedPresets.value[index] = preset;
  sortSaved();
  rememberSaved();
}

function onSavedSelect(raw: string): void {
  renaming.value = false;
  confirmingDelete.value = false;
  const id = raw === '' ? null : Number(raw);
  selectedSavedId.value = id;
  if (id !== null) void applySaved(id);
}

async function saveCurrentLook(): Promise<void> {
  const name = newName.value.trim();
  if (!name || savedBusy.value) return;
  savedBusy.value = true;

  try {
    const { data } = await axios.post(`/products/${props.product.slug}/saved-presets`, { name });
    replaceSaved(data.preset as SavedPreset);
    selectedSavedId.value = (data.preset as SavedPreset).id;
    newName.value = '';
  } catch (error: unknown) {
    fail(savedFailure(error, 'That look did not save.'));
  } finally {
    savedBusy.value = false;
  }
}

async function applySaved(id: number): Promise<void> {
  savedBusy.value = true;

  try {
    const { data } = await axios.post(`/products/${props.product.slug}/saved-presets/${id}/apply`);
    adoptValues((data?.values ?? {}) as Record<string, string>);
  } catch (error: unknown) {
    fail(savedFailure(error, 'That look did not apply.'));
  } finally {
    savedBusy.value = false;
  }
}

async function overwriteSaved(): Promise<void> {
  if (!selectedSaved.value || savedBusy.value) return;
  savedBusy.value = true;

  try {
    const { data } = await axios.post(`/products/${props.product.slug}/saved-presets/${selectedSaved.value.id}/overwrite`);
    replaceSaved(data.preset as SavedPreset);
  } catch (error: unknown) {
    fail(savedFailure(error, 'That look did not update.'));
  } finally {
    savedBusy.value = false;
  }
}

function startRename(): void {
  if (!selectedSaved.value) return;
  confirmingDelete.value = false;
  renameText.value = selectedSaved.value.name;
  renaming.value = true;
}

async function renameSaved(): Promise<void> {
  const name = renameText.value.trim();
  if (!selectedSaved.value || !name || savedBusy.value) return;
  savedBusy.value = true;

  try {
    const { data } = await axios.patch(`/products/${props.product.slug}/saved-presets/${selectedSaved.value.id}`, { name });
    replaceSaved(data.preset as SavedPreset);
    renaming.value = false;
  } catch (error: unknown) {
    fail(savedFailure(error, 'That look did not rename.'));
  } finally {
    savedBusy.value = false;
  }
}

async function deleteSaved(): Promise<void> {
  if (!selectedSaved.value || savedBusy.value) return;
  savedBusy.value = true;
  const id = selectedSaved.value.id;

  try {
    await axios.delete(`/products/${props.product.slug}/saved-presets/${id}`);
    savedPresets.value = savedPresets.value.filter((preset) => preset.id !== id);
    selectedSavedId.value = null;
    confirmingDelete.value = false;
    rememberSaved();
  } catch (error: unknown) {
    fail(savedFailure(error, 'That look did not delete.'));
  } finally {
    savedBusy.value = false;
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
 * own receipt - you see the overlay move - and these would otherwise write
 * silently into a page where everything else answers instantly.
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
    remember('chat_window', size);
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

const hideCommands = ref(props.chat_filters?.hide_commands ?? false);
const hiddenLoginsText = ref((props.chat_filters?.hidden_logins ?? []).join('\n'));
const savedLoginCount = ref(props.chat_filters?.hidden_logins.length ?? 0);
const maxHiddenLogins = computed(() => props.max_hidden_logins ?? 0);

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

const overHiddenCap = computed(() => typedLoginCount.value > maxHiddenLogins.value);

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
    const hiddenLogins: string[] = data?.chat_filters?.hidden_logins ?? [];
    savedLoginCount.value = hiddenLogins.length;
    // The entry gets the normalised list, so a restored page shows what is
    // actually hidden. The textarea on screen is still left alone.
    remember('chat_filters', { hide_commands: hideCommands.value, hidden_logins: hiddenLogins });
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
 * What OBS browser source this look wants, and therefore what shape the
 * preview is, as the manifest declares it. A ticker in a 500x800 box would
 * look nothing like a ticker.
 */
const sourceSize = computed(() => stageFor(props.stage, valueOf));

// The frame renders at the real browser-source size and is scaled down to fit,
// so 22px type looks like 22px type relative to the source rather than
// relative to whatever space the column happens to have.
const stageEl = ref<HTMLElement | null>(null);
const stageSize = reactive({ w: 0, h: 0 });
const scale = computed(() => {
  if (!stageSize.w || !stageSize.h) return 1;
  return Math.min(1, stageSize.w / sourceSize.value.w, stageSize.h / sourceSize.value.h);
});

let observer: ResizeObserver | null = null;

/* ------------------------------------------------------------ sample chat */

const rate = ref(0);

// The slider's top, and the speed Play resumes at: the last one played, or
// a quarter of the top on the first press.
const rateMax = computed(() => props.sample_chat_max ?? 120);
const lastRate = ref(0);

function togglePlay(): void {
  if (rate.value > 0) {
    setRate(0);
    return;
  }

  setRate(lastRate.value || Math.max(10, Math.round(rateMax.value / 4 / 10) * 10));
}

function postToFrame(payload: Record<string, unknown>): void {
  frame.value?.contentWindow?.postMessage({ ol: 'chat-sample', ...payload }, window.location.origin);
}

function setRate(perMinute: number): void {
  rate.value = perMinute;
  if (perMinute > 0) lastRate.value = perMinute;
  postToFrame({ rate: perMinute });
}

/* ---------------------------------------------------------------- scenes */

/** The last moment played, until Live puts the real game back. */
const activeScene = ref<string | null>(null);

function playScene(scene: Scene): void {
  activeScene.value = scene.key;
  // A plain copy: props are reactive proxies, and postMessage cannot clone a
  // proxy (it throws DataCloneError and the frame hears nothing).
  const events: unknown[] = JSON.parse(JSON.stringify(scene.events));
  frame.value?.contentWindow?.postMessage({ ol: 'preview-scene', events }, window.location.origin);
}

/**
 * A scene's values live only in the frame, so the real game comes back by
 * loading the frame again. A nonce in the query string, because a src that
 * differs only in its fragment does not load a new document.
 */
function backToLive(): void {
  activeScene.value = null;
  const [path, token] = frameSrc.value.split('#');
  const base = path.replace(/[?&]reload=\d+/, '');
  frameSrc.value = `${base}${base.includes('?') ? '&' : '?'}reload=${Date.now()}#${token}`;
}

/* ------------------------------------------------------------------ veil */

/**
 * The preview is veiled from a confirmed write until the frame has applied it.
 *
 * A knob's write lands in the frame over the same broadcast OBS listens on, a
 * few seconds after the server answers, and a preset is a dozen of those
 * arriving staggered. Watching the overlay restructure itself piece by piece
 * with nothing saying "this is in progress" reads as broken. So the stage is
 * covered from the moment a write is confirmed until every key written has
 * been reported back by the frame (OverlayRenderer's reportApplied), which
 * also covers the font stylesheet landing.
 *
 * The backstop is for a broadcast that never arrives - Reverb down, a frame
 * that reloaded mid-write - so the veil can never stick. It measures SILENCE,
 * restarting on every key the frame reports, rather than the whole wait: a
 * queue that is slow but alive (local `queue:listen` boots a process per job,
 * so a preset's keys land about a second apart) must not have the veil lifted
 * from under it with keys still arriving.
 */
const pendingKeys = reactive(new Set<string>());
const applyingLook = computed(() => pendingKeys.size > 0);
const VEIL_SILENCE_MS = 8000;
let veilBackstop: ReturnType<typeof setTimeout>;

function armVeilBackstop(): void {
  clearTimeout(veilBackstop);
  veilBackstop = setTimeout(() => pendingKeys.clear(), VEIL_SILENCE_MS);
}

function expectInFrame(keys: string[]): void {
  if (keys.length === 0) return;
  for (const key of keys) pendingKeys.add(key);
  armVeilBackstop();
}

function appliedInFrame(key: string): void {
  pendingKeys.delete(key);
  if (pendingKeys.size === 0) clearTimeout(veilBackstop);
  else armVeilBackstop();
}

/**
 * The frame's token has stopped working: mint another and reload the frame.
 *
 * The preview token lives in the URL fragment the frame was loaded with, so
 * the frame reloading ITSELF can only ever retry the dead token - that loop
 * ran forever on 2026-09-22 after a second browser session opened the
 * designer and previewToken() swept this session's token as stale. Only a
 * render of this page mints a fresh one, so the frame hands the 401 up here
 * and this asks the server for just `preview_url` again, then swaps the src.
 *
 * One attempt per window: if the fresh token is dead too, something else is
 * wrong and a reload every few seconds would not fix it.
 */
const RECOVERY_WINDOW_MS = 15_000;
let lastRecoveryAt = 0;

function recoverPreview(): void {
  const now = Date.now();
  if (now - lastRecoveryAt < RECOVERY_WINDOW_MS) return;
  lastRecoveryAt = now;

  router.reload({
    only: ['preview_url'],
    onSuccess: () => {
      pendingKeys.clear();
      clearTimeout(veilBackstop);
      // The fresh URL differs from the dead one only in its fragment, and a
      // fragment-only change to an iframe's src does not load a new document
      // - the frame keeps its dead one and merely gains a hash. A nonce in the
      // query string makes it a real navigation; the overlay ignores it.
      const [path, token] = props.preview_url.split('#');
      frameSrc.value = `${path}${path.includes('?') ? '&' : '?'}reload=${now}#${token}`;
    },
  });
}

/**
 * Keep the frame's token alive while the designer is open.
 *
 * The token runs out two hours after its last renewal
 * (ProductDesigner::TOKEN_LIFETIME_MINUTES), so renewing every twenty minutes,
 * and on coming back to a tab the browser may have throttled, means the frame
 * never sees it expire and never reloads. A 409 says the token is already gone
 * (a laptop that slept past it): recover onto a fresh one. Anything else, a
 * dropped connection, is left for the next tick.
 */
const RENEW_EVERY_MS = 20 * 60_000;
const RENEW_ON_RETURN_AFTER_MS = 5 * 60_000;
let renewTimer: ReturnType<typeof setInterval> | undefined;
let lastRenewAt = Date.now();

async function renewPreviewToken(): Promise<void> {
  lastRenewAt = Date.now();
  try {
    await axios.post(`/products/${props.product.slug}/design/preview-token`);
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 409) recoverPreview();
  }
}

function onVisibilityChange(): void {
  if (document.visibilityState === 'visible' && Date.now() - lastRenewAt > RENEW_ON_RETURN_AFTER_MS) void renewPreviewToken();
}

/**
 * What the frame says: that its feed exists (until then a message would be
 * lost), which control it has just applied, and that its token has expired.
 */
function onFrameMessage(event: MessageEvent): void {
  if (event.source !== frame.value?.contentWindow) return;

  const payload = event.data as { ol?: string; ready?: boolean; applied?: string; expired?: boolean } | null;
  if (payload?.ol !== 'chat-sample') return;

  if (payload.ready) postToFrame({ rate: rate.value });
  if (typeof payload.applied === 'string') appliedInFrame(payload.applied);
  if (payload.expired) recoverPreview();
}

onMounted(() => {
  window.addEventListener('message', onFrameMessage);
  renewTimer = setInterval(() => void renewPreviewToken(), RENEW_EVERY_MS);
  document.addEventListener('visibilitychange', onVisibilityChange);

  if (stageEl.value && typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(([entry]) => {
      stageSize.w = entry.contentRect.width;
      stageSize.h = entry.contentRect.height;
    });
    observer.observe(stageEl.value);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('message', onFrameMessage);
  clearInterval(renewTimer);
  document.removeEventListener('visibilitychange', onVisibilityChange);
  observer?.disconnect();
  clearTimeout(windowDebounce);
  clearTimeout(filtersDebounce);
  clearTimeout(savedFlash);
  clearTimeout(veilBackstop);
});

/** Groups render whatever controls the overlay actually has, never a blank row. */
function keysIn(group: { keys: string[] }): string[] {
  return group.keys.filter((key) => !!controls[key]);
}

const skinControl = computed(() => (props.skin_key ? (controls[props.skin_key] ?? null) : null));
</script>

<template>
  <Head :title="`Design ${product.name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <Heading
          :title="`Design ${product.name}`"
          description="Every change lands in the overlay as you make it - here and in OBS, at the same moment."
          description-class="text-sm text-muted-foreground"
        />
        <div class="flex shrink-0 items-center gap-2">
          <Link :href="`/products/${product.slug}/install`" class="btn btn-sm btn-cancel">Back to {{ product.name }}</Link>
        </div>
      </div>

      <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
        <!-- Knobs -->
        <div class="flex flex-col gap-5">
          <!-- Your looks. Above the built-ins on purpose: once someone has
               saved one, that is the thing they came back for. -->
          <section class="flex flex-col gap-2">
            <h2 class="text-sm font-semibold text-foreground">Your looks</h2>

            <template v-if="savedPresets.length">
              <select
                id="saved-look"
                class="w-full cursor-pointer rounded-sm border border-border bg-background px-2 py-1.5 text-sm text-foreground"
                :value="selectedSavedId ?? ''"
                :disabled="savedBusy"
                aria-label="Your saved looks"
                @change="onSavedSelect(($event.target as HTMLSelectElement).value)"
              >
                <option value="">Pick a saved look</option>
                <option v-for="preset in savedPresets" :key="preset.id" :value="preset.id">{{ preset.name }}</option>
              </select>

              <template v-if="selectedSaved">
                <p class="text-sm text-muted-foreground">
                  <template v-if="selectedDrifted">{{ selectedSaved.name }}, with changes. Update it to keep them.</template>
                  <template v-else>{{ selectedSaved.name }}, as saved.</template>
                </p>

                <form v-if="renaming" class="flex gap-1.5" @submit.prevent="renameSaved">
                  <input
                    v-model="renameText"
                    type="text"
                    class="input-border min-w-0 flex-1 text-sm"
                    aria-label="New name"
                    autofocus
                    @keydown.esc="renaming = false"
                  />
                  <button type="submit" class="btn btn-sm btn-primary" :disabled="!renameText.trim() || savedBusy">Rename</button>
                  <button type="button" class="btn btn-sm btn-cancel" @click="renaming = false">Cancel</button>
                </form>

                <div v-else class="flex flex-wrap gap-1.5">
                  <button type="button" class="btn btn-sm btn-cancel" :disabled="!selectedDrifted || savedBusy" @click="overwriteSaved">
                    Update with current look
                  </button>
                  <button type="button" class="btn btn-sm btn-cancel" :disabled="savedBusy" @click="startRename">Rename</button>
                  <template v-if="confirmingDelete">
                    <button type="button" class="btn btn-sm btn-danger" :disabled="savedBusy" @click="deleteSaved">
                      Delete {{ selectedSaved.name }}
                    </button>
                    <button type="button" class="btn btn-sm btn-cancel" @click="confirmingDelete = false">Keep</button>
                  </template>
                  <button v-else type="button" class="btn btn-sm btn-cancel" :disabled="savedBusy" @click="confirmingDelete = true">Delete</button>
                </div>
              </template>
            </template>

            <form class="flex gap-1.5" @submit.prevent="saveCurrentLook">
              <input
                v-model="newName"
                type="text"
                class="input-border min-w-0 flex-1 text-sm"
                placeholder="Name this look"
                aria-label="Name for the current look"
                :disabled="atSavedCap || savedBusy"
              />
              <button type="submit" class="btn btn-sm btn-primary" :disabled="!newName.trim() || atSavedCap || savedBusy">Save</button>
            </form>
            <p class="text-xs text-muted-foreground">
              <template v-if="atSavedCap"
                >{{ saved_presets_max }} looks saved, which is the most there is room for. Delete one to save another.</template
              >
              <template v-else>
                Saves every knob below as it is right now, under any name you like.
                <template v-if="savedPresets.length">{{ savedPresets.length }} of {{ saved_presets_max }} saved.</template>
              </template>
            </p>
          </section>

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
              <template v-if="activePreset"
                >Pick one of the presets above for a quick start or if you feel adventurous, start tweaking the design below.</template
              >
              <template v-else>Your own settings. Pick a preset to start over from one.</template>
            </p>
          </section>

          <!-- Skin. Only a product whose manifest names a skin key has one.
               Separate from the presets on purpose: the shape of a message
               and the palette on it are two choices, and Terminal in pink is
               one click from here. -->
          <section v-if="skin_key && skinControl" class="flex flex-col gap-2">
            <h2 class="text-sm font-semibold text-foreground">{{ skinControl.label }}</h2>
            <div class="grid grid-cols-2 gap-1.5">
              <button
                v-for="skin in skins"
                :key="skin.value"
                type="button"
                class="cursor-pointer rounded-sm border px-2.5 py-1.5 text-left text-sm"
                :class="
                  valueOf(skin_key) === skin.value
                    ? 'border-violet-500 bg-violet-500/10 text-foreground'
                    : 'border-border text-foreground hover:border-foreground/40'
                "
                :title="skin.hint"
                @click="writeControl(skin_key, skin.value)"
              >
                {{ skin.label }}
              </button>
            </div>
          </section>

          <section v-for="group in groups" :key="group.title" class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-foreground">{{ group.title }}</h2>
            <p v-if="group.personal" class="-mt-2 text-xs text-muted-foreground">
              Set your location here and toggle options you don't want. You can of course set any location you like, we're not judging!
            </p>

            <div v-for="key in keysIn(group)" :key="key" class="flex flex-col gap-1.5">
              <ControlKnob
                :id="`knob-${key}`"
                :control="controls[key]"
                :value="valueOf(key)"
                :suggested-fonts="suggested_fonts"
                :fonts-url="fonts_url"
                :disabled-reason="reasonFor(key)"
                :angle-colors="angleColorsFor(key)"
                @input="moveKnob(key, $event)"
                @commit="writeControl(key, $event)"
              />
            </div>
          </section>

          <!-- Account preferences rather than controls on this overlay: the
               chat foreach cap and the two display filters, each only when
               the manifest asks for it. None of them rides the control
               broadcast, and none of them shows in the preview either, since
               the sample chat has no commands in it and no real logins. So
               they say what they do and when. -->
          <section v-if="has('chat_window') || has('chat_filters')" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
              <h2 class="text-sm font-semibold text-foreground">What the feed shows</h2>
              <span v-if="feedSaved" class="text-xs text-green-600 dark:text-green-400">Saved</span>
            </div>

            <div v-if="has('chat_window')" class="flex flex-col gap-1.5">
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

            <template v-if="has('chat_filters')">
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
                  {{ typedLoginCount }} names typed. Only the first {{ maxHiddenLogins }} are saved.
                </p>
                <p v-else class="text-xs text-muted-foreground">
                  One Twitch username per line, up to {{ maxHiddenLogins }}.
                  <template v-if="savedLoginCount">{{ savedLoginCount }} {{ savedLoginCount === 1 ? 'name is' : 'names are' }} hidden.</template>
                </p>
              </div>
            </template>
          </section>

          <div class="flex flex-col gap-3 border-t border-border pt-5">
            <div>
              <h2 class="text-base font-bold text-foreground">Happy with your design?</h2>
              <p class="mt-1 text-sm text-foreground">
                Click the button below and follow the steps to add {{ product.name }} to OBS as a browser source. You can come back and change your
                design any time. You don't need to add this overlay again once installed! Changes you make on this page will directly reflect on the
                overlay you already have installed. Also, do not click the ADD TO OBS button when you're live, okay?
              </p>
            </div>
            <AddToObsButton :template="overlay" />
          </div>
        </div>

        <!-- Preview -->
        <div class="flex flex-col gap-2 lg:sticky lg:top-4">
          <!-- The sample chat is what sets the preview moving, so it gets a
               bar of its own joined to the stage: Play/Pause, a slider that
               takes the spare width, and the rate read out large. The readout
               has a fixed width so the slider never jumps as it changes. -->
          <div
            v-if="has('sample_chat')"
            class="-mb-2 flex flex-col gap-3 rounded-t-sm border border-b-0 border-border bg-card p-3 sm:flex-row sm:items-center sm:gap-4"
          >
            <button
              type="button"
              class="btn min-w-48 shrink-0"
              :class="rate === 0 ? 'btn-primary' : 'btn-secondary'"
              :aria-pressed="rate > 0"
              @click="togglePlay"
            >
              <Play v-if="rate === 0" class="size-4" />
              <Pause v-else class="size-4" />
              {{ rate === 0 ? 'Play sample chat' : 'Pause sample chat' }}
            </button>

            <div class="flex min-w-0 flex-1 items-center gap-3">
              <label class="sr-only" for="sample-rate">Sample chat speed, messages per minute</label>
              <input
                id="sample-rate"
                type="range"
                class="min-w-0 flex-1 cursor-pointer accent-[#1DAAFF]"
                min="0"
                :max="rateMax"
                step="10"
                :value="rate"
                @input="setRate(Number(($event.target as HTMLInputElement).value))"
              />
              <div class="w-24 shrink-0 text-right leading-tight" aria-live="polite">
                <span class="block text-xl font-semibold text-foreground tabular-nums">{{ rate === 0 ? 'Paused' : rate }}</span>
                <span class="block text-xs text-muted-foreground">{{ rate === 0 ? 'no chat' : 'messages / min' }}</span>
              </div>
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

          <!-- Scenes: moments of a game the server drives, played into the
               preview only, so every color can be seen where it shows up.
               Live reloads the frame onto the real game. -->
          <div
            v-if="scenes.length > 0"
            class="-mb-2 flex flex-col gap-2 rounded-t-sm border border-b-0 border-border bg-card p-3 sm:flex-row sm:items-center sm:gap-4"
          >
            <span class="shrink-0 text-sm font-semibold text-foreground">Show a moment</span>
            <div class="flex min-w-0 flex-1 flex-wrap gap-1.5">
              <button
                v-for="scene in scenes"
                :key="scene.key"
                type="button"
                class="cursor-pointer rounded-full border px-3 py-1 text-sm"
                :class="
                  activeScene === scene.key
                    ? 'border-green-500/60 bg-green-500/10 text-green-700 dark:text-green-300'
                    : 'border-border text-foreground hover:border-foreground/40'
                "
                :title="scene.blurb"
                @click="playScene(scene)"
              >
                {{ scene.label }}
              </button>
            </div>
            <button type="button" class="btn btn-sm btn-cancel shrink-0 cursor-pointer" :disabled="activeScene === null" @click="backToLive">
              <RotateCcw class="mr-1.5 size-3.5" />
              Live
            </button>
          </div>

          <div
            ref="stageEl"
            class="relative flex h-[min(72vh,820px)] items-center justify-center overflow-hidden rounded-sm border border-border bg-background p-2"
          >
            <div :style="{ width: `${sourceSize.w * scale}px`, height: `${sourceSize.h * scale}px` }" class="relative shrink-0">
              <iframe
                ref="frame"
                :src="frameSrc"
                :title="`Your ${overlay.name} overlay`"
                class="absolute top-0 left-0 origin-top-left border-0"
                :style="{ width: `${sourceSize.w}px`, height: `${sourceSize.h}px`, transform: `scale(${scale})` }"
              />
            </div>

            <!-- Covers the whole stage, not just the frame: a layout change
                 resizes the frame under the veil, and the veil must not
                 jump with it. -->
            <div
              v-if="applyingLook"
              class="absolute inset-0 flex items-center justify-center gap-3 bg-black/70 text-sm text-white"
              role="status"
              aria-live="polite"
            >
              <Loader2 class="size-5 animate-spin text-violet-400" />
              Applying settings
            </div>
          </div>

          <!-- The right padding keeps the last line clear of the help beacon,
               which is fixed to the bottom-right of every app page. -->
          <p class="pr-12 text-sm text-muted-foreground">
            The real overlay, at {{ sourceSize.w }}&times;{{ sourceSize.h }} - the browser source size this look wants.
            <template v-if="has('sample_chat')">The chat in it is invented, so it reads the same whether or not you are live.</template>
          </p>
        </div>
      </div>
    </div>
  </AppLayout>

  <RekaToast v-if="problem" :key="problemKey" :message="problem" type="error" @dismiss="problem = null" />
</template>
