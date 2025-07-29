<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import RekaToast from '@/components/RekaToast.vue';
import { watch, ref, computed } from 'vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import type { AppPageProps } from '@/types'
import RefreshButton from '@/components/RefreshButton.vue'
import RefreshIcon from '@/components/RefreshIcon.vue';

const page = usePage<AppPageProps>()
const toastMessage = ref(null)
const toastType = ref('info')

const auth = computed(() => page.props.auth);
const avatar = ref(auth.value.user?.avatar);
const twitch = "https://www.twitch.tv/"
const props = defineProps({
  twitchData: {
    type: Object,
    required: true,
  }
})

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Your Twitch Data',
    href: '/twitchdata',
  },
];

function getTierStyle(tier: string) {
  switch (tier) {
    case '2000': // Tier 2
      return 'bg-gradient-to-r from-gray-300 via-gray-100 to-gray-300 text-gray-800 ring-1 ring-gray-400 hover:ring-gray-400';
    case '3000': // Tier 3
      return 'bg-gradient-to-r from-yellow-400 via-yellow-200 to-yellow-400 text-yellow-900 ring-2 ring-yellow-500 shadow-lg hover:ring-yellow-200';
    default: // Tier 1
      return '';
  }
}

watch(
  () => page.props.flash?.message,
  (newMessage) => {
    if (newMessage) {
      toastMessage.value = newMessage
      toastType.value = page.props.flash?.type || 'info'
    }
  },
  { immediate: true }
)
</script>

<template>
  <Head title="Your Twitch Data" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col items-center gap-8 px-4 py-10">
      <div class="w-full max-w-4xl">
        <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" />
        <h1 class="text-4xl font-extrabold tracking-tight text-center mb-6">
          Your Twitch Data
        </h1>

        <div class="flex flex-row flex-wrap gap-2 justify-between">

          <RefreshButton action="/twitchdata/refresh/all" label="All" variantClass="bg-red-500 hover:bg-red-600 hover:ring-red-500">
            <RefreshIcon />
          </RefreshButton>

          <RefreshButton action="/twitchdata/refresh/info" label="Bio & Tags">
            <RefreshIcon />
          </RefreshButton>

          <RefreshButton action="/twitchdata/refresh/following" label="Following">
            <RefreshIcon />
          </RefreshButton>

          <RefreshButton action="/twitchdata/refresh/followers" label="Followers">
            <RefreshIcon />
          </RefreshButton>

          <RefreshButton action="/twitchdata/refresh/subscribers" label="Subscribers">
            <RefreshIcon />
          </RefreshButton>

          <RefreshButton action="/twitchdata/refresh/goals" label="Goals">
            <RefreshIcon />
          </RefreshButton>
        </div>

        <div class="rounded-xl border bg-background p-6 shadow-lg" v-if="props.twitchData?.channel?.broadcaster_login">
          <div class="grid place-content-center">
            <a :href="`${twitch}${props.twitchData.channel.broadcaster_login}`" target="_blank">
              <img 
                :src="avatar" 
                :alt="props.twitchData.channel.broadcaster_name"
                class="w-20 h-20 rounded-full my-2 inline-block shadow transition hover:ring-2 hover:ring-gray-700 hover:bg-accent/50 active:bg-accent"
                />
            </a>
          </div>
          <h2 class="text-2xl font-bold text-accent-foreground text-center">
            <a :href="`${twitch}${props.twitchData.channel.broadcaster_login}`" class="hover:text-muted-foreground" target="_blank">{{ props.twitchData.channel.broadcaster_name }}</a>
          </h2>
          <div class="text-sm text-muted-foreground text-center mb-4">{{ props.twitchData.user.description }}</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <a :href="`${twitch}${props.twitchData.channel.broadcaster_login}/about`">
              <div 
                class="rounded-2xl cursor-pointer border bg-accent/20 p-4 
                      backdrop-blur-sm text-center shadow transition hover:ring-2 hover:ring-gray-700 hover:bg-accent/50 active:bg-accent"
              >
                <p class="text-lg font-semibold text-muted-foreground">
                  Your Follower Count
                </p>
                <p class="text-2xl font-bold">
                  {{ props.twitchData?.channel_followers?.total }}
                </p>
              </div>
            </a>
            <a :href="`${twitch}${props.twitchData?.channel_followers?.data[0]?.user_login}`" target="_blank">
              <div 
                class="rounded-2xl cursor-pointer border bg-accent/20 p-4 
                      backdrop-blur-sm text-center shadow transition hover:ring-2 hover:ring-gray-700 hover:bg-accent/50 active:bg-accent"
              >
                <p class="text-lg font-semibold text-muted-foreground">
                  Latest Follower
                </p>
                <p class="text-xl font-bold">
                  {{ props.twitchData.channel_followers.data[0].user_name }}
                </p>
              </div>
            </a>
          </div>

          <div>
            <h3 class="text-lg font-semibold mb-2">Channel Tags</h3>
            <div class="flex flex-wrap gap-2">
              <a 
                v-for="tag in props.twitchData.channel.tags"
                :href="`https://www.twitch.tv/directory/all/tags/${tag}`"
                :key="tag"
                target="_blank"
                class="inline-block rounded-full border bg-accent/20 px-3 py-1 text-sm 
                      font-medium text-accent-foreground shadow transition hover:ring-2 
                      hover:ring-gray-700 hover:bg-accent/50 active:bg-accent"
              >
                {{ tag }}
              </a>
            </div>
          </div>
        </div>
        <div v-else>No cached data. Please hit Refresh All</div>
        <div class="mt-10" v-if="props.twitchData?.subscribers?.data">
          <h3 class="text-lg font-semibold mb-2">Subscribers</h3>
          <ul class="space-y-2 grid grid-cols-3 gap-2">
            <a 
              v-for="(sub, i) in props.twitchData.subscribers.data"
              :key="i"
              :href="`${twitch}${sub.user_name}`" target="_blank"
              class="block m-0 p-0"
            >
            <li              
              class="w-auto rounded-2xl cursor-pointer border bg-accent/20 p-2 px-4 backdrop-blur-sm duration-200 shadow transition hover:ring-2 hover:ring-gray-700 hover:bg-accent/50 active:bg-accent"
              :class="getTierStyle(sub.tier)"
            >
              <p class="font-semibold">{{ sub.user_name }}</p>
              <p class="text-sm">
                {{ sub.plan_name }}
                <span v-if="sub.is_gift" class="text-sm italic text-muted-foreground">
                  (Gifted by {{ sub.gifter_name || 'N/A' }})
                </span>
              </p>
            </li>
            </a>
          </ul>
        </div>
        <div v-else>No cached data. Please hit Refresh All</div>

      </div>
    </div>
  </AppLayout>
</template>
