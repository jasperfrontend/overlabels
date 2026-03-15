<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Why Ko-fi',
    href: '/why-kofi',
  },
];
</script>

<template>
  <Head>
    <title>Why Ko-fi - Overlabels</title>
    <meta
      name="description"
      content="Why Overlabels chose Ko-fi over PayPal for streamer donations. Zero platform fees, guest checkout, instant payouts, and more."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/why-kofi" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Why Ko-fi - Overlabels" />
    <meta
      property="og:description"
      content="Why Overlabels chose Ko-fi over PayPal for streamer donations. Zero platform fees, guest checkout, instant payouts, and more."
    />
    <meta property="og:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - Twitch overlay management" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Why Ko-fi - Overlabels" />
    <meta
      name="twitter:description"
      content="Why Overlabels chose Ko-fi over PayPal for streamer donations. Zero platform fees, guest checkout, instant payouts, and more."
    />
    <meta name="twitter:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - Twitch overlay management" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col items-center gap-8 px-4 py-10">
      <div class="w-full max-w-3xl space-y-10">
        <div class="text-center">
          <h1 class="text-4xl font-extrabold tracking-tight">Overlabels &hearts; Ko-fi</h1>
          <p class="mt-3 text-lg text-muted-foreground">
            Why we chose Ko-fi as our first external integration - and why we think you should use it too.
          </p>
        </div>

        <!-- The pitch -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Zero platform fees on tips</h2>
          <p class="text-muted-foreground">
            Ko-fi's free tier charges <strong class="text-foreground">0% platform fee</strong> on one-time donations.
            That means every cent your viewers send goes to you (minus payment processor fees, which everyone charges).
          </p>
          <p class="text-muted-foreground">
            Compare that to other platforms that take 5-10% off the top before you even see the money.
          </p>
        </section>

        <!-- Fee comparison -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Fee comparison</h2>
          <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
              <thead class="bg-muted text-left text-muted-foreground">
                <tr>
                  <th class="px-4 py-3">Platform</th>
                  <th class="px-4 py-3">Platform fee</th>
                  <th class="px-4 py-3">Payment processing</th>
                  <th class="px-4 py-3">You receive (on $5 tip)</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-t bg-green-500/5">
                  <td class="px-4 py-3 font-semibold">Ko-fi (Free)</td>
                  <td class="px-4 py-3 text-green-600 dark:text-green-400 font-bold">0%</td>
                  <td class="px-4 py-3 text-muted-foreground">~2.9% + $0.30</td>
                  <td class="px-4 py-3 font-semibold">~$4.56</td>
                </tr>
                <tr class="border-t">
                  <td class="px-4 py-3 font-semibold">Ko-fi Gold ($6/mo)</td>
                  <td class="px-4 py-3 text-green-600 dark:text-green-400 font-bold">0%</td>
                  <td class="px-4 py-3 text-muted-foreground">~2.9% + $0.30</td>
                  <td class="px-4 py-3 font-semibold">~$4.56</td>
                </tr>
                <tr class="border-t">
                  <td class="px-4 py-3 font-semibold">PayPal (Goods & Services)</td>
                  <td class="px-4 py-3 text-muted-foreground">0%</td>
                  <td class="px-4 py-3 text-muted-foreground">3.49% + $0.49</td>
                  <td class="px-4 py-3 font-semibold">~$4.34</td>
                </tr>
                <tr class="border-t">
                  <td class="px-4 py-3 font-semibold">PayPal (Friends & Family)</td>
                  <td class="px-4 py-3 text-muted-foreground">0%</td>
                  <td class="px-4 py-3 text-muted-foreground">0% (domestic)</td>
                  <td class="px-4 py-3 font-semibold">$5.00</td>
                </tr>
                <tr class="border-t">
                  <td class="px-4 py-3 font-semibold">StreamElements Tips</td>
                  <td class="px-4 py-3 text-muted-foreground">0%</td>
                  <td class="px-4 py-3 text-muted-foreground">~2.9% + $0.30</td>
                  <td class="px-4 py-3 font-semibold">~$4.56</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="text-sm text-muted-foreground">
            PayPal Friends & Family looks free, but it has no buyer protection, no chargebacks defense, and your
            donors need a PayPal account. International transfers also add fees.
          </p>
        </section>

        <!-- Why not PayPal directly -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Why not just PayPal directly?</h2>
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border p-4 space-y-1">
              <p class="font-semibold text-destructive">Exposes your real name and email</p>
              <p class="text-sm text-muted-foreground">
                PayPal shows your legal name to everyone who sends you money. Ko-fi keeps your identity private.
              </p>
            </div>
            <div class="rounded-lg border p-4 space-y-1">
              <p class="font-semibold text-destructive">Donors need a PayPal account</p>
              <p class="text-sm text-muted-foreground">
                Not everyone has PayPal. Ko-fi supports guest checkout - your viewers can donate with just a card.
              </p>
            </div>
            <div class="rounded-lg border p-4 space-y-1">
              <p class="font-semibold text-destructive">No webhook API</p>
              <p class="text-sm text-muted-foreground">
                PayPal's IPN/webhook system is enterprise-grade complexity. Ko-fi gives you a simple webhook URL
                that just works.
              </p>
            </div>
            <div class="rounded-lg border p-4 space-y-1">
              <p class="font-semibold text-destructive">Chargeback risk on F&F</p>
              <p class="text-sm text-muted-foreground">
                Friends & Family payments have zero seller protection. If someone disputes the charge, you lose.
              </p>
            </div>
          </div>
        </section>

        <!-- Ko-fi advantages -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">What makes Ko-fi great for streamers</h2>
          <div class="space-y-3">
            <div class="flex gap-3 items-start">
              <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-500/20 text-green-600 dark:text-green-400 text-sm font-bold">1</span>
              <div>
                <p class="font-semibold">Guest checkout</p>
                <p class="text-sm text-muted-foreground">Your viewers don't need a Ko-fi account or even a PayPal account. They can donate with any credit or debit card.</p>
              </div>
            </div>
            <div class="flex gap-3 items-start">
              <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-500/20 text-green-600 dark:text-green-400 text-sm font-bold">2</span>
              <div>
                <p class="font-semibold">Multiple payment methods</p>
                <p class="text-sm text-muted-foreground">PayPal, Stripe, Apple Pay, Google Pay - Ko-fi accepts them all. Connect Stripe and your donors don't even need PayPal.</p>
              </div>
            </div>
            <div class="flex gap-3 items-start">
              <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-500/20 text-green-600 dark:text-green-400 text-sm font-bold">3</span>
              <div>
                <p class="font-semibold">Instant payouts</p>
                <p class="text-sm text-muted-foreground">No holding period, no waiting 21 days for your money. PayPal sends it to you immediately through Ko-fi.</p>
              </div>
            </div>
            <div class="flex gap-3 items-start">
              <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-500/20 text-green-600 dark:text-green-400 text-sm font-bold">4</span>
              <div>
                <p class="font-semibold">Your own branded page</p>
                <p class="text-sm text-muted-foreground">A clean, customizable donation page at ko-fi.com/yourname. Way nicer than a raw PayPal.me link.</p>
              </div>
            </div>
            <div class="flex gap-3 items-start">
              <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-500/20 text-green-600 dark:text-green-400 text-sm font-bold">5</span>
              <div>
                <p class="font-semibold">Webhook integration</p>
                <p class="text-sm text-muted-foreground">Ko-fi sends a webhook on every donation - that's how Overlabels fires your alerts and updates your overlay controls in real time.</p>
              </div>
            </div>
            <div class="flex gap-3 items-start">
              <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-500/20 text-green-600 dark:text-green-400 text-sm font-bold">6</span>
              <div>
                <p class="font-semibold">Privacy</p>
                <p class="text-sm text-muted-foreground">Ko-fi never shows your real name or email to donors. Your identity stays private unless you choose to share it.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- CTA -->
        <section class="rounded-lg border bg-sidebar-accent p-6 text-center space-y-3">
          <p class="text-xl font-bold">Ready to connect Ko-fi to your overlays?</p>
          <p class="text-muted-foreground">
            Sign up at
            <a href="https://ko-fi.com" target="_blank" rel="noopener" class="text-violet-400 hover:underline font-medium">ko-fi.com</a>,
            then head to your
            <a href="/settings/integrations/kofi" class="text-violet-400 hover:underline font-medium">Ko-fi integration settings</a>
            to connect.
          </p>
        </section>

        <!-- Overlabels transparency -->
        <section class="rounded-lg border border-green-500/30 bg-green-500/5 p-6 space-y-3">
          <h2 class="text-2xl font-bold text-center">Overlabels takes nothing</h2>
          <p class="text-muted-foreground text-center">
            When your viewers donate through Ko-fi, <strong class="text-foreground">100% of that money goes to you</strong>.
            Overlabels never takes a cut, never skims a percentage, never charges a platform fee. Not now, not ever.
          </p>
          <p class="text-muted-foreground text-center">
            Overlabels itself is <strong class="text-foreground">completely free to use</strong>. No subscription, no premium tier,
            no paywall. We built this because streamers deserve tools that don't nickel-and-dime them.
          </p>
          <p class="text-sm text-muted-foreground text-center">
            The only fees you'll ever see are payment processing fees charged by PayPal or Stripe - and those go to
            PayPal and Stripe, not us. We literally never touch your money.
          </p>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
