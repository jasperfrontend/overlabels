<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';

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
    <meta name="description" content="Dashboard for Overlabels First Test Drive">
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-6 overflow-x-auto">
      <!-- Header Section -->
      <header class="rounded-2xl border bg-accent/20 p-4 shadow backdrop-blur-sm">
        <Heading title="Welcome to Overlabels" description="A Twitch overlay generator" />
        <p class="mt-2 text-sm">
          This is an early-stage demo, and <strong>nothing here is finalized</strong>.
          <br />
          <span class="font-semibold text-red-600">Warning:</span> There is no privacy policy yet, and connecting your Twitch data is at your own risk!
        </p>
      </header>

      <!-- Important Notice -->
      <div class="p-4 rounded-lg border bg-accent/20 shadow backdrop-blur-sm">
        <Heading title="Important Notice" description="Want to get rid of Overlabels?" />
        <p>
          If you'd like to disconnect this app from Twitch, follow the instructions on
          <a
            href="https://help.twitch.tv/s/article/twitch-account-settings#connections"
            target="_blank"
            class="text-red-600 underline"
          >
            this page
          </a>.
        </p>
      </div>

      <!-- How to Get Started -->
      <div class="p-4 rounded-lg border">
        <Heading title="How to get started" description="Follow these steps to get started" />
        <ol class="list-decimal list-inside mt-4 space-y-4">
          <li>
            <strong>Visit <Link class="hover:text-accent-foreground/60 p-0.5 px-1 bg-accent/50 rounded" :href="route('twitchdata')">/twitchdata</Link>:</strong>
            Ensure your Twitch data has been imported successfully. If not, try logging out and back in or use the
            <em>"Refresh All Data directly from the Twitch API"</em> button on that page.
          </li>
          <li>
            <strong>Visit <Link class="hover:text-accent-foreground/60 p-0.5 px-1 bg-accent/50 rounded" :href="route('tags.generator')">/tags</Link>:</strong>
            Here, you'll generate template tags for your Twitch account. Since permissions differ for affiliate and partner accounts, template tags need to be customized for your own account.
          </li>
          <li>
            <strong>Visit <Link class="hover:text-accent-foreground/60 p-0.5 px-1 bg-accent/50 rounded" :href="route('tokens.index')">/tokens</Link>:</strong>
            Generate secure overlay URL hashes. Be sure to read warnings on this page and
            <strong>never share these codes with anyone</strong>, as they can expose your Twitch data.
          </li>
          <li>
            <strong>Visit <Link class="hover:text-accent-foreground/60 p-0.5 px-1 bg-accent/50 rounded" :href="route('templates.index')">/templates</Link>:</strong>
            Use this page to design your OBS overlay with HTML and CSS. A default layout is preloaded to showcase the system's capabilities.
          </li>
        </ol>
      </div>
    </div>
  </AppLayout>
</template>
