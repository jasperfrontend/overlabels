<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import type { AppPageProps } from '@/types'
import { computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
  Sparkles,
  Zap,
  Shield,
  Users,
  Palette,
  BarChart3,
  MessageCircle,
  ChevronRight,
  Monitor,
  Twitch,
  Layers
} from 'lucide-vue-next';

const page = usePage<AppPageProps>()
const auth = computed(() => page.props.auth);
onMounted(() => {
  if (auth.value?.user) {
    router.visit('/dashboard')
  }
})

const features = [
  {
    icon: Sparkles,
    title: 'Dynamic Overlays',
    description: 'Create stunning, real-time overlays that react to your stream events instantly'
  },
  {
    icon: Palette,
    title: 'Customizable Templates',
    description: 'Design unique overlays with our powerful template editor and custom styling'
  },
  {
    icon: Zap,
    title: 'Real-time Updates',
    description: 'Instant synchronization with Twitch EventSub for follower alerts, raids, and more'
  },
  {
    icon: Shield,
    title: 'Secure & Reliable',
    description: 'Token-based access control and enterprise-grade security for your overlays'
  },
  {
    icon: BarChart3,
    title: 'Analytics Dashboard',
    description: 'Track engagement and overlay performance with detailed insights'
  },
  {
    icon: Users,
    title: 'Community Driven',
    description: 'Join thousands of streamers enhancing their broadcasts with Overlabels'
  }
];

const stats = [
  { label: 'Active Streamers', value: '10K+' },
  { label: 'Overlays Created', value: '50K+' },
  { label: 'Events Processed', value: '5M+' },
  { label: 'Uptime', value: '99.9%' }
];
</script>

