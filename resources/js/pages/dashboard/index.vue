<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import TemplateCard from '@/components/TemplateCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertCircle, Layers, Plus, Sparkles, Users } from 'lucide-vue-next';

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

const props = defineProps<{
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
</script>

<template>
  <Head>
    <title>Dashboard</title>
    <meta name="description" content="Dashboard for Overlabels - Your Twitch overlay hub">
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-8 p-8">
      <!-- Welcome Header -->
      <header class="relative overflow-hidden rounded-2xl border bg-gradient-to-br from-accent/30 via-accent/20 to-transparent p-8 shadow-lg backdrop-blur-sm">
        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-primary/10 to-transparent rounded-full blur-3xl" />
        <div class="relative">
          <div class="flex items-center gap-3 mb-3">
            <Sparkles class="w-7 h-7 text-primary" />
            <h1 class="text-3xl font-bold">Welcome back, {{ userName }}!</h1>
          </div>
          <p class="text-lg text-muted-foreground">
            Manage your overlays and discover templates from the community
          </p>
        </div>
      </header>

      <!-- Your Alert Templates Section -->
      <section v-if="userAlertTemplates.length > 0" class="space-y-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <AlertCircle class="w-6 h-6 text-primary" />
            <h2 class="text-2xl font-semibold">Your Alert Templates</h2>
          </div>
          <Button variant="outline" asChild>
            <Link href="/templates/create?type=alert" class="flex items-center gap-2">
              <Plus class="w-4 h-4" />
              New Alert
            </Link>
          </Button>
        </div>
        <div class="grid gap-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 min-[2560px]:grid-cols-5">
          <TemplateCard
            v-for="template in userAlertTemplates"
            :key="template.id"
            :template="template"
            :current-user-id="userId"
          />
        </div>
      </section>

      <!-- Your Static Templates Section -->
      <section v-if="userStaticTemplates.length > 0" class="space-y-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Layers class="w-6 h-6 text-primary" />
            <h2 class="text-2xl font-semibold">Your Static Overlays</h2>
          </div>
          <Button variant="outline" asChild>
            <Link href="/templates/create?type=static" class="flex items-center gap-2">
              <Plus class="w-4 h-4" />
              New Overlay
            </Link>
          </Button>
        </div>
        <div class="grid gap-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 min-[2560px]:grid-cols-5">
          <TemplateCard
            v-for="template in userStaticTemplates"
            :key="template.id"
            :template="template"
            :current-user-id="userId"
          />
        </div>
      </section>

      <!-- Empty State for User Templates -->
      <section v-if="userAlertTemplates.length === 0 && userStaticTemplates.length === 0" class="space-y-6">
        <Card class="border-dashed">
          <CardHeader class="text-center py-8">
            <CardTitle class="text-2xl">Get Started with Your First Template</CardTitle>
            <CardDescription class="mt-3 text-base">
              Create your own custom overlays or fork one from the community to get started
            </CardDescription>
          </CardHeader>
          <CardContent class="flex justify-center gap-4 pb-8">
            <Button size="lg" asChild>
              <Link href="/templates/create">
                <Plus class="w-4 h-4 mr-2" />
                Create Template
              </Link>
            </Button>
            <Button size="lg" variant="outline" asChild>
              <Link href="/templates">
                Browse Templates
              </Link>
            </Button>
          </CardContent>
        </Card>
      </section>

      <!-- Community Templates Section -->
      <section class="space-y-6">
        <div class="flex items-center gap-3">
          <Users class="w-6 h-6 text-primary" />
          <h2 class="text-2xl font-semibold">From the Community</h2>
          <span class="text-base text-muted-foreground">
            Public templates you can fork and customize
          </span>
        </div>
        
        <div v-if="communityTemplates.length > 0" class="grid gap-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 min-[2560px]:grid-cols-5">
          <TemplateCard
            v-for="template in communityTemplates"
            :key="template.id"
            :template="template"
            :show-owner="true"
            :current-user-id="userId"
          />
        </div>
        
        <Card v-else class="border-dashed">
          <CardHeader class="text-center py-8">
            <CardTitle class="text-xl">No Community Templates Yet</CardTitle>
            <CardDescription class="text-base mt-2">
              Be the first to share a template with the community!
            </CardDescription>
          </CardHeader>
        </Card>
        
        <div class="flex justify-center pt-6">
          <Button size="lg" variant="outline" asChild>
            <Link href="/templates">
              Browse All Templates
            </Link>
          </Button>
        </div>
      </section>

      <!-- Quick Links Section -->
      <section class="grid gap-6 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-4 mt-8">
        <Card class="group hover:shadow-md transition-all hover:border-accent">
          <CardHeader class="pb-4">
            <CardTitle class="text-base font-medium">Twitch Data</CardTitle>
          </CardHeader>
          <CardContent>
            <Link href="/twitchdata" class="text-sm text-muted-foreground hover:text-accent-foreground transition-colors">
              Sync your Twitch data
            </Link>
          </CardContent>
        </Card>
        
        <Card class="group hover:shadow-md transition-all hover:border-accent">
          <CardHeader class="pb-4">
            <CardTitle class="text-base font-medium">Template Tags</CardTitle>
          </CardHeader>
          <CardContent>
            <Link href="/tags" class="text-sm text-muted-foreground hover:text-accent-foreground transition-colors">
              Generate template tags
            </Link>
          </CardContent>
        </Card>
        
        <Card class="group hover:shadow-md transition-all hover:border-accent">
          <CardHeader class="pb-4">
            <CardTitle class="text-base font-medium">Access Tokens</CardTitle>
          </CardHeader>
          <CardContent>
            <Link href="/tokens" class="text-sm text-muted-foreground hover:text-accent-foreground transition-colors">
              Manage overlay access
            </Link>
          </CardContent>
        </Card>
        
        <Card class="group hover:shadow-md transition-all hover:border-accent">
          <CardHeader class="pb-4">
            <CardTitle class="text-base font-medium">Help & Docs</CardTitle>
          </CardHeader>
          <CardContent>
            <Link href="/help" class="text-sm text-muted-foreground hover:text-accent-foreground transition-colors">
              Learn how to use Overlabels
            </Link>
          </CardContent>
        </Card>
      </section>
    </div>
  </AppLayout>
</template>