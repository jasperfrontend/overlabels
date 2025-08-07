<script setup lang="ts">
import RefreshButton from '@/components/RefreshButton.vue';
import RefreshIcon from '@/components/RefreshIcon.vue';
import RekaToast from '@/components/RekaToast.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps } from '@/types';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage<AppPageProps>();
const toastMessage = ref(null);
const toastType = ref('info');

const auth = computed(() => page.props.auth);
const avatar = ref(auth.value.user?.avatar);
const twitch = 'https://www.twitch.tv/';
const props = defineProps({
  twitchData: {
    type: Object,
    required: true,
  },
});

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Your Twitch Data',
    href: '/twitchdata',
  },
];

function getTierStyle(tier: string) {
  switch (tier) {
    case '2000': // Tier 2
      return 'ring-2 ring-gray-400 hover:ring-gray-200';
    case '3000': // Tier 3
      return 'ring-2 ring-yellow-200 dark:ring-yellow-400/50 shadow-lg hover:ring-yellow-500 dark:hover:ring-yellow-300/75';
    default: // Tier 1
      return '';
  }
}
const confirmExpensiveApiCall = () => {
  if (confirm('This will make an expensive API call to refresh all your Twitch data. Are you sure you want to continue?')) {
    window.location.href = '/twitchdata/refresh/expensive';
  }
};
watch(
  () => page.props.flash?.message,
  (newMessage) => {
    if (newMessage) {
      toastMessage.value = newMessage;
      toastType.value = page.props.flash?.type || 'info';
    }
  },
  { immediate: true },
);
watch (
  () => auth.value.user,
  (authUser) => {
    if (authUser) {
      avatar.value = authUser.avatar;
    } else {
      router.visit('/', {
        replace: true,
        preserveState: true
      })
    }
  }, { immediate: true },
);
</script>

<template>
  <Head title="Your Twitch Data" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col items-center gap-8 px-4 py-10">
      <div class="w-full max-w-4xl">
        <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" />
        <h1 class="mb-6 text-center text-4xl font-extrabold tracking-tight">Your Twitch Data</h1>
        <div class="mb-4 flex flex-row flex-wrap justify-between gap-2">
          <RefreshButton action="/twitchdata/refresh/user" label="User">
            <RefreshIcon />
          </RefreshButton>

          <RefreshButton action="/twitchdata/refresh/info" label="Bio">
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

        <div class="rounded-xl border bg-background p-6 shadow-lg">
          <div class="grid place-content-center">
            <a :href="`${twitch}${props.twitchData.channel.broadcaster_login}`" target="_blank">
              <img
                :src="avatar"
                :alt="props.twitchData.channel.broadcaster_name"
                class="my-2 inline-block h-20 w-20 rounded-full shadow transition hover:bg-accent/50 hover:ring-2 hover:ring-gray-300 active:bg-accent dark:hover:ring-gray-700"
              />
            </a>
          </div>

          <h2 class="text-center text-2xl font-bold text-accent-foreground">
            <a :href="`${twitch}${props.twitchData.channel.broadcaster_login}`" class="hover:text-muted-foreground" target="_blank">{{
              props.twitchData.channel.broadcaster_name
            }}</a>
          </h2>

          <div class="mb-4 text-center text-sm text-muted-foreground">
            {{ props.twitchData.user.description }}
          </div>

          <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <a v-if="props.twitchData.channel_followers.total" :href="`${twitch}${props.twitchData.channel.broadcaster_login}/about`">
              <div
                class="cursor-pointer rounded-2xl border bg-accent/20 p-4 text-center shadow backdrop-blur-sm transition hover:bg-accent/50 hover:ring-2 hover:ring-gray-300 active:bg-accent dark:hover:ring-gray-700"
              >
                <p class="text-lg font-semibold text-muted-foreground">Your Follower Count</p>
                <p class="text-2xl font-bold">
                  {{ props.twitchData?.channel_followers?.total }}
                </p>
              </div>
            </a>

            <a
              v-if="props.twitchData?.channel_followers.data"
              :href="`${twitch}${props.twitchData?.channel_followers?.data[0]?.user_login}`"
              target="_blank"
            >
              <div
                class="cursor-pointer rounded-2xl border bg-accent/20 p-4 text-center shadow backdrop-blur-sm transition hover:bg-accent/50 hover:ring-2 hover:ring-gray-300 active:bg-accent dark:hover:ring-gray-700"
              >
                <p class="text-lg font-semibold text-muted-foreground">Latest Follower</p>
                <p class="text-xl font-bold">
                  {{ props.twitchData.channel_followers.data[0].user_name }}
                </p>
              </div>
            </a>
          </div>

          <div>
            <h3 class="mb-2 text-lg font-semibold">Channel Tags</h3>
            <div class="flex flex-wrap gap-2">
              <a
                v-for="tag in props.twitchData.channel.tags"
                :href="`https://www.twitch.tv/directory/all/tags/${tag}`"
                :key="tag"
                target="_blank"
                class="inline-block rounded-full border bg-accent/20 px-3 py-1 text-sm font-medium text-accent-foreground shadow transition hover:bg-accent/50 hover:ring-2 hover:ring-gray-300 active:bg-accent dark:hover:ring-gray-700"
              >
                {{ tag }}
              </a>
            </div>
          </div>

          <div class="mt-10">
            <h3 class="mb-2 text-lg font-semibold">Subscribers</h3>
            <ul class="grid grid-cols-3 gap-2 space-y-2">
              <li
                v-for="(sub, i) in props.twitchData.subscribers.data"
                :key="i"
                class="h-16 w-auto cursor-pointer rounded-2xl border bg-accent/20 p-2 px-4 shadow backdrop-blur-sm transition duration-200 hover:bg-accent/50 hover:ring-2 hover:ring-gray-300 active:bg-accent dark:hover:ring-gray-700"
                :class="getTierStyle(sub.tier)"
              >
                <a class="m-0 flex flex-col p-0" :href="`${twitch}${sub.user_name}`" target="_blank">
                  <span class="inline-block font-semibold">{{ sub.user_name }}</span>
                  <span class="inline-block text-sm">
                    {{ sub.plan_name }}
                    <span v-if="sub.is_gift" class="text-sm text-muted-foreground italic"> (Gifted by {{ sub.gifter_name || 'N/A' }}) </span>
                  </span>
                </a>
              </li>
            </ul>
          </div>

          <button type="submit" class="btn btn-danger mt-6 w-full" @click="confirmExpensiveApiCall">
            <RefreshIcon /> Refresh All Data directly from the Twitch API
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
