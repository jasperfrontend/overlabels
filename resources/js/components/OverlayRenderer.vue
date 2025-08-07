<template>
  <div v-if="error" class="error">{{ error }}</div>
  <div v-else>
    <div v-html="compiledHtml" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useEventSub } from '@/composables/useEventSub'

const props = defineProps<{
  slug: string
  token: string
}>()

const rawHtml = ref('')
const css = ref('')
const data = ref<Record<string, string | number>>({})
const error = ref('')

const compiledHtml = computed(() => {
  let html = rawHtml.value
  for (const [key, value] of Object.entries(data.value)) {
    const regex = new RegExp(`\\[\\[\\[${key}]]]`, 'g')
    html = html.replace(regex, value?.toString() ?? '')
  }
  return html
})

function injectStyle(styleString: string) {
  const existing = document.getElementById('overlay-style')
  if (existing) existing.remove()

  const style = document.createElement('style')
  style.id = 'overlay-style'
  style.textContent = styleString
  document.head.appendChild(style)
}

onMounted(async () => {
  try {
    const response = await fetch('/api/overlay/render', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slug: props.slug, token: props.token })
    })

    if (!response.ok) throw new Error('Failed to load overlay')

    const json = await response.json()
    rawHtml.value = json.template.html
    css.value = json.template.css
    data.value = json.data

    injectStyle(css.value)

    document.title = json.meta?.name || 'Overlay'
    document.getElementById('loading')?.remove()
  } catch (err) {
    error.value = err.message
    document.getElementById('loading')?.remove()
  }

  useEventSub((event) => {
    if (event.type === 'channel.follow') {
      data.value.followers_total = (data.value.followers_total || 0) + 1
      data.value.followers_latest_user_name = event.data.user_name
    }

    if (event.type === 'channel.subscribe') {
      data.value.subscribers_total = (data.value.subscribers_total || 0) + 1
      data.value.subscribers_latest_user_name = event.data.user_name
    }

    // Add more mappings as needed
  })

})
</script>
