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
              We exist to pipe live Twitch data into overlays. Styling and animation belong to your own CSS, not to us. The only animation styling
              Overlabels provides is optional Alert entrance and exit transitions, because that seemed like a good idea.
            </p>
          </div>

          <!-- Principle 2 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-green-500 text-white text-sm font-bold">2</span>
              The template layer is declarative, not decorative.
            </h2>
            <p class="text-muted-foreground">
              Template tags describe what data to show and when to show it, not how it should look. Overlabels never delivers any styled contents as a payload,
              that's all up to you. Wrap the output in a class, style the class. It's HTML and CSS after all.
            </p>
          </div>

          <!-- Principle 3 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-purple-500 text-white text-sm font-bold">3</span>
              Native web standards do the heavy lifting. Scripting is a no-no.
            </h2>
            <p class="text-muted-foreground">
              If something can be achieved with plain HTML, CSS and some conditional logic, it's perfect for Overlabels.
              If you can't solve your problem with HTML and CSS alone, it doesn't belong on Overlabels.
            </p>
          </div>

          <!-- Principle 4 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-white text-sm font-bold">4</span>
              Logic is allowed, for all your data.
            </h2>
            <p class="text-muted-foreground">
              Conditions (if/else), comparisons, and what we call <a href="/help/controls" class="text-violet-400 hover:underline">"controls"</a>, exist to control data flow. They can also control styles, animations, or transforms.
              You can use the conditional template tags in your CSS declarations as well. Overlabels gives you the tools, it's up to you how to use them.
            </p>
          </div>

          <!-- Principle 5 -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h2 class="mb-4 text-2xl font-bold text-foreground">
              <span class="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-500 text-white text-sm font-bold">5</span>
              No opinionated sugar.
            </h2>
            <p class="mb-4 text-muted-foreground">
              There will never be tags like <code>[[[blink]]]</code>, <code>[[[rotation=90]]]</code>, or <code>[[[rainbow]]]</code>. Those belong to CSS.<br>
              Then again, if you want to create a Control that blinks your text and you work it out in CSS and conditionals, please - be my guest.
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
              Overlabels is like Minecraft in a sense. Immensely powerful and based on logic and options, but don't expect a 20-page guidebook. You can use
              HTML, CSS, conditionals and Controls to create overlays. How you style them, what you do with them and how you control them, is up to you.
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
