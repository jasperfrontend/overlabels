<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check, Download, ExternalLink, EyeOff, Sliders, Trash2 } from '@lucide/vue';
import type { AppPageProps } from '@/types';
import { urlWithTab } from '@/composables/useAddressableTabs';
import { useConfirm } from '@/composables/useConfirm';
import { withLastMileHint } from '@/composables/useUiMode';
import ProductBadge from '@/components/ProductBadge.vue';
import ProductIcon from '@/components/product-icons/ProductIcon.vue';
import { hasProductIcon } from '@/components/product-icons/registry';
import ServiceLogo from '@/components/ServiceLogo.vue';
import { useEventColors } from '@/composables/useEventColors';
import ProductsLayout, { type ProductCategory } from '@/layouts/ProductsLayout.vue';
import ProductServices from '@/components/ProductServices.vue';
import type { ProductService } from '@/components/ProductServices.vue';
import AddToObsButton from '@/components/AddToObsButton.vue';
import AlertsOverlayPanel, { type AlertsOverlayState } from '@/components/AlertsOverlayPanel.vue';
import ProductRailStep from '@/components/ProductRailStep.vue';
import { productMoves, type MoveKey, type Moves } from '@/utils/productMoves';
import { SERVICE_LABELS, serviceLabel } from '@/utils/services';

type WireState = 'satisfied' | 'missing' | 'not_applicable';

interface Wire {
  key: string;
  state: WireState;
  label: string;
  message: string;
  route: string;
  cta: string;
}

interface Subject {
  key: string;
  label: string;
  context: string[];
  wires: Wire[];
  missing: number;
  applicable: boolean;
  needsAttention: boolean;
}

interface Ingredient {
  key: string;
  question: string;
  choices: { value: string; label: string }[];
  default: string;
}

interface Product {
  slug: string;
  name: string;
  description: string;
  category: string | null;
  requires_bot: boolean;
  hero: string | null;
  ingredients: Ingredient[];
  integrations: string[];
  notes: string[];
  ready_message: string | null;
  /** One line under the Add to OBS button: how big to make the browser source. Null when the dialog's own advice is right. */
  obs_hint: string | null;
  designer: Designer | null;
}
/**
 * The product's designer, declared by its manifest. Picking a look happens
 * on the designer, not here, so this page reads nothing from a preset but its
 * existence: how many there are, and what the page's groups are called, are
 * the two things the designer's card quotes. Null is a product with no
 * designer, and no card.
 */
interface Designer {
  presets: { key: string }[];
  groups: string[];
}

interface InstalledOverlay {
  ref: string;
  name: string;
  slug: string;
  id: number;
  type: string;
  /**
   * Not alerts: when a real overlay link last loaded it ("3 minutes ago"),
   * or null when none ever has. The designer's preview does not count.
   */
  loaded_ago?: string | null;
  /** Alerts only: the events it fires on, as the Triggers tab labels them. */
  fires_on?: string[];
  /** Alerts only: the static overlays it renders inside. Empty means every one. */
  targets?: string[];
  /** Alerts only: what else the connected service sends that this alert does not fire on. */
  more_events?: string[];
}

interface TestGuide {
  service: string;
  service_label: string;
  url: string;
  label: string;
  steps: string[];
  settings_url: string;
}

interface Landed {
  from_name: string;
  formatted_amount: string;
  at: string | null;
}

interface Installed {
  installed_at: string;
  subject: Subject | null;
  overlays: InstalledOverlay[];
  ingredients: Record<string, string>;
  /** Every donation service the product's alerts fire on, each with its connect. Empty for a product with no such alert. */
  services: ProductService[];
  /**
   * For a product that installs an alert and nothing of its own for OBS:
   * `loaded` when the Alerts overlay is live or an overlay link has served a
   * static overlay lately, and those overlays by name; `alerts_overlay` is
   * the Alerts overlay the beat asks for otherwise. Empty (and
   * `alerts_overlay` null) for a product with its own stage.
   */
  your_overlays: { loaded: boolean; overlays: { id: number; name: string; slug: string }[]; alerts_overlay: AlertsOverlayState | null };
  test_guide: TestGuide | null;
  landed: Landed | null;
  removes: string[];
}

const props = defineProps<{
  product: Product;
  installed: Installed | null;
  /**
   * True on the one view that ended this product's setup flow, and never
   * again: the server computes it from the flow it is ending.
   */
  arrived: boolean;
  categories: ProductCategory[];
  installed_count: number | null;
  /**
   * The Twitch Chat Overlay's redacted-messages setting (`!hide`), for the
   * card that opens its settings page. Null for every other product.
   */
  chat_redact?: { enabled: boolean; command: string } | null;
}>();

const page = usePage<AppPageProps>();
const { eventTypeDotClass } = useEventColors();
const installError = computed(() => (page.props.errors as Record<string, string> | undefined)?.install);

// Only the wires that apply to this product are steps. A wire that does not
// apply is not a step someone skipped, so it is not shown at all here: the
// product page is a checklist, and a checklist with greyed-out lines for
// things that were never part of the product reads as unfinished.
const steps = computed(() => (props.installed?.subject?.wires ?? []).filter((wire) => wire.state !== 'not_applicable'));
const remaining = computed(() => steps.value.filter((wire) => wire.state === 'missing').length);
// The first missing wire is the one to do now; the rest stay open beside it.
const currentWire = computed(() => steps.value.find((wire) => wire.state === 'missing')?.key ?? null);
// On the rail the ticks come first, so it reads as progress top to bottom:
// a tick below the step to do now would look like it came after it.
const railWires = computed(() => [
  ...steps.value.filter((wire) => wire.state === 'satisfied'),
  ...steps.value.filter((wire) => wire.state === 'missing'),
]);

