<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bot, Check, Circle, Download, ExternalLink, ListIcon, PlugZap, Trash2, TriangleAlert } from '@lucide/vue';
import type { AppPageProps } from '@/types';
import { serviceLabel } from '@/utils/services';
import { urlWithTab } from '@/composables/useAddressableTabs';
import { useConfirm } from '@/composables/useConfirm';
import { withLastMileHint } from '@/composables/useUiMode';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ProductBadge from '@/components/ProductBadge.vue';
import ProductServices from '@/components/ProductServices.vue';
import type { ProductService } from '@/components/ProductServices.vue';
import RekaToast from '@/components/RekaToast.vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
  requires_bot: boolean;
  hero: string | null;
  ingredients: Ingredient[];
  integrations: string[];
  overlays: { ref: string; name: string; type: string; description: string | null }[];
  lists: { slug: string; label: string }[];
  commands: { command: string; kind: 'appender' | 'alias' | 'command'; detail: string }[];
  notes: string[];
  ready_message: string | null;
}

interface InstalledOverlay {
  ref: string;
  name: string;
  slug: string;
  id: number;
  type: string;
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
  your_overlays: { loaded: boolean; overlays: { id: number; name: string }[]; total: number };
  test_guide: TestGuide | null;
  landed: Landed | null;
  removes: string[];
}

const props = defineProps<{
  product: Product;
  installed: Installed | null;
}>();

const page = usePage<AppPageProps>();
const isAuthed = computed(() => !!page.props.auth?.user);
const installError = computed(() => (page.props.errors as Record<string, string> | undefined)?.install);

// Same flash-to-toast wiring as AppLayout. This page renders outside it, so
// without this an install or uninstall only swapped a button label.
const flashMessage = ref<string | null>(null);
const flashType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const flashKey = ref(0);

watch(
  () => page.props.flash,
  (flash) => {
    if (!flash?.message) return;
    flashMessage.value = flash.message;
    flashType.value = flash.type || 'info';
    flashKey.value++;
  },
  { immediate: true },
);

// Only the wires that apply to this product are steps. A wire that does not
// apply is not a step someone skipped, so it is not shown at all here: the
// product page is a checklist, and a checklist with greyed-out lines for
// things that were never part of the product reads as unfinished.
const steps = computed(() => (props.installed?.subject?.wires ?? []).filter((wire) => wire.state !== 'not_applicable'));
const remaining = computed(() => steps.value.filter((wire) => wire.state === 'missing').length);
const done = computed(() => steps.value.length - remaining.value);
const progress = computed(() => (steps.value.length ? Math.round((done.value / steps.value.length) * 100) : 100));

// The product's questions, answered with their defaults until the person
// picks otherwise. Whatever the manifest wrote as {{key}} reads as the
// current answer here, so the list of what the click gives you follows
// the pick rather than showing a placeholder.
const answers = ref<Record<string, string>>(Object.fromEntries(props.product.ingredients.map((ingredient) => [ingredient.key, ingredient.default])));

function fill(text: string): string {
  return text.replace(/\{\{([a-z][a-z0-9_]*)\}\}/g, (whole, key: string) => answers.value[key] ?? whole);
}

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

function joinNames(names: string[]): string {
  return names.length <= 1 ? (names[0] ?? '') : `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`;
}

// Every service the alert fires on, with its connect. The pill names the
// connected ones, and the test-tip beat runs once per connected service that
// has a test button of its own; the picked service's guide stands in for a
// product with no services block.
const services = computed(() => props.installed?.services ?? []);
const connectedLabels = computed(() => services.value.filter((service) => service.connected).map((service) => service.label));
const testGuides = computed<TestGuide[]>(() => {
  const own = services.value.flatMap((service) => (service.connected && service.test_guide ? [service.test_guide] : []));
  if (own.length) return own;
  return props.installed?.test_guide ? [props.installed.test_guide] : [];
});

