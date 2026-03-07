  <script setup lang="ts">
/**
 * Renders the user's chosen Lucide icon. Clicking it opens an inline
 * text input so the user can type any lucide.dev icon code (kebab-case).
 * The typed value is converted to PascalCase for the dynamic component
 * lookup. Unknown icons silently fall back to HeartCrack.
 *
 * Saving PATCHes /settings/icon and stays on the current page.
 */
import * as LucideIcons from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{ userIcon: string }>();

// kebab-case  →  PascalCase  (e.g. "arrow-big-right" → "ArrowBigRight")
function toPascalCase(str: string): string {
  return str.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('');
}

const localIcon = ref(props.userIcon || 'smile');
watch(() => props.userIcon, v => { localIcon.value = v || 'smile'; });

const iconComponent = computed((): Component => {
  const key = toPascalCase(localIcon.value || 'smile');
  const candidate = (LucideIcons as unknown as Record<string, Component | undefined>)[key];
  // Icons in lucide-vue-next are functions (functional components), not objects.
  // Use nullish coalescing so any truthy export (function or object) is accepted.
  return candidate ?? LucideIcons.HeartCrack;
});

const editing = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);
const draft = ref('');

function startEditing() {
  draft.value = localIcon.value === 'smile' && !props.userIcon ? '' : localIcon.value;
  editing.value = true;
  nextTick(() => inputRef.value?.focus());
}

function save() {
  const trimmed = draft.value.trim().toLowerCase() || 'smile';
  localIcon.value = trimmed;
  editing.value = false;
  router.patch(route('settings.icon'), { icon: trimmed === 'smile' ? null : trimmed }, {
    preserveState: true,
    preserveScroll: true,
  });
}

function cancel() {
  editing.value = false;
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter') save();
  if (e.key === 'Escape') cancel();
}
</script>

<template>
  <div class="inline-flex items-center gap-2">
    <template v-if="!editing">
      <button
        type="button"
        class="rounded p-0.5 text-foreground transition-colors hover:text-violet-400 cursor-pointer focus:outline-none focus:ring-1 focus:ring-ring"
        title="Click to change your icon"
        @click="startEditing"
      >
        <component :is="iconComponent" class="size-6" />
      </button>
    </template>
    <template v-else>
      <input
        ref="inputRef"
        v-model="draft"
        type="text"
        placeholder="e.g. arrow-big-right"
        class="w-40 rounded border bg-background px-2 py-0.5 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
        @keydown="onKeydown"

      />
      <span class="text-xs text-muted-foreground">
        Browse at <a title="Open this link and click the icon you like. Then click the icon's name to copy it." href="https://lucide.dev/icons/" target="_blank" rel="noopener" class="underline hover:text-foreground">lucide.dev</a>
        <Button variant="outline" size="sm" @click="save" class="ml-2 text-violet-400 cursor-pointer">save</Button>
      </span>
    </template>
  </div>
</template>
