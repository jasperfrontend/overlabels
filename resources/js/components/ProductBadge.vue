<script setup lang="ts">
// The mark that says "an official Overlabels product". One shape, used on
// every product surface: the listing, the product page, the setup banner,
// the wiring subject. Fill is currentColor, so a text-* class colours it.
// Icon: tdesign:verified-filled, TDesign Icons (MIT),
// https://github.com/Tencent/tdesign-icons/blob/main/LICENSE
defineProps<{ label?: string }>();
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    :role="label ? 'img' : 'presentation'"
    :aria-label="label"
    :aria-hidden="label ? undefined : 'true'"
  >
    <path
      fill="currentColor"
      d="M15.616 3.268L12 .186L8.383 3.268l-4.737.378l-.378 4.737L.186 12l3.082 3.617l.378 4.737l4.737.378l3.616 3.082l3.617-3.082l4.737-.378l.378-4.737L23.813 12l-3.082-3.617l-.378-4.737zM11 16.414L6.585 12L8 10.586l3 3l5.5-5.5L17.914 9.5z"
    />
  </svg>
</template>
