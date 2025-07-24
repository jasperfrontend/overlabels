<script setup lang="ts">
const props = defineProps({
  name: { type: String, required: true },
})
</script>

<template>
  <div>hello, {{ props.name }}</div>
</template>