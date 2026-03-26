<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { EyeIcon, UserKey, Webhook, TriangleAlertIcon } from 'lucide-vue-next';

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
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <div class="text-left">
          <h1 class="text-4xl font-extrabold tracking-tight">Overlabels &hearts; Ko-fi</h1>
          <p class="mt-4 mb-6 text-lg text-foreground">
            Why we chose Ko-fi as our first external integration - and why we think you should use it too.
          </p>
        </div>

        <!-- The pitch -->
        <section class="space-y-4 mb-12">
          <h2 class="text-2xl font-bold">Zero platform fees on tips</h2>
          <p class="text-muted-foreground">
            Ko-fi's free tier charges <strong class="text-foreground">0% platform fee</strong> on one-time donations.
            That means every cent your viewers send goes to you (minus payment processor fees, which everyone charges).
          </p>
          <p class="text-muted-foreground">
            Compare that to other platforms that take 5-10% off the top before you even see the money.
          </p>
          <p class="text-muted-foreground font-semibold">
            Tip:
            Ko-fi has a setting that may be turned on where you share 5% of your received tips with Ko-fi. You can disable that,
            <a href="https://ko-fi.com/settings?tab=payment#advanced-options-container"
               target="_blank" rel="noopener" class="text-violet-400 hover:underline font-medium">right here</a>. Do keep in mind you will lose
            some platform functionality when you disable this.
          </p>
        </section>

        <!-- Fee comparison -->
        <section class="space-y-4 mb-12">
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
              </tbody>
            </table>
          </div>
          <p class="text-sm text-muted-foreground">
            PayPal Friends & Family looks free, but it has no buyer protection, no chargebacks defense, and your
            donors need a PayPal account. Conversion rates and international transfers also add fees.
          </p>
        </section>

        <!-- Why not PayPal directly -->
        <section class="space-y-4 mb-12">
          <h2 class="text-2xl font-bold">Why not just use PayPal directly?</h2>
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border flex gap-4 items-center p-4 space-y-1">
              <div>
                <EyeIcon class="size-10 text-red-400" />
              </div>
              <div>
                <p class="font-semibold text-destructive">Exposes your real name and email</p>
                <p class="text-sm">
                  PayPal shows your legal name to everyone who sends you money. Ko-fi keeps your identity private.
                </p>
              </div>
            </div>
            <div class="rounded-lg border flex gap-4 items-center p-4 space-y-1">
              <div>
                <UserKey class="size-10 text-red-400" />
              </div>
              <div>
                <p class="font-semibold text-destructive">No guest checkout</p>
                <p class="text-sm">
                  Not everyone has PayPal. Ko-fi supports guest checkout -your viewers can donate with a card.
                </p>
              </div>
            </div>
            <div class="rounded-lg border flex gap-4 items-center p-4 space-y-1">
              <div>
                <Webhook class="size-10 text-red-400" />
              </div>
              <div>
                <p class="font-semibold text-destructive">No webhook API</p>
                <p class="text-sm">
                  PayPal's IPN/webhook system is enterprise-grade complexity. Ko-fi gives you a simple webhook URL
                  that just works.
                </p>
              </div>
            </div>
            <div class="rounded-lg border flex gap-4 items-center p-4 space-y-1">
              <div>
                <TriangleAlertIcon class="size-10 text-red-400" />
              </div>
              <div>
                <p class="font-semibold text-destructive">Chargeback risk on F&F</p>
                <p class="text-sm">
                  Friends & Family payments have zero seller protection. If someone disputes the donation transaction, you will always lose.
                </p>
              </div>
            </div>
          </div>
        </section>

        <!-- Ko-fi advantages -->
        <section class="space-y-4 mb-12">
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
        <section class="rounded-lg border bg-sidebar-accent p-6 text-center space-y-3 mb-6">
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
        <section class="rounded-lg border border-violet-500/30 bg-violet-500/5 p-6 space-y-5">
          <h2 class="text-2xl font-bold">Overlabels takes nothing. Ko-fi integration is 100% free</h2>
          <p class="text-foreground">
            When your viewers donate through Ko-fi, <strong class="text-foreground">100% of that money goes to you</strong>.
            Overlabels never takes a cut and never charges a platform fee.
          </p>
          <p class="text-sm text-foreground">
            The only fees you'll ever see are payment processing fees charged by PayPal or Stripe, and those go directly to them, not us.
            We never handle your money, and we have no access to your Ko-fi account or its settings.
          </p>
          <p class="text-sm text-foreground">
            When a donation comes in, we save only three things: the donor's username, the amount, and their message (if they wrote one).
            No email addresses, no transaction IDs. Just enough to show the alert, nothing more.
          </p>
          <p class="text-sm text-foreground">
            So why is it free?<br>
            Honestly, because it wasn't hard to build. Ko-fi's donation notifications are a single, lightweight webhook --
            integrating it took about an hour. We're not gatekeeping a simple feature behind a subscription just because we can.
          </p>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
