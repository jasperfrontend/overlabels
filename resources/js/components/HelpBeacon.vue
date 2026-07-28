<script setup lang="ts">
/**
 * A floating help button that knows where the user is standing.
 *
 * The pages themselves declare which routes they cover, in a `context:`
 * frontmatter line, and the server resolves that into the `help` shared prop
 * (see App\Support\HelpContext). Nothing is guessed here: this component
 * renders what matched, or says plainly that nothing did.
 *
 * The dot is the whole point. It is the difference between a help button you
 * ignore and one that tells you, before you click, that there is something
 * here worth reading.
 */
import { usePage } from '@inertiajs/vue3';
import { CircleQuestionMark, ExternalLink, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import type { AppPageProps, HelpLink } from '@/types';

const page = usePage<AppPageProps>();
const { register } = useKeyboardShortcuts();

const links = computed<HelpLink[]>(() => page.props.help ?? []);
const hasHelp = computed(() => links.value.length > 0);

const open = ref(false);
const panel = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);

function close(returnFocus = true) {
    open.value = false;
    if (returnFocus) {
        trigger.value?.focus();
    }
}

function toggle() {
    if (!open.value) {
        open.value = true;

        return;
    }

    // Only pull focus back to the button when it currently lives inside the
    // panel. Alt+H to peek and Alt+H to dismiss should leave the caret in the
    // field the user was already typing in.
    close(panel.value?.contains(document.activeElement) === true);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        close();
    }
}

function onPointerDown(event: PointerEvent) {
    const target = event.target as Node;

    if (!panel.value?.contains(target) && !trigger.value?.contains(target)) {
        close(false);
    }
}

/**
 * Land on the first article, so opening the panel and pressing Enter reads the
 * most relevant page. Tab walks the rest.
 *
 * The panel itself is the fallback for the empty state, where there is no
 * article to land on but Escape still has to work. Focusing a link rather than
 * the container is also the better screen-reader result: the dialog's label is
 * announced on entry either way, and the user arrives on something actionable
 * instead of having to hunt for it.
 */
function focusFirstArticle() {
    const first = panel.value?.querySelector<HTMLElement>('[data-help-article]');

    (first ?? panel.value)?.focus();
}

watch(open, async (isOpen) => {
    if (isOpen) {
        window.addEventListener('keydown', onKeydown);
        window.addEventListener('pointerdown', onPointerDown);
        await nextTick();
        focusFirstArticle();
    } else {
        window.removeEventListener('keydown', onKeydown);
        window.removeEventListener('pointerdown', onPointerDown);
    }
});

// Navigating to a new page resolves different help, so the open panel would be
// showing the previous route's answers. Close it and let the dot speak instead.
watch(links, () => close(false));

onMounted(() => {
    // Alt+H sits next to Alt+R for the tags reference: both open a panel to
    // read something, where the Ctrl+* shortcuts do things. Registering it here
    // also lists it in the Ctrl+K shortcuts dialog, which reads the same registry.
    register('help-beacon', 'alt+h', toggle, { description: 'Help for this page' });
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('pointerdown', onPointerDown);
});
</script>

<template>
  <div class="fixed right-4 bottom-4 z-40 print:hidden sm:right-6 sm:bottom-6">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="translate-y-2 scale-95 opacity-0"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="translate-y-2 scale-95 opacity-0"
    >
      <div
        v-if="open"
        ref="panel"
        tabindex="-1"
        role="dialog"
        aria-modal="false"
        aria-label="Help for this page"
        class="absolute right-0 bottom-full mb-3 flex h-[650px] max-h-[calc(100dvh-7rem)] w-[375px] max-w-[calc(100vw-2rem)] origin-bottom-right flex-col overflow-hidden rounded-xl border border-border bg-background shadow-2xl outline-none"
      >
        <header class="flex shrink-0 items-start justify-between gap-3 border-b border-border px-4 py-3">
          <div>
            <h2 class="text-sm font-semibold text-foreground">Help for this page</h2>
            <p class="text-xs text-muted-foreground">
              {{ hasHelp ? 'Written for exactly where you are.' : 'Nothing specific here yet.' }}
            </p>
          </div>
          <button
            type="button"
            class="-mr-1 cursor-pointer rounded-md p-1 text-muted-foreground transition hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none"
            aria-label="Close help"
            @click="close()"
          >
            <X class="h-4 w-4" />
          </button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto">
          <ul v-if="hasHelp" class="divide-y divide-border">
            <li v-for="link in links" :key="link.slug">
              <a
                :href="link.url"
                target="_blank"
                rel="noopener noreferrer"
                data-help-article
                class="block cursor-pointer px-4 py-4 transition hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none focus-visible:-outline-offset-2"
              >
                <h3 class="text-sm font-medium text-foreground">{{ link.title }}</h3>
                <p v-if="link.lead" class="mt-1.5 text-sm leading-relaxed text-foreground/80">
                  {{ link.lead }}
                </p>
                <span class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-violet-500 dark:text-violet-400">
                  Read the full page
                  <ExternalLink class="h-3 w-3" aria-hidden="true" />
                  <span class="sr-only">(opens in a new tab)</span>
                </span>
              </a>
            </li>
          </ul>

          <div v-else class="px-4 py-8 text-center">
            <p class="text-sm text-foreground">
              No help page covers this screen yet.
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
              The full help section is still a good place to look.
            </p>
          </div>
        </div>

        <footer class="shrink-0 border-t border-border px-4 py-3">
          <a
            href="/help"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex cursor-pointer items-center gap-1 rounded-sm text-xs font-medium text-foreground hover:text-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none dark:hover:text-violet-400"
          >
            Browse all help
            <ExternalLink class="h-3 w-3" aria-hidden="true" />
            <span class="sr-only">(opens in a new tab)</span>
          </a>
        </footer>
      </div>
    </Transition>

    <button
      ref="trigger"
      type="button"
      class="relative flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-border bg-background text-muted-foreground shadow-lg transition hover:text-foreground hover:shadow-xl focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none"
      :aria-label="hasHelp ? `Help for this page, ${links.length} page${links.length === 1 ? '' : 's'} available` : 'Help'"
      :aria-expanded="open"
      title="Help for this page (Alt+H)"
      @click="toggle"
    >
      <CircleQuestionMark class="h-5 w-5" />
      <span
        v-if="hasHelp"
        class="absolute top-0 right-0 h-2.5 w-2.5 rounded-full bg-violet-500 ring-2 ring-background"
        aria-hidden="true"
      />
    </button>
  </div>
</template>
