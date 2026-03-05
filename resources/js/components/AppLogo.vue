<script setup lang="ts">

</script>

<template>
    <div class="flex text-left text-sm">
      <div class="app-logo"></div>
      <span class="ml-2 text-lg font-medium">Overlabels</span>
    </div>
</template>

<style scoped>

.app-logo {
  width: 20px;
  height: 20px;
}
html:not(.dark) .app-logo {
  background: url('/favicon-light.svg') center center no-repeat;
}
html.dark .app-logo {
  background: url('/favicon.svg') center center no-repeat;
}
</style>
