<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Bot, Check, Download, ExternalLink, Sliders, Trash2, TriangleAlert } from '@lucide/vue';
import type { AppPageProps } from '@/types';
import { urlWithTab } from '@/composables/useAddressableTabs';
import { useConfirm } from '@/composables/useConfirm';
import { withLastMileHint } from '@/composables/useUiMode';
import ProductBadge from '@/components/ProductBadge.vue';
import ServiceLogo from '@/components/ServiceLogo.vue';
import { useEventColors } from '@/composables/useEventColors';
import ProductsLayout, { type ProductCategory } from '@/layouts/ProductsLayout.vue';
import ProductServices from '@/components/ProductServices.vue';
import type { ProductService } from '@/components/ProductServices.vue';
import AddToObsButton from '@/components/AddToObsButton.vue';
import { productMoves, type MoveKey, type Moves } from '@/utils/productMoves';

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
   * For a product that installs an alert and nothing of its own for OBS: the
   * overlays an overlay link has served lately (`loaded`), else up to five of
   * the person's own, with the total. Empty for a product with its own stage.
   */
  your_overlays: { loaded: boolean; overlays: { id: number; name: string; slug: string }[]; total: number };
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
const done = computed(() => steps.value.length - remaining.value);
const progress = computed(() => (steps.value.length ? Math.round((done.value / steps.value.length) * 100) : 100));

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
// alert renders inside every static overlay the person has there. The OBS
// move offers their own overlays instead, any one of which is enough.
const yourOverlays = computed(() => props.installed?.your_overlays ?? { loaded: false, overlays: [], total: 0 });

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
const moves = computed<Moves>(() =>
  finished.value
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
const eyebrow = computed(() => (moves.value.pending === 1 ? 'Last step' : 'Next step'));
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
    :lead="product.description"
  >
    <!-- The product's own column keeps the measure it was written for; the
         layout's grid is wider than a page of prose wants to be. -->
    <div class="max-w-4xl">
      <!-- The same artwork as the listing card, full width, as the page's hero. -->
      <img v-if="product.hero" :src="product.hero" alt="" class="mb-4 block aspect-video w-full object-cover" />

      <!-- Finished: the win, said once. This is the one page in the app that
           is allowed to celebrate: the person did nothing but press a button
           and follow a few lines, and the page's job is to make that feel
           like it counted. One green band, one heading, one line of what is
           true right now. No "Installed" above it, no "Connected" pill under
           it and no bot line beside it - each said the same thing in other
           words - and Uninstall is at the foot of the page, not next to the
           win. -->
      <header
        v-if="finished"
        class="flex items-start gap-4 border border-green-600/60 bg-green-50 p-5 sm:gap-5 sm:p-6 dark:border-green-500/60 dark:bg-green-950/40"
      >
        <!-- The product's mark with a tick on its corner. The tick lands
             (once) on the view that ended the setup, and just sits there on
             any later visit. -->
        <span class="relative mt-0.5 flex shrink-0">
          <ProductBadge v-if="product.category === 'product'" label="An official Overlabels product" class="size-10 text-cyan-300" />
          <ServiceLogo
            v-else-if="product.integrations[0]"
            :source="product.integrations[0]"
            class="size-9"
            :class="eventTypeDotClass('product', product.integrations[0])"
          />
          <span
            class="flex items-center justify-center rounded-full bg-green-500 text-white"
            :class="[
              product.category === 'product' || product.integrations[0]
                ? 'absolute -right-1.5 -bottom-1.5 size-5 ring-2 ring-green-50 dark:ring-background'
                : 'size-9',
              { 'product-tick': arrivedNow },
            ]"
            aria-hidden="true"
          >
            <Check class="size-3" stroke-width="3.5" />
          </span>
        </span>

        <div class="flex min-w-0 flex-col gap-1.5">
          <h2 class="text-2xl leading-tight font-bold tracking-[-0.015em] text-pretty text-foreground sm:text-3xl">Everything is in place</h2>
          <p v-if="product.ready_message" class="max-w-[600px] text-[15px] leading-relaxed text-pretty text-foreground">
            {{ product.ready_message }}
          </p>
          <dl v-if="product.ingredients.length" class="mt-1 flex flex-col gap-1 text-sm">
            <div v-for="ingredient in product.ingredients" :key="ingredient.key" class="flex flex-wrap gap-x-2">
              <dt class="text-muted-foreground">{{ ingredient.question }}</dt>
              <dd class="font-medium text-foreground">{{ answerLabel(ingredient) }}</dd>
            </div>
          </dl>
          <p v-if="installError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ installError }}</p>
        </div>
      </header>

      <!-- Not installed, or installed with steps left: the state band. The
           product's name and description are the page's own heading and lead,
           above the breadcrumb, so this carries what is true of THIS account:
           whether it is installed, the button that changes that, and the
           questions the install asks. -->
      <header
        v-else
        class="flex flex-col gap-3 border p-5"
        :class="installed ? 'border-green-600/60 bg-green-50 dark:border-green-500/60 dark:bg-green-950/40' : 'border-sidebar-border bg-sidebar'"
      >
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex min-w-0 items-center gap-3">
            <!-- The badge is only ever "an official Overlabels product" in
                 one color; the green band and the Installed line carry the
                 installed state. An alert shows its service's icon instead:
                 it is that service's API inside Overlabels. -->
            <ProductBadge v-if="product.category === 'product'" label="An official Overlabels product" class="size-9 shrink-0 text-cyan-300" />
            <ServiceLogo
              v-else-if="product.integrations[0]"
              :source="product.integrations[0]"
              class="size-8 shrink-0"
              :class="eventTypeDotClass('product', product.integrations[0])"
            />
            <div v-if="installed || product.requires_bot" class="flex min-w-0 flex-col gap-1">
              <p v-if="installed" class="text-xs font-semibold tracking-wide text-green-700 uppercase dark:text-green-400">Installed</p>
              <p v-if="product.requires_bot" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                <Bot class="size-3.5" />
                Works through the Overlabels bot in your chat
              </p>
            </div>
          </div>

          <button v-if="!installed" type="button" class="btn btn-primary shrink-0 cursor-pointer" @click="install">
            <Download class="mr-2 size-4" />
            Install
          </button>
        </div>

        <!-- The product's questions. Before the install they are a form the
             button reads; after it they are a record of what was answered. -->
        <div v-if="!installed && product.ingredients.length" class="flex flex-col gap-3">
          <label v-for="ingredient in product.ingredients" :key="ingredient.key" class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-foreground">{{ ingredient.question }}</span>
            <select v-model="answers[ingredient.key]" class="input-border w-full max-w-xs cursor-pointer">
              <option v-for="choice in ingredient.choices" :key="choice.value" :value="choice.value">{{ choice.label }}</option>
            </select>
          </label>
        </div>
        <dl v-else-if="installed && product.ingredients.length" class="flex flex-col gap-1 text-sm">
          <div v-for="ingredient in product.ingredients" :key="ingredient.key" class="flex flex-wrap gap-x-2">
            <dt class="text-muted-foreground">{{ ingredient.question }}</dt>
            <dd class="font-medium text-foreground">{{ answerLabel(ingredient) }}</dd>
          </div>
        </dl>

        <p v-if="installError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ installError }}</p>
      </header>

      <!-- Finished: what is left to put it on stream, as moves (productMoves).
           Everything here happens outside Overlabels - OBS, the service's own
           site - which is why none of it is a setup step. Exactly one move is
           current, the first one not done: it alone carries the fuchsia glow
           the setup flow used for "this is the button", and an eyebrow that
           says whether it is the last. A done move shrinks to a tick row.
           Nothing is numbered, so a product with one move never shows a lone
           "1". No settings vocabulary here. -->
      <ol
        v-if="finished && moves.moves.length"
        ref="movesEl"
        class="mt-6 flex flex-col gap-3"
        aria-label="Putting it on stream"
        :data-tab-start="moves.current ? '' : undefined"
      >
        <li
          v-for="move in moves.moves"
          :key="move.key"
          :aria-current="move.key === moves.current ? 'step' : undefined"
          :data-product-focus="move.key === moves.current ? '' : undefined"
          :class="
            move.done
              ? 'flex items-start gap-3 border border-green-600/40 p-4 dark:border-green-500/40'
              : 'flex flex-col items-start gap-3 border border-sidebar-border bg-sidebar p-5 sm:p-6 dark:bg-black/35'
          "
        >
          <!-- Done: a tick and what is now true. -->
          <template v-if="move.done">
            <span
              class="mt-px inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-green-500 text-white"
              :class="{ 'product-tick': arrivedNow || flipped.has(move.key) }"
              aria-hidden="true"
            >
              <Check class="size-3.5" stroke-width="3" />
            </span>
            <div class="flex min-w-0 flex-1 flex-col items-start gap-1.5">
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
                  Your overlay link has been serving {{ yourOverlays.overlays.length === 1 ? 'that overlay' : 'those overlays' }} lately, so there is
                  nothing to add to OBS.
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
                 alert renders inside every static overlay in OBS, so the move
                 offers the person's own overlays, each by name: any one of
                 them is enough. -->
            <template v-else-if="move.key === 'obs' && yourOverlays.overlays.length">
              <div class="flex flex-col gap-1">
                <h3 class="text-xl leading-tight font-semibold text-foreground">Put one of your overlays in OBS</h3>
                <p class="text-sm leading-relaxed text-pretty text-foreground">
                  The alert shows inside every overlay of yours that is in OBS, on top of whatever is there. One is enough, and the alert itself needs
                  nothing added.
                </p>
              </div>
              <ul class="flex w-full flex-col divide-y divide-sidebar-border">
                <li
                  v-for="overlay in yourOverlays.overlays"
                  :key="overlay.id"
                  class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0"
                >
                  <span class="min-w-0 truncate text-sm font-medium text-foreground">{{ overlay.name }}</span>
                  <AddToObsButton :template="overlay" variant="compact" @done="recheck" />
                </li>
              </ul>
              <p v-if="yourOverlays.total > yourOverlays.overlays.length" class="text-[13px] leading-relaxed text-muted-foreground">
                And {{ yourOverlays.total - yourOverlays.overlays.length }} more on
                <Link :href="route('templates.index', { type: 'static' })" :class="noteLink">your overlays page</Link>.
              </p>
            </template>

            <template v-else-if="move.key === 'obs'">
              <div class="flex flex-col gap-1">
                <h3 class="text-xl leading-tight font-semibold text-foreground">Make an overlay for the alert to show in</h3>
                <p class="text-sm leading-relaxed text-pretty text-foreground">
                  The alert shows inside an overlay of yours that is in OBS. You have none yet.
                </p>
              </div>
              <Link :href="withLastMileHint(route('templates.create'), product.slug)" class="btn btn-secondary cursor-pointer">Make an overlay</Link>
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
        </li>
      </ol>

      <!-- Steps left, and the product has services: their connects are right
           here too, so the one thing the checklist asks for is a click away
           on this page and not a link out of it. -->
      <section
        v-if="installed && installed.subject && remaining > 0 && services.length"
        class="mt-8 flex flex-col gap-3 border border-sidebar-border bg-sidebar p-5 dark:bg-black/35"
      >
        <div class="flex flex-col gap-1">
          <h2 class="text-base leading-[1.35] font-semibold text-foreground">{{ servicesTitle }}</h2>
          <p class="text-sm leading-relaxed text-pretty text-foreground">{{ servicesLead }}</p>
        </div>
        <ProductServices :services="services" />
      </section>

      <!-- Installed, steps left: the checklist as a progress piece. Once
           remaining hits zero the moves above are the sole post-completion
           view - this section stops rendering rather than sitting underneath
           them saying the same thing twice. -->
      <section v-if="installed && installed.subject && remaining > 0" class="mt-8 flex flex-col gap-3">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
          <h2 class="text-lg font-semibold text-foreground">Finish setting up</h2>
          <span class="text-sm text-fuchsia-600 tabular-nums dark:text-fuchsia-400">
            {{ remaining === 1 ? 'One thing left' : `${remaining} things left` }}
          </span>
        </div>

        <div class="h-2 w-full overflow-hidden bg-muted" role="progressbar" :aria-valuenow="done" :aria-valuemin="0" :aria-valuemax="steps.length">
          <div class="product-progress h-full bg-fuchsia-500" :style="{ width: `${progress}%` }" />
        </div>

        <ul class="flex flex-col gap-2">
          <li
            v-for="wire in steps"
            :key="wire.key"
            class="collection-row border p-3"
            :class="wire.state === 'missing' ? 'border-fuchsia-500/40' : 'border-green-500/40'"
          >
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
              <TriangleAlert v-if="wire.state === 'missing'" class="size-4 shrink-0 self-center text-fuchsia-600 dark:text-fuchsia-400" />
              <span
                v-else
                class="product-tick inline-flex size-5 shrink-0 items-center justify-center self-center rounded-full bg-green-500 text-white"
              >
                <Check class="size-3.5" stroke-width="3" />
              </span>
              <p class="font-medium text-foreground">{{ wire.label }}</p>
            </div>
            <div class="mt-1 flex flex-col gap-2 pl-6 sm:flex-row sm:items-center sm:gap-3">
              <p class="min-w-0 flex-1 text-sm" :class="wire.state === 'missing' ? 'text-foreground' : 'text-muted-foreground'">
                {{ wire.message }}
              </p>
              <Link v-if="wire.state === 'missing'" :href="route(wire.route)" class="btn btn-sm btn-primary shrink-0 cursor-pointer">
                {{ wire.cta }}
              </Link>
            </div>
          </li>
        </ul>
      </section>

      <!-- The looks, as one door rather than a wall of cards. Only a product
           whose manifest declares a designer has one, and only an install
           can open it. It is optional, so it never competes with a move that
           is not: it sits still while anything above is left to do, and
           starts breathing once nothing is - the part that is the streamer's
           own, on a page that is otherwise done. -->
      <Link
        v-if="installed && designer"
        :href="withLastMileHint(route('products.design', product.slug), product.slug)"
        class="product-design mt-8 flex cursor-pointer flex-col gap-4 border border-violet-500 p-5 transition-colors hover:bg-violet-500/[0.08] sm:flex-row sm:items-center sm:gap-5 dark:border-violet-400 dark:hover:bg-violet-400/[0.09]"
        :class="{ 'product-design-calm': !finished || moves.current !== null }"
      >
        <span
          class="flex size-12 shrink-0 items-center justify-center bg-violet-500 text-white dark:bg-violet-400 dark:text-sidebar"
          aria-hidden="true"
        >
          <Sliders class="size-6" />
        </span>
        <span class="min-w-0 flex-1">
          <span class="block text-lg leading-[1.3] font-semibold text-foreground">Design it yourself</span>
          <span class="block text-sm leading-relaxed text-pretty text-foreground">
            Start from one of {{ designer.presets.length }} looks, then change the {{ designerGroups }} next to your own overlay.
          </span>
        </span>
        <!-- A span, not a button: this whole card is one link, and a button
             inside an anchor is invalid and navigates twice. -->
        <span class="btn btn-primary shrink-0 self-start sm:self-auto">
          Open the designer
          <ArrowRight class="product-design-arrow ml-2 size-4" />
        </span>
      </Link>

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

