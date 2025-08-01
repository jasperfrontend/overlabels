<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogOverlay, DialogContent } from '@/components/ui/dialog'
import { useMediaQuery } from '@vueuse/core'

const props = defineProps({
  slug: {
    type: String,
    required: true,
  },
})

const isOpen = ref(false)
const activePost = ref(null)
const helpArticles = ref([])

const isDesktop = useMediaQuery('(min-width: 768px)')

const loadHelpArticles = async () => {
  // try {
  //   const res = await fetch(`/api/help-proxy/${props.slug}`)
  //   const json = await res.json()
  //   helpArticles.value = (Array.isArray(json) ? json : json.data ?? []).map(post => ({
  //     ...post,
  //     content: post.content ?? post.excerpt ?? '',
  //   }))
  // } catch (err) {
  //   console.warn('Failed to fetch help articles:', err)
  // }
}

onMounted(() => {
  loadHelpArticles()
})

const closeDialog = () => {
  isOpen.value = false
  activePost.value = null
}
</script>

<template>
  <div class="fixed bottom-6 right-6 z-50">
    <Button class="rounded-full shadow-lg h-14 w-14 p-0 text-xl bg-blue-600 hover:bg-blue-700" @click="isOpen = true">
      ?
    </Button>

    <Dialog :open="isOpen">
      <DialogOverlay class="bg-black/50 fixed inset-0 z-40" />
      <DialogContent
        class="fixed z-50 shadow-2xl rounded-xl overflow-hidden"
        :class="[
          'transition-all duration-200',
          isDesktop ? 'bottom-24 right-6 w-[360px] min-h-[60vh] max-h-[80vh]' : 'bottom-0 left-0 right-0 top-0 m-0 w-full h-full',
          activePost ? 'w-[720px]' : '',
          'bg-white dark:bg-zinc-900 text-black dark:text-white'
        ]"
      >
        <div class="border-b pb-2 border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
          <h2 class="text-lg font-bold">
            {{ activePost ? activePost.title : 'Help Center' }}
          </h2>
          <Button size="sm" variant="ghost" @click="closeDialog">Close ✕</Button>
        </div>

        <div class="overflow-y-auto pr-2 py-2">
          <template v-if="!activePost">
            <div
              v-for="post in helpArticles"
              :key="post.id"
              class="rounded-md border p-4 mb-3 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 border-zinc-200 dark:border-zinc-700"
              @click="activePost = post"
            >
              <h3 class="text-blue-600 dark:text-blue-400 font-semibold text-sm mb-1">{{ post.title }}</h3>
              <div class="text-sm text-zinc-700 dark:text-zinc-300" v-html="post.excerpt" />
            </div>
          </template>

          <template v-else>
            <div v-html="activePost.content" class="prose dark:prose-invert max-w-none" />
          </template>
        </div>

        <div v-if="activePost" class="px-4 py-2 border-t border-zinc-200 dark:border-zinc-700 text-sm text-right">
          <Button size="sm" variant="ghost" @click="activePost = null">← Back to list</Button>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
.prose :deep(img) {
  max-width: 100%;
  height: auto;
}
.prose :deep(a) {
  @apply text-blue-500 underline;
}
.prose :deep(p) p {
  margin-bottom: 1rem;
}
</style>
