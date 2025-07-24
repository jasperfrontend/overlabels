<script setup>
const props = defineProps({
  foxPic: { type: String, required: true },
})

</script>

<template>
  <div class="w-full h-full" :style="`background: url(${props.foxPic}) center center no-repeat; background-size: cover;`"></div>
</template>