// Installed with nothing left in the setup flow. A product whose circuit has
// no subject has no wires, so nothing is left for it either.
const finished = computed(() => !!props.installed && remaining.value === 0);

// The product's questions, answered with their defaults until the person
// picks otherwise.
const answers = ref<Record<string, string>>(Object.fromEntries(props.product.ingredients.map((ingredient) => [ingredient.key, ingredient.default])));

function answerLabel(ingredient: Ingredient): string {
  const value = props.installed?.ingredients[ingredient.key];
  return ingredient.choices.find((choice) => choice.value === value)?.label ?? value ?? '';
}

function install(): void {
  router.post(route('products.install', props.product.slug), { ingredients: answers.value });
}

// Only a static overlay goes into OBS. An alert renders inside the static
// overlays it targets, so offering it an "Add to OBS" button sends the person
// to a page that warns them off doing exactly that.
const stages = computed(() => (props.installed?.overlays ?? []).filter((overlay) => overlay.type !== 'alert'));
const alerts = computed(() => (props.installed?.overlays ?? []).filter((overlay) => overlay.type === 'alert'));
const stageNames = computed(() => joinNames(stages.value.map((stage) => stage.name)));

// The designer. Its looks are its opening move, not a row of cards here: ten
// previews of someone else's chat asked the streamer to choose before they
// could see their own in it. All this page does with the designer is count
// its looks, name its groups and point at the one door that has them.
const designer = computed(() => props.product.designer);
const designerGroups = computed(() => joinNames((designer.value?.groups ?? []).map((title) => title.toLowerCase())));

function joinNames(names: string[]): string {
  return names.length <= 1 ? (names[0] ?? '') : `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`;
}

// Every service the alert fires on, with its connect. The test-tip move runs
// once per connected service that has a test button of its own; the picked
// service's guide stands in for a product with no services block.
const services = computed(() => props.installed?.services ?? []);
const unconnected = computed(() => services.value.filter((service) => !service.connected));
const testGuides = computed<TestGuide[]>(() => {
  const own = services.value.flatMap((service) => (service.connected && service.test_guide ? [service.test_guide] : []));
  if (own.length) return own;
  return props.installed?.test_guide ? [props.installed.test_guide] : [];
});

// A product that installs only an alert has nothing of its own for OBS: the
// alert renders inside the Alerts overlay (every static overlay until that is
// live). The OBS move asks for the Alerts overlay.
const yourOverlays = computed(() => props.installed?.your_overlays ?? { loaded: false, overlays: [], alerts_overlay: null });

// Up to three names, then a count: "Scene, Clock, Chat and 4 more".
const loadedNames = computed(() => {
  const names = yourOverlays.value.overlays.map((overlay) => overlay.name);
  const extra = names.length - 3;
  return extra > 0 ? `${names.slice(0, 3).join(', ')} and ${extra} more` : joinNames(names);
});

// One service is the usual product, and the block speaks to it by name.
const servicesTitle = computed(() => (services.value.length === 1 ? `Connect ${services.value[0].label}` : 'Connect the services you take tips on'));
const servicesLead = computed(() =>
  services.value.length === 1
    ? `The alert fires on ${services.value[0].label} the moment it is connected.`
    : 'A tip from any connected one lands as the alert. Connect the ones you use, now or later, right here.',
);

// An inline link in the page's smaller print: violet, underlined at half
// strength until hovered. One string so the sites cannot drift.
const noteLink =
  'cursor-pointer text-violet-600 underline decoration-violet-600/45 underline-offset-2 hover:text-violet-500 hover:decoration-current dark:text-violet-400 dark:decoration-violet-400/45 dark:hover:text-violet-300';

// "Watch it land". The server says whether an event one of the product's
// alerts fires on has arrived since the install, so a reload after the test
// tip still says so; the alert broadcast flips it the moment one lands while
// the page is open. The broadcast carries the template's slug, which is how
// this product's alert is told apart from any other.
const landed = ref<Landed | null>(props.installed?.landed ?? null);
watch(
  () => props.installed?.landed,
  (value) => {
    if (value) landed.value = value;
  },
);
const landedLine = computed(() => {
  if (!landed.value) return '';
  const { from_name: who, formatted_amount: amount } = landed.value;
  if (who && amount) return `${who} tipped ${amount}`;
  return who ? `${who} tipped` : '';
});
const alertSlugs = computed(() => new Set(alerts.value.map((alert) => alert.slug)));
let channel: any = null;

function onAlertTriggered(e: { alert?: { alert_template_slug?: string; data?: Record<string, unknown> } }): void {
  const slug = e.alert?.alert_template_slug;
  if (!slug || !alertSlugs.value.has(slug)) return;
  landed.value = {
    from_name: String(e.alert?.data?.['event.from_name'] ?? ''),
    formatted_amount: String(e.alert?.data?.['event.formatted_amount'] ?? ''),
    at: new Date().toISOString(),
  };
  // The tip came through the service's link, so the server now counts the
  // service as reached; read that back rather than leave the page saying
  // "paste your link" next to a tip that used it.
  router.reload({ only: ['installed'] });
}

onMounted(() => {
  const twitchId = (page.props.auth as any)?.user?.twitch_id;
  const echo = (window as any).Echo;
  if (!props.installed || !alerts.value.length || !twitchId || !echo) return;
  channel = echo.private(`alerts.${twitchId}`);
  channel.listen('.alert.triggered', onAlertTriggered);
});

