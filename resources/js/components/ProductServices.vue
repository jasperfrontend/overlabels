<script lang="ts">
/**
 * One donation service a product's alert fires on, as the product page
 * shows it: where it stands for this person, and the one thing that
 * connects it. Built server-side by ServiceConnections.
 */
export interface ProductService {
  service: string;
  label: string;
  /** How it connects: one click and back (oauth), paste a token (token), get a link then paste back a secret (secret), one click makes the link (none). */
  kind: 'oauth' | 'token' | 'secret' | 'none';
  /** The wiring rule: the service can actually reach us, not merely "a row exists". */
  connected: boolean;
  has_credential: boolean;
  /** Our link for the service to call, once a row exists. Null for the OAuth pair, which never paste anything. */
  webhook_url: string | null;
  /** The service has sent something at least once: the only proof our link was pasted on its side. */
  received: boolean;
  connect_url: string;
  settings_url: string;
  test_guide: { service: string; service_label: string; url: string; label: string; steps: string[]; settings_url: string } | null;
}
</script>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ExternalLink } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { AppPageProps } from '@/types';

/**
 * One row per donation service a product's alert fires on, with the connect
 * for it right there. Nobody leaves the product page to "make it work": an
 * OAuth service is one click and comes back here, a webhook service gets its
 * link and its one field inline, and the row ticks itself the moment the
 * service can reach us.
 */
defineProps<{ services: ProductService[] }>();

const page = usePage<AppPageProps>();
const errors = computed(() => (page.props.errors as Record<string, string> | undefined) ?? {});

// What the person typed for the services that take input: Ko-fi's token,
// Buy Me a Coffee's secret. Keyed by service so five rows share one map.
const typed = ref<Record<string, string>>({});
const busy = ref<string | null>(null);
const copied = ref<string | null>(null);

function connect(service: ProductService): void {
  const value = (typed.value[service.service] ?? '').trim();
  const data = service.kind === 'token' ? { verification_token: value } : service.kind === 'secret' ? { webhook_secret: value } : {};

  busy.value = service.service;
  router.post(service.connect_url, data, {
    preserveScroll: true,
    onSuccess: () => {
      typed.value[service.service] = '';
    },
    onFinish: () => {
      busy.value = null;
    },
  });
}

function copy(service: ProductService): void {
  if (!service.webhook_url) return;
  navigator.clipboard.writeText(service.webhook_url).then(() => {
    copied.value = service.service;
    setTimeout(() => {
      if (copied.value === service.service) copied.value = null;
    }, 2000);
  });
}

// The field's own error, as the save route names it.
function fieldError(service: ProductService): string | undefined {
  return service.kind === 'token' ? errors.value.verification_token : service.kind === 'secret' ? errors.value.webhook_secret : undefined;
}

const pill =
  'inline-flex cursor-pointer items-center gap-2 rounded-full border border-foreground/35 bg-transparent px-4 py-2 text-sm font-semibold text-foreground hover:border-foreground/60 hover:bg-foreground/6 disabled:cursor-not-allowed disabled:opacity-50';
const openLink =
  'inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-violet-600 underline decoration-violet-600/45 underline-offset-2 hover:text-violet-500 hover:decoration-current dark:text-violet-400 dark:decoration-violet-400/45 dark:hover:text-violet-300';
</script>

<template>
  <ul class="flex w-full flex-col divide-y divide-sidebar-border">
    <li v-for="service in services" :key="service.service" class="flex flex-col gap-2.5 py-3 first:pt-0 last:pb-0">
      <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-sm">
          <span class="font-semibold text-foreground">{{ service.label }}</span>
          <span v-if="service.connected" class="inline-flex items-center gap-1 text-[13px] font-medium text-green-700 dark:text-green-400">
            <Check class="size-3.5" stroke-width="2.4" />
            Connected
          </span>
          <span v-else class="text-[13px] text-muted-foreground">Not connected</span>
        </p>

        <!-- One click and back, or one click and the link appears. -->
        <a v-if="service.kind === 'oauth' && !service.connected" :href="service.connect_url" :class="pill">Connect {{ service.label }}</a>
        <button
          v-else-if="service.kind === 'none' && !service.webhook_url"
          type="button"
          :class="pill"
          :disabled="busy === service.service"
          @click="connect(service)"
        >
          Connect {{ service.label }}
        </button>
        <button
          v-else-if="service.kind === 'secret' && !service.webhook_url"
          type="button"
          :class="pill"
          :disabled="busy === service.service"
          @click="connect(service)"
        >
          Get the link for {{ service.label }}
        </button>
      </div>

      <!-- Ko-fi: its webhooks page shows a verification token; that comes first, the link after. -->
      <div v-if="service.kind === 'token' && !service.has_credential" class="flex flex-col gap-2">
        <p class="text-sm leading-relaxed text-foreground">
          {{ service.label }} shows a verification token on its webhooks page.
          <a v-if="service.test_guide" :href="service.test_guide.url" target="_blank" rel="noopener" :class="openLink">
            {{ service.test_guide.label }}
            <ExternalLink class="size-3.5" />
          </a>
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="typed[service.service]"
            type="text"
            autocomplete="off"
            spellcheck="false"
            placeholder="Paste the verification token"
            class="input-border max-w-xs min-w-0 flex-1"
            @keydown.enter.prevent="connect(service)"
          />
          <button type="button" :class="pill" :disabled="busy === service.service" @click="connect(service)">Connect {{ service.label }}</button>
        </div>
        <p v-if="fieldError(service)" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ fieldError(service) }}</p>
      </div>

      <!-- The link to paste on the service's side, until the service has used it once. -->
      <div v-if="service.webhook_url && !service.received" class="flex flex-col gap-2">
        <p class="text-sm leading-relaxed text-foreground">
          Paste this link into the webhook settings on {{ service.label }}.
          <a v-if="service.test_guide" :href="service.test_guide.url" target="_blank" rel="noopener" :class="openLink">
            {{ service.test_guide.label }}
            <ExternalLink class="size-3.5" />
          </a>
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <input
            :value="service.webhook_url"
            readonly
            class="input-border min-w-0 flex-1 font-mono text-xs"
            @focus="($event.target as HTMLInputElement).select()"
          />
          <button type="button" :class="pill" @click="copy(service)">{{ copied === service.service ? 'Copied' : 'Copy' }}</button>
        </div>
      </div>

      <!-- Buy Me a Coffee: it reveals a secret once the link is saved on its side; that comes back here. -->
      <div v-if="service.kind === 'secret' && service.webhook_url && !service.has_credential" class="flex flex-col gap-2">
        <p class="text-sm leading-relaxed text-foreground">Once the link is saved there, {{ service.label }} shows a secret. Paste it here.</p>
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="typed[service.service]"
            type="text"
            autocomplete="off"
            spellcheck="false"
            placeholder="Paste the secret"
            class="input-border max-w-xs min-w-0 flex-1"
            @keydown.enter.prevent="connect(service)"
          />
          <button type="button" :class="pill" :disabled="busy === service.service" @click="connect(service)">Save the secret</button>
        </div>
        <p v-if="fieldError(service)" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ fieldError(service) }}</p>
      </div>
    </li>
  </ul>
</template>
