<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase title="Log in to your account" description="Use the button below to log in through Twitch">
        <Head title="Log in" />
        <LoginSocial />
    </AuthBase>
</template>