// What is left once the flow is done, as moves: see productMoves. Empty on
// any page that is not finished.
const allMoves = computed<Moves>(() =>
  props.installed
    ? productMoves({
        stages: stages.value.map((stage) => ({ loaded: !!stage.loaded_ago })),
        alerts: alerts.value.length,
        hostLoaded: yourOverlays.value.loaded,
        services: services.value.map(({ service, kind, connected, webhook_url, received }) => ({ service, kind, connected, webhook_url, received })),
        tests: testGuides.value.map((guide) => guide.service),
        landed: landed.value !== null,
      })
    : { moves: [], current: null, pending: 0, paste: [] },
);
const moves = computed<Moves>(() => (finished.value ? allMoves.value : { moves: [], current: null, pending: 0, paste: [] }));

// While setup steps are still open, the moves after them are drawn on the
// rail as what comes next, greyed and without their actions: nothing in them
// can be done yet. Connecting a service is left out, because the integration
// wire above already carries the connect.
const upcomingMoves = computed(() =>
  props.installed && !finished.value ? allMoves.value.moves.filter((move) => !move.done && move.key !== 'services') : [],
);

function upcomingTitle(key: MoveKey): string {
  if (key === 'obs') return stages.value.length ? `Add ${stageNames.value} to OBS` : 'Add your Alerts overlay to OBS';
  return testTitle(key);
}

function upcomingLine(key: MoveKey): string {
  if (key !== 'obs') return '';
  return stages.value.length
    ? 'One click makes your link, and you drag it onto OBS as a browser source.'
    : 'The alert shows inside your Alerts overlay, one overlay that all your alerts share.';
}

// Before the install, the rail is the plan: Install, then what usually follows
// it for this kind of product. Each one is phrased so it stays true if it is
// already done (the bot already on, the service already connected): after the
// install that step simply shows its tick.
const previews = computed(() => {
  if (props.installed) return [];
  const list: { key: string; title: string; line: string }[] = [];
  const alerting = props.product.category === 'alert';
  const named = alerting ? props.product.integrations.filter((service) => service in SERVICE_LABELS).map(serviceLabel) : [];

  if (props.product.requires_bot) {
    list.push({
      key: 'bot',
      title: 'Have the Overlabels bot in your chat',
      line: 'It works through chat, so the bot needs to be switched on and a moderator in your channel. Already there? Then this one ticks itself.',
    });
  }
  if (named.length) {
    list.push({ key: 'services', title: `Connect ${joinNames(named)}`, line: 'Already connected? Then this one ticks itself.' });
  }
  list.push(
    alerting
      ? {
          key: 'obs',
          title: 'Add your Alerts overlay to OBS',
          line: 'The alert shows inside your Alerts overlay, one overlay that all your alerts share.',
        }
      : { key: 'obs', title: 'Add it to OBS', line: 'One click makes your link, and you drag it onto OBS as a browser source.' },
  );
  if (named.length) {
    list.push({ key: 'test', title: 'Send yourself a test tip', line: `From ${joinNames(named)}, and watch it land on this page.` });
  }
  return list;
});

// How many steps the rail still holds, for the heading.
const stepsLeft = computed(() => {
  if (!props.installed) return 1 + previews.value.length;
  if (!finished.value) return remaining.value + upcomingMoves.value.length;
  return moves.value.pending;
});

const COUNT_WORDS = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];

function countWord(n: number): string {
  return COUNT_WORDS[n] ?? String(n);
}

const heading = computed(() => {
  if (!props.installed) return `On stream in ${countWord(stepsLeft.value)} steps`;
  if (stepsLeft.value === 0) return 'Everything is in place';
  const word = countWord(stepsLeft.value);
  return `${word.charAt(0).toUpperCase()}${word.slice(1)} ${stepsLeft.value === 1 ? 'step' : 'steps'} left`;
});

const lead = computed(() => {
  if (!props.installed) return 'Install it, and this page walks you through the rest.';
  if (stepsLeft.value === 0) return props.product.ready_message;
  return `${props.product.name} is installed. Finish the steps below to put it on stream.`;
});

const allDone = computed(() => !!props.installed && stepsLeft.value === 0);
const eyebrow = computed(() => (moves.value.pending === 1 ? 'Last step' : 'Next step'));
const wireEyebrow = computed(() => (stepsLeft.value === 1 ? 'Last step' : 'Next step'));

// The designer's button is the page's primary only once nothing else is.
const designerPrimary = computed(() => allDone.value);

// "Installed 2 days ago", from the circuit's own context line.
const installedLine = computed(() => props.installed?.subject?.context?.[0] ?? 'Installed');
const lastTestKey = computed(() => [...moves.value.moves].reverse().find((move) => move.key.startsWith('test:') || move.key === 'watch')?.key);
const obsDone = computed(() => moves.value.moves.find((move) => move.key === 'obs')?.done ?? true);

function guideFor(key: MoveKey): TestGuide | undefined {
  return testGuides.value.find((guide) => `test:${guide.service}` === key);
}

// The paste of our link on a service's side rides on that service's test-tip
// move, since only the test can prove it (productMoves). A service with no
// test move of its own rides on the last one, so no paste is ever dropped.
function pasteFor(key: MoveKey): ProductService[] {
  const testKeys = new Set(moves.value.moves.map((move) => move.key));
  return services.value.filter(
    (service) =>
      moves.value.paste.includes(service.service) &&
      (key === `test:${service.service}` || (key === lastTestKey.value && !testKeys.has(`test:${service.service}`))),
  );
}

