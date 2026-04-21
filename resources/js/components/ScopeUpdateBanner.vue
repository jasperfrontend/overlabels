<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const scope = computed(() => page.props.twitchScope);
const missing = computed(() => scope.value?.missing ?? []);

const SCOPE_LABELS: Record<string, string> = {
  'channel:read:hype_train': 'Hype Train',
  'channel:read:charity': 'Charity',
  'channel:read:polls': 'Polls',
  'channel:read:predictions': 'Predictions',
  'channel:read:goals': 'Goals',
  'channel:read:subscriptions': 'Subscriptions',
  'channel:read:redemptions': 'Channel Points',
  'moderator:read:followers': 'Followers',
  'channel:moderate': 'Moderation',
  'user:read:email': 'Email',
  'user:read:follows': 'Follows',
  'user:read:subscriptions': 'Your subscriptions',
};

const featureList = computed(() => {
  const labels = missing.value
    .map((s) => SCOPE_LABELS[s] ?? s)
    .filter((v, i, arr) => arr.indexOf(v) === i);
  return labels.join(', ');
});
</script>

<template>
  <div
    v-if="missing.length > 0"
    class="flex items-center justify-between bg-yellow-400 px-4 py-2 text-sm font-medium text-yellow-900"
  >
    <span>
      Twitch needs to re-confirm permissions to unlock new alert types ({{ featureList }}).
      Reauthorizing takes a few seconds and does not sign you out.
    </span>
    <a
      href="/auth/redirect/twitch?reauth=1"
      class="ml-4 cursor-pointer rounded border border-yellow-700 bg-yellow-300 px-3 py-1 text-xs font-semibold hover:bg-yellow-200"
    >
      Reauthorize Twitch
    </a>
  </div>
</template>
