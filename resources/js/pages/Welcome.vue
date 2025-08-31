<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Code2,
  Zap,
  Shield,
  Globe,
  ChevronDown,
  ArrowRight,
  ArrowDown,
  Check,
  Github,
  Twitter,
  Layers,
  Gauge,
  Lock
} from 'lucide-vue-next';
import { ref } from 'vue';

const features = [
  {
    icon: Code2,
    title: 'Code Your Own',
    description: 'Full control with HTML & CSS. No drag-and-drop limitations.'
  },
  {
    icon: Zap,
    title: 'Lightning Fast',
    description: '300-600ms EventSub processing. Real-time overlay updates.'
  },
  {
    icon: Shield,
    title: 'Zero Lock-in',
    description: 'Your overlays, your code. Export anytime, fork anything.'
  },
  {
    icon: Globe,
    title: 'Open Source',
    description: 'Fully transparent. Built on Laravel, Vue, and modern web tech.'
  }
];

const benefits = [
  'Template Tags for dynamic Twitch data',
  'Event Tags for real-time alerts',
  'Hash-based secure authentication',
  'Fork any public overlay instantly',
  'Built-in code editor with syntax highlighting',
  'Responsive previews in real-time'
];

const activeTab = ref('template');
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-background to-muted/20 dark:from-gray-900 dark:to-gray-950">
    <Head>
      <title>Overlabels - Code Your Stream Overlays</title>
      <meta name="description" content="Build powerful Twitch overlays with HTML, CSS, and dynamic template tags. No vendor lock-in, fully open source." />
      <meta name="keywords" content="twitch overlays, obs overlays, stream overlays, html overlays, custom overlays" />
    </Head>

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 backdrop-blur-lg bg-background/80 dark:bg-gray-900/80 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center">
            <img src="https://overlabels.com/favicon.png" alt="" class="w-10 h-10 p-2" />
            <span class="ml-2 text-xl font-bold bg-gradient-to-r from-violet-600 to-purple-600 dark:from-violet-400 dark:to-purple-400 bg-clip-text text-transparent">
              Overlabels
            </span>
          </div>

          <div class="flex items-center gap-8">
            <DarkModeToggle />
            <div v-if="$page.props.auth.user">
              <Link :href="route('dashboard.index')" class="btn btn-primary">
                Dashboard
                <ArrowRight class="ml-2 h-4 w-4" />
              </Link>
            </div>
            <div v-else>
              <LoginSocial />
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-violet-500/10 via-purple-500/5 to-pink-500/10 dark:from-violet-500/5 dark:via-purple-500/3 dark:to-pink-500/5" />

      <div class="container relative mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-32">
        <div class="text-center max-w-4xl mx-auto">
          <Badge class="mb-4 px-3 py-1">
            100% Open Source & free forever
          </Badge>

          <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold tracking-tight mb-6">
            <span class="bg-gradient-to-r from-violet-600 to-purple-600 dark:from-violet-400 dark:to-purple-400 bg-clip-text text-transparent">
              Build OBS Overlays
            </span>
            <br />
            <span class="text-foreground dark:text-white">
              With Code, Not Clicks
            </span>
          </h1>

          <p class="text-lg sm:text-xl text-muted-foreground dark:text-gray-400 mb-8 max-w-2xl mx-auto">
            Create powerful, dynamic stream overlays using HTML, CSS, and our intuitive template tags.
            No vendor lock-in, no subscription fees, just pure creative freedom.
          </p>

          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <Link v-if="!$page.props.auth.user" href="#get-started">
              <button class="btn btn-primary cursor-pointer">
                Get Started Free
                <ArrowRight class="ml-2 h-5 w-5" />
              </button>
            </Link>
            <Link href="#how-it-works">
              <button class="btn btn-secondary cursor-pointer">
                See How It Works
                <ChevronDown class="ml-2 h-5 w-5" />
              </button>
            </Link>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Grid -->
    <section class="py-20 sm:py-32">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl sm:text-4xl font-bold mb-4">Why Developers Love Overlabels</h2>
          <p class="text-muted-foreground dark:text-gray-400 max-w-2xl mx-auto">
            Built by developers, for developers. Every feature designed with code-first approach.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card v-for="feature in features" :key="feature.title" class="group hover:shadow-lg transition-all duration-300 hover:-translate-y-1 dark:bg-gray-800/50">
            <CardHeader>
              <div class="h-12 w-12 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center mb-4 group-hover:bg-violet-200 dark:group-hover:bg-violet-900/50 transition-colors">
                <component :is="feature.icon" class="h-6 w-6 text-violet-600 dark:text-violet-400" />
              </div>
              <CardTitle class="text-lg">{{ feature.title }}</CardTitle>
            </CardHeader>
            <CardContent>
              <CardDescription>{{ feature.description }}</CardDescription>
            </CardContent>
          </Card>
        </div>
      </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-20 sm:py-32 bg-muted/30 dark:bg-gray-900/30">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl sm:text-4xl font-bold mb-4">Template Tags Made Simple</h2>
          <p class="text-muted-foreground dark:text-gray-400 max-w-2xl mx-auto">
            Dynamic data injection with our intuitive triple-bracket syntax
          </p>
        </div>

        <!-- Tab Navigation -->
        <div class="flex justify-center mb-8">
          <div class="inline-flex rounded-lg bg-muted p-1 dark:bg-gray-800">
            <button
              @click="activeTab = 'template'"
              :class="[
                'px-4 py-2 rounded-md font-medium transition-all cursor-pointer',
                activeTab === 'template'
                  ? 'bg-background dark:bg-gray-700 text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              ]"
            >
              Template Tags
            </button>
            <button
              @click="activeTab = 'event'"
              :class="[
                'px-4 py-2 rounded-md font-medium transition-all cursor-pointer',
                activeTab === 'event'
                  ? 'bg-background dark:bg-gray-700 text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              ]"
            >
              Event Tags
            </button>
          </div>
        </div>

        <div class="max-w-4xl mx-auto">
          <!-- Template Tags Example -->
          <div v-show="activeTab === 'template'" class="space-y-4">
            <Card class="dark:bg-gray-800/50">
              <CardHeader>
                <Badge variant="outline" class="w-fit mb-2">Your Code</Badge>
                <div class="bg-muted dark:bg-gray-900 rounded-lg p-4 font-mono text-sm overflow-x-auto">
                  <code class="text-violet-600 dark:text-violet-400">
                    &lt;div class="follower-count"&gt;<br />
                    &nbsp;&nbsp;&lt;h2&gt;Followers&lt;/h2&gt;<br />
                    &nbsp;&nbsp;&lt;p class="count"&gt;[[[followers_total]]]&lt;/p&gt;<br />
                    &lt;/div&gt;
                  </code>
                </div>
              </CardHeader>
            </Card>

            <div class="flex justify-center">
              <ArrowDown class="h-8 w-8 text-muted-foreground animate-pulse" />
            </div>

            <Card class="dark:bg-gray-800/50">
              <CardHeader>
                <Badge variant="outline" class="w-fit mb-2 border-green-500 text-green-600 dark:text-green-400">Live Result</Badge>
                <div class="bg-muted dark:bg-gray-900 rounded-lg p-4 font-mono text-sm overflow-x-auto">
                  <code class="text-green-600 dark:text-green-400">
                    &lt;div class="follower-count"&gt;<br />
                    &nbsp;&nbsp;&lt;h2&gt;Followers&lt;/h2&gt;<br />
                    &nbsp;&nbsp;&lt;p class="count"&gt;1,534&lt;/p&gt;<br />
                    &lt;/div&gt;
                  </code>
                </div>
              </CardHeader>
            </Card>
          </div>

          <!-- Event Tags Example -->
          <div v-show="activeTab === 'event'" class="space-y-4">
            <Card class="dark:bg-gray-800/50">
              <CardHeader>
                <Badge variant="outline" class="w-fit mb-2">Your Alert Template</Badge>
                <div class="bg-muted dark:bg-gray-900 rounded-lg p-4 font-mono text-sm overflow-x-auto">
                  <code class="text-violet-600 dark:text-violet-400">
                    &lt;div class="sub-alert"&gt;<br />
                    &nbsp;&nbsp;&lt;h1&gt;New Subscriber!&lt;/h1&gt;<br />
                    &nbsp;&nbsp;&lt;p&gt;[[[event.user_name]]] just subscribed&lt;/p&gt;<br />
                    &nbsp;&nbsp;&lt;p&gt;Tier [[[event.tier_display]]]&lt;/p&gt;<br />
                    &lt;/div&gt;
                  </code>
                </div>
              </CardHeader>
            </Card>

            <div class="flex justify-center">
              <Zap class="h-8 w-8 text-yellow-500 animate-pulse" />
            </div>

            <Card class="dark:bg-gray-800/50">
              <CardHeader>
                <Badge variant="outline" class="w-fit mb-2 border-green-500 text-green-600 dark:text-green-400">When Event Fires</Badge>
                <div class="bg-muted dark:bg-gray-900 rounded-lg p-4 font-mono text-sm overflow-x-auto">
                  <code class="text-green-600 dark:text-green-400">
                    &lt;div class="sub-alert"&gt;<br />
                    &nbsp;&nbsp;&lt;h1&gt;New Subscriber!&lt;/h1&gt;<br />
                    &nbsp;&nbsp;&lt;p&gt;CoolStreamer42 just subscribed&lt;/p&gt;<br />
                    &nbsp;&nbsp;&lt;p&gt;Tier 2&lt;/p&gt;<br />
                    &lt;/div&gt;
                  </code>
                </div>
              </CardHeader>
            </Card>
          </div>
        </div>
      </div>
    </section>

    <!-- Benefits -->
    <section class="py-20 sm:py-32">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
          <div>
            <h2 class="text-3xl sm:text-4xl font-bold mb-6">
              Everything You Need,<br />
              <span class="text-violet-600 dark:text-violet-400">Nothing You Don't</span>
            </h2>
            <p class="text-muted-foreground dark:text-gray-400 mb-8">
              Overlabels gives you the tools to create professional stream overlays without the bloat.
              Focus on creativity, not configuration.
            </p>

            <div class="space-y-3">
              <div v-for="benefit in benefits" :key="benefit" class="flex items-start gap-3">
                <div class="h-6 w-6 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <Check class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                </div>
                <span class="text-foreground dark:text-gray-200">{{ benefit }}</span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <Card class="dark:bg-gray-800/50">
              <CardHeader>
                <Gauge class="h-8 w-8 text-violet-600 dark:text-violet-400 mb-2" />
                <CardTitle class="text-2xl">300ms</CardTitle>
                <CardDescription>Average event processing time</CardDescription>
              </CardHeader>
            </Card>

            <Card class="dark:bg-gray-800/50">
              <CardHeader>
                <Layers class="h-8 w-8 text-violet-600 dark:text-violet-400 mb-2" />
                <CardTitle class="text-2xl">100%</CardTitle>
                <CardDescription>Your code, your control</CardDescription>
              </CardHeader>
            </Card>

            <Card class="dark:bg-gray-800/50">
              <CardHeader>
                <Lock class="h-8 w-8 text-violet-600 dark:text-violet-400 mb-2" />
                <CardTitle class="text-2xl">Secure</CardTitle>
                <CardDescription>Hash-based authentication</CardDescription>
              </CardHeader>
            </Card>

            <Card class="dark:bg-gray-800/50">
              <CardHeader>
                <Globe class="h-8 w-8 text-violet-600 dark:text-violet-400 mb-2" />
                <CardTitle class="text-2xl">Open</CardTitle>
                <CardDescription>Fork & share freely</CardDescription>
              </CardHeader>
            </Card>
          </div>
        </div>
      </div>
    </section>

    <!-- Tech Stack -->
    <section class="py-20 sm:py-32 bg-muted/30 dark:bg-gray-900/30">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl sm:text-4xl font-bold mb-4">Built on Modern Tech</h2>
          <p class="text-muted-foreground dark:text-gray-400 max-w-2xl mx-auto">
            Powered by battle-tested frameworks and technologies you already know and love
          </p>
        </div>

        <div class="flex flex-wrap justify-center gap-4">
          <Badge variant="default" class="px-4 py-2 text-sm border border-sidebar">Laravel</Badge>
          <Badge variant="default" class="px-4 py-2 text-sm border border-sidebar">PostgreSQL</Badge>
          <Badge variant="default" class="px-4 py-2 text-sm border border-sidebar">Vue.js</Badge>
          <Badge variant="default" class="px-4 py-2 text-sm border border-sidebar">Inertia.js</Badge>
          <Badge variant="default" class="px-4 py-2 text-sm border border-sidebar">TypeScript</Badge>
          <Badge variant="default" class="px-4 py-2 text-sm border border-sidebar">Tailwind CSS</Badge>
          <Badge variant="default" class="px-4 py-2 text-sm border border-sidebar">Vite</Badge>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section id="get-started" class="py-20 sm:py-32">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <Card class="max-w-2xl mx-auto text-center bg-gradient-to-br from-violet-500/10 to-purple-500/10 dark:from-violet-900/20 dark:to-purple-900/20 border-violet-200 dark:border-sidebar">
          <CardHeader>
            <CardTitle class="text-3xl sm:text-4xl mb-4">
              Ready to Code Your Overlays?
            </CardTitle>
            <CardDescription class="text-base mb-8">
              Join the growing community of streamers who prefer code over clicks.
              It's free, open source, and you own everything you create.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="$page.props.auth.user" class="space-y-4">
              <p class="text-lg font-medium text-green-600 dark:text-green-400">
                ✓ You're logged in!
              </p>
              <Link :href="route('dashboard.index')" class="btn btn-primary text-center mx-auto">
                  Go to Dashboard
                  <ArrowRight class="ml-2 h-5 w-5" />
              </Link>
            </div>
            <div v-else class="space-y-4">
              <LoginSocial />
              <p class="text-xs text-muted-foreground dark:text-gray-500 mt-8">
                By signing in, you agree that this is an MVP and things might break.
                Your data is safe and you can revoke access anytime via Twitch settings.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 border-t border-border/50 dark:border-gray-800">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-2">
            <img src="https://overlabels.com/favicon.png" alt="" class="w-10 h-10 p-2" />
            <span class="font-semibold">Overlabels</span>
            <Badge variant="outline" class="ml-2">Beta</Badge>
          </div>

          <div class="flex items-center gap-6 text-sm text-muted-foreground">
            <Link href="/terms" class="hover:text-foreground transition-colors">Terms</Link>
            <Link href="/privacy" class="hover:text-foreground transition-colors">Privacy</Link>
            <a href="https://github.com/jasperfrontend/overlabels" target="_blank" rel="noopener" class="hover:text-foreground transition-colors flex">
              Overlabels on Github
              <Github class="h-5 w-5 ml-2" />
            </a>
          </div>
        </div>

        <div class="mt-8 text-center text-xs text-muted-foreground dark:text-gray-500">
          Made by JasperDiscovers for the Twitch streaming community. Forever free, forever open.<br>
          FAQ: Will you support Kick.com? No.
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

</style>
