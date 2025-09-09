<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Manifesto',
    href: '/manifesto',
  },
];
</script>

<template>
  <Head title="Overlabels Manifesto" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <div class="mb-8">
          <h1 class="mb-4 text-4xl font-bold">The Overlabels Manifesto</h1>
          <p class="text-lg text-muted-foreground">Our principles for building a data engine, not a design engine.</p>
        </div>

        <div class="space-y-8">
          <!-- Principle 1 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-500 text-white text-sm font-bold">1</span>
              Overlabels is a data engine, not a design engine.
            </h2>
            <p class="text-muted-foreground">
              We exist to pipe live Twitch data into overlays. Styling and animation belong to CSS, not to us.
            </p>
          </div>

          <!-- Principle 2 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-green-500 text-white text-sm font-bold">2</span>
              The template layer is declarative, not decorative.
            </h2>
            <p class="text-muted-foreground">
              Template tags describe what data to show and when to show it, not how it should look.
            </p>
          </div>

          <!-- Principle 3 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-purple-500 text-white text-sm font-bold">3</span>
              Native web standards do the heavy lifting.
            </h2>
            <p class="text-muted-foreground">
              If something can be achieved with plain HTML and CSS, it should be. JavaScript is filtered out entirely from frontend templates. If you can't solve your problem with HTML and CSS alone, it doesn't belong on Overlabels.
            </p>
          </div>

          <!-- Principle 4 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-white text-sm font-bold">4</span>
              Logic is allowed, but only for data.
            </h2>
            <p class="text-muted-foreground">
              Conditions (if/else), comparisons, and loops exist to control data flow. They do not control styles, animations, or transforms.
              You can use the conditional template tags in your CSS declarations as well. Use them wisely.
            </p>
          </div>

          <!-- Principle 5 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-500 text-white text-sm font-bold">5</span>
              No opinionated sugar.
            </h2>
            <p class="mb-4 text-muted-foreground">
              There will never be tags like <code>[[[blink]]]</code>, <code>[[[rotation=90]]]</code>, or <code>[[[rainbow]]]</code>. Those belong to CSS.
            </p>
          </div>

          <!-- Principle 6 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-cyan-500 text-white text-sm font-bold">6</span>
              We keep overlays portable.
            </h2>
            <div class="space-y-3 text-muted-foreground">
              <p>Any overlay built with Overlabels works in any recent OBS version and any recent browser.</p>
              <p>Overlays can be shared freely between users unless you choose to keep them private.</p>
              <p>Any overlay created with Overlabels will work with any account.</p>
            </div>
          </div>

          <!-- Principle 7 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-pink-500 text-white text-sm font-bold">7</span>
              Minimum viable magic.
            </h2>
            <p class="text-muted-foreground">
              If you can explain a feature in one sentence without referencing JS, it probably belongs in Overlabels. If it takes a paragraph, it probably doesn't.
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="mt-12 rounded-lg border border-sidebar bg-sidebar p-6 text-center">
          <p class="text-muted-foreground">
            These principles guide every decision we make. They ensure Overlabels remains focused, predictable, and respectful of web standards, while also
            keeping the system fast and easy to use.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
