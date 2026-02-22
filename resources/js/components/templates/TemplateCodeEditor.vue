<script setup lang="ts">
import { ref, computed } from 'vue';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import { ChevronDown, ChevronUp, FileCode2, Code, Palette, Keyboard } from 'lucide-vue-next';

const props = defineProps<{
  isDark: boolean;
}>();

const emit = defineEmits<{
  'toggle-shortcuts': [];
}>();

const headValue = defineModel<string>('head', { required: true });
const htmlValue = defineModel<string>('html', { required: true });
const cssValue = defineModel<string>('css', { required: true });

const editorTabs = [
  { key: 'head', label: 'HEAD', icon: FileCode2, color: 'text-pink-500 dark:text-pink-400' },
  { key: 'html', label: 'HTML', icon: Code, color: 'text-cyan-500 dark:text-cyan-400' },
  { key: 'css', label: 'CSS', icon: Palette, color: 'text-lime-500 dark:text-lime-400' },
] as const;

type CodeTab = (typeof editorTabs)[number]['key'];

const codeTab = ref<CodeTab>('html');
const isExpanded = ref(false);

const baseTheme = EditorView.theme({
  '&': { height: '100%', fontSize: '14px' },
  '.cm-scroller': { overflow: 'auto' },
  '.cm-content': { padding: '16px' },
  '.cm-focused .cm-cursor': { borderLeftColor: '#3b82f6' },
});

const htmlExtensions = computed(() => [html(), baseTheme, ...(props.isDark ? [oneDark] : [])]);
const cssExtensions = computed(() => [css(), baseTheme, ...(props.isDark ? [oneDark] : [])]);
</script>

<template>
  <div>
    <div class="overflow-hidden rounded-sm border border-border" :style="{ height: isExpanded ? '800px' : '500px' }">
      <div class="flex h-full">
        <!-- Vertical file tabs -->
        <div class="flex flex-col border-r border-border bg-sidebar text-sidebar-foreground">
          <button
            v-for="tab in editorTabs"
            :key="tab.key"
            type="button"
            @click="codeTab = tab.key"
            :class="[
              'flex cursor-pointer items-center gap-1.5 px-5 py-3 text-left text-xs uppercase transition-colors',
              codeTab === tab.key
                ? 'bg-background text-accent-foreground'
                : 'text-sidebar-foreground/60 hover:bg-background/40 hover:text-sidebar-foreground',
            ]"
          >
            <component :is="tab.icon" :class="tab.color" class="size-3.5" />
            {{ tab.label }}
          </button>
          <div class="mt-auto px-3 py-2">
            <p class="text-xs uppercase text-sidebar-foreground/30">{{ codeTab }}</p>
            <p v-if="codeTab === 'head'" class="text-[10px] leading-tight text-sidebar-foreground/20">
              Use &lt;link&gt; tags only.<br />Scripts are stripped.
            </p>
          </div>
        </div>

        <!-- Editor panel -->
        <div class="relative flex-1 bg-background">
          <textarea
            v-show="codeTab === 'head'"
            v-model="headValue"
            class="font-mono absolute inset-0 resize-none bg-background p-4 text-sm text-foreground outline-none"
            placeholder="Enter <head> content here… e.g. <link> tags for fonts or icon libraries."
          />
          <Codemirror
            v-show="codeTab === 'html'"
            v-model="htmlValue"
            class="absolute inset-0"
            :autofocus="true"
            :indent-with-tab="true"
            :tab-size="2"
            :extensions="htmlExtensions"
            placeholder="Enter your HTML here… Use [[[tag_name]]] for dynamic content"
          />
          <Codemirror
            v-show="codeTab === 'css'"
            v-model="cssValue"
            class="absolute inset-0"
            :indent-with-tab="true"
            :tab-size="2"
            :extensions="cssExtensions"
            placeholder="Enter your CSS styles here…"
          />
        </div>
      </div>
    </div>

    <!-- Expand / shortcuts row -->
    <div class="mt-3 flex justify-between">
      <button
        type="button"
        @click="isExpanded = !isExpanded"
        class="flex cursor-pointer items-center gap-1 text-sm text-muted-foreground hover:text-accent-foreground"
      >
        <ChevronUp v-if="isExpanded" class="h-4 w-4" />
        <ChevronDown v-else class="h-4 w-4" />
        {{ isExpanded ? 'Collapse editor' : 'Expand editor' }}
      </button>
      <a
        href="#"
        @click.prevent="emit('toggle-shortcuts')"
        class="flex cursor-pointer items-center text-sm text-muted-foreground hover:text-accent-foreground"
      >
        <Keyboard class="mr-2 h-4 w-4" />
        Keyboard Shortcuts
      </a>
    </div>
  </div>
</template>