function testTitle(key: MoveKey): string {
  const label = guideFor(key)?.service_label;
  const paste = pasteFor(key);
  if (!label) return paste.length ? `Paste your link into ${joinNames(paste.map((service) => service.label))}, then watch it land` : 'Watch it land';
  return paste.length ? `Paste your link into ${label}, then send yourself a test tip` : `Send yourself a test tip from ${label}`;
}

// After "Close this screen and continue" in the OBS dialog: ask the server
// whether a real link has loaded the overlay. OBS fetches a browser source the
// moment it is made, so by the time someone has ticked the box and pressed
// Continue the load is normally logged, and the move turns into its tick where
// they clicked. One more look a few seconds later covers a slow OBS; nothing
// beyond that, and no polling. If OBS never loaded it the move stays open,
// which is the honest answer. The person's own tick is never taken as proof.
let recheckTimer: ReturnType<typeof setTimeout> | null = null;

function recheck(): void {
  if (recheckTimer) clearTimeout(recheckTimer);
  router.reload({
    only: ['installed'],
    onSuccess: () => {
      if (obsDone.value) return;
      recheckTimer = setTimeout(() => router.reload({ only: ['installed'] }), 4000);
    },
  });
}

// Coming back to this tab is the other moment worth a look: plenty of people
// press Continue first and only then switch to OBS to drop the link in. While
// the OBS move is open, returning to the page asks once, at most every five
// seconds. Still not polling: nothing happens while the page is not looked at.
let lastReturnCheck = 0;

function onReturn(): void {
  if (document.visibilityState !== 'visible' || obsDone.value) return;
  const now = Date.now();
  if (now - lastReturnCheck < 5000) return;
  lastReturnCheck = now;
  router.reload({ only: ['installed'] });
}

onMounted(() => {
  window.addEventListener('focus', onReturn);
  document.addEventListener('visibilitychange', onReturn);
});

// Stop listening rather than leave: the channel is shared with anything else
// on the account that listens, and leaving would take it down for them too.
onBeforeUnmount(() => {
  channel?.stopListening('.alert.triggered', onAlertTriggered);
  if (recheckTimer) clearTimeout(recheckTimer);
  window.removeEventListener('focus', onReturn);
  document.removeEventListener('visibilitychange', onReturn);
});

// A tick lands (the pop-in) on the arrival view, and on a move that turns
// done while the page is open. A revisit shows the ticks already sitting
// there: a celebration that replays on every visit stops meaning anything.
const flipped = ref(new Set<MoveKey>());
watch(
  () => moves.value.moves.map((move) => [move.key, move.done] as const),
  (now, before) => {
    const wasOpen = new Set(before.filter(([, done]) => !done).map(([key]) => key));
    const turned = now.filter(([key, done]) => done && wasOpen.has(key)).map(([key]) => key);
    if (turned.length) flipped.value = new Set([...flipped.value, ...turned]);
  },
);

// The arrival. The setup flow lights its next step with data-product-focus
// while it runs, but on this view it has just ended, so it lights nothing;
// the page lights its own current move instead (the binding in the template,
// a STATE: it is on whenever the move really is not done) and, only on the
// view that ended the flow, scrolls to it (an EVENT). Same split, and the
// same fuchsia, as useProductFocus.
//
// `arrivedNow` is captured before the prop is cleared, so the band's tick
// still lands on this view. The prop is cleared with replaceProp because Back
// and Forward restore this page from its history entry rather than the server,
// and without it they would scroll again. The watch covers the last step
// finished inline on this page, where Inertia reuses this component and
// onMounted does not run a second time.
const arrivedNow = ref(props.arrived);
const movesEl = ref<HTMLElement | null>(null);

function arrive(): void {
  arrivedNow.value = true;
  router.replaceProp('arrived', false);

  // After Inertia's own scroll reset for the visit, which runs once the page
  // has mounted and, in Firefox with smooth scrolling, a frame later still:
  // two frames and a task puts this behind it. Under reduced motion it jumps;
  // 'auto' would not, since app.css makes the page scroll smoothly. Focus is
  // not moved: .btn rings on :focus, and a mouse arrival would stack a violet
  // ring on the fuchsia glow.
  requestAnimationFrame(() =>
    requestAnimationFrame(() =>
      setTimeout(() => {
        const el = movesEl.value?.querySelector<HTMLElement>('[aria-current="step"]');
        if (!el) return;
        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        el.scrollIntoView({ block: 'center', behavior: reduced ? 'instant' : 'smooth' });
      }),
    ),
  );
}

onMounted(() => {
  if (props.arrived) arrive();
});
watch(
  () => props.arrived,
  (now) => {
    if (now) arrive();
  },
);

const { confirm } = useConfirm();
const uninstallError = computed(() => (page.props.errors as Record<string, string> | undefined)?.uninstall);

// The dialog names exactly what goes, from the install's own ledger. An
// integration the streamer had before the install is not on the list and
// is not touched.
async function uninstall(): Promise<void> {
  const removes = props.installed?.removes ?? [];
  const lines = removes.length ? removes.map((line) => `- ${line}`).join('\n') : '- Nothing is left to remove; only the install record goes.';
  const ok = await confirm({
    title: `Uninstall ${props.product.name}?`,
    message: `This removes:\n${lines}\n\nYou can install it again afterwards.`,
    confirmLabel: 'Uninstall',
  });
  if (!ok) return;
  // A refusal renders its error here at the foot of the page, so the page
  // stays where the button is rather than jumping to the top.
  router.post(route('products.uninstall', props.product.slug), {}, { preserveScroll: 'errors' });
}
</script>

