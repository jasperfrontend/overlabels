<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
  <AuthBase title="Connect with Twitch" description="Overlabels has been created for the Twitch community">
    <Head title="Connect with Twitch" />
    <LoginSocial />
  </AuthBase>
</template>
