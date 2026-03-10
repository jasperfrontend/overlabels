<template>
  <button
    class="inline-flex cursor-pointer items-center gap-2.5 rounded-lg bg-[#9146FF] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#7c3aed] active:scale-[0.98]"
    @click="loginWithTwitch"
  >
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
      <path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0 1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z" />
    </svg>
    Login with Twitch
  </button>
</template>

<script lang="ts" setup>
function loginWithTwitch() {
  window.location.href = '/auth/redirect/twitch';
}
</script>