/* The designer card. Same device as the green pills - a glow, a border and
   an icon, never a gradient wash - in the app's violet: a halo outside, a
   wash inside, both breathing slowly so the card catches the eye on a page
   that is otherwise done. Hover holds it at its brightest. */
.product-design {
  box-shadow:
    0 0 20px -4px rgb(139 92 246 / 0.35),
    inset 0 0 24px 0 rgb(139 92 246 / 0.12);
  animation: product-design-breathe 5s ease-in-out infinite;
}

/* While a move above is still open, or setup is still going, the card is
   still: one thing glows on this page at a time, and it is the move. Hover
   still lights it, since the selector below outranks this one. */
.product-design-calm {
  animation: none;
  box-shadow: none;
}

.product-design:hover {
  animation: none;
  box-shadow:
    0 0 30px -2px rgb(139 92 246 / 0.55),
    inset 0 0 34px 0 rgb(139 92 246 / 0.2);
}

.product-design-arrow {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.product-design:hover .product-design-arrow {
  transform: translateX(4px);
}

@keyframes product-design-breathe {
  0%,
  100% {
    box-shadow:
      0 0 16px -6px rgb(139 92 246 / 0.28),
      inset 0 0 20px 0 rgb(139 92 246 / 0.09);
  }
  50% {
    box-shadow:
      0 0 28px -2px rgb(139 92 246 / 0.5),
      inset 0 0 30px 0 rgb(139 92 246 / 0.18);
  }
}

/* The only motion on the page besides the current move's glow (app.css):
   the bar fills, a tick lands, the waiting dot breathes, the landed line
   arrives. All gone under reduced motion. */
.product-progress {
  transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

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
  .product-progress,
  .product-design-arrow {
    transition: none;
  }
  .product-design:hover .product-design-arrow {
    transform: none;
  }
  .product-tick,
  .product-pulse,
  .product-landed,
  .product-design {
    animation: none;
  }
}
</style>
