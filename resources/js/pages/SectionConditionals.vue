<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
</script>

<template>
  <section id="conditionals" class="scroll-mt-16 border-b border-sidebar-accent py-24">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-5xl">
        <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Conditional Rendering</Badge>
        <h2 class="mb-4 text-3xl font-bold sm:text-4xl">A comparison engine in your template.</h2>
        <p class="mb-12 max-w-2xl text-lg text-foreground">
          Any tag (Twitch data, control value, or event payload) can drive a conditional block. Evaluated
          client-side in the overlay with no
          server round-trips and no <code class="rounded bg-muted px-1.5 text-sm">eval()</code>. Nesting supported up
          to 10 levels deep.
          <Link href="/help/conditionals" class="ml-1 text-sky-500 hover:underline">Full syntax reference →</Link>
        </p>

        <div class="mb-10 grid gap-8 lg:grid-cols-2">
          <div>
            <h3 class="mb-4 text-xs font-semibold tracking-widest text-foreground uppercase">Syntax</h3>
            <div class="overflow-hidden rounded-sm">
              <div class="bg-card p-5 font-mono text-sm leading-7">
                <div><span class="text-sky-600 dark:text-sky-400">[[[if:variable operator value]]]</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                <div><span class="text-sky-600 dark:text-sky-400">[[[elseif:variable operator value]]]</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                <div><span class="text-sky-600 dark:text-sky-400">[[[else]]]</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
              </div>
            </div>
          </div>

          <div>
            <h3 class="mb-4 text-xs font-semibold tracking-widest text-foreground uppercase">Operators</h3>
            <div class="divide-y divide-sidebar-accent overflow-hidden rounded-sm bg-card/50">
              <div
                v-for="[op, label] in [
                    ['=', 'Equal'],
                    ['!=', 'Not equal'],
                    ['>', 'Greater than'],
                    ['<', 'Less than'],
                    ['>=', 'Greater than or equal'],
                    ['<=', 'Less than or equal'],
                  ]"
                :key="op"
                class="flex items-center gap-4 bg-card/50 px-4 py-2.5"
              >
                <code class="w-8 shrink-0 font-mono text-sm text-amber-700 dark:text-amber-400">{{ op }}</code>
                <span class="text-sm text-muted-foreground">{{ label }}</span>
              </div>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">
              Numeric comparisons are numeric. Truthy check treats <code class="rounded bg-muted px-1">"0"</code>,
              <code class="rounded bg-muted px-1">"false"</code>, and empty string as false.
            </p>
          </div>
        </div>

        <div class="overflow-hidden rounded-sm">
          <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
            <span class="font-mono text-xs text-muted-foreground">Language-aware overlay + milestone block</span>
          </div>
          <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
            <div><span class="text-sky-600 dark:text-sky-400">[[[if:channel_language = en]]]</span></div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-foreground">Welcome to the stream</span
            ><span class="text-zinc-500">&lt;/p&gt;</span>
            </div>
            <div><span class="text-sky-600 dark:text-sky-400">[[[elseif:channel_language = es]]]</span></div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-foreground">Bienvenidos al stream</span
            ><span class="text-zinc-500">&lt;/p&gt;</span>
            </div>
            <div><span class="text-sky-600 dark:text-sky-400">[[[else]]]</span></div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-foreground">Welcome</span
            ><span class="text-zinc-500">&lt;/p&gt;</span>
            </div>
            <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
            <div class="mt-3"></div>
            <div><span class="text-sky-600 dark:text-sky-400">[[[if:followers_total &gt;= 10000]]]</span></div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span
              class="text-emerald-600 dark:text-emerald-400">"tenk-badge"</span
            ><span class="text-zinc-500">&gt;</span><span class="text-foreground">10K club</span><span
              class="text-zinc-500">&lt;/div&gt;</span>
            </div>
            <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
