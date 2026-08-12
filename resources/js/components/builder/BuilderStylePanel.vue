<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { css as cssLang } from '@codemirror/lang-css';
import { html as htmlLang } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import { ChevronDown, ChevronRight, Palette } from '@lucide/vue';
import type { BuilderPlacement } from '@/types';
import { harvestBuilderClasses } from '@/utils/builderClasses';

// Overlay-level CSS and <head> for a Builder composition. The blocks supply the
// look; this is where the user bends it to their overlay. Both values are
// stored in metadata.builder and appended last at compile time - see
// composeBuilderTemplate.
//
// Whether an editor's content has reached the block previews is surfaced here
// rather than up by the canvas: the canvas is what goes stale, but the panel is
// what is on screen when it happens, since you are typing in it. Every part of
// that signal is laid out so it can appear and disappear without moving
// anything - the action row is always present and only fades, and the border
// only changes colour. A block of UI that grows into existence on a keystroke
// shoves the editor down mid-sentence, and shoves it back up again the moment a
// typo is fixed.
const props = defineProps<{
  placements: BuilderPlacement[];
  cssStale?: boolean;
  headStale?: boolean;
}>();

const emit = defineEmits<{ sendToPreview: [] }>();

const cssValue = defineModel<string>('css', { required: true });
const headValue = defineModel<string>('head', { required: true });

const stale = computed(() => !!(props.cssStale || props.headStale));

const open = ref(false);
const showAllClasses = ref(false);

const classes = computed(() => harvestBuilderClasses(props.placements));
const styledClasses = computed(() => classes.value.filter((c) => c.styled));
const otherClasses = computed(() => classes.value.filter((c) => !c.styled));

const hasContent = computed(() => !!(cssValue.value.trim() || headValue.value.trim()));

/** A class already carrying a rule in the user's CSS, so the chip can say so. */
function alreadyStyled(name: string): boolean {
  return new RegExp(`\\.${name.replace(/[.*+?^${}()|[\]\\-]/g, '\\$&')}(?![\\w-])`).test(cssValue.value);
}

/** Append an empty rule for a class. Appending beats inserting at the cursor:
 *  it is predictable, and never splits a rule the user is mid-way through. */
function addRule(name: string) {
  const existing = cssValue.value.trimEnd();
  cssValue.value = `${existing ? `${existing}\n\n` : ''}.${name} {\n  \n}\n`;
}

const isDark = ref(document.documentElement.classList.contains('dark'));
let observer: MutationObserver | null = null;

