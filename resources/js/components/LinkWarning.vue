<template>
  <a href="#" @click.prevent="handleClick"><slot /></a>
</template>

<script setup lang="ts">
import { useLinkWarning } from '@/composables/useLinkWarning'

const props = defineProps<{
  to: string
  warning: string
}>()

const { triggerLinkWarning } = useLinkWarning()

function handleClick() {
  triggerLinkWarning(() => {
    if (props.to.startsWith('http')) {
      window.open(props.to, '_blank')
    } else {
      // Assume you're using Vue Router
      window.location.href = props.to
    }
  }, props.warning)
}
</script>
