<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { XIcon, ChevronLeftIcon, ChevronRightIcon } from 'lucide-vue-next';

const modalShow = ref(false)
const selectedImgIndex = ref(0)

const props = defineProps({
  foxes: {
    type: Object,
    required: true,
  }
})

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Foxes Gallery',
    href: '/foxes',
  },
];

const currentImage = computed(() => {
  return props.foxes.data[selectedImgIndex.value]
})

const canNavigatePrev = computed(() => selectedImgIndex.value > 0)
const canNavigateNext = computed(() => selectedImgIndex.value < props.foxes.data.length - 1)

function showModal(foxIndex: number) {
  selectedImgIndex.value = foxIndex
  modalShow.value = true
  document.body.style.overflow = 'hidden'
}

function closeModal() {
  modalShow.value = false
  document.body.style.overflow = 'auto'
}

function navigatePrev() {
  if (canNavigatePrev.value) {
    selectedImgIndex.value--
  }
}

function navigateNext() {
  if (canNavigateNext.value) {
    selectedImgIndex.value++
  }
}

function handleKeydown(e: KeyboardEvent) {
  if (!modalShow.value) return
  
  switch (e.key) {
    case 'Escape':
      closeModal()
      break
    case 'ArrowLeft':
      navigatePrev()
      break
    case 'ArrowRight':
      navigateNext()
      break
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = 'auto'
})

// Helper function to get the best image URL
const getImageUrl = (fox: { cloudinary_url: any; api_url: any; local_file: any; }) => {
  return fox.cloudinary_url || fox.api_url || `/storage/${fox.local_file}`;
};

</script>

<template>
  <Head title="Foxes Gallery" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="mx-auto max-w-3xl py-8">
        <h1 class="text-2xl font-bold mb-6">Fox Gallery</h1>
        <!-- <pre>{{ props.foxes }}</pre> -->
        <!-- Pagination controls -->
        <div v-if="props.foxes.first_page_url || props.foxes.last_page_url" class="flex justify-center mt-6 mb-6 gap-2">
          <template v-for="(link, idx) in props.foxes.links">
            <button
              v-if="link.url"
              :key="idx"
              :disabled="link.active"
              @click="$inertia.visit(link.url)"
              v-html="link.label"
              class="px-3 cursor-pointer py-1 border rounded-lg hover:bg-gray-100 dark:hover:bg-zinc-800 transition-all duration-200 hover:shadow-md dark:border-zinc-700"
              :class="{ 
                'font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400 border-blue-200 dark:border-blue-800': link.active, 
                'opacity-50 cursor-not-allowed': !link.url 
              }"
            />
            <button v-else :key="`sep-${idx}`" class="px-2 text-gray-400" v-html="link.label" />
          </template>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <div
            v-for="(fox, index) in props.foxes.data"
            :key="fox.id"
            class="group rounded-xl border border-gray-200 dark:border-zinc-700 p-3 bg-white dark:bg-zinc-900 shadow-sm hover:shadow-xl dark:hover:shadow-2xl hover:shadow-gray-200/50 dark:hover:shadow-black/50 transition-all duration-300 hover:-translate-y-1 flex flex-col items-center cursor-pointer"
            @click="showModal(index)"
          >
            <div class="relative overflow-hidden rounded-lg w-full">
              <img
                :src="getImageUrl(fox)"
                :alt="`Fox ${fox.id}`"
                class="aspect-video object-cover w-full transition-transform duration-300 group-hover:scale-110"
              />
              <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-all duration-300 flex items-center justify-center">
                <div class="bg-white/90 dark:bg-zinc-900/90 px-3 py-1 rounded-full text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  View Image
                </div>
              </div>
            </div>
            
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-3 text-center">
              {{ (new Date(fox.created_at)).toLocaleString() }}
            </div>
          </div>
        </div>

        <!-- Pagination controls -->
        <div v-if="props.foxes.first_page_url || props.foxes.last_page_url" class="flex justify-center mt-8 gap-2">
          <template v-for="(link, idx) in props.foxes.links">
            <button
              v-if="link.url"
              :key="idx"
              :disabled="link.active"
              @click="$inertia.visit(link.url)"
              v-html="link.label"
              class="px-3 py-1 cursor-pointer border rounded-lg hover:bg-gray-100 dark:hover:bg-zinc-800 transition-all duration-200 hover:shadow-md dark:border-zinc-700"
              :class="{ 
                'font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400 border-blue-200 dark:border-blue-800': link.active, 
                'opacity-50 cursor-not-allowed': !link.url 
              }"
            />
            <span v-else :key="`sep-${idx}`" class="px-2 text-gray-400" v-html="link.label" />
          </template>
        </div>
      </div>

      <!-- Enhanced Modal -->
      <Teleport to="body">
        <div 
          v-if="modalShow" 
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
          @click.self="closeModal"
        >
          <div 
            class="relative max-w-4xl max-h-[90vh] w-full bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-300"
            @click.stop
          >
            <!-- Close button -->
            <button
              @click="closeModal"
              class="cursor-pointer absolute top-4 right-4 z-10 p-2 bg-black/20 hover:bg-black/40 dark:bg-white/20 dark:hover:bg-white/40 rounded-full transition-all duration-200 backdrop-blur-sm group"
            >
              <XIcon class="w-5 h-5 text-white group-hover:rotate-90 transition-transform duration-200" />
            </button>

            <!-- Navigation buttons -->
            <button
              v-if="canNavigatePrev"
              @click="navigatePrev"
              class="cursor-pointer absolute left-4 top-1/2 -translate-y-1/2 z-10 p-3 bg-black/20 hover:bg-black/40 dark:bg-white/20 dark:hover:bg-white/40 rounded-full transition-all duration-200 backdrop-blur-sm group"
            >
              <ChevronLeftIcon class="w-6 h-6 text-white group-hover:-translate-x-0.5 transition-transform duration-200" />
            </button>

            <button
              v-if="canNavigateNext"
              @click="navigateNext"
              class="cursor-pointer absolute right-4 top-1/2 -translate-y-1/2 z-10 p-3 bg-black/20 hover:bg-black/40 dark:bg-white/20 dark:hover:bg-white/40 rounded-full transition-all duration-200 backdrop-blur-sm group"
            >
              <ChevronRightIcon class="w-6 h-6 text-white group-hover:translate-x-0.5 transition-transform duration-200" />
            </button>

            <!-- Image container -->
            <div class="relative">
              <img 
                :src="getImageUrl(currentImage)" 
                :alt="`Fox ${currentImage.id}`"
                class="w-full h-auto max-h-[80vh] object-contain bg-gray-100 dark:bg-zinc-800"
              />
              
              <!-- Image info overlay -->
              <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-6">
                <div class="flex items-center justify-between text-white">
                  <div>
                    <h3 class="text-lg font-semibold">Fox #{{ currentImage.id }}</h3>
                    <p class="text-sm text-white/80">
                      {{ (new Date(currentImage.created_at)).toLocaleString() }}
                    </p>
                  </div>
                  <div class="text-sm text-white/80">
                    {{ selectedImgIndex + 1 }} / {{ props.foxes.data.length }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>  
  </AppLayout>
</template>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes zoom-in-95 {
  from { transform: scale(0.95); }
  to { transform: scale(1); }
}

.animate-in {
  animation-fill-mode: both;
}

.fade-in {
  animation-name: fade-in;
}

.zoom-in-95 {
  animation-name: zoom-in-95;
}

.duration-300 {
  animation-duration: 300ms;
}
</style>