<template>
  <Head>
    <title>{{ product.name }}</title>
    <meta name="description" :content="product.description" />
  </Head>

  <ProductsLayout
    :categories="categories"
    :category="product.category"
    :installed-count="installed_count"
    :crumb="product.name"
    :heading="product.name"
    :slug="product.slug"
    :lead="product.description"
  >
    <!-- The product's own column keeps the measure it was written for; the
         layout's grid is wider than a page of prose wants to be. -->
    <div class="max-w-4xl">
      <!-- The same artwork as the listing card, full width, as the page's hero. -->
      <img v-if="product.hero" :src="product.hero" alt="" class="mb-4 block aspect-video w-full object-cover" />

      <!-- The page is one rail in every state: before the install it is the
           plan, while setting up it is the progress, and once done it is the
           record. The heading counts what is left, and only when nothing is
           does it celebrate: the product's mark gets its tick, landing (once)
           on the view that ended the setup. No band, no box: the rail's nodes
           carry every state, so there is nothing to make inconsistent. -->
      <header class="flex items-start gap-4 pt-6 sm:gap-5">
        <span class="relative mt-0.5 flex shrink-0">
          <ProductIcon v-if="product.category === 'product' && hasProductIcon(product.slug)" :slug="product.slug" class="size-10 text-foreground" />
          <ProductBadge v-else-if="product.category === 'product'" label="An official Overlabels product" class="size-10 text-cyan-300" />
          <ServiceLogo
            v-else-if="product.integrations[0]"
            :source="product.integrations[0]"
            class="size-9"
            :class="eventTypeDotClass('product', product.integrations[0])"
          />
          <span
            v-if="allDone"
            class="flex items-center justify-center rounded-full bg-green-500 text-white"
            :class="[
              product.category === 'product' || product.integrations[0] ? 'absolute -right-1.5 -bottom-1.5 size-5 ring-2 ring-background' : 'size-9',
              { 'product-tick': arrivedNow },
            ]"
            aria-hidden="true"
          >
            <Check class="size-3" stroke-width="3.5" />
          </span>
        </span>

        <div class="flex min-w-0 flex-col gap-1.5">
          <h2 class="text-2xl leading-tight font-bold tracking-[-0.015em] text-pretty text-foreground sm:text-3xl">{{ heading }}</h2>
          <p v-if="lead" class="max-w-[600px] text-[15px] leading-relaxed text-pretty text-foreground">{{ lead }}</p>
          <!-- After the install, the answers to the product's questions, as a record. -->
          <dl v-if="installed && product.ingredients.length" class="mt-1 flex flex-col gap-1 text-sm">
            <div v-for="ingredient in product.ingredients" :key="ingredient.key" class="flex flex-wrap gap-x-2">
              <dt class="text-muted-foreground">{{ ingredient.question }}</dt>
              <dd class="font-medium text-foreground">{{ answerLabel(ingredient) }}</dd>
            </div>
          </dl>
          <p v-if="installed && installError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ installError }}</p>
        </div>
      </header>

      <!-- Set up: the install, then its wires while any are missing, then the
           moves (productMoves) that put it on stream. Everything after the
           install happens outside this page's control - OBS, Twitch, the
           service's own site - so each node says where it stands and carries
           its own action. Exactly one node is current: it alone glows, and
           an eyebrow says whether it is the last. Nothing is numbered. -->
      <p class="mt-9 mb-4 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Set up</p>
      <ol ref="movesEl" class="flex flex-col" aria-label="Putting it on stream" :data-tab-start="stepsLeft > 0 ? '' : undefined">
        <!-- Not installed: Install is the one thing to do, with the
             product's questions above its button. -->
        <template v-if="!installed">
          <ProductRailStep state="current" aria-current="step">
            <h3 class="text-xl leading-tight font-semibold text-foreground">Install it</h3>
            <p class="text-sm leading-relaxed text-pretty text-foreground">Adds {{ product.name }} to your account.</p>
            <div v-if="product.ingredients.length" class="flex w-full flex-col gap-3">
              <label v-for="ingredient in product.ingredients" :key="ingredient.key" class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-foreground">{{ ingredient.question }}</span>
                <select v-model="answers[ingredient.key]" class="input-border w-full max-w-xs cursor-pointer">
                  <option v-for="choice in ingredient.choices" :key="choice.value" :value="choice.value">{{ choice.label }}</option>
                </select>
              </label>
            </div>
            <button type="button" class="btn btn-primary cursor-pointer" @click="install">
              <Download class="mr-2 size-4" />
              Install
            </button>
            <p v-if="installError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ installError }}</p>
          </ProductRailStep>

          <ProductRailStep v-for="(preview, i) in previews" :key="preview.key" state="upcoming" :last="i === previews.length - 1">
            <h3 class="pt-0.5 text-base leading-[1.35] font-semibold text-muted-foreground">{{ preview.title }}</h3>
            <p class="text-sm leading-relaxed text-pretty text-muted-foreground">{{ preview.line }}</p>
          </ProductRailStep>
        </template>

        <template v-else>
          <ProductRailStep state="done" :ticked="arrivedNow" :last="finished ? !moves.moves.length : !steps.length && !upcomingMoves.length">
            <h3 class="pt-0.5 text-base leading-[1.35] font-semibold text-foreground">{{ installedLine }}</h3>
          </ProductRailStep>

          <!-- Steps left: the product's wires, each with its own fix. A
               missing integration offers its connects right here rather than
               a link out of the page. -->
          <template v-if="!finished">
            <ProductRailStep
              v-for="(wire, i) in railWires"
              :key="wire.key"
              :state="wire.state === 'satisfied' ? 'done' : wire.key === currentWire ? 'current' : 'open'"
              :aria-current="wire.key === currentWire ? 'step' : undefined"
              :last="i === railWires.length - 1 && !upcomingMoves.length"
            >
              <p v-if="wire.key === currentWire" class="text-xs font-semibold tracking-wide text-fuchsia-600 uppercase dark:text-fuchsia-400">
                {{ wireEyebrow }}
              </p>
              <h3
                class="font-semibold text-foreground"
                :class="wire.state === 'satisfied' ? 'pt-0.5 text-base leading-[1.35]' : 'text-xl leading-tight'"
              >
                {{ wire.label }}
              </h3>
              <p class="text-sm leading-relaxed text-pretty" :class="wire.state === 'satisfied' ? 'text-muted-foreground' : 'text-foreground'">
                {{ wire.message }}
              </p>
              <template v-if="wire.state === 'missing'">
                <ProductServices v-if="wire.key === 'product.integration' && services.length" :services="services" />
                <Link v-else :href="route(wire.route)" class="btn cursor-pointer" :class="wire.key === currentWire ? 'btn-primary' : 'btn-secondary'">
                  {{ wire.cta }}
                </Link>
              </template>
            </ProductRailStep>

            <ProductRailStep v-for="(move, i) in upcomingMoves" :key="move.key" state="upcoming" :last="i === upcomingMoves.length - 1">
              <h3 class="pt-0.5 text-base leading-[1.35] font-semibold text-muted-foreground">{{ upcomingTitle(move.key) }}</h3>
              <p v-if="upcomingLine(move.key)" class="text-sm leading-relaxed text-pretty text-muted-foreground">{{ upcomingLine(move.key) }}</p>
            </ProductRailStep>
          </template>

          <!-- Finished: what is left to put it on stream, as moves. A done
               move is its tick and what is now true; an open one carries its
               action right here. -->
          <template v-if="finished">
            <ProductRailStep
              v-for="(move, i) in moves.moves"
              :key="move.key"
              :state="move.done ? 'done' : move.key === moves.current ? 'current' : 'open'"
              :ticked="arrivedNow || flipped.has(move.key)"
              :aria-current="move.key === moves.current ? 'step' : undefined"
              :last="i === moves.moves.length - 1"
            >
              <!-- Done: what is now true. -->
              <template v-if="move.done">
                <div class="flex min-w-0 flex-col items-start gap-1.5 pt-0.5">
                  <!-- Loaded from a real link is what the server can see. That is
                   OBS nearly always, but a browser tab or the phone code
                   counts too, so the line says loaded, never "is in OBS". -->
                  <template v-if="move.key === 'obs' && stages.length">
                    <h3 class="text-base leading-[1.35] font-semibold text-foreground">
                      {{ stageNames }} {{ stages.length === 1 ? 'has' : 'have' }} loaded from your link
                    </h3>
                    <p v-for="stage in stages" :key="stage.id" class="text-sm leading-relaxed text-pretty text-foreground">
                      {{ stages.length === 1 ? 'Last loaded' : `${stage.name} last loaded` }} {{ stage.loaded_ago }}.
                    </p>
                    <p class="text-sm leading-relaxed text-pretty text-muted-foreground">
                      Missing from OBS, or want it in another scene? Add it again - your first link keeps working.
                    </p>
                    <div class="mt-1 flex flex-wrap gap-2">
                      <AddToObsButton
                        v-for="stage in stages"
                        :key="stage.id"
                        :template="stage"
                        variant="again"
                        :hint="product.obs_hint"
                        @done="recheck"
                      />
                    </div>
                  </template>

                  <template v-else-if="move.key === 'obs'">
                    <h3 class="text-base leading-[1.35] font-semibold text-foreground">The alert shows inside {{ loadedNames }}</h3>
                    <p class="text-sm leading-relaxed text-pretty text-foreground">
                      Your overlay link has been serving {{ yourOverlays.overlays.length === 1 ? 'that overlay' : 'those overlays' }} lately, so there
                      is nothing to add to OBS.
                    </p>
                  </template>

                  <!-- The payoff. Read from the stored event on load and flipped
                   live by the alert broadcast. -->
                  <template v-else>
                    <h3 class="text-base leading-[1.35] font-semibold text-foreground">It landed</h3>
                    <p
                      v-if="landedLine"
                      :key="landedLine"
                      class="product-landed product-glow flex items-center gap-2.5 rounded-full border border-green-500/50 bg-green-500/8 px-3.5 py-1.5 text-[13px] font-medium text-green-700 dark:text-green-400"
                      role="status"
                    >
                      <span class="size-2 shrink-0 rounded-full bg-green-500" aria-hidden="true" />
                      {{ landedLine }}
                    </p>
                    <p class="text-sm leading-relaxed text-pretty text-foreground">
                      Every real tip{{ guideFor(move.key) ? ` from ${guideFor(move.key)?.service_label}` : '' }} fires the alert the same way.
                    </p>
                  </template>
                </div>
              </template>

              <!-- Open: the move as a card, with its one action right here. -->
              <template v-else>
                <p v-if="move.key === moves.current" class="text-xs font-semibold tracking-wide text-fuchsia-600 uppercase dark:text-fuchsia-400">
                  {{ eyebrow }}
                </p>

                <!-- A service not connected yet, with its connect right here. Only
                 the unconnected ones: a connected one's link paste rides on
                 its test tip below. -->
                <template v-if="move.key === 'services'">
                  <div class="flex flex-col gap-1">
                    <h3 class="text-xl leading-tight font-semibold text-foreground">{{ servicesTitle }}</h3>
                    <p class="text-sm leading-relaxed text-pretty text-foreground">{{ servicesLead }}</p>
                  </div>
                  <ProductServices :services="unconnected" />
                </template>

                <!-- The product's own stage into OBS: the whole step is one click
                 here, which makes the link and shows it to drag onto OBS. -->
                <template v-else-if="move.key === 'obs' && stages.length">
                  <div class="flex flex-col gap-1">
                    <h3 class="text-xl leading-tight font-semibold text-foreground">Add {{ stageNames }} to OBS</h3>
                    <p class="text-sm leading-relaxed text-pretty text-foreground">
                      {{
                        stages.length === 1
                          ? `One click makes your link. Drag it onto OBS and ${stageNames} appears as a new browser source.`
                          : 'One click makes each link. Drag each one onto OBS and it appears as a new browser source.'
                      }}
                    </p>
                  </div>
                  <div class="flex w-full flex-col gap-3 sm:max-w-sm">
                    <div v-for="stage in stages" :key="stage.id" class="flex flex-col gap-1.5">
                      <p v-if="stages.length > 1" class="text-sm font-medium text-foreground">{{ stage.name }}</p>
                      <AddToObsButton :template="stage" :hint="product.obs_hint" @done="recheck" />
                    </div>
                  </div>
                  <p v-if="product.obs_hint" class="text-sm leading-relaxed text-pretty text-foreground">{{ product.obs_hint }}</p>
                  <p class="text-[13px] leading-relaxed text-pretty text-muted-foreground">
                    Streamlabs Desktop and any other app with a browser source work the same way.
                  </p>
                </template>

                <!-- An alert-only product has nothing of its own for OBS. The
                 alert shows inside the account's Alerts overlay, so the move
                 asks for that one overlay, created and added right here. -->
                <template v-else-if="move.key === 'obs' && yourOverlays.alerts_overlay">
                  <div class="flex flex-col gap-1">
                    <h3 class="text-xl leading-tight font-semibold text-foreground">Add your Alerts overlay to OBS</h3>
                    <p class="text-sm leading-relaxed text-pretty text-foreground">
                      The alert shows inside your Alerts overlay, one overlay that all your alerts share. Add it to OBS once, on top of your scene,
                      and the alert itself needs nothing added.
                    </p>
                  </div>
                  <AlertsOverlayPanel :state="yourOverlays.alerts_overlay" :reload="['installed']" class="w-full sm:max-w-xl" />
                </template>

                <!-- A real test event, from the service's own site, opened in a
                 new tab so this page stays put to notice it land. The steps
                 are the service's, not the product's: ServiceTestGuides. -->
                <template v-else>
                  <h3 class="text-xl leading-tight font-semibold text-foreground">{{ testTitle(move.key) }}</h3>
                  <!-- Our link, still to paste on the service's side. The test
                   below is what proves it went in, so the two share a card. -->
                  <ProductServices v-if="pasteFor(move.key).length" :services="pasteFor(move.key)" inline />
                  <template v-if="guideFor(move.key)">
                    <a
                      :href="guideFor(move.key)?.url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-foreground/35 bg-transparent px-4 py-2 text-sm font-semibold text-foreground hover:border-foreground/60 hover:bg-foreground/6"
                    >
                      <ExternalLink class="size-3.5" />
                      {{ guideFor(move.key)?.label }}
                    </a>
                    <ol class="flex list-decimal flex-col gap-1.25 pl-5 text-sm leading-[1.55] text-foreground">
                      <li v-for="(step, i) in guideFor(move.key)?.steps" :key="i">{{ step }}</li>
                    </ol>
                    <p class="text-[13px] leading-relaxed text-pretty text-muted-foreground">
                      Pressing it again? A second identical test is dropped as a retry, unless
                      <a :href="guideFor(move.key)?.settings_url" :class="noteLink">test mode</a>
                      is on for {{ guideFor(move.key)?.service_label }}.
                    </p>
                  </template>
                  <p
                    v-if="move.key === lastTestKey"
                    class="flex items-center gap-2.5 rounded-full border border-foreground/25 bg-foreground/3 px-3.5 py-2.25 text-[13px] text-foreground"
                    role="status"
                  >
                    <span class="product-pulse size-2 shrink-0 rounded-full bg-blue-300" aria-hidden="true" />
                    Waiting for your test tip. This page notices the moment it arrives.
                  </p>
                </template>
              </template>
            </ProductRailStep>
          </template>
        </template>
      </ol>

      <!-- Make it yours: the optional extras, each a violet node with its own
           icon. The designer is a door, not a wall of cards, and only a
           product whose manifest declares one has it; before the install it
           is drawn greyed, as what the install opens. Its button is the
           page's primary only once nothing above is left to do. Redacted
           messages is a chat setting, not a look, so it is its own node,
           opening /settings/chat. -->
      <template v-if="designer || (installed && chat_redact)">
        <p class="mt-10 mb-4 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          Make it yours<span v-if="!installed" class="font-medium tracking-normal normal-case"> - after install</span>
        </p>
        <ol class="flex flex-col">
          <ProductRailStep v-if="designer" :state="installed ? 'extra' : 'later'" :last="!(installed && chat_redact)">
            <template #icon><Sliders class="size-3.5" /></template>
            <h3 class="text-lg leading-[1.3] font-semibold" :class="installed ? 'text-foreground' : 'text-muted-foreground'">Design its look</h3>
            <p class="max-w-[620px] text-sm leading-relaxed text-pretty" :class="installed ? 'text-foreground' : 'text-muted-foreground'">
              Start from one of {{ designer.presets.length }} looks, then change the {{ designerGroups }} next to your own overlay.
            </p>
            <Link
              v-if="installed"
              :href="withLastMileHint(route('products.design', product.slug), product.slug)"
              class="btn cursor-pointer"
              :class="designerPrimary ? 'btn-primary' : 'btn-secondary'"
            >
              Open the designer
              <ArrowRight v-if="designerPrimary" class="ml-2 size-4" />
            </Link>
          </ProductRailStep>

          <ProductRailStep v-if="installed && chat_redact" state="extra" last>
            <template #icon><EyeOff class="size-3.5" /></template>
            <h3 class="text-lg leading-[1.3] font-semibold text-foreground">
              Let chatters hide a message
              <span class="ml-1 text-sm font-medium" :class="chat_redact.enabled ? 'text-green-700 dark:text-green-400' : 'text-muted-foreground'">
                {{ chat_redact.enabled ? 'On' : 'Off' }}
              </span>
            </h3>
            <p class="max-w-[620px] text-sm leading-relaxed text-pretty text-foreground">
              A chatter types <code class="font-mono">!{{ chat_redact.command }}</code> before their message, and your overlay draws it as solid bars.
              It still shows in Twitch chat as normal.
            </p>
            <Link :href="withLastMileHint(`${route('settings.chat')}#redacted-messages`, product.slug)" class="btn btn-secondary cursor-pointer">
              {{ chat_redact.enabled ? 'Change' : 'Turn it on' }}
            </Link>
          </ProductRailStep>
        </ol>
      </template>

      <!-- Finished: the reference, quiet and below everything that needs
           doing. Facts about the alert are not a step, so they live here and
           not in a card that looks like one. -->
      <section v-if="finished && (alerts.length || product.notes.length)" class="mt-12 flex flex-col gap-8">
        <div v-if="alerts.length" class="flex flex-col gap-3">
          <h3 class="text-sm font-medium text-foreground">How the alert works</h3>
          <div v-for="alert in alerts" :key="alert.id" class="flex flex-col gap-2 text-sm">
            <!-- One line of what it does. Where it shows is said only when it
                 is narrowed to some overlays: "every overlay" is what the OBS
                 move above already says. -->
            <p v-if="alert.fires_on?.length" class="leading-relaxed text-pretty text-foreground">
              <Link
                :href="route('templates.show', alert.id)"
                class="cursor-pointer font-semibold text-foreground underline-offset-2 hover:underline"
                >{{ alert.name }}</Link
              >
              fires on every {{ joinNames(alert.fires_on) }}{{ alert.targets?.length ? `, inside ${joinNames(alert.targets)}` : '' }}.
            </p>
            <p v-else class="leading-relaxed text-pretty text-foreground">
              <Link
                :href="route('templates.show', alert.id)"
                class="cursor-pointer font-semibold text-foreground underline-offset-2 hover:underline"
                >{{ alert.name }}</Link
              >
              fires on nothing right now.
              <Link :href="urlWithTab(route('templates.show', alert.id), 'triggers')" :class="noteLink">Turn its event back on</Link>
            </p>
            <p v-if="alert.more_events?.length" class="text-[13px] leading-relaxed text-pretty text-muted-foreground">
              {{ installed?.test_guide?.service_label ?? 'It' }} also sends {{ joinNames(alert.more_events) }}.
              <Link :href="urlWithTab(route('templates.show', alert.id), 'triggers')" :class="noteLink">The alert's Triggers tab</Link>
              switches those on for the same alert.
            </p>
          </div>
        </div>

        <div v-if="product.notes.length" class="flex flex-col gap-3">
          <h3 class="text-sm font-medium text-foreground">Good to know</h3>
          <ul class="flex list-disc flex-col gap-2 pl-5 text-sm leading-relaxed text-pretty text-foreground marker:text-muted-foreground">
            <li v-for="note in product.notes" :key="note">{{ note }}</li>
          </ul>
        </div>
      </section>

      <!-- The foot of an installed page: the overlay's own controls, and the
           one destructive action, as far from the win as the page allows. -->
      <footer v-if="installed" class="mt-10 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-t border-sidebar-border pt-5">
        <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
          <!-- A product with a designer already has its door to the same
               settings above, so it gets no second one here. -->
          <Link
            v-for="stage in designer ? [] : stages"
            :key="stage.id"
            :href="urlWithTab(route('templates.show', stage.id), 'controls')"
            :class="noteLink"
            class="inline-flex items-center"
          >
            {{ stage.name }} controls
            <ArrowRight class="ml-1 size-3.5" />
          </Link>
        </div>
        <div class="ml-auto flex flex-col items-end gap-2">
          <button type="button" class="btn btn-sm btn-chill cursor-pointer" @click="uninstall">
            <Trash2 class="mr-2 size-4" />
            Uninstall
          </button>
          <p v-if="uninstallError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ uninstallError }}</p>
        </div>
      </footer>
    </div>
  </ProductsLayout>
</template>

<style scoped>
/* The soft inner glow the landed line carries. Same glow the status pills
   wear elsewhere. */
.product-glow {
  box-shadow: inset 0 0 12px 0 rgb(34 197 94 / 0.18);
}

/* The only motion on the page besides the current node's glow
   (ProductRailStep): a tick lands, the waiting dot breathes, the landed line
   arrives. All gone under reduced motion. */
.product-tick {
  animation: product-tick-land 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.product-pulse {
  animation: product-pulse 1.4s ease-in-out infinite;
}

.product-landed {
  animation: product-tick-land 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes product-pulse {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.35;
    transform: scale(0.82);
  }
}

@keyframes product-tick-land {
  from {
    transform: scale(0.4);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .product-tick,
  .product-pulse,
  .product-landed {
    animation: none;
  }
}
</style>
