<script setup lang="ts">
interface OverlayOption {
  id: number
  name: string
  slug: string
}

const props = defineProps<{
  staticOverlays: OverlayOption[]
  modelValue: number[]
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [ids: number[]] }>()

function toggle(id: number) {
  emit(
    'update:modelValue',
    props.modelValue.includes(id) ? props.modelValue.filter((x) => x !== id) : [...props.modelValue, id],
  )
}
</script>

<template>
  <div>
    <p class="mb-3 text-sm text-muted-foreground">
      Leave all unchecked to show this alert on <strong>all</strong> static overlays. Select one or more to restrict
      where this alert fires.
    </p>

    <div v-if="staticOverlays.length === 0" class="rounded-lg border-2 border-dashed border-muted-foreground/25 p-8 text-center text-sm text-muted-foreground">
      You have no static overlays yet.
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="overlay in staticOverlays"
        :key="overlay.id"
        class="flex cursor-pointer items-center space-x-3 rounded-lg border p-3 transition-colors hover:bg-background"
        :class="{ 'border-primary bg-primary/5': modelValue.includes(overlay.id) }"
        @click="!disabled && toggle(overlay.id)"
      >
        <input
          type="checkbox"
          :id="`target-overlay-${overlay.id}`"
          :checked="modelValue.includes(overlay.id)"
          :disabled="disabled"
          class="pointer-events-none hidden"
          @click.prevent
        />
        <div class="flex flex-1 cursor-pointer items-center justify-between">
          <span class="font-medium">{{ overlay.name }}</span>
          <span class="text-xs text-muted-foreground">{{ overlay.slug }}</span>
        </div>
      </div>
    </div>

    <p class="mt-3 text-xs text-muted-foreground">
      <template v-if="modelValue.length > 0">
        {{ modelValue.length }} overlay{{ modelValue.length !== 1 ? 's' : '' }} selected
      </template>
      <template v-else> All overlays </template>
    </p>
  </div>
</template>
