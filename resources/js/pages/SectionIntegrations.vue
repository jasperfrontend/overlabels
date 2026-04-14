<script setup lang="ts">

import { DollarSign, Heart } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { ref } from 'vue';

const integrationsTab = ref<'kofi' | 'streamlabs' | 'streamelements'>('kofi');

const integrationConfigs = {
  kofi: {
    name: 'Ko-fi',
    namespace: 'kofi',
    tagline: 'Donations, subscriptions, shop orders',
    description:
      'Paste your Ko-fi verification token, set your webhook URL, done. Every Ko-fi event flows through the same alert pipeline as Twitch events.',
  },
  streamlabs: {
    name: 'Streamlabs',
    namespace: 'streamlabs',
    tagline: 'Live donation tracking via OAuth',
    description:
      'One click to authenticate. Overlabels listens for donations in real time and auto-provisions six controls the moment you connect.',
  },
  streamelements: {
    name: 'StreamElements',
    namespace: 'streamelements',
    tagline: 'Live donation tracking via JWT',
    description:
      'Paste your JWT token to authenticate. Overlabels listens for donations in real time and auto-provisions six controls the moment you connect.',
  },
} as const;
</script>

<template>
  <section id="integrations" class="scroll-mt-16 border-b border-sidebar-accent py-24">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-5xl">
        <div class="mb-4 flex items-center gap-3">
          <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Integrations</Badge>
        </div>
        <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Show donations from different sources.</h2>
        <p class="mb-12 max-w-2xl text-lg text-foreground">
          Connect your Ko-fi, StreamElements or Streamlabs account and Overlabels automatically tracks every donation in real time.
          Counters update, alerts fire, and your overlay stays current - all without touching a single line of code
          after setup.
        </p>

        <!-- Integration tabs -->
        <div class="mb-8 flex gap-0 overflow-hidden border-b border-sidebar-accent">
          <button
            v-for="service in (['kofi', 'streamlabs', 'streamelements'] as const)"
            :key="service"
            @click="integrationsTab = service"
            :class="[
                '-mb-px shrink-0 cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                integrationsTab === service ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground',
              ]"
          >
            {{ integrationConfigs[service].name }}
          </button>
        </div>

        <!-- Unified integration card -->
        <div class="mb-12 rounded-sm bg-card p-6">
          <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-500/10">
              <Heart v-if="integrationsTab === 'kofi'" class="h-5 w-5 text-sky-500" />
              <DollarSign v-else class="h-5 w-5 text-sky-500" />
            </div>
            <div>
              <h3 class="font-semibold">{{ integrationConfigs[integrationsTab].name }}</h3>
              <p class="text-xs text-muted-foreground">{{ integrationConfigs[integrationsTab].tagline }}</p>
            </div>
          </div>
          <p class="mb-4 max-w-3xl text-sm text-foreground">
            {{ integrationConfigs[integrationsTab].description }}
          </p>
          <p class="mb-4 text-xs text-muted-foreground">
            Six auto-provisioned controls, identical shape across every service:
          </p>
          <div class="grid gap-1.5 font-mono text-xs sm:grid-cols-2">
            <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
              [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:total_received]]]
            </div>
            <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
              [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:latest_donor_name]]]
            </div>
            <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
              [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:donations_received]]]
            </div>
            <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
              [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:latest_donation_amount]]]
            </div>
            <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
              [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:latest_donation_message]]]
            </div>
            <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
              [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:latest_donation_currency]]]
            </div>
          </div>
        </div>

        <!-- Shared alert template example -->
        <div class="overflow-hidden rounded-sm">
          <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
            <span class="font-mono text-xs text-muted-foreground">One alert template works for all connected external donation services</span>
          </div>
          <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
            <div>
              <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"donation-alert"</span><span
              class="text-zinc-500">&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;h2&gt;</span><span
              class="text-amber-700 dark:text-amber-400">[[[event.from_name]]]</span><span class="text-foreground"> donated </span><span
              class="text-amber-700 dark:text-amber-400">[[[event.formatted_amount]]]</span><span
              class="text-zinc-500">&lt;/h2&gt;</span>
            </div>
            <div>&nbsp;&nbsp;<span class="text-sky-600 dark:text-sky-400">[[[if:event.message]]]</span></div>
            <div>
              &nbsp;&nbsp;&nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span
              class="text-amber-700 dark:text-amber-400">[[[event.message]]]</span><span class="text-zinc-500">&lt;/p&gt;</span>
            </div>
            <div>&nbsp;&nbsp;<span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span
              class="text-foreground">via </span><span
              class="text-amber-700 dark:text-amber-400">[[[event.source]]]</span><span class="text-zinc-500">&lt;/small&gt;</span>
            </div>
            <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
          </div>
        </div>
        <p class="mt-3 text-sm text-muted-foreground">
          Ko-fi, StreamElements and Streamlabs expose the same normalized event tags. Write your donation alert once and it works for
          both - <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs text-amber-700 dark:text-amber-400">[[[event.source]]]</code>
          tells your overlay which platform it came from.
        </p>

        <!-- The latest() killer feature -->
        <div class="mt-16 border-t border-sidebar-accent pt-16">
          <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">
            No vendor lock-in
          </Badge>
          <h3 class="mb-4 text-2xl font-bold sm:text-3xl">
            Three donation services plus Twitch bits. One <code class="font-mono text-sky-500">latest()</code>.
          </h3>
          <p class="mb-4 max-w-3xl text-lg text-foreground">
            Every other overlay tool on the market is owned by a donation platform. Streamlabs' overlays show Streamlabs donations. StreamElements' overlays show StreamElements donations. Ko-fi's overlays show Ko-fi donations. That's not a bug, it's the business model.
          </p>
          <p class="mb-8 max-w-3xl text-lg text-foreground">
            Overlabels doesn't sell donation ingest, so we don't care which service the money came through. Pass all three donation services plus Twitch bits into a single <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-base text-sky-500">latest()</code> function and you get the actual most-recent supporter across any of your connected revenue streams. <strong>One name, one amount, four pipes.</strong>
          </p>

          <div class="overflow-hidden rounded-sm">
            <div class="flex items-center gap-2 border-b border-sky-500/20 bg-sky-400/10 dark:bg-sky-950/20 px-4 py-2.5">
              <span class="font-mono text-xs text-sky-600 dark:text-sky-400">Two expression controls. The whole cross-service story.</span>
            </div>
            <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
              <div><span class="text-zinc-600 dark:text-zinc-400 italic">// c:latest_donator</span></div>
              <div><span class="text-sky-600 dark:text-sky-400">latest</span><span class="text-zinc-500">(</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.<span class="text-pink-700 dark:text-pink-400">kofi</span>.latest_donor_name<span class="text-green-700 dark:text-green-400">_at</span></span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.kofi.latest_donor_name</span><span class="text-zinc-500">,</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.<span class="text-pink-700 dark:text-pink-400">streamelements</span>.latest_donor_name<span class="text-green-700 dark:text-green-400">_at</span></span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.streamelements.latest_donor_name</span><span class="text-zinc-500">,</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.<span class="text-pink-700 dark:text-pink-400">streamlabs</span>.latest_donor_name<span class="text-green-700 dark:text-green-400">_at</span></span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.streamlabs.latest_donor_name</span><span class="text-zinc-500">,</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.<span class="text-pink-700 dark:text-pink-400">twitch</span>.latest_cheerer_name<span class="text-green-700 dark:text-green-400">_at</span></span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.twitch.latest_cheerer_name</span></div>
              <div><span class="text-zinc-500">)</span></div>
              <div class="mt-4"></div>
              <div><span class="text-zinc-600 dark:text-zinc-400 italic">// c:last_donation_amount</span></div>
              <div><span class="text-sky-600 dark:text-sky-400">latest</span><span class="text-zinc-500">(</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.<span class="text-pink-700 dark:text-pink-400">kofi</span>.latest_donation_amount<span class="text-green-700 dark:text-green-400">_at</span></span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.kofi.latest_donation_amount</span><span class="text-zinc-500">,</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.<span class="text-pink-700 dark:text-pink-400">streamelements</span>.latest_donation_amount<span class="text-green-700 dark:text-green-400">_at</span></span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.streamelements.latest_donation_amount</span><span class="text-zinc-500">,</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.<span class="text-pink-700 dark:text-pink-400">streamlabs</span>.latest_donation_amount<span class="text-green-700 dark:text-green-400">_at</span></span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.streamlabs.latest_donation_amount</span><span class="text-zinc-500">,</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.<span class="text-pink-700 dark:text-pink-400">twitch</span>.latest_cheer_amount<span class="text-green-700 dark:text-green-400">_at</span></span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.twitch.latest_cheer_amount</span></div>
              <div><span class="text-zinc-500">)</span></div>
            </div>
          </div>
          <p class="mt-3  text-sm text-foreground">
            <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-sm text-sky-500">latest()</code> takes pairs of <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs">(timestamp, label)</code> arguments, picks the highest timestamp, and returns its paired label. Every control in Overlabels automatically exposes an <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs"><span class="text-green-700 dark:text-green-400">_at</span></code> companion holding its last-update time in seconds - every timestamp on the platform is normalized that way* - so the same pattern works for totals, counters, or anything else you want to rank by recency. Reactive, so your overlay catches up the instant a new donation lands on any pipe.
          </p>
        </div>

        <!-- Reverse subathon case study -->
        <div class="mt-16 border-t border-sidebar-accent pt-16">
          <Badge class="mb-4 border-sky-500/40 bg-sky-500/10 hover:border-sky-500/40 hover:bg-sky-500/10 px-2.5 py-0.5 text-xs font-semibold text-sky-500">
            Case study
          </Badge>
          <h3 class="mb-4 text-2xl font-bold sm:text-3xl">
            Here's how to do a <span class="text-sky-500">reverse subathon</span>.
          </h3>
          <p class="mb-4 max-w-3xl text-lg text-foreground">
            A reverse subathon is the evil twin of the classic. The clock starts at some big number and every donation <strong>subtracts</strong> time. When it hits zero, the stream ends. It is the chaos engine of audience-participation streaming, and it's three controls and one expression in Overlabels.
          </p>
          <p class="mb-8 max-w-3xl text-lg text-foreground">
            Create three number controls: <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-700 dark:text-amber-400">c.donathon_timer</code> (starting seconds), <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-700 dark:text-amber-400">c.deduction_per_donation</code> (seconds to strip per donation), and <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-700 dark:text-amber-400">c.total_donations</code> (a counter your alert template increments on every donation across every service). Then a single expression control does the rest:
          </p>

          <div class="overflow-hidden rounded-sm">
            <div class="flex items-center gap-2 border-b border-sky-500/20 bg-sky-400/10 dark:bg-sky-950/20 px-4 py-2.5">
              <span class="font-mono text-xs text-sky-600 dark:text-sky-400">One expression. The whole show.</span>
            </div>
            <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
              <div><span class="text-zinc-600 dark:text-zinc-400 italic">// c:time_remaining</span></div>
              <div><span class="text-sky-600 dark:text-sky-400">clamp</span><span class="text-zinc-500">(</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.donathon_timer</span> <span class="text-zinc-500">-</span> <span class="text-zinc-500">(</span><span class="text-amber-700 dark:text-amber-400">c.deduction_per_donation</span> <span class="text-zinc-500">*</span> <span class="text-amber-700 dark:text-amber-400">c.total_donations</span><span class="text-zinc-500">),</span></div>
              <div>&nbsp;&nbsp;<span class="text-emerald-600 dark:text-emerald-400">0</span><span class="text-zinc-500">,</span></div>
              <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.donathon_timer</span></div>
              <div><span class="text-zinc-500">)</span></div>
            </div>
          </div>
          <p class="mt-3 max-w-3xl text-sm text-muted-foreground">
            <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs text-sky-500">clamp()</code> keeps the result between zero and the original timer so the clock can't go negative or somehow inflate. Pipe it through <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs">|duration:hh:mm:ss</code> and you have a broadcast-ready countdown that reacts the instant any donation lands on any service.
          </p>

          <div class="mt-8 max-w-3xl border-l-4 border-sky-500/40 bg-sky-500/5 px-5 py-4">
            <p class="text-sm text-foreground">
              <strong>Btw</strong>: if you want a classic subathon that <em>adds</em> time on every donation, just swap the <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs">-</code> for a <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs">+</code>. That's it. You're welcome &lt;3
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>

</style>
