<script setup lang="ts">

import { ArrowRight } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <section class="border-b border-sidebar-accent py-24 sm:py-36">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-5xl">

        <h1 class="mb-6 text-5xl leading-[1.05] font-bold tracking-tight sm:text-6xl md:text-7xl">
          Your overlay is a webpage.<br />
          <span class="text-sky-500">We make it reactive.</span>
        </h1>

        <p class="mb-4 max-w-2xl text-xl leading-relaxed text-foreground">
          Template tags, a reactive expression engine, and pipe formatters wired into the HTML and CSS you already write. Pull live Twitch data with <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-base text-amber-700 dark:text-amber-400">[[[tag]]]</code>. Derive state with <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-base text-amber-700 dark:text-amber-400">c.wins / (c.wins + c.losses) * 100</code>. React to follows, raids, and donations from Ko-fi, Streamlabs, and StreamElements. Update anything from your dashboard and watch the overlay catch up in milliseconds.
        </p>
        <p class="mb-14 max-w-2xl text-base text-foreground">
          No drag-and-drop editor. No proprietary file format. No lock-in. <strong>Overlabels is the reactive substrate - your overlay is just a webpage it keeps alive.</strong>
        </p>

        <!-- Hero code blocks -->
        <div class="mb-14 grid gap-4 lg:grid-cols-2">
          <div class="overflow-hidden rounded-sm">
            <div class="flex items-center gap-2 border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
              <span class="font-mono text-xs text-muted-foreground">overlay.html</span>
            </div>
            <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
              <div>
                <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"stat-bar"</span
              ><span class="text-zinc-500">&gt;</span>
              </div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span
                class="text-amber-700 dark:text-amber-400">[[[followers_total]]]</span
              ><span class="text-zinc-500">&lt;/span&gt;</span>
              </div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span
                class="text-foreground">followers</span
              ><span class="text-zinc-500">&lt;/small&gt;</span>
              </div>
              <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              <div class="mt-2"></div>
              <div><span class="text-sky-600 dark:text-sky-400">[[[if:followers_total &gt;= 1000]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span
                class="text-emerald-600 dark:text-emerald-400">"milestone"</span
              ><span class="text-zinc-500">&gt;</span>
              </div>
              <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-foreground">four digits. let's go.</span></div>
              <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;/div&gt;</span></div>
              <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
            </div>
          </div>

          <div class="overflow-hidden rounded-sm bg-emerald-50 dark:bg-emerald-950 ">
            <div class="flex items-center gap-2 border-b border-emerald-500/20 px-4 py-2.5">
              <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
              <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400">live in OBS</span>
            </div>
            <div class="overflow-x-auto bg-emerald-50 dark:bg-emerald-950 p-5 font-mono text-sm leading-7">
              <div>
                <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"stat-bar"</span
              ><span class="text-zinc-500">&gt;</span>
              </div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span
                class="text-emerald-600 dark:text-emerald-300">1,342</span
              ><span class="text-zinc-500">&lt;/span&gt;</span>
              </div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span
                class="text-foreground">followers</span
              ><span class="text-zinc-500">&lt;/small&gt;</span>
              </div>
              <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              <div class="mt-2"></div>
              <div>
                <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"milestone"</span
              ><span class="text-zinc-500">&gt;</span>
              </div>
              <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-foreground">four digits. let's go.</span></div>
              <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap gap-4">
          <Link v-if="!$page.props.auth.user" href="#get-started">
            <button class="btn btn-primary">Get started free
              <ArrowRight class="ml-2 h-4 w-4" />
            </button>
          </Link>
          <Link v-else :href="route('dashboard.index')">
            <button class="btn btn-primary">Go to dashboard
              <ArrowRight class="ml-2 h-4 w-4" />
            </button>
          </Link>
          <Link href="/help/manifesto">
            <button class="btn btn-secondary">Read the manifesto</button>
          </Link>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>

</style>
