<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import TemplateCard from '@/components/TemplateCard.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Layers, Plus, Bell, Users, Zap, Sparkles, AlertTriangle, X, RotateCcw } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { ref, onMounted } from 'vue';

interface Template {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  type: 'static' | 'alert';
  is_public: boolean;
  view_count: number;
  fork_count: number;
  owner?: {
    id: number;
    name: string;
    avatar?: string;
  };
  created_at: string;
  updated_at: string;
}

defineProps<{
  userName: string;
  userId: number;
  userAlertTemplates: Template[];
  userStaticTemplates: Template[];
  communityTemplates: Template[];
}>();

const breadcrumbs = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
];

// Welcome alert state management
const showWelcomeAlert = ref(true);

onMounted(() => {
  const stored = localStorage.getItem('overlabels-welcome-dismissed');
  if (stored === 'true') {
    showWelcomeAlert.value = false;
  }
});

const dismissWelcomeAlert = () => {
  showWelcomeAlert.value = false;
  localStorage.setItem('overlabels-welcome-dismissed', 'true');
};

const showWelcomeAlertAgain = () => {
  showWelcomeAlert.value = true;
  localStorage.setItem('overlabels-welcome-dismissed', 'false');
};
</script>

<template>
  <Head>
    <title>Dashboard</title>
    <meta name="description" content="Dashboard for Overlabels - Your Twitch overlay hub" />
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <!-- Welcome Alert Section -->
      <section v-if="showWelcomeAlert" class="relative mb-6 rounded-xl overflow-hidden border border-purple-200/30 dark:border-purple-800/30 bg-gradient-to-br from-purple-50/70 via-purple-100/50 to-purple-200/30 dark:from-purple-900/30 dark:via-purple-950/20 dark:to-purple-900/20 p-6 shadow-sm backdrop-blur-sm">
        <button
          @click="dismissWelcomeAlert"
          class="absolute top-4 right-4 p-1.5 rounded-md hover:bg-purple-200/30 dark:hover:bg-purple-800/30 transition-colors"
          title="Dismiss welcome message"
        >
          <X class="h-5 w-5 text-purple-600 dark:text-purple-400" />
        </button>

        <div class="flex items-center gap-3 mb-4">
          <Zap class="h-6 w-6 text-purple-600 dark:text-purple-400" />
          <h2 class="text-xl font-semibold text-purple-800 dark:text-purple-300">Welcome to the Overlabels MVP</h2>
        </div>

        <div class="bg-purple-100/50 dark:bg-purple-900/30 border-l-4 border-purple-500/50 p-4 mb-5 rounded-r-md">
          <div class="flex items-center gap-2">
            <Sparkles class="h-5 w-5 text-purple-600 dark:text-purple-400" />
            <span class="font-medium text-purple-800 dark:text-purple-300">PLEASE READ THIS TO GET STARTED (this will be automated later):</span>
          </div>
        </div>

        <ul class="space-y-4">
          <li class="flex items-start gap-3">
            <div class="mt-1.5 h-2 w-2 rounded-full bg-purple-600 dark:bg-purple-400 flex-shrink-0"></div>
            <p class="text-gray-700 dark:text-gray-300">
              Generate your <Link :href="route('tags.generator')" class="text-purple-600 dark:text-purple-400 hover:underline">Template Tags</Link>
            </p>
          </li>

          <li class="flex items-start gap-3">
            <div class="mt-1.5 h-2 w-2 rounded-full bg-purple-600 dark:bg-purple-400 flex-shrink-0"></div>
            <div class="text-gray-700 dark:text-gray-300">
              Generate a <Link :href="route('tokens.index')" class="text-purple-600 dark:text-purple-400 hover:underline">Secure Token</Link> and store it somewhere safe! Remember: It's only shown once after creating it!
              <div class="mt-3 bg-red-100/50 dark:bg-red-900/30 border border-red-200/50 dark:border-red-800/50 p-3 rounded-md">
                <div class="flex items-start gap-2">
                  <AlertTriangle class="h-4 w-4 text-red-600 dark:text-red-400 mt-0.5" />
                  <div>
                    <span class="text-red-700 dark:text-red-300">Treat that thing <u class="text-red-800 dark:text-red-200">like a password</u> and </span>
                    <strong class="text-red-800 dark:text-red-200 font-medium">DO NOT EVER SHARE YOUR SECURE TOKEN WITH ANYBODY. DO NOT SHOW IT ON STREAM!</strong>
                  </div>
                </div>
              </div>
            </div>
          </li>

          <li class="flex items-start gap-3">
            <div class="mt-1.5 h-2 w-2 rounded-full bg-purple-600 dark:bg-purple-400 flex-shrink-0"></div>
            <p class="text-gray-700 dark:text-gray-300">
              Visit <Link class="text-purple-600 dark:text-purple-400 hover:underline" :href="route('kits.index')">Kits</Link> and fork the Starter Kit to get a sensible set of defaults to work with.
            </p>
          </li>

          <li class="flex items-start gap-3">
            <div class="mt-1.5 h-2 w-2 rounded-full bg-purple-600 dark:bg-purple-400 flex-shrink-0"></div>
            <p class="text-gray-700 dark:text-gray-300">
              Assign the Event Alerts to your Events in the <Link class="text-purple-600 dark:text-purple-400 hover:underline" :href="route('events.index')">Alerts Builder</Link> so that new EventSub events trigger the correct overlay.
            </p>
          </li>

          <li class="flex items-start gap-3">
            <div class="mt-1.5 h-2 w-2 rounded-full bg-purple-600 dark:bg-purple-400 flex-shrink-0"></div>
            <p class="text-green-600 dark:text-green-400 font-medium">Start fiddling! Enjoy!</p>
          </li>
        </ul>
      </section>

      <!-- Show Welcome Again Section -->
      <section v-if="!showWelcomeAlert" class="mb-6">
        <button
          @click="showWelcomeAlertAgain"
          class="flex items-center gap-2 text-sm text-muted-foreground hover:text-violet-400 transition-colors group cursor-pointer"
        >
          <RotateCcw class="h-4 w-4 group-hover:text-violet-400" />
          <span>Show getting started guide</span>
        </button>
      </section>

      <section v-if="userAlertTemplates.length > 0" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Bell class="mr-2 h-6 w-6" />
            <Heading title="Your Alert Templates" />
          </div>
          <a class="btn btn-sm btn-cancel flex items-center gap-2" :href="route('templates.create')">
            New Alert
            <Plus class="h-4 w-4" />
          </a>
        </div>
        <div class="grid grid-cols-1 gap-6 min-[2560px]:grid-cols-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3">
          <TemplateCard v-for="template in userAlertTemplates" :key="template.id" :template="template" :current-user-id="userId" />
        </div>
      </section>

      <div class="mt-6 mb-2 h-px w-full bg-muted-foreground/10" />

      <!-- Your Static Templates Section -->
      <section v-if="userStaticTemplates.length > 0" class="space-y-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Layers class="h-6 w-6 text-primary" />
            <h2 class="text-xl font-semibold">Your Static Overlays</h2>
          </div>
          <a class="btn btn-sm btn-cancel flex items-center gap-2" :href="route('templates.create')">
            New Overlay
            <Plus class="h-4 w-4" />
          </a>
        </div>
        <div class="grid grid-cols-1 gap-6 min-[2560px]:grid-cols-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3">
          <TemplateCard v-for="template in userStaticTemplates" :key="template.id" :template="template" :current-user-id="userId" />
        </div>
      </section>

      <div class="mt-6 mb-2 h-px w-full bg-muted-foreground/10" />

      <!-- Empty State for User Templates -->
      <section v-if="userAlertTemplates.length === 0 && userStaticTemplates.length === 0" class="space-y-6">
        <Card class="border border-sidebar">
          <CardHeader class="py-4 text-center">
            <CardTitle class="text-2xl">Get Started with Your First Template</CardTitle>
            <CardDescription class="mt-3 text-base"> Create your own custom overlays or fork one from the community to get started </CardDescription>
          </CardHeader>
          <CardContent class="flex justify-center gap-4 pb-8">
            <Link class="btn btn-sm btn-secondary" :href="route('templates.create')">
              <Plus class="mr-2 h-4 w-4" />
              Create Template
            </Link>
            <Link size="lg" class="btn btn-sm btn-primary" :href="route('templates.index')"> Browse Templates </Link>
          </CardContent>
        </Card>
      </section>

      <!-- Community Templates Section -->
      <section class="space-y-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Users class="h-6 w-6 text-primary" />
            <h2 class="text-2xl font-semibold">From the Community</h2>
            <span class="text-base text-muted-foreground"> Public templates you can fork and customize </span>
          </div>
          <Link class="btn btn-sm btn-cancel flex items-center gap-2" :href="route('dashboard.recents')">
            Community templates
            <Users class="h-4 w-4" />
          </Link>
        </div>

        <div
          v-if="communityTemplates.length > 0"
          class="grid grid-cols-1 gap-6 min-[2560px]:grid-cols-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3"
        >
          <TemplateCard v-for="template in communityTemplates" :key="template.id" :template="template" :show-owner="true" :current-user-id="userId" />
        </div>

        <Card v-else class="border border-sidebar">
          <CardHeader class="flex-column justify-center gap-2 py-8 text-center">
            <CardTitle class="text-xl">No Community Templates Yet</CardTitle>
            <CardDescription class="mt-2 text-base"> Be the first to share a template with the community! </CardDescription>
          </CardHeader>
        </Card>

        <div class="flex py-6">
          <Link :href="`${route('templates.index')}?direction=desc&filter=mine&search=&type=`" class="btn btn-sm btn-cancel">Browse Your Templates</Link>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
