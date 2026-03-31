<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Access Denied" />
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <div class="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                        <AppLogoIcon class="size-9 fill-current text-foreground dark:text-white" />
                    </div>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">Access Denied</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            Your access to this application has been restricted. If you believe this is an error, please contact the site administrator.
                        </p>
                    </div>
                </div>
                <div class="text-center">
                    <Link :href="route('home')" class="text-sm text-muted-foreground hover:underline">
                        Return to home
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
