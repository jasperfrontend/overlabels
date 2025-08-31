<template>
  <a
    class="btn btn-private cursor-pointer"
    @click="loginWithTwitch"
  >
    Login with Twitch
  </a>
</template>

<script lang="js" setup>
function loginWithTwitch () {
  window.location.href = '/auth/redirect/twitch'
}
</script>