// A product that installs only an alert has nothing of its own for OBS: the
// alert renders inside every static overlay the person has there. The OBS
// beat offers their own overlays instead, any one of which is enough.
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

// An inline link inside the finished band's footnotes: violet, underlined
// at half strength until hovered. One string so the two sites cannot drift.
const noteLink =
  'cursor-pointer text-violet-600 underline decoration-violet-600/45 underline-offset-2 hover:text-violet-500 hover:decoration-current dark:text-violet-400 dark:decoration-violet-400/45 dark:hover:text-violet-300';

// Beat three, "watch it land". The server says whether an event one of the
// product's alerts fires on has arrived since the install, so a reload after
// the test tip still says so; the alert broadcast flips it the moment one
// lands while the page is open. The broadcast carries the template's slug,
// which is how this product's alert is told apart from any other.
const landed = ref<Landed | null>(props.installed?.landed ?? null);
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
}

onMounted(() => {
  const twitchId = (page.props.auth as any)?.user?.twitch_id;
  const echo = (window as any).Echo;
  if (!props.installed || !alerts.value.length || !twitchId || !echo) return;
  channel = echo.private(`alerts.${twitchId}`);
  channel.listen('.alert.triggered', onAlertTriggered);
});

// Stop listening rather than leave: the channel is shared with anything else
// on the account that listens, and leaving would take it down for them too.
onBeforeUnmount(() => channel?.stopListening('.alert.triggered', onAlertTriggered));

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
  router.post(route('products.uninstall', props.product.slug));
}
</script>