<template>
<div>
    <Head title="Welcome to Overlabels - Professional Stream Overlays">
        <meta name="description" content="Create professional, dynamic overlays for your Twitch stream. Real-time alerts, custom templates, and seamless OBS integration." />
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-gradient-to-b from-white to-gray-50 dark:from-gray-950 dark:to-gray-900">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 w-full border-b bg-white/80 backdrop-blur-md dark:bg-gray-950/80 dark:border-gray-800">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Layers class="h-8 w-8 text-purple-600 dark:text-purple-400" />
                        <span class="text-2xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 dark:from-purple-400 dark:to-pink-400 bg-clip-text text-transparent">
                            Overlabels
                        </span>
                    </div>
                    <div class="flex items-center gap-4">
                        <Link href="#features" class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition-colors">
                            Features
                        </Link>
                        <Link href="#pricing" class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition-colors">
                            Pricing
                        </Link>
                        <LoginSocial />
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-purple-100 via-pink-50 to-blue-100 dark:from-purple-950/20 dark:via-pink-950/20 dark:to-blue-950/20 opacity-50"></div>
            <div class="relative container mx-auto px-6 py-24 lg:py-32">
                <div class="max-w-4xl mx-auto text-center">
                    <Badge class="mb-4 bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                        <Sparkles class="h-3 w-3 mr-1" />
                        Trusted by 10,000+ Streamers
                    </Badge>
                    <h1 class="text-5xl lg:text-7xl font-bold bg-gradient-to-r from-gray-900 to-gray-700 dark:from-white dark:to-gray-300 bg-clip-text text-transparent mb-6">
                        Professional Stream Overlays Made Simple
                    </h1>
                    <p class="text-xl text-gray-600 dark:text-gray-400 mb-8 max-w-2xl mx-auto">
                        Transform your Twitch stream with dynamic, customizable overlays. Real-time alerts,
                        seamless OBS integration, and powerful analytics - all in one platform.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                        <LoginSocial />
                        <Button variant="outline" size="lg" class="gap-2" asChild>
                            <a href="https://discord.gg/overlabels" target="_blank" rel="noopener noreferrer">
                                <MessageCircle class="h-5 w-5" />
                                Join our Discord
                            </a>
                        </Button>
                    </div>
                    <div class="mt-12 flex items-center justify-center gap-8 text-center">
                        <div v-for="stat in stats" :key="stat.label">
                            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ stat.value }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ stat.label }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-24 bg-white dark:bg-gray-900">
            <div class="container mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold mb-4 text-gray-900 dark:text-white">
                        Everything You Need for Professional Streaming
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                        Powerful features designed to elevate your stream and engage your audience
                    </p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <Card v-for="feature in features" :key="feature.title" class="border-gray-200 dark:border-gray-800 hover:shadow-lg transition-shadow">
                        <CardContent class="p-6">
                            <div class="h-12 w-12 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mb-4">
                                <component :is="feature.icon" class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                            </div>
                            <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                                {{ feature.title }}
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ feature.description }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="py-24 bg-gray-50 dark:bg-gray-950">
            <div class="container mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold mb-4 text-gray-900 dark:text-white">
                        Get Started in Minutes
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                        Simple setup process to get your overlays running
                    </p>
                </div>
                <div class="max-w-4xl mx-auto">
                    <div class="space-y-8">
                        <div class="flex gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-purple-600 dark:bg-purple-500 text-white flex items-center justify-center font-bold">
                                1
                            </div>
                            <div class="flex-grow">
                                <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                                    Connect Your Twitch Account
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">
                                    Secure OAuth integration with one-click authorization
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-purple-600 dark:bg-purple-500 text-white flex items-center justify-center font-bold">
                                2
                            </div>
                            <div class="flex-grow">
                                <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                                    Create or Choose a Template
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">
                                    Design your own or pick from our professional templates
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-purple-600 dark:bg-purple-500 text-white flex items-center justify-center font-bold">
                                3
                            </div>
                            <div class="flex-grow">
                                <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                                    Add to OBS as Browser Source
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">
                                    Copy your unique overlay URL and paste it into OBS
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-purple-600 dark:bg-purple-500 text-white flex items-center justify-center font-bold">
                                4
                            </div>
                            <div class="flex-grow">
                                <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                                    Go Live!
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">
                                    Your overlays update in real-time as you stream
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-24 bg-white dark:bg-gray-900">
            <div class="container mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold mb-4 text-gray-900 dark:text-white">
                        Simple, Transparent Pricing
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                        Start free and upgrade as you grow
                    </p>
                </div>
                <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                    <Card class="border-gray-200 dark:border-gray-800">
                        <CardContent class="p-8">
                            <h3 class="text-2xl font-bold mb-2 text-gray-900 dark:text-white">Free</h3>
                            <div class="text-4xl font-bold mb-4 text-gray-900 dark:text-white">$0</div>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">Perfect for getting started</p>
                            <ul class="space-y-3 mb-8">
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    3 Active Overlays
                                </li>
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    Basic Templates
                                </li>
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    Standard Support
                                </li>
                            </ul>
                            <Button variant="outline" class="w-full">Get Started</Button>
                        </CardContent>
                    </Card>

                    <Card class="border-purple-600 dark:border-purple-400 shadow-lg scale-105">
                        <CardContent class="p-8">
                            <Badge class="mb-2 bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                Most Popular
                            </Badge>
                            <h3 class="text-2xl font-bold mb-2 text-gray-900 dark:text-white">Pro</h3>
                            <div class="text-4xl font-bold mb-4 text-gray-900 dark:text-white">$9<span class="text-lg font-normal">/mo</span></div>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">For serious streamers</p>
                            <ul class="space-y-3 mb-8">
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    Unlimited Overlays
                                </li>
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    Premium Templates
                                </li>
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    Analytics Dashboard
                                </li>
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    Priority Support
                                </li>
                            </ul>
                            <Button class="w-full bg-purple-600 hover:bg-purple-700 dark:bg-purple-500 dark:hover:bg-purple-600">
                                Upgrade to Pro
                            </Button>
                        </CardContent>
                    </Card>

                    <Card class="border-gray-200 dark:border-gray-800">
                        <CardContent class="p-8">
                            <h3 class="text-2xl font-bold mb-2 text-gray-900 dark:text-white">Team</h3>
                            <div class="text-4xl font-bold mb-4 text-gray-900 dark:text-white">$29<span class="text-lg font-normal">/mo</span></div>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">For streaming teams</p>
                            <ul class="space-y-3 mb-8">
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    Everything in Pro
                                </li>
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    Team Collaboration
                                </li>
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    Custom Branding
                                </li>
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <ChevronRight class="h-4 w-4 text-green-500" />
                                    API Access
                                </li>
                            </ul>
                            <Button variant="outline" class="w-full">Contact Sales</Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-24 bg-gradient-to-r from-purple-600 to-pink-600 dark:from-purple-700 dark:to-pink-700">
            <div class="container mx-auto px-6 text-center">
                <h2 class="text-4xl font-bold mb-4 text-white">
                    Ready to Transform Your Stream?
                </h2>
                <p class="text-xl text-white/90 mb-8 max-w-2xl mx-auto">
                    Join thousands of streamers using Overlabels to create professional, engaging overlays
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <LoginSocial />
                    <Button variant="secondary" size="lg" class="gap-2" asChild>
                        <a href="https://discord.gg/overlabels" target="_blank" rel="noopener noreferrer">
                            <MessageCircle class="h-5 w-5" />
                            Questions? Join Discord
                        </a>
                    </Button>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 bg-gray-100 dark:bg-gray-950 border-t border-gray-200 dark:border-gray-800">
            <div class="container mx-auto px-6">
                <div class="grid md:grid-cols-4 gap-8 mb-8">
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <Layers class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                            <span class="text-xl font-bold text-gray-900 dark:text-white">Overlabels</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Professional stream overlays for Twitch streamers
                        </p>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-4 text-gray-900 dark:text-white">Product</h4>
                        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <li><Link href="#features">Features</Link></li>
                            <li><Link href="#pricing">Pricing</Link></li>
                            <li><a href="/docs">Documentation</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-4 text-gray-900 dark:text-white">Community</h4>
                        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <li><a href="https://discord.gg/overlabels" target="_blank" rel="noopener noreferrer">Discord</a></li>
                            <li><a href="https://twitter.com/overlabels" target="_blank" rel="noopener noreferrer">Twitter</a></li>
                            <li><a href="https://github.com/overlabels" target="_blank" rel="noopener noreferrer">GitHub</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-4 text-gray-900 dark:text-white">Legal</h4>
                        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <li><Link href="/privacy">Privacy Policy</Link></li>
                            <li><Link href="/terms">Terms of Service</Link></li>
                            <li><a href="/contact">Contact</a></li>
                        </ul>
                    </div>
                </div>
                <div class="pt-8 border-t border-gray-200 dark:border-gray-800 text-center text-sm text-gray-600 dark:text-gray-400">
                    <p>&copy; 2025 Overlabels. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</div>
</template>
