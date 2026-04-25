<script setup lang="ts">
const props = defineProps({
  title: String,
  description: [String, Number, null],
  imageUrl: String,
  imageUrlAlt: String,
  weaponUses: [Number, null],
})
</script>

<template>
  <div class="weapon text-center p-2 pt-1 w-40 h-30 bg-olive-700/50 border border-olive-500/50">
    <span class="text-yellow-400">{{ props.title }}</span>
    <div class="value text-center">

      <img :src="props.imageUrl" class="size-12 m-auto" :alt="props.imageUrlAlt" />

      <div
        v-if="props.weaponUses"
        class="relative w-full h-2.5 mt-2 overflow-hidden bg-red-400/50 bg-auto bg-repeat"
        role="progressbar"
      >
        <span
          class="absolute inset-y-0 left-0 transition-[width] duration-200 ease-out bg-green-400/50 bg-auto bg-repeat"
          :style="{ width: 10 > 0 ? `${Math.min(100, (props.weaponUses / 10) * 100)}%` : '0%' }"
        >
          <span class="medievalsharp-regular relative z-10 flex pt-0.5 h-full items-center justify-center text-xs font-bold tracking-wide text-white">
            {{ props.weaponUses }} / 10
          </span>
        </span>
      </div>

      <div
        v-if="props.description"
        class="text-sm pt-1"
      >
        {{ props.description }}
      </div>

    </div>
  </div>
</template>
