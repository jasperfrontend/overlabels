<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Undo2, X } from '@lucide/vue';
import type { AppPageProps, WhatsNew, WhatsNewItem } from '@/types';

const props = defineProps<{ whatsNew: WhatsNew }>();

const page = usePage<AppPageProps>();

// Dates follow the account's locale rather than the browser's, the same way
// every other formatted value in the app does.
const locale = computed(() => page.props.auth.user?.locale || undefined);

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat(locale.value, { month: 'short', day: 'numeric' }).format(new Date(iso));
}

const items = computed(() => props.whatsNew.items);
const overflow = computed(() => Math.max(0, props.whatsNew.total - items.value.length));

// The card has three states, and "nothing at all" is one of them: someone who
// has never marked anything seen and has nothing waiting gets no card and no
// caught-up bar, because a standing "all caught up" they never earned is just
// another thing to skip past.
const hasUnseen = computed(() => items.value.length > 0);
const showCaughtUp = computed(() => !hasUnseen.value && props.whatsNew.canUndo);

const working = ref(false);

function markAllSeen(): void {
  if (working.value) return;
  working.value = true;
  router.post(route('dashboard.whats-new.seen'), {}, { preserveScroll: true, onFinish: () => (working.value = false) });
}

function undo(): void {
  if (working.value) return;
  working.value = true;
  router.delete(route('dashboard.whats-new.undo'), {
    preserveScroll: true,
    onFinish: () => (working.value = false),
  });
}

function dismiss(item: WhatsNewItem): void {
  if (working.value) return;
  working.value = true;
  router.delete(route('dashboard.whats-new.dismiss', item.id), {
    preserveScroll: true,
    onFinish: () => (working.value = false),
  });
}

// An internal destination is caught server-side on arrival, so the card says
// nothing. An external one takes the reader out of the app where no request of
// ours will ever see it, so the click is the only chance to record it.
function onCtaClick(item: WhatsNewItem): void {
  if (!item.cta?.external || item.stale) return;
  router.post(route('dashboard.whats-new.visited', item.id), {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
  <!-- Unread. Two columns from md up: a narrow rail that says what this is,
       and one row per unseen post. Below md the rail stacks on top. -->
  <section
    v-if="hasUnseen"
    class="m-4 mb-6 border border-sidebar-border bg-background md:grid md:grid-cols-[300px_1fr]"
    aria-labelledby="whats-new-heading"
  >
    <div class="flex flex-col gap-3 border-b border-sidebar-border p-6 md:border-r md:border-b-0">
      <h2 id="whats-new-heading" class="text-lg font-semibold text-foreground">What's new</h2>
      <Link
        :href="route('updates.index')"
        class="inline-flex items-center gap-1.5 text-sm font-semibold text-teal-600 hover:text-foreground dark:text-teal-400"
      >
        Full history lives in Updates
        <ArrowRight class="h-3.5 w-3.5" />
      </Link>
    </div>

    <div class="flex flex-col">
      <article
        v-for="(item, index) in items"
        :key="item.id"
        class="whats-new-row group grid grid-cols-[16px_1fr_auto] items-start gap-4 border-b border-sidebar-border p-6"
        :style="`--row-delay: ${0.05 + index * 0.12}s`"
      >
        <span class="relative mt-1.5 inline-flex h-4 w-4 items-center justify-center">
          <!-- Teal and yellow are deliberately foreign to the app's violet, and
               they are fixed palette values rather than theme tokens so no
               theme (Sepia included) can tint them back into the family. A
               stale row drops to grey and stops pinging: the reader has been
               where this points, so it has nothing left to announce. -->
          <span v-if="!item.stale" class="whats-new-ping absolute h-2 w-2 rounded-full bg-yellow-400" aria-hidden="true" />
          <span class="relative h-2 w-2 rounded-full" :class="item.stale ? 'bg-muted-foreground/40' : 'bg-teal-400'" aria-hidden="true" />
        </span>

        <div class="flex flex-col gap-1.5">
          <Link :href="item.href" class="font-semibold hover:underline" :class="item.stale ? 'text-muted-foreground' : 'text-foreground'">
            {{ item.title }}
          </Link>
          <div class="font-mono text-xs" :class="item.stale ? 'text-muted-foreground/70' : 'text-violet-600 dark:text-violet-400'">
            {{ formatDate(item.published_at) }}<span v-if="item.stale"> · visited</span>
          </div>
          <p v-if="item.excerpt" class="max-w-[62ch] text-sm leading-relaxed" :class="item.stale ? 'text-muted-foreground' : 'text-foreground'">
            {{ item.excerpt }}
          </p>
          <a
            v-if="item.cta"
            :href="item.cta.href"
            class="mt-1 inline-flex cursor-pointer items-center gap-1.5 self-start text-sm"
            :class="item.stale ? 'text-muted-foreground hover:text-foreground' : 'text-teal-600 hover:text-foreground dark:text-teal-400'"
            @click="onCtaClick(item)"
          >
            {{ item.cta.label }}
            <ArrowRight class="h-3.5 w-3.5" />
          </a>
        </div>

        <!-- Visible on touch, hover-revealed from md up, matching CollectionList. -->
        <button
          type="button"
          class="btn btn-plain btn-xs text-muted-foreground transition-opacity md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100"
          :disabled="working"
          :title="`Dismiss ${item.title}`"
          :aria-label="`Dismiss ${item.title}`"
          @click="dismiss(item)"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </article>

      <div class="mt-auto flex flex-wrap items-center gap-3 p-4">
        <span class="text-xs text-muted-foreground">
          <template v-if="overflow > 0"> {{ overflow }} more waiting in Updates. This card only shows up when something new ships. </template>
          <template v-else> This card only shows up when something new ships. </template>
        </span>
        <button type="button" class="btn btn-chill btn-xs ml-auto" :disabled="working" @click="markAllSeen">Mark all as seen</button>
      </div>
    </div>
  </section>

  <!-- Read. One thin bar, so the space costs almost nothing while it waits. -->
  <div v-else-if="showCaughtUp" class="m-4 mb-6 flex flex-wrap items-center gap-3 border border-sidebar-border bg-background px-6 py-3.5">
    <span class="h-2 w-2 shrink-0 rounded-full bg-green-500" aria-hidden="true" />
    <span class="text-sm text-muted-foreground">All caught up. This bar lights up again when something new ships.</span>
    <button type="button" class="btn btn-plain btn-xs ml-auto text-muted-foreground" :disabled="working" @click="undo">
      <Undo2 class="mr-1.5 h-3.5 w-3.5" />
      Undo
    </button>
  </div>
</template>

<style scoped>
/* Motion is the one signal a badge could not give: it fires on arrival and
   then stops. Twice, not ambient - see the handoff brief behind this card. */
@keyframes whats-new-ping {
  0% {
    transform: scale(1);
    opacity: 0.7;
  }
  100% {
    transform: scale(2.6);
    opacity: 0;
  }
}

@keyframes whats-new-rise {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

.whats-new-ping {
  animation: whats-new-ping 1.1s cubic-bezier(0, 0, 0.2, 1) var(--row-delay, 0s) 2;
}

.whats-new-row {
  animation: whats-new-rise 0.5s ease-out var(--row-delay, 0s) both;
}

@media (prefers-reduced-motion: reduce) {
  .whats-new-ping,
  .whats-new-row {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
