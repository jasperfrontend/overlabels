<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Help',
    href: '/help'
  },
  {
    title: 'Formatting',
    href: '/help/formatting'
  }
];
</script>

<template>
  <Head>
    <title>Formatting Pipes - Overlabels</title>
    <meta
      name="description"
      content="Learn how to format numbers, durations, currencies, and dates in your Twitch overlays using pipe syntax. Zero dependencies, fully locale-aware."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/formatting" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Formatting Pipes - Overlabels" />
    <meta
      property="og:description"
      content="Learn how to format numbers, durations, currencies, and dates in your Twitch overlays using pipe syntax. Zero dependencies, fully locale-aware."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Formatting Pipes - Overlabels" />
    <meta
      name="twitter:description"
      content="Learn how to format numbers, durations, currencies, and dates in your Twitch overlays using pipe syntax. Zero dependencies, fully locale-aware."
    />
    <meta name="twitter:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <!-- Header -->
        <div class="mb-8">
          <h1 class="mb-4 text-4xl font-bold">Formatting Pipes</h1>
          <p class="text-lg text-foreground mb-4">
            Pipes let you format raw values directly inside your template tags. Append a pipe character and a formatter
            name to any tag,
            and the overlay renders the formatted result instead of the raw value.
          </p>
          <p class="text-lg text-foreground">
            No JavaScript required, no external libraries, no build step. Just add a pipe to your tag and you are done.
          </p>
        </div>

        <!-- Table of Contents -->
        <div class="mb-12">
          <h2 class="mb-6 text-2xl font-bold" id="toc">Table of Contents</h2>
          <ul class="space-y-2">
            <li><a href="#syntax" class="text-violet-400 hover:underline">Pipe Syntax</a></li>
            <li><a href="#round" class="text-violet-400 hover:underline">round - Round Numbers</a></li>
            <li><a href="#number" class="text-violet-400 hover:underline">number - Locale-Aware Numbers</a></li>
            <li><a href="#currency" class="text-violet-400 hover:underline">currency - Currency Formatting</a></li>
            <li><a href="#duration" class="text-violet-400 hover:underline">duration - Time Durations</a></li>
            <li><a href="#date" class="text-violet-400 hover:underline">date - Date Formatting</a></li>
            <li><a href="#text" class="text-violet-400 hover:underline">uppercase / lowercase - Text Transforms</a></li>
            <li><a href="#locale" class="text-violet-400 hover:underline">Locale Settings</a></li>
            <li><a href="#css" class="text-violet-400 hover:underline">Pipes in CSS</a></li>
            <li><a href="#tips" class="text-violet-400 hover:underline">Tips</a></li>
          </ul>
        </div>

        <!-- Pipe Syntax -->
        <div class="mb-12" id="syntax">
          <h2 class="mb-6 text-2xl font-bold">Pipe Syntax</h2>

          <div class="space-y-6">
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <p class="mb-4 text-foreground">
                Add a pipe <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">|</code> after any tag
                name,
                followed by the formatter. Some formatters accept arguments after a colon.
              </p>
              <div class="space-y-3">
                <div class="rounded bg-background p-4 font-mono text-sm leading-relaxed">
                  <div class="text-muted-foreground">Without formatting:</div>
                  <div>[[[c:score]]]</div>
                  <div class="mt-3 text-muted-foreground">With a formatter:</div>
                  <div>[[[c:score|round]]]</div>
                  <div class="mt-3 text-muted-foreground">With a formatter and arguments:</div>
                  <div>[[[c:timer|duration:hh:mm:ss]]]</div>
                </div>
              </div>
              <p class="mt-4 text-sm text-foreground">
                The pipe is stripped before the tag is resolved. A tag like <code
                class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:score|round]]]</code>
                still reads the value of the <code
                class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">score</code> control - the pipe only
                affects how it is displayed.
              </p>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Works with all tag types</h3>
              <p class="mb-4 text-foreground">
                Pipes work on any template tag - controls, Twitch data, Ko-fi data, StreamLabs data, event data.
                Anything between
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[</code> and
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">]]]</code> can have a pipe.
              </p>
              <div class="rounded bg-background p-4 font-mono text-sm leading-relaxed">
                <div class="text-muted-foreground">Control value:</div>
                <div>[[[c:amount|currency:EUR]]]</div>
                <div class="mt-3 text-muted-foreground">Ko-fi integration data:</div>
                <div>[[[c:kofi:latest_donation_amount|currency]]]</div>
                <div class="mt-3 text-muted-foreground">Event data in alerts:</div>
                <div>[[[event.user_name|uppercase]]]</div>
              </div>
            </div>
          </div>
        </div>

        <!-- round -->
        <div class="mb-12" id="round">
          <h2 class="mb-6 text-2xl font-bold">
            <code class="rounded bg-background px-2 py-1 font-mono text-2xl">|round</code>
            <span class="ml-2 text-lg font-normal text-muted-foreground">Round numbers</span>
          </h2>

          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <p class="mb-4 text-foreground">
              Rounds a numeric value. Without arguments, rounds to a whole number. Pass a number to control decimal
              places.
            </p>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                <tr class="border-b border-sidebar text-left">
                  <th class="pb-2 pr-4 font-semibold">Tag</th>
                  <th class="pb-2 pr-4 font-semibold">Raw value</th>
                  <th class="pb-2 font-semibold">Output</th>
                </tr>
                </thead>
                <tbody class="font-mono">
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4">[[[c:score|round]]]</td>
                  <td class="py-2 pr-4">42.789</td>
                  <td class="py-2">43</td>
                </tr>
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4">[[[c:score|round:1]]]</td>
                  <td class="py-2 pr-4">42.789</td>
                  <td class="py-2">42.8</td>
                </tr>
                <tr>
                  <td class="py-2 pr-4">[[[c:score|round:2]]]</td>
                  <td class="py-2 pr-4">42.789</td>
                  <td class="py-2">42.79</td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- number -->
        <div class="mb-12" id="number">
          <h2 class="mb-6 text-2xl font-bold">
            <code class="rounded bg-background px-2 py-1 font-mono text-2xl">|number</code>
            <span class="ml-2 text-lg font-normal text-muted-foreground">Locale-aware numbers</span>
          </h2>

          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <p class="mb-4 text-foreground">
              Formats a number with thousands separators and decimal notation based on your
              <a href="#locale" class="text-violet-400 hover:underline">locale setting</a>.
              Optionally pass a number to fix decimal places.
            </p>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                <tr class="border-b border-sidebar text-left">
                  <th class="pb-2 pr-4 font-semibold">Tag</th>
                  <th class="pb-2 pr-4 font-semibold">Raw value</th>
                  <th class="pb-2 pr-4 font-semibold">en-US</th>
                  <th class="pb-2 font-semibold">nl-NL</th>
                </tr>
                </thead>
                <tbody class="font-mono">
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4">[[[c:viewers|number]]]</td>
                  <td class="py-2 pr-4">1234567</td>
                  <td class="py-2 pr-4">1,234,567</td>
                  <td class="py-2">1.234.567</td>
                </tr>
                <tr>
                  <td class="py-2 pr-4">[[[c:ratio|number:2]]]</td>
                  <td class="py-2 pr-4">3.5</td>
                  <td class="py-2 pr-4">3.50</td>
                  <td class="py-2">3,50</td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- currency -->
        <div class="mb-12" id="currency">
          <h2 class="mb-6 text-2xl font-bold">
            <code class="rounded bg-background px-2 py-1 font-mono text-2xl">|currency</code>
            <span class="ml-2 text-lg font-normal text-muted-foreground">Currency formatting</span>
          </h2>

          <div class="space-y-6">
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <p class="mb-4 text-foreground">
                Formats a number as a currency value with the proper symbol, decimal places, and separators for your
                locale.
              </p>
              <p class="mb-4 text-foreground">
                Without arguments, the currency is determined by your locale (EUR for Dutch, GBP for British, USD for
                American, etc.).
                Pass a three-letter <a href="https://en.wikipedia.org/wiki/ISO_4217" target="_blank"
                                       class="text-violet-400 hover:underline">ISO 4217</a> currency code to override.
              </p>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                  <tr class="border-b border-sidebar text-left">
                    <th class="pb-2 pr-4 font-semibold">Tag</th>
                    <th class="pb-2 pr-4 font-semibold">Raw value</th>
                    <th class="pb-2 pr-4 font-semibold">en-US</th>
                    <th class="pb-2 font-semibold">nl-NL</th>
                  </tr>
                  </thead>
                  <tbody class="font-mono">
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:goal|currency]]]</td>
                    <td class="py-2 pr-4">42.5</td>
                    <td class="py-2 pr-4">$42.50</td>
                    <td class="py-2">&euro; 42,50</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:goal|currency:EUR]]]</td>
                    <td class="py-2 pr-4">42.5</td>
                    <td class="py-2 pr-4">&euro;42.50</td>
                    <td class="py-2">&euro; 42,50</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">[[[c:goal|currency:JPY]]]</td>
                    <td class="py-2 pr-4">4250</td>
                    <td class="py-2 pr-4">&yen;4,250</td>
                    <td class="py-2">JP&yen; 4.250</td>
                  </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-6">
              <p class="text-foreground">
                <strong>Tip:</strong> If your streaming currency differs from your locale's default, just pass the code
                explicitly.
                A Dutch streamer who receives USD donations can use
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:kofi:latest_donation_amount|currency:USD]]]</code>
                and the number formatting will still respect the Dutch locale (period for thousands, comma for
                decimals).
              </p>
            </div>
          </div>
        </div>

        <!-- duration -->
        <div class="mb-12" id="duration">
          <h2 class="mb-6 text-2xl font-bold">
            <code class="rounded bg-background px-2 py-1 font-mono text-2xl">|duration</code>
            <span class="ml-2 text-lg font-normal text-muted-foreground">Time durations</span>
          </h2>

          <div class="space-y-6">
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Auto-format (no arguments)</h3>
              <p class="mb-4 text-foreground">
                Without arguments, the duration formatter picks the most readable format based on how large the value
                is.
                The input is always in <strong>seconds</strong>.
              </p>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                  <tr class="border-b border-sidebar text-left">
                    <th class="pb-2 pr-4 font-semibold">Raw seconds</th>
                    <th class="pb-2 font-semibold">Output</th>
                  </tr>
                  </thead>
                  <tbody class="font-mono">
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">45</td>
                    <td class="py-2">0:45</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">754</td>
                    <td class="py-2">12:34</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">8107</td>
                    <td class="py-2">2:15:07</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">93907</td>
                    <td class="py-2">1d 2h 5m</td>
                  </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Explicit patterns</h3>
              <p class="mb-4 text-foreground">
                Pass a pattern using <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">dd</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">hh</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">mm</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">ss</code> tokens.
                Time overflows into the largest unit in your pattern.
              </p>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                  <tr class="border-b border-sidebar text-left">
                    <th class="pb-2 pr-4 font-semibold">Tag</th>
                    <th class="pb-2 pr-4 font-semibold">Seconds</th>
                    <th class="pb-2 font-semibold">Output</th>
                  </tr>
                  </thead>
                  <tbody class="font-mono">
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:timer|duration:hh:mm:ss]]]</td>
                    <td class="py-2 pr-4">8107</td>
                    <td class="py-2">02:15:07</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:timer|duration:mm:ss]]]</td>
                    <td class="py-2 pr-4">8107</td>
                    <td class="py-2">135:07</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:timer|duration:dd:hh:mm:ss]]]</td>
                    <td class="py-2 pr-4">93907</td>
                    <td class="py-2">01:02:05:07</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">[[[c:timer|duration:mm:ss]]]</td>
                    <td class="py-2 pr-4">45</td>
                    <td class="py-2">00:45</td>
                  </tr>
                  </tbody>
                </table>
              </div>
              <p class="mt-4 text-sm text-foreground">
                The overflow rule means <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">mm:ss</code>
                with 8107 seconds gives you <code
                class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">135:07</code>,
                not <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">15:07</code>.
                The hours spill into the minutes because there is no <code
                class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">hh</code> in the pattern.
              </p>
            </div>

            <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-6">
              <p class="text-foreground">
                <strong>Tip:</strong> Negative values (like a countdown past zero) are supported. The output will be
                prefixed with
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">-</code>, e.g. <code
                class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">-02:15</code>.
              </p>
            </div>
          </div>
        </div>

        <!-- date -->
        <div class="mb-12" id="date">
          <h2 class="mb-6 text-2xl font-bold">
            <code class="rounded bg-background px-2 py-1 font-mono text-2xl">|date</code>
            <span class="ml-2 text-lg font-normal text-muted-foreground">Date formatting</span>
          </h2>

          <div class="space-y-6">
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <p class="mb-4 text-foreground">
                Formats a date or datetime string. Without arguments, shows a locale-aware date and time.
                Use a named preset or a custom pattern to control the exact output.
              </p>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                  <tr class="border-b border-sidebar text-left">
                    <th class="pb-2 pr-4 font-semibold">Tag</th>
                    <th class="pb-2 pr-4 font-semibold">en-US</th>
                    <th class="pb-2 font-semibold">nl-NL</th>
                  </tr>
                  </thead>
                  <tbody class="font-mono">
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:event_date|date]]]</td>
                    <td class="py-2 pr-4">Apr 5, 2026, 7:00 PM</td>
                    <td class="py-2">5 apr 2026, 19:00</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:event_date|date:short]]]</td>
                    <td class="py-2 pr-4">Apr 5, 7:00 PM</td>
                    <td class="py-2">5 apr, 19:00</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:event_date|date:long]]]</td>
                    <td class="py-2 pr-4">Saturday, April 5, 2026, 7:00 PM</td>
                    <td class="py-2">zaterdag 5 april 2026, 19:00</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:event_date|date:date]]]</td>
                    <td class="py-2 pr-4">Apr 5, 2026</td>
                    <td class="py-2">5 apr 2026</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:event_date|date:time]]]</td>
                    <td class="py-2 pr-4">7:00:00 PM</td>
                    <td class="py-2">19:00:00</td>
                  </tr>
                  <tr class="border-b border-sidebar/50">
                    <td class="py-2 pr-4">[[[c:event_date|date:dd-MM-yyyy]]]</td>
                    <td class="py-2 pr-4" colspan="2">05-04-2026</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">[[[c:event_date|date:dd-MM-yyyy HH:mm]]]</td>
                    <td class="py-2 pr-4" colspan="2">05-04-2026 19:00</td>
                  </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Available tokens</h3>
              <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                  <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">yyyy</code>
                  <span class="ml-2 text-sm text-muted-foreground">Full year</span>
                </div>
                <div>
                  <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">MM</code>
                  <span class="ml-2 text-sm text-muted-foreground">Month (01-12)</span>
                </div>
                <div>
                  <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">dd</code>
                  <span class="ml-2 text-sm text-muted-foreground">Day (01-31)</span>
                </div>
                <div>
                  <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">HH</code>
                  <span class="ml-2 text-sm text-muted-foreground">Hours (00-23)</span>
                </div>
                <div>
                  <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">mm</code>
                  <span class="ml-2 text-sm text-muted-foreground">Minutes (00-59)</span>
                </div>
                <div>
                  <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">ss</code>
                  <span class="ml-2 text-sm text-muted-foreground">Seconds (00-59)</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- uppercase / lowercase -->
        <div class="mb-12" id="text">
          <h2 class="mb-6 text-2xl font-bold">
            <code class="rounded bg-background px-2 py-1 font-mono text-2xl">|uppercase</code>
            <code class="ml-2 rounded bg-background px-2 py-1 font-mono text-2xl">|lowercase</code>
            <span class="ml-2 text-lg font-normal text-muted-foreground">Text transforms</span>
          </h2>

          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <p class="mb-4 text-foreground">
              Simple text case transformations. No arguments needed.
            </p>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                <tr class="border-b border-sidebar text-left">
                  <th class="pb-2 pr-4 font-semibold">Tag</th>
                  <th class="pb-2 pr-4 font-semibold">Raw value</th>
                  <th class="pb-2 font-semibold">Output</th>
                </tr>
                </thead>
                <tbody class="font-mono">
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4">[[[event.user_name|uppercase]]]</td>
                  <td class="py-2 pr-4">NightBot</td>
                  <td class="py-2">NIGHTBOT</td>
                </tr>
                <tr>
                  <td class="py-2 pr-4">[[[event.user_name|lowercase]]]</td>
                  <td class="py-2 pr-4">NightBot</td>
                  <td class="py-2">nightbot</td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Locale -->
        <div class="mb-12" id="locale">
          <h2 class="mb-6 text-2xl font-bold">Locale Settings</h2>

          <div class="space-y-6">
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <p class="mb-4 text-foreground">
                The <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">|number</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">|currency</code>, and
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">|date</code> formatters are all
                locale-aware.
                Your locale controls things like:
              </p>
              <ul class="list-disc pl-6 space-y-2 text-foreground">
                <li>Thousands separator (comma vs period vs space)</li>
                <li>Decimal separator (period vs comma)</li>
                <li>Currency symbol and position</li>
                <li>Date month names and ordering</li>
                <li>Default currency code (USD for en-US, EUR for nl-NL, etc.)</li>
              </ul>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Changing your locale</h3>
              <p class="mb-4 text-foreground">
                Go to
                <Link href="/settings/appearance" class="text-violet-400 hover:underline">Settings &gt; Appearance
                </Link>
                and pick your locale from the dropdown. You will see a live preview of how numbers, currencies, and
                dates
                will look in your overlays.
              </p>
              <p class="text-foreground">
                The locale is applied to all your overlays automatically. Your viewers do not need to do anything.
              </p>
            </div>
          </div>
        </div>

        <!-- Pipes in CSS -->
        <div class="mb-12" id="css">
          <h2 class="mb-6 text-2xl font-bold">Pipes in CSS</h2>

          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <p class="mb-4 text-foreground">
              Pipes work in CSS too - anywhere you can use a template tag, you can pipe it.
            </p>
            <div class="rounded bg-background p-4 font-mono text-sm leading-relaxed">
              &lt;style&gt;<br />
              &nbsp;&nbsp;.timer &#123;<br />
              &nbsp;&nbsp;&nbsp;&nbsp;/* Hides when timer hits 00:00 */<br />
              &nbsp;&nbsp;&nbsp;&nbsp;content: '[[[c:round_timer|duration:mm:ss]]]';<br />
              &nbsp;&nbsp;&#125;<br />
              &nbsp;&nbsp;.donation &#123;<br />
              &nbsp;&nbsp;&nbsp;&nbsp;content: '[[[c:kofi:latest_donation_amount|currency]]]';<br />
              &nbsp;&nbsp;&#125;<br />
              &lt;/style&gt;
            </div>
          </div>
        </div>

        <!-- Tips -->
        <div class="mb-12" id="tips">
          <h2 class="mb-6 text-2xl font-bold">Tips</h2>

          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <div class="space-y-8 text-foreground">
              <div>
                <h3 class="text-xl mb-3">One pipe per tag</h3>
                <p>
                  Each tag supports exactly one pipe. You cannot chain multiple formatters like
                  <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:score|round|number]]]</code>.
                  Pick the one formatter that gets you closest to what you need.
                </p>
              </div>
              <div>
                <h3 class="text-xl mb-3">Pipes are display-only</h3>
                <p>
                  Formatting never changes the stored value. Your control still holds the raw number - the pipe just
                  changes
                  how the overlay renders it. Two tags referencing the same control with different pipes will display
                  differently
                  but read the same underlying value.
                </p>
              </div>
              <div>
                <h3 class="text-xl mb-3">Unknown formatters are ignored</h3>
                <p>
                  If you typo a formatter name, the value is displayed as-is. No errors, no blank output - just the raw
                  value.
                  Check your spelling if formatting does not seem to apply.
                </p>
              </div>
              <div>
                <h3 class="text-xl mb-3">Duration expects seconds</h3>
                <p>
                  The <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">|duration</code> formatter
                  always expects the
                  raw value to be in seconds. Timer controls already output seconds, so they work perfectly. If you are
                  feeding in a custom
                  value, make sure it is seconds.
                </p>
              </div>
              <div>
                <h3 class="text-xl mb-3">Same tag, different format</h3>
                <p class="mb-3">
                  You can reference the same control multiple times with different pipes. This is useful for showing the
                  same value in
                  different formats.
                </p>
                <div class="rounded bg-background p-4 font-mono text-sm leading-relaxed">
                  &lt;div class="timer-display"&gt;[[[c:timer|duration:hh:mm:ss]]]&lt;/div&gt;<br />
                  &lt;div class="timer-raw"&gt;[[[c:timer]]] seconds&lt;/div&gt;
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick reference -->
        <div class="mb-12">
          <h2 class="mb-6 text-2xl font-bold">Quick Reference</h2>

          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                <tr class="border-b border-sidebar text-left">
                  <th class="pb-2 pr-4 font-semibold">Pipe</th>
                  <th class="pb-2 pr-4 font-semibold">Arguments</th>
                  <th class="pb-2 font-semibold">Description</th>
                </tr>
                </thead>
                <tbody>
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4 font-mono">|round</td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">N (decimal places)</td>
                  <td class="py-2">Round to N decimals (default: 0)</td>
                </tr>
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4 font-mono">|number</td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">N (decimal places)</td>
                  <td class="py-2">Locale-aware thousands separators</td>
                </tr>
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4 font-mono">|currency</td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">CODE (e.g. EUR, GBP)</td>
                  <td class="py-2">Locale-aware currency with symbol</td>
                </tr>
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4 font-mono">|duration</td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">pattern (hh:mm:ss, mm:ss, etc.)</td>
                  <td class="py-2">Seconds to human-readable time</td>
                </tr>
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4 font-mono">|date</td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">short, long, date, time, or pattern</td>
                  <td class="py-2">Locale-aware date + time formatting</td>
                </tr>
                <tr class="border-b border-sidebar/50">
                  <td class="py-2 pr-4 font-mono">|uppercase</td>
                  <td class="py-2 pr-4 text-muted-foreground">-</td>
                  <td class="py-2">ALL CAPS</td>
                </tr>
                <tr>
                  <td class="py-2 pr-4 font-mono">|lowercase</td>
                  <td class="py-2 pr-4 text-muted-foreground">-</td>
                  <td class="py-2">all lowercase</td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <h2 class="mb-6 text-2xl font-bold" id="help">More help</h2>
        <p class="mb-6 text-foreground">
          See the
          <Link href="/help/conditionals" class="text-violet-400 hover:underline">Conditional Tags</Link>
          and
          <Link href="/help/controls" class="text-violet-400 hover:underline">Controls</Link>
          guides for more on
          how template tags and controls work. If you are stuck,
          <a href="mailto:jasper@emailjasper.com" class="text-violet-400 hover:underline">jasper@emailjasper.com</a>
          or <a href="https://github.com/jasperfrontend/overlabels/issues" target="_blank"
                class="text-violet-400 hover:underline">open an issue</a> on
          <a href="https://github.com/jasperfrontend/overlabels" target="_blank"
             class="text-violet-400 hover:underline">GitHub</a>.
        </p>

      </div>
    </div>
  </AppLayout>
</template>
