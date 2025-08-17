<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import { Head } from '@inertiajs/vue3';
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
    <LoginSocial />
    <p class="mt-4 text-lg text-gray-600">By logging in you agree you will end up in a ridiculous mess where nothing is finished. Have fun!</p>
  </div>
</template>
