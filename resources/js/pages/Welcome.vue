<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="flex h-screen flex-col items-center justify-center">
    <Head>
      <title>Welcome to Overlabels</title>
      <meta name="description" content="Welcome to Overlabels" />
      <meta name="author" content="" />
      <meta name="keywords" content="" />
      <meta name="robots" content="noindex, nofollow" />
    </Head>
    <h1 class="mb-4 text-4xl font-bold">Welcome to Overlabels</h1>
    <p class="mb-4 text-lg text-gray-600">Please log in to continue</p>
    <div v-if="$page.props.auth.user">
      <Link href="/dashboard" class="bg-accent p-2 px-4 block mt-6 rounded hover:bg-violet-500 transition-color">You are logged in. Go to the dashboard</Link>
    </div>
    <div v-else>
      <LoginSocial />
      <p class="mt-4 text-lg text-gray-600">By logging in you agree you will end up in a ridiculous mess where nothing is finished. Have fun!</p>
    </div>
  </div>
</template>
