<script setup lang="ts">

import { ArrowRight } from 'lucide-vue-next';
import LoginSocial from '@/components/LoginSocial.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <section id="get-started" class="border-b border-sidebar-accent py-24">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-2xl text-center">
        <h2 class="mb-6 text-4xl font-bold tracking-tight sm:text-5xl">
          Ship your overlay.<br />
          <span class="text-sky-500">Free. Forever.</span>
        </h2>
        <p class="mx-auto mb-10 max-w-lg text-lg text-foreground">
          No paywalls. No tiers. No artificial limits. Everything you create is yours. The whole thing is open source.
        </p>

        <div v-if="$page.props.auth.user" class="flex flex-col items-center gap-4">
          <Link :href="route('dashboard.index')" class="btn btn-primary px-8 text-base"> Go to Dashboard
            <ArrowRight class="ml-2 h-5 w-5" />
          </Link>
        </div>
        <div v-else class="flex flex-col items-center gap-6">
          <LoginSocial />
          <p class="text-xs text-foreground">
            Authenticate with Twitch. You must have an email address attached to your account before you can login.
            Revoke access anytime from your Twitch settings.
          </p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>

</style>
