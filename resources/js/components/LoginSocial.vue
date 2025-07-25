<template>
  <v-btn
    variant="outlined"
    class="bg-primary p-2 px-4 font-bold text-muted rounded cursor-pointer"
    @click="loginWithTwitch"
  >
    Login with Twitch
  </v-btn>
</template>

<script setup>
function loginWithTwitch () {
  window.location.href = '/auth/redirect/twitch'
}
</script>
