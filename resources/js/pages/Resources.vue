<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Free Resources',
    href: '/resources',
  },
];

const colors = [
  { name: 'HSL Picker', url: 'https://www.hslpicker.com', description: 'Pick any color and get HSL, hex, and RGB values instantly.' },
  { name: 'Coolors', url: 'https://coolors.co/generate', description: 'Generate color palettes with one click. Free tier has limits on saves.' },
  { name: 'ColorHub', url: 'https://www.colorhub.app/browse', description: 'AI-driven palette generation with 150+ presets. Export as CSS or Tailwind.' },
  { name: 'Color Hunt', url: 'https://colorhunt.co/', description: 'Community-driven collection of trending color palettes.' },
  { name: 'Color Hex', url: 'https://www.color-hex.com/color-palettes/', description: 'Thousands of user-submitted color palettes with hex, RGB, and HSL codes.' },
  { name: 'mycolor.space', url: 'https://mycolor.space/', description: 'Enter one color, get matching palettes and gradients automatically.' },
];

const gradients = [
  { name: 'CSS Gradient', url: 'https://cssgradient.io', description: 'Visual editor for linear and radial CSS gradients. Copy the code.' },
  { name: 'Josh Comeau Gradient Generator', url: 'https://www.joshwcomeau.com/gradient-generator/', description: 'Advanced gradients with color space interpolation for smoother results.' },
  { name: 'WebGradients', url: 'https://webgradients.com', description: '180 curated linear gradients with one-click CSS copy.' },
  { name: 'Mesh Gradient Generator', url: 'https://csshero.org/mesher/', description: 'Generate CSS mesh gradients with multi-point color blends.' },
  { name: 'WebAIM Contrast Checker', url: 'https://webaim.org/resources/contrastchecker/', description: 'Check if your text is readable against your background. WCAG AA/AAA.' },
];

const icons = [
  { name: 'Icones', url: 'https://icones.js.org/', description: 'Massive icon explorer with instant SVG copy. Covers 100+ icon sets.' },
  { name: 'Tabler Icons', url: 'https://tabler.io/icons', description: '6,000+ pixel-perfect stroke icons. MIT licensed.' },
  { name: 'Lucide', url: 'https://lucide.dev', description: '1,000+ clean SVG icons. Fork of Feather Icons, MIT licensed.' },
  { name: 'Heroicons', url: 'https://heroicons.com', description: 'SVG icons by the Tailwind CSS team. Click to copy. MIT licensed.' },
  { name: 'Phosphor Icons', url: 'https://phosphoricons.com', description: '1,000+ icons in 6 weights (thin, regular, bold, duotone, etc.). MIT licensed.' },
  { name: 'CopyIcon', url: 'https://copyicon.com/', description: '285,000 icons with one-click SVG and PNG copy.' },
  { name: 'Bootstrap Icons', url: 'https://icons.getbootstrap.com', description: '2,000+ open source SVG icons. MIT licensed.' },
];

const fonts = [
  { name: 'Bunny Fonts', url: 'https://fonts.bunny.net/', description: 'Privacy-friendly, GDPR-compliant drop-in replacement for Google Fonts.' },
  { name: 'Google Fonts', url: 'https://fonts.google.com/', description: '1,500+ free open-source fonts with CDN hosting and CSS embeds.' },
  { name: 'Fontsource', url: 'https://fontsource.org', description: 'Self-host open-source fonts via npm. Version-locked, works offline.' },
  { name: 'Velvetyne Type Foundry', url: 'https://velvetyne.fr', description: 'Bold, expressive, experimental open-source typefaces. Great for display text.' },
];

const textures = [
  { name: 'Transparent Textures', url: 'https://www.transparenttextures.com', description: '397 transparent overlay patterns. Pick a color, choose a pattern, copy the CSS.' },
  { name: 'fffuel', url: 'https://www.fffuel.co', description: '70+ free SVG generators for gradients, patterns, blobs, noise, and shapes.' },
  { name: 'ambientCG', url: 'https://ambientcg.com', description: '2,000+ CC0 public domain textures. No attribution, no registration.' },
];

const animations = [
  { name: 'Animate.css', url: 'https://animate.style', description: '70+ ready-to-use CSS animation classes. Add a class, get an animation.' },
  { name: 'Animista', url: 'https://animista.net', description: 'Customize easing, duration, and delay for CSS animations. Copy the code.' },
  { name: 'LottieFiles', url: 'https://lottiefiles.com', description: 'Lightweight vector animations. Works natively in Overlabels overlays.' },
  { name: 'loading.io/css', url: 'https://loading.io/css/', description: 'Pure CSS loader and spinner animations. CC0, truly free.' },
  { name: 'SVG Spinners', url: 'https://github.com/n3r4zzurr0/svg-spinners', description: '24 SVG spinner animations with CSS and SMIL. MIT licensed.' },
  { name: 'Animated Backgrounds', url: 'https://freefrontend.com/css-animated-backgrounds/', description: '47 CSS animated background effects with copyable code.' },
];

