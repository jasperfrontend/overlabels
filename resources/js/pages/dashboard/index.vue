<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const breadcrumbs = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
];
</script>

<template>
  <Head title="Welcome to Overlabels First Test Drive!" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-6 overflow-x-auto">
      <!-- Header Section -->
      <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
        <h1 class="text-3xl font-bold text-blue-900">Welcome to Overlabels First Test Drive!</h1>
        <p class="text-blue-800 mt-2">
          This is an early-stage demo, and <strong>nothing here is finalized</strong>.
          <br />
          <span class="font-semibold underline text-red-600">Warning:</span> There is no privacy policy yet, and connecting your Twitch data is at your own risk!
        </p>
      </div>

      <!-- Important Notice -->
      <div class="bg-red-100 p-4 rounded-lg border border-red-200">
        <h2 class="text-xl font-semibold text-red-700">Important:</h2>
        <p class="text-red-700 mt-2">
          If you'd like to disconnect this app from Twitch, follow the instructions on
          <a
            href="https://help.twitch.tv/s/article/twitch-account-settings?language=en_US#connections"
            target="_blank"
            class="text-red-600 underline"
          >
            this page
          </a>.
        </p>
      </div>

      <!-- How to Get Started -->
      <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800">Getting Started</h2>
        <p class="text-gray-700 mt-2">
          Follow these steps to generate your first overlay:
        </p>
        <ol class="list-decimal list-inside mt-4 text-gray-700 space-y-4">
          <li>
            <strong>Visit <Link href="/twitchdata">/twitchdata</Link>:</strong>
            Ensure your Twitch data has been imported successfully. If not, try logging out and back in or use the
            <em>"Refresh All Data directly from the Twitch API"</em> button on that page.
          </li>
          <li>
            <strong>Visit <Link href="/tags-generator">/tags-generator</Link>:</strong>
            Here, you'll generate template tags for your Twitch account. Since permissions differ for affiliate and partner accounts, template tags need to be customized for your own account.
          </li>
          <li>
            <strong>Visit <Link href="/overlay-hashes">/overlay-hashes</Link>:</strong>
            Generate secure overlay URLs. Be sure to read warnings on this page and
            <strong>never share these URLs with anyone</strong>, as they can expose your Twitch data.
          </li>
          <li>
            <strong>Visit <Link href="/template-builder">/template-builder</Link>:</strong>
            Use this page to design your OBS overlay with HTML and CSS. A default layout is preloaded to showcase the system's capabilities.
          </li>
        </ol>
      </div>
    </div>
  </AppLayout>
</template>
