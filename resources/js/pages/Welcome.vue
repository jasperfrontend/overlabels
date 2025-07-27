<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">

        <div class="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
            <main class="flex w-full max-w-[500px] flex-col-reverse overflow-hidden rounded-lg lg:max-w-4xl lg:flex-row">
                <div class="flex w-full flex-col items-center text-center justify-center gap-4 p-6 lg:p-8">
                    <h1 class="text-3xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]">
                        Welcome to Overlabels
                    </h1>
                    <p class="text-sm text-[#1b1b18] dark:text-[#EDEDEC]">
                        Log in to manage your OBS Overlays
                    </p>
                    <LoginSocial />
                </div>

            </main>
        </div>
        <div class="hidden h-14.5 lg:block"></div>
    </div>
</template>
