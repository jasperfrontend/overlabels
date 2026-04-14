<script setup lang="ts">
import TwitchIcon from '@/components/TwitchIcon.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="bg-sidebar p-8 rounded shadow-md transition-all hover:ring-2 hover:shadow ring-sidebar ring-offset-background">
          <div class="space-y-2 flex flex-col items-center">
            <div class="app-logo flex flex-col items-center">
              <TwitchIcon class="size-10" />
            </div>
            <h1 class="text-3xl font-medium">{{ title }}</h1>
            <p class="text-sm text-foreground">{{ description }}</p>
          </div>
          <slot />
        </div>
      <div class="text-sm text-muted-foreground text-center">
        <div class="text-foreground mb-4">&copy; {{ new Date().getFullYear() }} Overlabels. All rights reserved.</div>
        By connecting your Twitch account you agree to our <a href="/terms" target="_blank" class="underline hover:text-foreground">Terms of Service</a> and <a href="/privacy" target="_blank" class="underline hover:text-foreground">Privacy Policy</a>.<br>
        You can remove Overlabels from your Twitch account at any time in your <a href="https://www.twitch.tv/settings/connections" target="_blank" class="underline hover:text-foreground">Twitch settings</a>.
      </div>
    </div>
</template>
