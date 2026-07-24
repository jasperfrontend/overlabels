<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import WelcomeNavbar from '@/components/welcome/WelcomeNavbar.vue';
import SectionHero from '@/components/welcome/SectionHero.vue';
import SectionSyntax from '@/components/welcome/SectionSyntax.vue';
import SectionPlayground from '@/components/welcome/SectionPlayground.vue';
import SectionControls from '@/components/welcome/SectionControls.vue';
import SectionConditionals from '@/components/welcome/SectionConditionals.vue';
import SectionEvents from '@/components/welcome/SectionEvents.vue';
import SectionIntegrations from '@/components/welcome/SectionIntegrations.vue';
import SectionKits from '@/components/welcome/SectionKits.vue';
import SectionOnboarding from '@/components/welcome/SectionOnboarding.vue';
import SectionCta from '@/components/welcome/SectionCta.vue';
import SectionFooter from '@/components/welcome/SectionFooter.vue';
</script>

<template>
  <div class="min-h-screen bg-sidebar-accent text-foreground">
    <Head
      title="Reactive Twitch overlays for people who code"
    >
      <meta
        name="description"
        content="Template tags, reactive expressions, and pipe formatters on top of the HTML and CSS you already write. Live Twitch data, event alerts, and donation tracking from Ko-fi, Streamlabs, and StreamElements. Free and open source."
      />

      <!-- Open Graph -->
      <meta property="og:type" content="website" />
      <meta property="og:url" content="https://overlabels.com/" />
      <meta property="og:site_name" content="Overlabels" />
      <meta property="og:title" content="Overlabels • Reactive Twitch overlays for people who code" />
      <meta
        property="og:description"
        content="Template tags, reactive expressions, and pipe formatters on top of the HTML and CSS you already write. Live Twitch data, event alerts and donation tracking from Ko-fi, Streamlabs, StreamElements, Buy Me A Coffee, FourthWall and Throne. Free and open source."
      />
      <meta property="og:image"
            content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
      <meta property="og:image:width" content="1200" />
      <meta property="og:image:height" content="630" />
      <meta property="og:image:alt" content="Overlabels • reactive Twitch overlays for people who code" />

      <!-- Twitter / X -->
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content="Overlabels • Reactive Twitch overlays for people who code" />
      <meta
        name="twitter:description"
        content="Template tags, reactive expressions, and pipe formatters on top of the HTML and CSS you already write. Live Twitch data, event alerts, and donation tracking from Ko-fi, Streamlabs, and StreamElements. Free and open source."
      />
      <meta name="twitter:image"
            content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
      <meta name="twitter:image:alt" content="Overlabels • reactive Twitch overlays for people who code" />
    </Head>
    <WelcomeNavbar />
    <SectionHero />
    <SectionSyntax />
    <SectionPlayground />
    <SectionControls />
    <SectionConditionals />
    <SectionEvents />
    <SectionIntegrations />
    <SectionKits />
    <SectionOnboarding />
    <SectionCta />
    <SectionFooter />
  </div>
</template>
