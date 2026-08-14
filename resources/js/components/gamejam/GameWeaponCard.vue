<script setup lang="ts">
const props = defineProps({
  title: String,
  description: [String, Number, null],
  imageUrl: String,
  imageUrlAlt: String,
  weaponUses: [Number, null],
});
</script>

<template>
  <div class="weapon h-30 w-40 border border-olive-500/50 bg-olive-700/50 p-2 pt-1 text-center">
    <span class="text-yellow-400">{{ props.title }}</span>
    <div class="value text-center">
      <img :src="props.imageUrl" class="m-auto size-12" :alt="props.imageUrlAlt" />

      <div v-if="props.weaponUses" class="relative mt-2 h-2.5 w-full overflow-hidden bg-red-400/50 bg-auto bg-repeat" role="progressbar">
        <span
          class="absolute inset-y-0 left-0 bg-green-400/50 bg-auto bg-repeat transition-[width] duration-200 ease-out"
          :style="{ width: 10 > 0 ? `${Math.min(100, (props.weaponUses / 10) * 100)}%` : '0%' }"
        >
          <span class="medievalsharp-regular relative z-10 flex h-full items-center justify-center pt-0.5 text-xs font-bold tracking-wide text-white">
            {{ props.weaponUses }} / 10
          </span>
        </span>
      </div>

      <div v-if="props.description" class="pt-1 text-sm">
        {{ props.description }}
      </div>
    </div>
  </div>
</template>