const sounds = [
  { name: 'Pixabay Sound Effects', url: 'https://pixabay.com/sound-effects/', description: 'Free sound effects. No attribution required. Great for alert sounds.' },
  { name: 'Freesound', url: 'https://freesound.org', description: '700,000+ sounds. Filter by CC0 for no-attribution use. Requires free account.' },
  { name: 'Sonniss GDC Bundle', url: 'https://gdc.sonniss.com', description: 'Massive free sound effect bundles (multiple GBs). No attribution required.' },
];

const cssTools = [
  { name: 'Box Shadow Examples', url: 'https://getcssscan.com/css-box-shadow-examples', description: '95 beautiful box-shadow presets from Stripe, Material Design, Tailwind. Click to copy.' },
  { name: 'Clippy', url: 'https://bennettfeely.com/clippy/', description: 'Visual CSS clip-path maker. Drag points to create custom shapes.' },
  { name: 'Neumorphism.io', url: 'https://neumorphism.io', description: 'Generate soft, neumorphic CSS shadows with a visual editor.' },
  { name: 'Glassmorphism Generator', url: 'https://hype4.academy/tools/glassmorphism-generator', description: 'Create frosted-glass CSS effects with adjustable blur and opacity.' },
];
</script>

<template>
  <Head>
    <title>Free Resources - Overlabels</title>
    <meta
      name="description"
      content="Free tools and resources for building beautiful Twitch overlays. Colors, gradients, icons, fonts, textures, animations, sounds, and CSS tools - all free to use."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/resources" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Free Resources - Overlabels" />
    <meta
      property="og:description"
      content="Free tools and resources for building beautiful Twitch overlays. Colors, gradients, icons, fonts, textures, animations, sounds, and CSS tools - all free to use."
    />
    <meta property="og:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - Twitch overlay management" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Free Resources - Overlabels" />
    <meta
      name="twitter:description"
      content="Free tools and resources for building beautiful Twitch overlays. Colors, gradients, icons, fonts, textures, animations, sounds, and CSS tools - all free to use."
    />
    <meta name="twitter:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - Twitch overlay management" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col items-center gap-8 px-4 py-10">
      <div class="w-full max-w-3xl space-y-10">
        <div class="text-center">
          <h1 class="text-4xl font-extrabold tracking-tight">Free Resources</h1>
          <p class="mt-3 text-lg text-muted-foreground">
            Tools we love for building overlays. All free, no credit required unless noted.
          </p>
        </div>

        <!-- Colors -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Colors</h2>
          <p class="text-muted-foreground">Pick colors, generate palettes, and find the right look for your overlays.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in colors"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Gradients & Contrast -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Gradients & Contrast</h2>
          <p class="text-muted-foreground">Generate CSS gradients and check readability.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in gradients"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Icons -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Icons (1-click SVG copy)</h2>
          <p class="text-muted-foreground">Browse thousands of icons and copy the SVG straight into your overlay HTML.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in icons"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Fonts -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Fonts</h2>
          <p class="text-muted-foreground">Free font libraries you can use in your overlays via CSS imports.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in fonts"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Textures & Patterns -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Textures & Patterns</h2>
          <p class="text-muted-foreground">Backgrounds, seamless patterns, and SVG generators for overlay flair.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in textures"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Animations -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Animations</h2>
          <p class="text-muted-foreground">CSS animations, Lottie files, loaders, and animated backgrounds for your overlays.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in animations"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Sounds -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">Sounds</h2>
          <p class="text-muted-foreground">Free sound effects for alert overlays. No attribution required unless noted.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in sounds"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- CSS Tools -->
        <section class="space-y-4">
          <h2 class="text-2xl font-bold">CSS Tools</h2>
          <p class="text-muted-foreground">Visual generators for shadows, shapes, glass effects, and more.</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <a
              v-for="resource in cssTools"
              :key="resource.url"
              :href="resource.url"
              target="_blank"
              rel="noopener"
              class="group rounded-lg border p-4 transition hover:border-violet-500/50 hover:bg-violet-500/5"
            >
              <p class="font-semibold group-hover:text-violet-400">{{ resource.name }}</p>
              <p class="text-sm text-muted-foreground">{{ resource.description }}</p>
            </a>
          </div>
        </section>

        <!-- Note -->
        <section class="rounded-lg border bg-sidebar-accent p-5 text-center">
          <p class="text-sm text-muted-foreground">
            Know a great free resource that should be on this list?
            Let us know at <a href="mailto:admin@overlabels.com" class="text-violet-400 hover:underline">admin@overlabels.com</a>.
          </p>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
