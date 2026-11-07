<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download, Eye, Save, Settings2, Trash2, X } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';

// A board for judging overlabels-buttons.css: every live
// variant and size, both themes side by side, the real pairs the app puts
// next to each other. Nothing here is data; it is the stylesheet on a wall.

const variants = ['primary', 'secondary', 'chill', 'cancel', 'danger', 'warning', 'plain'];
const sizes = [
  { cls: 'btn-xs', label: 'xs' },
  { cls: 'btn-sm', label: 'sm' },
  { cls: '', label: 'default' },
  { cls: 'btn-xl', label: 'xl' },
];
const themes = [
  { key: 'light', cls: '', label: 'Light (switch the app to light mode to see this one)' },
  { key: 'dark', cls: 'dark', label: 'Dark' },
];
</script>

<template>
  <Head title="Buttons" />

  <AppLayout
    :breadcrumbs="[
      { title: 'Admin', href: route('admin.dashboard') },
      { title: 'Buttons', href: route('admin.buttons') },
    ]"
  >
    <div class="p-4">
      <p class="mb-4 max-w-prose text-sm text-foreground">
        Every live button in <code>overlabels-buttons.css</code>. Hover, press and Tab through them. Rows are <code>items-start</code> on purpose, so
        a height difference between variants shows instead of being stretched away.
      </p>

      <div class="grid gap-4 xl:grid-cols-2">
        <section v-for="theme in themes" :key="theme.key" :class="theme.cls">
          <div class="space-y-8 border border-border bg-background p-6 text-foreground">
            <h2 class="text-lg font-semibold">{{ theme.label }}</h2>

            <div>
              <h3 class="mb-3 text-sm font-medium text-muted-foreground">Variants: text, icon, disabled</h3>
              <div class="space-y-3">
                <div v-for="v in variants" :key="v" class="flex flex-wrap items-start gap-3">
                  <span class="w-20 pt-2 font-mono text-xs text-muted-foreground">{{ v }}</span>
                  <button type="button" class="btn" :class="`btn-${v}`">Button</button>
                  <button type="button" class="btn gap-2" :class="`btn-${v}`"><Save class="size-4" /> With icon</button>
                  <button type="button" class="btn" :class="`btn-${v}`" disabled>Disabled</button>
                </div>
              </div>
            </div>

            <div>
              <h3 class="mb-3 text-sm font-medium text-muted-foreground">Sizes</h3>
              <div class="space-y-3">
                <div v-for="s in sizes" :key="s.label" class="flex flex-wrap items-start gap-3">
                  <span class="w-20 pt-2 font-mono text-xs text-muted-foreground">{{ s.label }}</span>
                  <button type="button" class="btn btn-primary gap-2" :class="s.cls"><Download class="size-4" /> Primary</button>
                  <button type="button" class="btn btn-secondary gap-2" :class="s.cls"><Eye class="size-4" /> Secondary</button>
                  <button type="button" class="btn btn-chill gap-2" :class="s.cls"><Eye class="size-4" /> Chill</button>
                </div>
              </div>
            </div>

            <div>
              <h3 class="mb-3 text-sm font-medium text-muted-foreground">Pairs the app actually uses</h3>
              <div class="space-y-3">
                <div class="flex flex-wrap items-start gap-3">
                  <button type="button" class="btn btn-primary">Save</button>
                  <button type="button" class="btn btn-cancel">Cancel</button>
                </div>
                <div class="flex flex-wrap items-start gap-3">
                  <button type="button" class="btn btn-danger gap-2"><Trash2 class="size-4" /> Delete</button>
                  <button type="button" class="btn btn-cancel gap-2"><X class="size-4" /> Cancel</button>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-3 border border-border p-4">
                  <button type="button" class="btn btn-chill gap-2"><Eye class="size-4" /> View product</button>
                  <button type="button" class="btn btn-primary gap-2"><Settings2 class="size-4" /> Manage product</button>
                </div>
                <div class="flex flex-wrap items-start gap-3">
                  <button type="button" class="btn btn-sm btn-primary">Primary</button>
                  <button type="button" class="btn btn-sm btn-secondary">Secondary</button>
                  <button type="button" class="btn btn-sm btn-chill">Chill</button>
                  <button type="button" class="btn btn-sm btn-cancel">Cancel</button>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