<template>
  <Head>
    <title>{{ product.name }}</title>
    <meta name="description" :content="product.description" />
  </Head>

  <!-- This page renders outside AppLayout, where the app's single ConfirmDialog
       normally lives, so the uninstall confirm needs its own mount. -->
  <ConfirmDialog />
  <RekaToast v-if="flashMessage" :key="flashKey" :message="flashMessage" :type="flashType" @dismiss="flashMessage = null" />
  <div class="min-h-screen bg-background text-foreground">
    <div class="mx-auto max-w-4xl p-4 lg:p-6">
      <div class="mb-6 flex items-center justify-between">
        <a href="/" class="flex cursor-pointer items-center gap-2 text-sm font-bold tracking-tight text-foreground hover:text-violet-400">
          <img src="/favicon-light.svg" alt="" class="h-6 w-6 dark:hidden" />
          <img src="/favicon.png" alt="" class="hidden h-6 w-6 dark:block" />
          Overlabels
        </a>
        <div class="flex items-center gap-4 text-sm">
          <Link href="/products" class="text-violet-400 hover:underline">All products</Link>
          <Link v-if="isAuthed" :href="route('dashboard.index')" class="text-violet-400 hover:underline">Dashboard</Link>
        </div>
      </div>

      <!-- The same artwork as the listing card, full width, as the page's hero. -->
      <img v-if="product.hero" :src="product.hero" alt="" class="mb-4 block aspect-video w-full object-cover" />

      <!-- Installed, the header turns green and carries the badge large. This
           is the one page in the app that is allowed to celebrate: the person
           did nothing but press a button and follow a few lines, and the page's
           job is to make that feel like it counted. -->
      <header
        class="flex flex-col gap-3 border p-5"
        :class="installed ? 'border-green-500/60 bg-green-950/40 dark:bg-green-950/40' : 'border-sidebar-border bg-sidebar'"
      >
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="flex min-w-0 items-start gap-3">
            <ProductBadge
              label="An official Overlabels product"
              class="mt-0.5 size-9 shrink-0"
              :class="installed ? 'text-green-500' : 'text-violet-400'"
            />
            <div class="min-w-0">
              <p v-if="installed" class="text-xs font-semibold tracking-wide text-green-500 uppercase">Installed</p>
              <h1 class="text-2xl font-semibold text-foreground">{{ product.name }}</h1>
              <p v-if="product.requires_bot" class="mt-1 inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                <Bot class="size-3.5" />
                Works through the Overlabels bot in your chat
              </p>
            </div>
          </div>

          <div class="flex shrink-0 flex-col items-end gap-2">
            <button v-if="installed" type="button" class="btn btn-sm btn-chill cursor-pointer" @click="uninstall">
              <Trash2 class="mr-2 size-4" />
              Uninstall
            </button>
            <button v-else-if="isAuthed" type="button" class="btn btn-primary cursor-pointer" @click="install">
              <Download class="mr-2 size-4" />
              Install
            </button>
            <a v-else :href="`/login?redirect_to=/products/${product.slug}`" class="btn btn-primary cursor-pointer">
              <Download class="mr-2 size-4" />
              Log in with Twitch to install
            </a>
          </div>
        </div>

        <p class="max-w-prose text-foreground">{{ product.description }}</p>

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
        <p v-if="uninstallError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ uninstallError }}</p>
      </header>

      <!-- Installed and finished: the win, said once, then the beats that
           make it land - the stage into OBS, a real test event from the
           service, and this page noticing the alert fire. A stage sitting in
           OBS with nothing on it is not a payoff; an alert landing is. No
           settings vocabulary here. Green is a pill, a tick and a dot, never
           a wall: the cards are the page's own dark, and the one thing that
           glows is the line that says the tip arrived. -->
      <section v-if="installed && installed.subject && remaining === 0" class="product-ready mt-8 flex flex-col gap-6">
        <div class="flex flex-col items-center gap-3.5 text-center">
          <span
            class="product-glow inline-flex items-center gap-2 rounded-full border border-green-500/55 bg-green-500/8 py-1.25 pr-3.5 pl-2.75 text-[13px] font-semibold text-green-700 dark:text-green-400"
          >
            <Check class="size-3.5" stroke-width="2.4" />
            {{ connectedLabels.length ? `${joinNames(connectedLabels)} connected` : 'Connected' }}
          </span>
          <h2 class="text-3xl leading-[1.2] font-bold tracking-[-0.015em] text-foreground">Everything is in place</h2>
          <p v-if="product.ready_message" class="max-w-[600px] text-[15px] leading-relaxed text-pretty text-muted-foreground">
            {{ product.ready_message }}
          </p>
        </div>

        <ol class="product-beats flex flex-col gap-3">
          <!-- Every service the alert fires on, each with its connect right
               here. One is enough for the alert to work, and the page must
               never call the product done and send someone elsewhere for the
               rest. -->
          <li
            v-if="services.length"
            class="product-beat grid grid-cols-[28px_minmax(0,1fr)] gap-4 border border-sidebar-border bg-sidebar p-5 dark:bg-black/35"
          >
            <span
              class="product-beat-number flex size-7 items-center justify-center rounded-full border border-foreground/35 text-[13px] font-semibold text-foreground/80 tabular-nums"
              aria-hidden="true"
            />
            <div class="flex min-w-0 flex-col gap-3">
              <div class="flex flex-col gap-1">
                <h3 class="text-base leading-[1.35] font-semibold text-foreground">{{ servicesTitle }}</h3>
                <p class="text-sm leading-relaxed text-pretty text-foreground">{{ servicesLead }}</p>
              </div>
              <ProductServices :services="services" />
            </div>
          </li>

          <!-- The stage into OBS. The overlay's own OBS tab does the
               handholding (it makes the token link and says the size); this
               beat only sends them there and says what they will find. -->
          <li
            v-if="stages.length"
            class="product-beat grid grid-cols-[28px_minmax(0,1fr)] gap-4 border border-sidebar-border bg-sidebar p-5 dark:bg-black/35"
          >
            <span
              class="product-beat-number flex size-7 items-center justify-center rounded-full border border-foreground/35 text-[13px] font-semibold text-foreground/80 tabular-nums"
              aria-hidden="true"
            />
            <div class="flex min-w-0 flex-col items-start gap-2.5">
              <h3 class="text-base leading-[1.35] font-semibold text-foreground">Put {{ joinNames(stages.map((stage) => stage.name)) }} in OBS</h3>
              <p class="text-sm leading-relaxed text-pretty text-foreground">
                The OBS tab makes your overlay link and tells you the size. Add it in OBS as a Browser source, then keep OBS open.
              </p>
              <div class="flex flex-wrap gap-2">
                <Link
                  v-for="overlay in stages"
                  :key="overlay.id"
                  :href="urlWithTab(withLastMileHint(route('templates.show', overlay.id), product.slug), 'obs')"
                  class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-blue-500/50 bg-blue-500/8 px-4 py-2 text-sm font-semibold text-blue-700 hover:border-blue-500 hover:bg-blue-500/18 hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200"
                >
                  <ExternalLink class="size-3.5" />
                  Add {{ overlay.name }} to OBS
                </Link>
              </div>
            </div>
          </li>

          <!-- An alert-only product has nothing of its own for OBS. The alert
               renders inside every static overlay in OBS, so the beat offers
               the person's own overlays: any one of them is enough. -->
          <li
            v-if="!stages.length && alerts.length"
            class="product-beat grid grid-cols-[28px_minmax(0,1fr)] gap-4 border border-sidebar-border bg-sidebar p-5 dark:bg-black/35"
          >
            <span
              class="product-beat-number flex size-7 items-center justify-center rounded-full border border-foreground/35 text-[13px] font-semibold text-foreground/80 tabular-nums"
              aria-hidden="true"
            />
            <div class="flex min-w-0 flex-col items-start gap-2.5">
              <h3 class="text-base leading-[1.35] font-semibold text-foreground">Have an overlay in OBS</h3>
              <!-- The access log says which overlays a link has served lately.
                   When it names any, the alert already shows there and the
                   beat is done; otherwise a short list to pick one from. -->
              <p v-if="yourOverlays.loaded" class="text-sm leading-relaxed text-pretty text-foreground">
                The alert already shows inside {{ loadedNames }}, on top of whatever is there. That is what your overlay link has been serving lately,
                so there is nothing to add.
              </p>
              <template v-else-if="yourOverlays.overlays.length">
                <p class="text-sm leading-relaxed text-pretty text-foreground">
                  The alert shows inside every overlay of yours that is in OBS, on top of whatever is there. One is enough. An overlay's OBS tab makes
                  its link and tells you the size.
                </p>
                <div class="flex flex-wrap gap-2">
                  <Link
                    v-for="overlay in yourOverlays.overlays"
                    :key="overlay.id"
                    :href="urlWithTab(withLastMileHint(route('templates.show', overlay.id), product.slug), 'obs')"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-blue-500/50 bg-blue-500/8 px-4 py-2 text-sm font-semibold text-blue-700 hover:border-blue-500 hover:bg-blue-500/18 hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200"
                  >
                    <ExternalLink class="size-3.5" />
                    Add {{ overlay.name }} to OBS
                  </Link>
                </div>
                <p v-if="yourOverlays.total > yourOverlays.overlays.length" class="text-[13px] leading-relaxed text-muted-foreground">
                  And {{ yourOverlays.total - yourOverlays.overlays.length }} more on
                  <Link :href="route('templates.index', { type: 'static' })" :class="noteLink">your overlays page</Link>.
                </p>
              </template>
              <template v-else>
                <p class="text-sm leading-relaxed text-pretty text-foreground">
                  The alert shows inside every overlay of yours that is in OBS, on top of whatever is there. You have none yet.
                </p>
                <Link
                  :href="route('templates.create')"
                  class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-blue-500/50 bg-blue-500/8 px-4 py-2 text-sm font-semibold text-blue-700 hover:border-blue-500 hover:bg-blue-500/18 hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200"
                >
                  Make an overlay
                </Link>
              </template>
            </div>
          </li>

          <!-- A real test event, from the service's own site, opened in a new
               tab so this page stays put for the last beat. One beat per
               connected service; the steps are the service's, not the
               product's: ServiceTestGuides. -->
          <li
            v-for="guide in testGuides"
            :key="guide.service"
            class="product-beat grid grid-cols-[28px_minmax(0,1fr)] gap-4 border border-sidebar-border bg-sidebar p-5 dark:bg-black/35"
          >
            <span
              class="product-beat-number flex size-7 items-center justify-center rounded-full border border-foreground/35 text-[13px] font-semibold text-foreground/80 tabular-nums"
              aria-hidden="true"
            />
            <div class="flex min-w-0 flex-col items-start gap-3">
              <h3 class="text-base leading-[1.35] font-semibold text-foreground">Send yourself a test tip from {{ guide.service_label }}</h3>
              <a
                :href="guide.url"
                target="_blank"
                rel="noopener"
                class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-foreground/35 bg-transparent px-4 py-2 text-sm font-semibold text-foreground hover:border-foreground/60 hover:bg-foreground/6"
              >
                <ExternalLink class="size-3.5" />
                {{ guide.label }}
              </a>
              <ol class="flex list-decimal flex-col gap-1.25 pl-5 text-sm leading-[1.55] text-foreground">
                <li v-for="(step, i) in guide.steps" :key="i">{{ step }}</li>
              </ol>
              <p class="text-[13px] leading-relaxed text-pretty text-muted-foreground">
                Pressing it again? A second identical test is dropped as a retry, unless
                <a :href="guide.settings_url" :class="noteLink">test mode</a>
                is on for {{ guide.service_label }}.
              </p>
            </div>
          </li>

          <!-- The payoff. Landed is read from the stored event on load and
               flipped live by the alert broadcast. The alert's wiring sits
               under it as done steps, so nobody goes looking for an OBS step
               for the alert. -->
          <li
            v-if="alerts.length"
            class="product-beat grid grid-cols-[28px_minmax(0,1fr)] gap-4 border border-sidebar-border bg-sidebar p-5 dark:bg-black/35"
          >
            <span
              class="product-beat-number flex size-7 items-center justify-center rounded-full border border-foreground/35 text-[13px] font-semibold text-foreground/80 tabular-nums"
              aria-hidden="true"
            />
            <div class="flex min-w-0 flex-col gap-3">
              <h3 class="text-base leading-[1.35] font-semibold text-foreground">Watch it land</h3>
              <p
                v-if="landed"
                class="product-landed product-glow flex items-center gap-2.5 self-start rounded-full border border-green-500/50 bg-green-500/8 px-3.5 py-2.25 text-[13px] font-medium text-green-700 dark:text-green-400"
                role="status"
              >
                <span class="size-2 shrink-0 rounded-full bg-green-500" aria-hidden="true" />
                <span>It landed{{ landedLine ? `: ${landedLine}` : '' }}</span>
              </p>
              <p
                v-else
                class="flex items-center gap-2.5 self-start rounded-full border border-foreground/25 bg-foreground/3 px-3.5 py-2.25 text-[13px] text-foreground"
                role="status"
              >
                <span class="product-pulse size-2 shrink-0 rounded-full bg-blue-300" aria-hidden="true" />
                Waiting for your test tip. This page notices the moment it arrives.
              </p>

              <div v-for="alert in alerts" :key="alert.id" class="flex flex-col gap-3 text-sm">
                <p class="leading-relaxed text-foreground">
                  <Link
                    :href="withLastMileHint(route('templates.show', alert.id), product.slug)"
                    class="cursor-pointer font-semibold text-foreground underline-offset-2 hover:underline"
                  >
                    {{ alert.name }}
                  </Link>
                  is the alert. Nothing to add to OBS for it.
                </p>
                <div class="flex flex-col gap-2">
                  <p class="grid grid-cols-[16px_minmax(0,1fr)] items-start gap-2.5">
                    <Check class="mt-[3px] size-4 text-green-500" stroke-width="2.2" />
                    <span v-if="alert.fires_on?.length" class="leading-[1.55] text-pretty text-foreground"
                      >Fires on {{ joinNames(alert.fires_on) }}.</span
                    >
                    <span v-else class="leading-[1.55] text-pretty text-foreground"
                      >Has no trigger switched on yet. Its Triggers tab is where that lives.</span
                    >
                  </p>
                  <p class="grid grid-cols-[16px_minmax(0,1fr)] items-start gap-2.5">
                    <Check class="mt-[3px] size-4 text-green-500" stroke-width="2.2" />
                    <span v-if="alert.targets?.length" class="leading-[1.55] text-pretty text-foreground">
                      Shows inside {{ joinNames(alert.targets) }}, on top of whatever is there.
                    </span>
                    <span v-else class="leading-[1.55] text-pretty text-foreground">Shows inside every static overlay of yours.</span>
                  </p>
                </div>
                <p v-if="alert.more_events?.length" class="pl-[26px] text-[13px] leading-relaxed text-pretty text-muted-foreground">
                  {{ installed.test_guide?.service_label ?? 'It' }} also sends {{ joinNames(alert.more_events) }}.
                  <Link :href="urlWithTab(withLastMileHint(route('templates.show', alert.id), product.slug), 'triggers')" :class="noteLink">
                    The alert's Triggers tab
                  </Link>
                  switches those on for the same alert.
                </p>
              </div>
            </div>
          </li>
        </ol>
      </section>

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

      <!-- Installed, steps left: the checklist as a progress piece. Everything
           the installer did is already a tick; each remaining line has one
           button; the bar fills as they go. -->
      <section v-if="installed && installed.subject" class="mt-8 flex flex-col gap-3">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
          <h2 class="text-lg font-semibold text-foreground">{{ remaining ? 'Finish setting up' : 'Your setup' }}</h2>
          <span v-if="remaining" class="text-sm text-fuchsia-600 tabular-nums dark:text-fuchsia-400">
            {{ remaining === 1 ? 'One thing left' : `${remaining} things left` }}
          </span>
          <span v-else class="inline-flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
            <Check class="size-4" />
            {{ steps.length }} of {{ steps.length }} done
          </span>
        </div>

        <div class="h-2 w-full overflow-hidden bg-muted" role="progressbar" :aria-valuenow="done" :aria-valuemin="0" :aria-valuemax="steps.length">
          <div class="product-progress h-full" :class="remaining ? 'bg-fuchsia-500' : 'bg-green-500'" :style="{ width: `${progress}%` }" />
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

        <div v-if="installed.overlays.length" class="mt-2 flex flex-col gap-2">
          <h3 class="text-sm font-medium text-foreground">{{ installed.overlays.length === 1 ? 'Your overlay' : 'Your overlays' }}</h3>
          <ul class="flex flex-col gap-2">
            <li v-for="overlay in installed.overlays" :key="overlay.id" class="collection-row relative border border-border p-3">
              <Link :href="route('templates.show', overlay.id)" class="absolute inset-0 z-0 cursor-pointer" :aria-label="overlay.name" />
              <div class="flex items-center justify-between gap-3">
                <p class="text-foreground">{{ overlay.name }}</p>
                <ExternalLink class="size-4 text-muted-foreground" />
              </div>
            </li>
          </ul>
        </div>

        <div v-if="product.notes.length" class="mt-2 flex flex-col gap-2">
          <h3 class="text-sm font-medium text-foreground">Good to know</h3>
          <ul class="flex flex-col gap-1">
            <li v-for="note in product.notes" :key="note" class="flex gap-2 text-sm text-foreground">
              <Circle class="mt-1.5 size-2 shrink-0 text-muted-foreground" />
              <span>{{ note }}</span>
            </li>
          </ul>
        </div>
      </section>

      <!-- Not installed: what the click does, and what it cannot do for you. -->
      <section v-else class="mt-8 grid gap-6 md:grid-cols-2">
        <div class="flex flex-col gap-3">
          <h2 class="text-lg font-semibold text-foreground">Installing gives you</h2>
          <ul class="flex flex-col gap-2">
            <li v-for="overlay in product.overlays" :key="overlay.ref" class="collection-row border border-border p-3">
              <p class="font-medium text-foreground">{{ overlay.name }}</p>
              <p v-if="overlay.description" class="mt-1 text-sm text-foreground">{{ overlay.description }}</p>
            </li>
            <li v-for="integration in product.integrations" :key="integration" class="collection-row border border-border p-3">
              <p class="inline-flex items-center gap-2 font-medium text-foreground">
                <PlugZap class="size-4 text-violet-400" />
                {{ serviceLabel(fill(integration)) }} connected
              </p>
              <p class="mt-1 text-sm text-foreground">The integration and its controls, ready before you open the overlay.</p>
            </li>
            <li v-for="list in product.lists" :key="list.slug" class="collection-row border border-border p-3">
              <p class="inline-flex items-center gap-2 font-medium text-foreground">
                <ListIcon class="size-4 text-violet-400" />
                A list called {{ list.label }}
              </p>
              <p class="mt-1 text-sm text-foreground">
                Empty to start. Mods work it with <code class="text-violet-400">!list {{ list.slug }}</code
                >.
              </p>
            </li>
            <li v-for="command in product.commands" :key="command.command" class="collection-row border border-border p-3">
              <p class="inline-flex items-center gap-2 font-medium text-foreground">
                <Bot class="size-4 text-violet-400" />
                The <code class="text-violet-400">{{ command.command }}</code> {{ command.kind === 'alias' ? 'alias' : 'chat command' }}
              </p>
              <p class="mt-1 text-sm text-foreground">{{ command.detail }}</p>
            </li>
          </ul>
        </div>

        <div class="flex flex-col gap-3">
          <h2 class="text-lg font-semibold text-foreground">You still do</h2>
          <ul class="flex flex-col gap-2 text-sm text-foreground">
            <li v-if="product.requires_bot" class="collection-row border border-border p-3">
              Switch the Overlabels bot on for your channel and type <code class="text-violet-400">/mod overlabels</code> in your chat.
            </li>
            <li v-if="product.overlays.some((overlay) => overlay.type !== 'alert')" class="collection-row border border-border p-3">
              Add the overlay to OBS as a browser source. The page tells you when that is the only thing left.
            </li>
            <li v-else-if="product.overlays.some((overlay) => overlay.type === 'alert')" class="collection-row border border-border p-3">
              Have one of your overlays in OBS. The alert shows inside every overlay that is there, so one is enough.
            </li>
          </ul>
          <p class="text-sm text-muted-foreground">About five minutes, and this page keeps track of which of these is done.</p>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
/* The beats number themselves, so a product with no test guide or no alert
   skips a beat without leaving a gap in the count. */
.product-beats {
  counter-reset: beat;
}

.product-beat {
  counter-increment: beat;
}

.product-beat-number::before {
  content: counter(beat);
}

/* The soft inner glow the two green pills carry: "Connected" at the top and
   the landed line. Same glow the status pills wear elsewhere. */
.product-glow {
  box-shadow: inset 0 0 12px 0 rgb(34 197 94 / 0.18);
}

/* The only motion on the page: the bar fills, a completed tick lands, the
   waiting dot breathes, the landed line arrives. All gone under reduced
   motion. */
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
  .product-progress {
    transition: none;
  }
  .product-tick,
  .product-pulse,
  .product-landed {
    animation: none;
  }
}
</style>
