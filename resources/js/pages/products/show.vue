<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bot, Check, Circle, Download, ExternalLink, ListIcon, PlugZap, Trash2, TriangleAlert } from '@lucide/vue';
import type { AppPageProps } from '@/types';
import { useConfirm } from '@/composables/useConfirm';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ProductBadge from '@/components/ProductBadge.vue';
import RekaToast from '@/components/RekaToast.vue';
import { ref, watch } from 'vue';

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

interface Product {
  slug: string;
  name: string;
  description: string;
  requires_bot: boolean;
  hero: string | null;
  integrations: string[];
  overlays: { ref: string; name: string; type: string; description: string | null }[];
  lists: { slug: string; label: string }[];
  commands: { command: string; kind: 'appender' | 'alias' | 'command'; detail: string }[];
  notes: string[];
  ready_message: string | null;
}

interface Installed {
  installed_at: string;
  subject: Subject | null;
  overlays: { ref: string; name: string; slug: string; id: number }[];
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

const integrationLabels: Record<string, string> = {
  checkin: 'Chat Checkin',
};

function integrationLabel(key: string): string {
  return integrationLabels[key] ?? key;
}

function install(): void {
  router.post(route('products.install', props.product.slug));
}

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

        <p v-if="installError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ installError }}</p>
        <p v-if="uninstallError" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ uninstallError }}</p>
      </header>

      <!-- Installed and finished: the win, said once and loudly. The badge,
           the name, what it means in the streamer's world, and the two things
           they want next. No settings vocabulary here. -->
      <section
        v-if="installed && installed.subject && remaining === 0"
        class="product-ready mt-8 flex flex-col items-center gap-4 border border-green-500 bg-green-600 p-8 text-center text-white"
      >
        <ProductBadge label="An official Overlabels product" class="size-16 text-white" />
        <div>
          <h2 class="text-2xl font-semibold">Everything is in place</h2>
          <p v-if="product.ready_message" class="mt-1 text-base text-white/90">{{ product.ready_message }}</p>
        </div>
        <div class="flex flex-wrap justify-center gap-2">
          <Link
            v-for="overlay in installed.overlays"
            :key="overlay.id"
            :href="route('templates.show', overlay.id)"
            class="inline-flex cursor-pointer items-center gap-2 border border-white/60 bg-white px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-50"
          >
            <ExternalLink class="size-4" />
            Add {{ overlay.name }} to OBS
          </Link>
        </div>
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
          <h3 class="text-sm font-medium text-foreground">Your overlay</h3>
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
                {{ integrationLabel(integration) }} connected
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
            <li class="collection-row border border-border p-3">
              Add the overlay to OBS as a browser source. The page tells you when that is the only thing left.
            </li>
          </ul>
          <p class="text-sm text-muted-foreground">About five minutes, and this page keeps track of which of these is done.</p>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
/* The only motion on the page: the bar fills, a completed tick lands. Both
   are gone under reduced motion. */
.product-progress {
  transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.product-tick {
  animation: product-tick-land 0.35s cubic-bezier(0.16, 1, 0.3, 1);
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
  .product-tick {
    animation: none;
  }
}
</style>
