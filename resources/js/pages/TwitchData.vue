<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const twitch = "https://www.twitch.tv/"

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Your Twitch Data',
    href: '/foxes',
  },
];

const props = defineProps({
  twitchData: {
    type: Object,
    required: true,
  }
})

function getTierStyle(tier: string) {
  switch (tier) {
    case '2000': // Tier 2
      return 'bg-gradient-to-r from-gray-300 via-gray-100 to-gray-300 text-gray-800 ring-1 ring-gray-400';
    case '3000': // Tier 3
      return 'bg-gradient-to-r from-yellow-400 via-yellow-200 to-yellow-400 text-yellow-900 ring-2 ring-yellow-500 shadow-lg';
    default: // Tier 1
      return 'bg-muted text-muted-foreground ring-0';
  }
}

</script>

<template>
  <Head title="Your Twitch Data" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col items-center gap-8 px-4 py-10">
      <div class="w-full max-w-4xl">
        <h1 class="text-4xl font-extrabold tracking-tight text-center mb-6">
          Your Twitch Data
        </h1>
        <div class="rounded-xl border bg-background p-6 shadow-lg">
          <h2 class="text-2xl font-bold text-accent-foreground mb-4 text-center">
            <a :href="`${twitch}${props.twitchData.channel.broadcaster_name}`" class="hover:text-muted-foreground" target="_blank">{{ props.twitchData.channel.broadcaster_name }}</a>
          </h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="rounded-2xl border bg-accent/20 p-4 backdrop-blur-sm text-center shadow">
              <p class="text-lg font-semibold text-muted-foreground">
                Your Follower Count
              </p>
              <p class="text-2xl font-bold">
                {{ props.twitchData.channel_followers.total }}
              </p>
            </div>
            <div class="rounded-2xl border bg-accent/20 p-4 backdrop-blur-sm text-center shadow">
              <p class="text-lg font-semibold text-muted-foreground">
                Latest Follower
              </p>
              <p class="text-xl font-bold">
                <a :href="`${twitch}${props.twitchData.channel_followers.data[0].user_name}`" class="hover:text-muted-foreground" target="_blank">
                  {{ props.twitchData.channel_followers.data[0].user_name }}
                </a>
              </p>
            </div>
          </div>

          <div>
            <h3 class="text-lg font-semibold mb-2">Channel Tags</h3>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="tag in props.twitchData.channel.tags"
                :key="tag"
                class="inline-block rounded-full bg-accent px-3 py-1 text-sm font-medium text-accent-foreground shadow"
              >
                {{ tag }}
              </span>
            </div>
          </div>
        </div>

        <div class="mt-10">
          <h3 class="text-lg font-semibold mb-2">Subscribers</h3>
          <ul class="space-y-2">
            <li
              v-for="(sub, i) in props.twitchData.subscribers.data"
              :key="i"
              class="rounded-xl p-4 flex justify-between items-center transition-shadow duration-200"
              :class="getTierStyle(sub.tier)"
            >
              <div>
                <p class="font-semibold"><a :href="`${twitch}${sub.user_name}`" target="_blank" class="hover:text-accent-foreground">{{ sub.user_name }}</a></p>
                <p class="text-sm">
                  {{ sub.plan_name }}
                  <span v-if="sub.is_gift" class="text-xs italic text-muted-foreground">
                    (Gifted by {{ sub.gifter_name || 'N/A' }})
                  </span>
                </p>
              </div>
              <span class="text-sm font-semibold uppercase tracking-wide">
                Tier {{ sub.tier / 1000 }}
              </span>
            </li>
          </ul>
        </div>

        <!-- Optional: raw JSON dump for nerdy debug mode -->
        <details class="mt-8">
          <summary class="cursor-pointer text-sm text-muted-foreground underline">
            Show raw JSON data
          </summary>
          <pre class="mt-2 max-h-96 overflow-auto bg-muted text-xs p-4 rounded-xl whitespace-pre-wrap">
            {{ props.twitchData }}
          </pre>
        </details>
      </div>
    </div>
  </AppLayout>
</template>
