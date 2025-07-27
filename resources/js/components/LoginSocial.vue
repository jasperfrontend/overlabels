<template>
  <a
    class="p-2 px-4 font-bold rounded text-center cursor-pointer
     bg-purple-700 hover:bg-purple-900 text-purple-100 hover:text-purple-300 ring-offset-1 ring-1 focus:ring-purple-500"
    @click="loginWithTwitch"
  >
    Login with Twitch
  </a>
</template>

<script setup>
function loginWithTwitch () {
  window.location.href = '/auth/redirect/twitch'
}
</script>