onMounted(() => {
  observer = new MutationObserver(() => {
    isDark.value = document.documentElement.classList.contains('dark');
  });
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

onUnmounted(() => observer?.disconnect());

const baseTheme = EditorView.theme({
  '&': { height: '100%', fontSize: '13px' },
  '.cm-scroller': { overflow: 'auto' },
  '.cm-content': { padding: '5px' },
});

const editorKey = computed(() => (isDark.value ? 'dark' : 'light'));
const cssExtensions = computed(() => [cssLang(), baseTheme, ...(isDark.value ? [oneDark] : [])]);
const headExtensions = computed(() => [htmlLang(), baseTheme, ...(isDark.value ? [oneDark] : [])]);
</script>

<template>
  <div class="border border-sidebar-border bg-sidebar-accent">
    <button
      type="button"
      class="flex w-full cursor-pointer items-center gap-2 px-4 py-3 text-left text-sm font-medium text-accent-foreground hover:bg-background/40"
      @click="open = !open"
    >
      <ChevronDown v-if="open" class="h-4 w-4 shrink-0" />
      <ChevronRight v-else class="h-4 w-4 shrink-0" />
      <Palette class="h-4 w-4 shrink-0 text-lime-500 dark:text-lime-400" />
      Your CSS and fonts
      <!-- One dot, two meanings, so collapsing the panel with unsent changes
           still shows something. Swapped rather than added: a second dot would
           nudge the row every time the state flipped. -->
      <span
        v-if="hasContent || stale"
        class="h-1.5 w-1.5 rounded-full transition-colors"
        :class="stale ? 'bg-orange-400' : 'bg-violet-400'"
        :title="stale ? 'Your changes are not in the block previews yet' : 'This overlay has custom CSS'"
      />
      <span class="ml-auto text-xs font-normal text-muted-foreground">Optional</span>
    </button>

    <div v-if="open" class="border-t border-sidebar-border p-4">
      <p class="mb-4 text-sm text-foreground">
        Restyle the blocks on this overlay. Your CSS is scoped to the grid and applied last, so a rule here
        beats the same selector inside a block. Write plain CSS - Overlabels scopes it for you.
      </p>
      <p class="mb-4 text-sm text-foreground">
        The block previews above do not follow along as you type. Send your changes over when you want to look at them -
        the saved overlay uses what is in these editors either way.
      </p>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
        <!-- Classes actually present in the placed blocks -->
        <div class="min-w-0">
          <h4 class="mb-2 text-xs font-medium tracking-wide text-accent-foreground uppercase">Classes in use</h4>

          <p v-if="!classes.length" class="text-sm text-foreground">
            Place a block to see the classes you can target.
          </p>

          <div v-else class="max-h-64 space-y-1 overflow-auto pr-1 lg:max-h-96">
            <button
              v-for="entry in styledClasses"
              :key="entry.name"
              type="button"
              class="block w-full cursor-pointer truncate border px-2 py-1 text-left font-mono text-xs transition-colors hover:border-violet-400 hover:bg-violet-400/10"
              :class="
                alreadyStyled(entry.name)
                  ? 'border-violet-400/60 bg-violet-400/10 text-accent-foreground'
                  : 'border-sidebar-border text-foreground'
              "
              :title="
                entry.structural
                  ? 'On every block wrapper in this overlay'
                  : `Used by ${entry.blocks.join(', ')}`
              "
              @click="addRule(entry.name)"
            >
              .{{ entry.name }}
            </button>

            <template v-if="otherClasses.length">
              <button
                type="button"
                class="mt-2 cursor-pointer text-xs text-violet-500 hover:underline dark:text-violet-400"
                @click="showAllClasses = !showAllClasses"
              >
                {{ showAllClasses ? 'Hide' : `Show ${otherClasses.length} unstyled` }}
              </button>

              <button
                v-for="entry in showAllClasses ? otherClasses : []"
                :key="entry.name"
                type="button"
                class="block w-full cursor-pointer truncate border px-2 py-1 text-left font-mono text-xs transition-colors hover:border-violet-400 hover:bg-violet-400/10"
                :class="
                  alreadyStyled(entry.name)
                    ? 'border-violet-400/60 bg-violet-400/10 text-accent-foreground'
                    : 'border-sidebar-border text-muted-foreground'
                "
                :title="`Used by ${entry.blocks.join(', ')}, not styled by it`"
                @click="addRule(entry.name)"
              >
                .{{ entry.name }}
              </button>
            </template>
          </div>
        </div>

        <!-- CSS + head editors -->
        <div class="min-w-0 space-y-4">
          <div>
            <!-- The action never unmounts, it only fades, so this row's height
                 is the same whether or not there is anything to send. That is
                 the whole point: typing a typo and deleting it again must not
                 push the editor down and pull it back up. -->
            <div class="mb-1 flex items-center justify-between gap-2">
              <label for="builder-custom-css" class="block text-xs font-medium tracking-wide text-accent-foreground uppercase">
                CSS
              </label>
              <div
                class="flex items-center gap-2 transition-opacity duration-200"
                :class="stale ? 'opacity-100' : 'pointer-events-none opacity-0'"
                :inert="!stale"
              >
                <span class="text-xs text-orange-700 dark:text-orange-300">Not in the previews yet</span>
                <button type="button" class="btn btn-warning btn-sm cursor-pointer" @click="emit('sendToPreview')">
                  Send to preview
                </button>
              </div>
            </div>
            <div
              class="relative h-64 border bg-background transition-colors"
              :class="cssStale ? 'border-orange-500/70 dark:border-orange-400/70' : 'border-sidebar-border'"
            >
              <Codemirror
                id="builder-custom-css"
                :key="'builder-css-' + editorKey"
                v-model="cssValue"
                class="absolute inset-0"
                :indent-with-tab="true"
                :tab-size="2"
                :extensions="cssExtensions"
                placeholder="Click a class on the left to start a rule, or write your own CSS here."
              />
            </div>
          </div>

          <div>
            <label for="builder-custom-head" class="mb-1 block text-xs font-medium tracking-wide text-accent-foreground uppercase">
              Fonts and other &lt;head&gt; tags
            </label>
            <div
              class="relative h-28 border bg-background transition-colors"
              :class="headStale ? 'border-orange-500/70 dark:border-orange-400/70' : 'border-sidebar-border'"
            >
              <Codemirror
                id="builder-custom-head"
                :key="'builder-head-' + editorKey"
                v-model="headValue"
                class="absolute inset-0"
                :indent-with-tab="true"
                :tab-size="2"
                :extensions="headExtensions"
                placeholder='<link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet">'
              />
            </div>
            <p class="mt-1 text-xs text-foreground">
              Added after the head tags the blocks bring themselves. Scripts are stripped on save.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
