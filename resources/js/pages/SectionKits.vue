<script setup lang="ts">

import { AlertTriangle, GitFork, Layers, Shield } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
</script>

<template>
  <section id="kits" class="scroll-mt-16 border-b bg-card py-24">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-5xl">
        <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Kits &amp; Copying</Badge>
        <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Good design compounds.</h2>

        <div class="flex items-center justify-center mb-4 max-w-3xl gap-2.5 border border-violet-500/30 bg-violet-500/10 px-4 py-3">
          <AlertTriangle class="h-4 w-4 shrink-0 text-violet-500" />
          <p class="text-sm text-violet-700 dark:text-violet-300">
            <strong>Work in progress</strong>. Duplicating overlays works, but Integration-controlled Controls may not transfer correctly.
          </p>
        </div>
        <p class="mb-12 max-w-3xl text-lg text-foreground">
          Any public template or kit can be copied. One click, one copy, fully yours to modify, extend, or break. An
          Overlay Kit is a collection of
          templates - a static overlay, a follower alert, a subscription alert, a raid alert - designed as a cohesive
          visual system. Copy the kit,
          get everything at once.
        </p>

        <div class="grid gap-6 sm:grid-cols-3">
          <div class="rounded-sm border border-sidebar-accent bg-sidebar-accent p-6">
            <GitFork class="mb-4 h-8 w-8 text-sky-500" />
            <h3 class="mb-2 font-semibold">Copy anything public</h3>
            <p class="text-sm text-muted-foreground">
              Every public template is a starting point. Copy it, own it, ship it. The original is always untouched.
            </p>
          </div>
          <div class="rounded-sm border border-sidebar-accent bg-sidebar-accent p-6">
            <Layers class="mb-4 h-8 w-8 text-sky-500" />
            <h3 class="mb-2 font-semibold">Overlay Kits</h3>
            <p class="text-sm text-muted-foreground">
              Collections of templates sharing a visual language. Copy the kit, get the whole system. No assembly
              required.
            </p>
          </div>
          <div class="rounded-sm border border-sidebar-accent bg-sidebar-accent p-6">
            <Shield class="mb-4 h-8 w-8 text-sky-500" />
            <h3 class="mb-2 font-semibold">Controls carry over</h3>
            <p class="text-sm text-muted-foreground">
              Copying a template with controls opens the Import Wizard. Pick which controls come with the copy.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>

</style>
