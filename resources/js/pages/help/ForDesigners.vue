<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'For designers', href: '/help/for-designers' },
];
</script>

<template>
  <Head>
    <title>Overlabels for Designers - what to deliver, what to avoid</title>
    <meta
      name="description"
      content="A handoff guide for designers working on Twitch overlays in Overlabels. The two surfaces (static + alert), the unknown-background problem, variable-length content, fluid layout, CSS animation constraints, and a concrete deliverables checklist."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/for-designers" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Overlabels for Designers - what to deliver, what to avoid" />
    <meta
      property="og:description"
      content="A handoff guide for designers working on Twitch overlays. The two surfaces, the unknown-background problem, variable-length content, fluid layout, CSS animation, and a deliverables checklist."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Overlabels for Designers - what to deliver, what to avoid" />
    <meta
      name="twitter:description"
      content="The two surfaces, the unknown-background problem, variable-length content, fluid layout, CSS animation, and a deliverables checklist."
    />
    <meta name="twitter:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">

        <!-- Header -->
        <div class="mb-10">
          <h1 class="mb-4 text-4xl font-bold">Overlabels for Designers</h1>
          <p class="mb-3 text-lg text-foreground">
            This page is for the designer working on a Twitch overlay that will be implemented in Overlabels, and for
            the streamer who hired them and is wondering what to actually ask for.
          </p>
          <p class="text-lg text-foreground">
            Overlay design has constraints that web and product design don't usually train for: the background is
            literally a video game, an IRL camera walking through a sunlit park, a cooking stream, or a moving
            cycling shot. Every text field is variable-length, and every animation has to be expressible in CSS.
            Mockups that look pristine in Figma can fall apart the second a username goes from "Jasper" to
            "xX_LongUsername2024_Xx", or the streamer switches from a dark dungeon to a blown-out outdoor scene where
            the horizon is pure white. This page is the pre-flight checklist.
          </p>
        </div>

        <!-- TOC -->
        <div class="mb-12 rounded-lg border border-sidebar bg-sidebar-accent p-6">
          <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
          <ol class="list-decimal space-y-1 pl-6 text-foreground">
            <li><a href="#two-surfaces" class="text-violet-400 hover:underline">The two surfaces: static and alert</a></li>
            <li><a href="#background" class="text-violet-400 hover:underline">The background is unknown</a></li>
            <li><a href="#variable-content" class="text-violet-400 hover:underline">Every text field is variable-length</a></li>
            <li><a href="#fluid-layout" class="text-violet-400 hover:underline">Fluid layout, not pixel-perfect</a></li>
            <li><a href="#animation" class="text-violet-400 hover:underline">Animation lives in CSS</a></li>
            <li><a href="#deliverables" class="text-violet-400 hover:underline">What to deliver</a></li>
            <li><a href="#avoid" class="text-violet-400 hover:underline">What not to deliver</a></li>
            <li><a href="#handoff" class="text-violet-400 hover:underline">Working with the implementer</a></li>
            <li><a href="#deep-dives" class="text-violet-400 hover:underline">Deep dives</a></li>
          </ol>
        </div>

        <!-- 1. Two surfaces -->
        <section class="mb-14" id="two-surfaces">
          <h2 class="mb-4 text-2xl font-bold">1. The two surfaces: static and alert</h2>
          <p class="mb-4 text-foreground">
            An Overlabels overlay is two distinct surfaces, with different constraints. Designing them as one thing
            is the most common mistake.
          </p>

          <div class="mb-4 rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h3 class="mb-2 text-xl font-semibold">Static overlay</h3>
            <p class="mb-2 text-foreground">
              The always-on layer. Camera frames, follower counters, donation goals, current game or location, recent
              supporter, GPS speed, subathon timer. It sits on the screen for hours. Live values mutate inside it.
            </p>
            <p class="text-foreground">
              <strong>Design constraint:</strong> nothing in the static overlay should ever distract from what the
              streamer is actually doing - whether that's playing a game, walking through a city on an IRL stream,
              cooking, or just chatting on a webcam. No looping animations that pull the eye. No flashing. No
              high-contrast motion at the edges of the safe area. Subtle drift, breathing, or pulse-on-event is fine.
              A 4-second loop that draws attention every 4 seconds for 6 hours is not.
            </p>
          </div>

          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h3 class="mb-2 text-xl font-semibold">Alert overlay</h3>
            <p class="mb-2 text-foreground">
              The one-shot layer. Fires when an event arrives (a follow, a sub, a raid, a Ko-fi donation). Animates in,
              holds for a few seconds with the event data on screen, animates out, vanishes.
            </p>
            <p class="text-foreground">
              <strong>Design constraint:</strong> alerts are <em>supposed</em> to draw attention. They have a lifecycle
              (enter, hold, exit) and a duration (typically 4-8 seconds total). The hold phase needs to be readable in
              a second or two by a viewer who looks up at it after the streamer reacts. Animation can be loud; copy
              cannot be wordy.
            </p>
          </div>
        </section>

        <!-- 2. Background -->
        <section class="mb-14" id="background">
          <h2 class="mb-4 text-2xl font-bold">2. The background is unknown</h2>
          <p class="mb-4 text-foreground">
            The overlay sits on top of whatever the streamer is showing - dark dungeon, sunlit IRL street, white
            starting-soon screen, a cycling horizon that swings between pavement and overblown sky. A design that
            pops against gameplay can fall apart mid-broadcast when the camera steps into noon sun.
          </p>
          <p class="mb-3 text-foreground">Standard strategies for "readable on any background":</p>
          <ul class="mb-4 list-disc space-y-1 pl-6 text-foreground">
            <li><strong>Contrast plates</strong> - semi-transparent dark or blurred panel behind text. Most common professional move.</li>
            <li><strong>Text stroke</strong> - 2-3px outline. Cheap, slightly ugly at small sizes.</li>
            <li><strong>Drop shadow</strong> - soft, 8-16px blur, low opacity. Lifts text off anything.</li>
            <li>
              <strong>Backdrop blur</strong> -
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">backdrop-filter: blur(8px)</code>
              behind a tinted panel. Modern, expensive on low-end GPUs.
            </li>
          </ul>
          <p class="text-foreground">
            <strong>Designer deliverable:</strong> mock against four backgrounds - dark game, sunlit-overblown IRL,
            night-time IRL, and pure white. If the design holds in all four, ship.
          </p>
        </section>

        <!-- 3. Variable content -->
        <section class="mb-14" id="variable-content">
          <h2 class="mb-4 text-2xl font-bold">3. Every text field is variable-length</h2>
          <p class="mb-4 text-foreground">
            Live data fields are not fixed-width. A username can be 3 characters or 25. A donation amount can be $1 or
            $1,000. A donation message can be empty or 200 characters of emoji and exclamation marks. A follower count
            can be 12 or 12,000,000. The same overlay slot has to accommodate all of these without breaking.
          </p>

          <div class="mb-6 overflow-x-auto rounded-lg border border-sidebar bg-sidebar-accent">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold">Field</th>
                  <th class="p-3 font-semibold">Realistic short</th>
                  <th class="p-3 font-semibold">Realistic medium</th>
                  <th class="p-3 font-semibold">Realistic worst-case</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3 text-foreground">Twitch username</td>
                  <td class="p-3 text-foreground">an</td>
                  <td class="p-3 text-foreground">JasperDiscovers</td>
                  <td class="p-3 text-foreground">xX_DragonSlayer2024_Xx (25 chars max)</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">Donation amount</td>
                  <td class="p-3 text-foreground">$1</td>
                  <td class="p-3 text-foreground">$25</td>
                  <td class="p-3 text-foreground">$1,234,567</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">Donation message</td>
                  <td class="p-3 text-foreground">(empty)</td>
                  <td class="p-3 text-foreground">"Love the stream!"</td>
                  <td class="p-3 text-foreground">200 chars of mixed text and emoji</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">Follower count</td>
                  <td class="p-3 text-foreground">12</td>
                  <td class="p-3 text-foreground">8,432</td>
                  <td class="p-3 text-foreground">12,847,392</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">Game / category title</td>
                  <td class="p-3 text-foreground">Doom</td>
                  <td class="p-3 text-foreground">Elden Ring, or "Just Chatting"</td>
                  <td class="p-3 text-foreground">Tom Clancy's Rainbow Six Siege Extraction, or "Travel &amp; Outdoors"</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">GPS speed</td>
                  <td class="p-3 text-foreground">0 km/h</td>
                  <td class="p-3 text-foreground">42 km/h</td>
                  <td class="p-3 text-foreground">217 km/h (or m/s with three decimals)</td>
                </tr>
              </tbody>
            </table>
          </div>

          <p class="mb-4 text-foreground">
            <strong>Strategies:</strong>
          </p>
          <ul class="list-disc space-y-2 pl-6 text-foreground">
            <li><strong>Truncate with ellipsis</strong> for fields that have a hard layout slot (donor message in a 1-line alert).</li>
            <li><strong>Allow vertical growth</strong> for fields that should never be cut (donation message, raid greeting). Design the panel to expand downward.</li>
            <li><strong>Right-align numbers</strong> so the digit count visually grows leftward into space you reserved.</li>
            <li><strong>Auto-shrink font size</strong> for hero text fields (subathon timer, big counter) where the value can grow by orders of magnitude.</li>
            <li><strong>Test against the worst case.</strong> Mock up the alert with the longest realistic donor name and message. If it survives, ship.</li>
          </ul>
        </section>

        <!-- 4. Fluid layout -->
        <section class="mb-14" id="fluid-layout">
          <h2 class="mb-4 text-2xl font-bold">4. Fluid layout, not pixel-perfect</h2>
          <p class="mb-4 text-foreground">
            The reference resolution is 1920x1080. But OBS scales browser sources, streamers run different DPI, and a
            "fits perfectly at exactly 1920" design tends to look fragile at 1280 or wrong at 2560. Design in a way
            that survives:
          </p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li>
              <strong>Think in flex and grid, not absolute pixels.</strong> Mock at 1920x1080 but specify spacing as
              ratios or rems where it matters ("16px gap between item and label" rather than "label at x=842").
            </li>
            <li>
              <strong>Anchor to corners, not coordinates.</strong> "Top-right, 32px from the edges" implements
              cleanly. "x=1856, y=32" implies pixel-positioning that doesn't survive scaling.
            </li>
            <li>
              <strong>Use SVGs and vector decoration.</strong> A raster decoration at 1920 looks fuzzy when OBS scales
              the source to 2560. SVG stays crisp.
            </li>
            <li>
              <strong>Set explicit safe areas.</strong> Twitch overlays the chat sidebar on theater mode (and Twitch
              streamers' webcams often live in known zones). Identify safe areas in the design and avoid putting
              critical info in them.
            </li>
          </ul>
        </section>

        <!-- 5. Animation -->
        <section class="mb-14" id="animation">
          <h2 class="mb-4 text-2xl font-bold">5. Animation lives in CSS</h2>
          <p class="mb-4 text-foreground">
            Overlabels overlays don't run JavaScript (this is a deliberate security and shareability decision -
            see <Link href="/help/for-creators#constraint" class="text-violet-400 hover:underline">"The constraint is the feature"</Link>
            on the For Creators page). All animation runs through CSS keyframes, transitions, and transforms. That has
            consequences for what a designer can spec.
          </p>

          <p class="mb-3 text-foreground"><strong>Things CSS does well:</strong></p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li>Transforms (translate, scale, rotate, skew) - GPU-accelerated, butter-smooth</li>
            <li>Opacity fades, color transitions, blur</li>
            <li>Keyframed loops with custom easing curves (cubic-bezier)</li>
            <li>Stagger via animation-delay, mid-anim pauses via easing tricks</li>
            <li>Reactive animation: a transition that fires whenever a Control changes value (which means a donation can drive a pulse without anyone writing code)</li>
          </ul>

          <p class="mb-3 text-foreground"><strong>Things CSS can't do, that a video tool can:</strong></p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li>Per-particle physics (sand, water, smoke - too expensive in CSS)</li>
            <li>Procedural shape morphing beyond what SVG path-morphing allows</li>
            <li>True 3D scenes with lighting (CSS 3D is fake-3D plane stacking)</li>
            <li>Frame-perfect synchronization with audio</li>
          </ul>

          <p class="mb-4 text-foreground">
            <strong>Lottie is supported.</strong> If a designer wants a complex vector animation (a celebration burst,
            a coin shower, a custom logo reveal), exporting to Lottie via After Effects + Bodymovin and dropping the
            JSON in is fine - Overlabels includes the lottie-web player. Note: lottie.host's upload UI was absorbed
            into lottiefiles.com, so new uploads go through there or tiiny.host. Existing lottie.host URLs still work.
          </p>

          <p class="text-foreground">
            <strong>Designer deliverable for animation:</strong> a video reference of the desired motion (Lottie export
            preferred, or a quick screen-recording from After Effects / Figma's prototyping mode), <em>plus</em>
            timing and easing notes ("400ms ease-out, then a 2s hold, then 600ms ease-in"). The implementer translates
            those notes into CSS keyframes. Without the timing notes, the implementer is guessing.
          </p>
        </section>

        <!-- 6. What to deliver -->
        <section class="mb-14" id="deliverables">
          <h2 class="mb-4 text-2xl font-bold">6. What to deliver</h2>
          <p class="mb-4 text-foreground">
            A handoff that lets an implementer translate the design into Overlabels HTML/CSS without follow-up
            questions. In rough priority:
          </p>

          <div class="space-y-4">
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-5">
              <h3 class="mb-2 text-lg font-semibold">A Figma file (or equivalent)</h3>
              <p class="text-foreground">
                Frames at 1920x1080 for each surface (static overlay, each alert variant). Layers named meaningfully -
                "donor-name", "amount-pill", "icon-coin" - not "Rectangle 47". Components used for repeated elements.
                If the file is messy, the implementer prices in cleanup time.
              </p>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-5">
              <h3 class="mb-2 text-lg font-semibold">Multiple states per surface</h3>
              <p class="text-foreground">
                Show the static overlay at minimum once with realistic short content and once with worst-case long
                content. Show each alert at minimum its short and long state, plus the entry / hold / exit moments
                annotated. Empty states matter too - what does the "latest donor" panel look like before anyone has
                donated this stream?
              </p>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-5">
              <h3 class="mb-2 text-lg font-semibold">Color tokens</h3>
              <p class="text-foreground">
                A small palette of named colors (primary, accent, success, warning, surface, surface-elevated, text,
                text-muted) with hex values. The implementer puts these into CSS custom properties so every component
                pulls from the same source. Don't sprinkle 47 hand-picked hex values across the design.
              </p>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-5">
              <h3 class="mb-2 text-lg font-semibold">Typography spec</h3>
              <p class="text-foreground">
                Font family, weight, size (rems preferred), line-height, letter-spacing, and the actual font file or
                Google Fonts URL. Be explicit about fallbacks for users who block third-party fonts. If the font
                doesn't have a free web license, flag it now - that's a license-check moment, not an implementation
                decision.
              </p>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-5">
              <h3 class="mb-2 text-lg font-semibold">Asset exports</h3>
              <p class="text-foreground">
                SVG for icons and decorative shapes, exported with optimized paths and sane viewBox. PNG (transparent)
                for raster art that genuinely needs to be raster (a textured logo, a painted illustration). WebP is
                fine for photographic content. <em>Not</em> JPGs with hard backgrounds. <em>Not</em> flattened final
                renders of the whole UI - those are mockups, not assets.
              </p>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-5">
              <h3 class="mb-2 text-lg font-semibold">Animation references</h3>
              <p class="text-foreground">
                One short video per animated element showing the desired motion at the desired timing. Lottie JSON if
                the animation is complex. Annotated timing notes ("400ms ease-out enter, 2.5s hold, 500ms ease-in
                exit") for everything. Without these, the implementer estimates - which is fine, but the streamer ends
                up with motion the designer didn't intend.
              </p>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-5">
              <h3 class="mb-2 text-lg font-semibold">A list of which fields are live data</h3>
              <p class="text-foreground">
                Mark every text element as either "static copy" or "live data". For live data, name the source
                ("Twitch follower count", "latest Ko-fi donor", "GPS speed"). The implementer maps these to the right
                Overlabels Controls and template tags. The
                <Link href="/help/integration-presets" class="text-violet-400 hover:underline">Integration Presets</Link>
                page is the catalog of every available live data field.
              </p>
            </div>
          </div>
        </section>

        <!-- 7. What not to deliver -->
        <section class="mb-14" id="avoid">
          <h2 class="mb-4 text-2xl font-bold">7. What not to deliver</h2>
          <ul class="list-disc space-y-2 pl-6 text-foreground">
            <li>
              <strong>A single flattened PNG of the overlay.</strong> Looks great, useless for implementation.
            </li>
            <li>
              <strong>Mockups with placeholder Lorem Ipsum.</strong> Use realistic strings: real-length usernames,
              real donation amounts, real game titles or IRL category labels.
            </li>
            <li>
              <strong>Pixel-fixed coordinates.</strong> "Position label at x=842, y=560" is not implementable in a
              fluid layout. Specify in spacing relationships ("16px below the avatar, left-aligned with the donor
              name").
            </li>
            <li>
              <strong>Animation specs without timing.</strong> "It pulses" is ambiguous. "200ms scale 1.0 to 1.05
              ease-out, 200ms back to 1.0 ease-in, on every donation event" is implementable.
            </li>
            <li>
              <strong>Fonts that aren't web-licensed.</strong> If the designer specifies a $300/seat foundry font, the
              streamer either pays the license or the implementer substitutes a Google Font and the design drifts.
              Catch this at design-review.
            </li>
            <li>
              <strong>Designs that only work on one background.</strong> If the mockup is on a dark game and the
              streamer's next stream is a sunlit IRL walk - or even just a different scene in the same broadcast,
              like an IRL streamer stepping out of a shaded alley into noon sun - the overlay falls apart in real time.
            </li>
            <li>
              <strong>Adobe After Effects projects with no Lottie export and no video reference.</strong> The
              implementer can't open the .aep, and CSS animation is not video animation. Lottie or video, not project
              files.
            </li>
          </ul>
        </section>

        <!-- 8. Handoff -->
        <section class="mb-14" id="handoff">
          <h2 class="mb-4 text-2xl font-bold">8. Working with the implementer</h2>
          <p class="mb-4 text-foreground">
            The implementer (the streamer themselves, or someone they hired to translate the design into Overlabels)
            needs three things to start: the Figma file, the asset exports, and the live-data field list. Everything
            else can be questions during the build.
          </p>
          <p class="mb-4 text-foreground">
            <strong>What goes well:</strong>
          </p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li>The designer is available for a 30-minute review when the implementer has a working draft.</li>
            <li>Naming in the Figma file matches naming in the implementer's CSS (the donor-name layer becomes the .donor-name class).</li>
            <li>The designer accepts that some pixel-perfect details are going to flex when the design meets real data and real backgrounds, and is willing to iterate on those moments rather than fight them.</li>
          </ul>
          <p class="mb-4 text-foreground">
            <strong>What goes badly:</strong>
          </p>
          <ul class="list-disc space-y-2 pl-6 text-foreground">
            <li>The designer disappears after handoff and the implementer has to make every micro-decision alone.</li>
            <li>"That's not what I designed" is the only feedback after a draft, with no specifics.</li>
            <li>Animation that wasn't specced in the design becomes scope creep mid-implementation ("oh, can it also do this").</li>
          </ul>
        </section>

        <!-- Bottom line -->
        <div class="mb-14 rounded-lg border border-violet-400/40 bg-violet-400/5 p-6">
          <p class="mb-3 text-lg font-medium text-foreground">Bottom line</p>
          <p class="text-foreground">
            Your static and alert overlays need to be ready for
            <strong>59 presets across 8 integrations</strong>
            (see <Link href="/help/integration-presets" class="text-violet-400 hover:underline">/help/integration-presets</Link>
            for the full catalog). The <strong>static overlay</strong> holds persistent state that mutates in real
            time - follower counts, donation totals, latest donor name, GPS speed - and has to keep showing those
            values readably whether they're short or long, small or huge. <strong>Alerts</strong> fire one-shot
            animations when events arrive, with the same flex requirement: the next chatter who subscribes might be
            named <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">XWXWXWXWXWXWXWXWXWXWXW</code>,
            the next Ko-fi donation might be $0.50 or $5,000, and your beautifully balanced "thanks for the sub"
            panel has to absorb both without tanking. Design once, survive everything the audience throws at it.
          </p>
        </div>

        <!-- 9. Deep dives -->
        <section class="mb-14" id="deep-dives">
          <h2 class="mb-4 text-2xl font-bold">9. Deep dives</h2>
          <p class="mb-4 text-foreground">
            The technical pages a designer might want to skim, to see what their design will be implemented against:
          </p>
          <ul class="list-disc space-y-2 pl-6 text-foreground">
            <li>
              <Link href="/help/for-creators" class="text-violet-400 hover:underline">For Creators</Link>
              - the system overview. What Overlabels is beneath the HTML/CSS surface, including the no-JS rule and why
              it exists.
            </li>
            <li>
              <Link href="/help/integration-presets" class="text-violet-400 hover:underline">Integration Presets</Link>
              - the catalog of every live data field across Twitch, Ko-fi, Streamlabs, StreamElements, Fourthwall,
              BMAC, and Overlabels GPS. Useful for marking "this is live data" in a design handoff.
            </li>
            <li>
              <Link href="/help/controls" class="text-violet-400 hover:underline">Controls</Link>
              - the seven mutable value types the streamer can adjust live during a stream.
            </li>
            <li>
              <Link href="/help/expressions" class="text-violet-400 hover:underline">Expression Controls</Link>
              - how live data turns into derived values that drive design states (a goal-progress percentage that
              drives a fill-bar width, for example).
            </li>
            <li>
              <Link href="/help/formatting" class="text-violet-400 hover:underline">Formatting Pipes</Link>
              - how raw values become locale-aware display strings. A designer specifying "currency, two decimals,
              EUR" in a mockup maps to a one-line pipe in the implementation.
            </li>
          </ul>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
